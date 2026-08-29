import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { createMemoryHistory, createRouter, RouterView } from 'vue-router'
import RadioRoom from '../views/RadioRoom.vue'

const fetchMock = vi.fn<typeof fetch>()
const timestamp = '2026-08-28T11:00:00.000Z'

vi.mock('../composables/useAuth', () => ({
  useAuth: () => ({
    user: ref({
      id: 'session-1',
      name: 'Rookie',
      email: 'rookie@example.com',
      callsign: 'ROOKIE-7',
    }),
    logout: vi.fn().mockResolvedValue(undefined),
    ready: ref(true),
  }),
}))

function jsonResponse(payload: unknown, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

async function mountRadio() {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'radio', component: RadioRoom },
      { path: '/login', name: 'login', component: { template: '<div>login</div>' } },
    ],
  })
  await router.push('/')
  await router.isReady()

  const wrapper = mount(
    defineComponent({
      setup: () => () => h(RouterView),
    }),
    {
      global: {
        plugins: [router],
      },
    },
  )

  await flushPromises()
  return wrapper
}

beforeEach(() => {
  window.localStorage.clear()
  window.sessionStorage.clear()
  document.cookie = 'XSRF-TOKEN=test-csrf'
  fetchMock.mockImplementation(async (input) => {
    const url = String(input)

    if (url.includes('/sanctum/csrf-cookie')) {
      return new Response(null, { status: 204 })
    }

    if (url.endsWith('/sessions/current') && !url.includes('heartbeat')) {
      return jsonResponse({ message: 'The radio session has expired.', code: 'radio_session_expired' }, 401)
    }

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
          heartbeat_interval_seconds: 10,
          presence_ttl_seconds: 30,
          server_time: timestamp,
        },
      }, 201)
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

describe('RadioRoom backend integration', () => {
  it('connects on mount and renders live presence from the control API', async () => {
    const wrapper = await mountRadio()

    expect(wrapper.get('.mode-badge').text()).toContain('Base linked')
    expect(wrapper.get('.speaker-copy strong').text()).toBe('Open line')
    expect(wrapper.get('.speaker-copy small').text()).toBe('2 remote peers')
    expect(wrapper.get('.station-stats').text()).toContain('Base linked')

    wrapper.unmount()
  })

  it('shows a retryable state when the backend cannot be reached', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))
    const wrapper = await mountRadio()

    expect(wrapper.get('.mic-alert--connection').text()).toContain('Control API offline')
    expect(wrapper.get('.mic-alert--connection').text()).toContain('backend is running')
    expect(wrapper.get('.mode-badge').text()).toContain('Base offline')

    wrapper.unmount()
  })
})
