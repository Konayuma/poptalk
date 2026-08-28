import { afterEach, describe, expect, it, vi } from 'vitest'
import { RadioApiError, radioApi } from '../radioApi'

const fetchMock = vi.fn<typeof fetch>()

function jsonResponse(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  fetchMock.mockReset()
})

describe('radioApi', () => {
  it('creates a session with the configured JSON contract', async () => {
    const payload = {
      data: {
        id: 'session-1',
        callsign: 'ROOKIE-7',
        channel: 7,
        last_seen_at: '2026-08-28T11:00:00.000Z',
        connected_at: '2026-08-28T11:00:00.000Z',
      },
      meta: {
        session_token: 'session-token',
        heartbeat_interval_seconds: 10,
        presence_ttl_seconds: 30,
        server_time: '2026-08-28T11:00:00.000Z',
      },
    }
    fetchMock.mockResolvedValue(jsonResponse(payload))
    vi.stubGlobal('fetch', fetchMock)

    await expect(radioApi.createSession('ROOKIE-7', 7)).resolves.toEqual(payload)

    const [url, options] = fetchMock.mock.calls[0]!
    const headers = options?.headers as Headers
    expect(url).toBe('/api/v1/sessions')
    expect(options?.method).toBe('POST')
    expect(options?.body).toBe(JSON.stringify({ callsign: 'ROOKIE-7', channel: 7 }))
    expect(headers.get('Accept')).toBe('application/json')
    expect(headers.get('Content-Type')).toBe('application/json')
    expect(headers.has('Authorization')).toBe(false)
  })

  it('sends the bearer token to protected channel endpoints', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse({
        data: {
          number: 7,
          listener_count: 1,
          is_busy: false,
          active_transmission: null,
        },
      }),
    )
    vi.stubGlobal('fetch', fetchMock)

    await radioApi.channel('secret-token', 7)

    const [url, options] = fetchMock.mock.calls[0]!
    expect(url).toBe('/api/v1/channels/7')
    expect((options?.headers as Headers).get('Authorization')).toBe('Bearer secret-token')
  })

  it('preserves structured API errors for the connection layer', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(
        {
          message: 'Channel 7 is already in use.',
          code: 'channel_busy',
          errors: { channel: ['Wait for the current caller to release PTT.'] },
        },
        409,
      ),
    )
    vi.stubGlobal('fetch', fetchMock)

    const request = radioApi.startTransmission('secret-token', 7)

    await expect(request).rejects.toMatchObject({
      name: 'RadioApiError',
      message: 'Channel 7 is already in use.',
      status: 409,
      code: 'channel_busy',
      errors: { channel: ['Wait for the current caller to release PTT.'] },
    })
  })

  it('maps network and timeout failures to stable client errors', async () => {
    fetchMock.mockRejectedValueOnce(new TypeError('Failed to fetch'))
    vi.stubGlobal('fetch', fetchMock)

    await expect(radioApi.health()).rejects.toEqual(
      expect.objectContaining<Partial<RadioApiError>>({
        status: 0,
        code: 'network_error',
      }),
    )

    vi.useFakeTimers()
    fetchMock.mockImplementationOnce((_url, options) => {
      return new Promise<Response>((_resolve, reject) => {
        options?.signal?.addEventListener('abort', () => {
          reject(new DOMException('Aborted', 'AbortError'))
        })
      })
    })

    const timedOutRequest = expect(radioApi.health()).rejects.toEqual(
      expect.objectContaining<Partial<RadioApiError>>({
        status: 0,
        code: 'timeout',
      }),
    )

    await vi.advanceTimersByTimeAsync(8_000)
    await timedOutRequest
  })

  it('accepts empty successful responses when releasing resources', async () => {
    fetchMock.mockResolvedValue(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(radioApi.endTransmission('secret-token', 'tx/1')).resolves.toBeUndefined()
    expect(fetchMock.mock.calls[0]?.[0]).toBe('/api/v1/transmissions/tx%2F1')
  })
})
