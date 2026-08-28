import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import App from '../App.vue'

const fetchMock = vi.fn<typeof fetch>()
const timestamp = '2026-08-28T11:00:00.000Z'

function jsonResponse(payload: unknown) {
  return new Response(JSON.stringify(payload), {
    status: 200,
    headers: { 'Content-Type': 'application/json' },
  })
}

beforeEach(() => {
  window.localStorage.clear()
  window.sessionStorage.clear()
  fetchMock.mockImplementation(async (input) => {
    const url = String(input)

    if (url.endsWith('/sessions')) {
      return jsonResponse({
        data: {
          id: 'session-1',
          callsign: 'ROOKIE-7',
          channel: 7,
          last_seen_at: timestamp,
          connected_at: timestamp,
        },
        meta: {
          session_token: 'session-token',
          heartbeat_interval_seconds: 10,
          presence_ttl_seconds: 30,
          server_time: timestamp,
        },
      })
    }

    if (url.endsWith('/channels/7')) {
      return jsonResponse({
        data: {
          number: 7,
          listener_count: 3,
          is_busy: false,
          active_transmission: null,
        },
      })
    }

    throw new TypeError(`Unexpected API request: ${url}`)
  })
  vi.stubGlobal('fetch', fetchMock)
})

afterEach(() => {
  vi.unstubAllGlobals()
  fetchMock.mockReset()
  window.localStorage.clear()
  window.sessionStorage.clear()
})

describe('App backend integration', () => {
  it('connects on mount and renders live presence from the control API', async () => {
    const wrapper = mount(App)
    await flushPromises()

    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(wrapper.get('.mode-badge').text()).toContain('Base linked')
    expect(wrapper.get('.speaker-copy strong').text()).toBe('Open line')
    expect(wrapper.get('.speaker-copy small').text()).toBe('2 remote peers')
    expect(wrapper.get('.station-stats').text()).toContain('Base linked')

    wrapper.unmount()
  })

  it('shows a retryable state when the backend cannot be reached', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    const wrapper = mount(App)
    await flushPromises()

    expect(wrapper.get('.mic-alert--connection').text()).toContain('Control API offline')
    expect(wrapper.get('.mic-alert--connection').text()).toContain(
      'backend is running',
    )
    expect(wrapper.get('.mode-badge').text()).toContain('Base offline')

    wrapper.unmount()
  })
})
