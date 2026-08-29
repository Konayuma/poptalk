import { afterEach, describe, expect, it, vi } from 'vitest'
import { RadioApiError, radioApi } from '../radioApi'

const fetchMock = vi.fn<typeof fetch>()

function jsonResponse(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function lastRequest() {
  const [url, options] = fetchMock.mock.calls.at(-1)!
  return { url: String(url), options }
}

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
  fetchMock.mockReset()
  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'
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
        heartbeat_interval_seconds: 10,
        presence_ttl_seconds: 30,
        server_time: '2026-08-28T11:00:00.000Z',
      },
    }
    document.cookie = 'XSRF-TOKEN=test-csrf'
    fetchMock.mockResolvedValue(jsonResponse(payload, 201))
    vi.stubGlobal('fetch', fetchMock)

    await expect(radioApi.createSession('ROOKIE-7', 7)).resolves.toEqual(payload)

    const { url, options } = lastRequest()
    const headers = options?.headers as Headers
    expect(url).toBe('/api/v1/sessions')
    expect(options?.method).toBe('POST')
    expect(options?.credentials).toBe('include')
    expect(options?.body).toBe(JSON.stringify({ callsign: 'ROOKIE-7', channel: 7 }))
    expect(headers.get('Accept')).toBe('application/json')
    expect(headers.get('Content-Type')).toBe('application/json')
    expect(headers.get('X-XSRF-TOKEN')).toBe('test-csrf')
    expect(headers.has('Authorization')).toBe(false)
  })

  it('sends cookies to protected channel endpoints', async () => {
    document.cookie = 'XSRF-TOKEN=test-csrf'
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

    await radioApi.channel(7)

    const { url, options } = lastRequest()
    expect(url).toBe('/api/v1/channels/7')
    expect(options?.credentials).toBe('include')
    expect((options?.headers as Headers).has('Authorization')).toBe(false)
  })

  it('preserves structured API errors for the connection layer', async () => {
    document.cookie = 'XSRF-TOKEN=test-csrf'
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

    const request = radioApi.startTransmission(7)

    await expect(request).rejects.toMatchObject({
      name: 'ApiError',
      message: 'Channel 7 is already in use.',
      status: 409,
      code: 'channel_busy',
      errors: { channel: ['Wait for the current caller to release PTT.'] },
    })
  })

  it('maps network and timeout failures to stable client errors', async () => {
    document.cookie = 'XSRF-TOKEN=test-csrf'
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
    document.cookie = 'XSRF-TOKEN=test-csrf'
    fetchMock.mockResolvedValue(new Response(null, { status: 204 }))
    vi.stubGlobal('fetch', fetchMock)

    await expect(radioApi.endTransmission('tx/1')).resolves.toBeUndefined()
    expect(lastRequest().url).toBe('/api/v1/transmissions/tx%2F1')
  })
})
