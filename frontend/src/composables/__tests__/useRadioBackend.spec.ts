import { defineComponent, h } from 'vue'
import { mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  RadioApiError,
  radioApi,
  type ChannelStatus,
  type RadioSession,
  type ResourceResponse,
  type Transmission,
} from '../../services/radioApi'
import { useRadioBackend } from '../useRadioBackend'

vi.mock('../../services/radioApi', async (importOriginal) => {
  const actual = await importOriginal<typeof import('../../services/radioApi')>()

  return {
    ...actual,
    radioApi: {
      health: vi.fn(),
      createSession: vi.fn(),
      currentSession: vi.fn(),
      updateSession: vi.fn(),
      heartbeatSession: vi.fn(),
      endSession: vi.fn(),
      channel: vi.fn(),
      channels: vi.fn(),
      startTransmission: vi.fn(),
      heartbeatTransmission: vi.fn(),
      endTransmission: vi.fn(),
    },
  }
})

const api = vi.mocked(radioApi)
const SESSION_TOKEN_KEY = 'poptalk.radio-session-token'
const connectedAt = '2026-08-28T11:00:00.000Z'

function session(overrides: Partial<RadioSession> = {}): RadioSession {
  return {
    id: 'session-1',
    callsign: 'ROOKIE-7',
    channel: 7,
    last_seen_at: connectedAt,
    connected_at: connectedAt,
    ...overrides,
  }
}

function channelStatus(
  number: number,
  activeTransmission: Transmission | null = null,
): ChannelStatus {
  return {
    number,
    listener_count: 1,
    is_busy: activeTransmission !== null,
    active_transmission: activeTransmission,
  }
}

function transmission(overrides: Partial<Transmission> = {}): Transmission {
  return {
    id: 'transmission-1',
    session_id: 'session-1',
    callsign: 'ROOKIE-7',
    channel: 7,
    started_at: connectedAt,
    last_seen_at: connectedAt,
    ended_at: null,
    ...overrides,
  }
}

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason?: unknown) => void
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise
    reject = rejectPromise
  })

  return { promise, resolve, reject }
}

let wrapper: VueWrapper | null = null

function mountBackend() {
  let backend: ReturnType<typeof useRadioBackend> | undefined
  const Harness = defineComponent({
    setup() {
      backend = useRadioBackend()
      return () => h('div')
    },
  })

  wrapper = mount(Harness)
  if (!backend) throw new Error('The radio backend harness did not initialize.')

  return backend
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.resetAllMocks()
  window.sessionStorage.clear()

  api.createSession.mockResolvedValue({
    data: session(),
    meta: {
      session_token: 'new-session-token',
      heartbeat_interval_seconds: 10,
      presence_ttl_seconds: 30,
      server_time: connectedAt,
    },
  })
  api.currentSession.mockResolvedValue({ data: session() })
  api.updateSession.mockImplementation(async (_token, changes) => ({
    data: session(changes),
  }))
  api.heartbeatSession.mockResolvedValue({ data: session() })
  api.channel.mockImplementation(async (_token, number) => ({
    data: channelStatus(number),
  }))
  api.channels.mockResolvedValue({ data: [channelStatus(7)] })
  api.startTransmission.mockResolvedValue({ data: transmission() })
  api.heartbeatTransmission.mockResolvedValue({ data: transmission() })
  api.endTransmission.mockResolvedValue(undefined)
  api.endSession.mockResolvedValue(undefined)
})

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  window.sessionStorage.clear()
  vi.useRealTimers()
})

describe('useRadioBackend', () => {
  it('creates a session, stores its token, and loads channel presence', async () => {
    const backend = mountBackend()

    await expect(backend.connect('ROOKIE-7', 7)).resolves.toBe(true)

    expect(api.createSession).toHaveBeenCalledWith('ROOKIE-7', 7)
    expect(api.channel).toHaveBeenCalledWith('new-session-token', 7)
    expect(window.sessionStorage.getItem(SESSION_TOKEN_KEY)).toBe('new-session-token')
    expect(backend.connectionState.value).toBe('connected')
    expect(backend.session.value).toEqual(session())
    expect(backend.remotePeerCount.value).toBe(0)
  })

  it('replaces an expired stored session', async () => {
    window.sessionStorage.setItem(SESSION_TOKEN_KEY, 'expired-token')
    api.currentSession.mockRejectedValueOnce(
      new RadioApiError('The session expired.', 401, 'invalid_session_token'),
    )
    const backend = mountBackend()

    await expect(backend.connect('ROOKIE-7', 7)).resolves.toBe(true)

    expect(api.currentSession).toHaveBeenCalledWith('expired-token')
    expect(api.createSession).toHaveBeenCalledOnce()
    expect(window.sessionStorage.getItem(SESSION_TOKEN_KEY)).toBe('new-session-token')
  })

  it('serializes rapid identity changes so the newest channel wins', async () => {
    const backend = mountBackend()
    await backend.connect('ROOKIE-7', 7)

    const firstUpdate = deferred<ResourceResponse<RadioSession>>()
    api.updateSession
      .mockImplementationOnce(() => firstUpdate.promise)
      .mockImplementationOnce(async (_token, changes) => ({
        data: session(changes),
      }))

    const updateToEight = backend.updateIdentity('ROOKIE-8', 8)
    const updateToNine = backend.updateIdentity('ROOKIE-9', 9)

    expect(api.updateSession).toHaveBeenCalledTimes(1)
    firstUpdate.resolve({ data: session({ callsign: 'ROOKIE-8', channel: 8 }) })

    await expect(Promise.all([updateToEight, updateToNine])).resolves.toEqual([true, true])
    expect(api.updateSession).toHaveBeenCalledTimes(2)
    expect(api.updateSession).toHaveBeenLastCalledWith('new-session-token', {
      callsign: 'ROOKIE-9',
      channel: 9,
    })
    expect(backend.session.value).toMatchObject({ callsign: 'ROOKIE-9', channel: 9 })
    expect(backend.isSyncingIdentity.value).toBe(false)
  })

  it('claims and releases the PTT floor through the API', async () => {
    const backend = mountBackend()
    await backend.connect('ROOKIE-7', 7)

    await expect(backend.claimFloor(7)).resolves.toBe(true)
    expect(backend.currentTransmission.value).toEqual(transmission())

    await backend.releaseFloor()
    expect(api.endTransmission).toHaveBeenCalledWith(
      'new-session-token',
      'transmission-1',
    )
    expect(backend.currentTransmission.value).toBeNull()
  })

  it('keeps a busy-channel conflict available for the UI', async () => {
    const backend = mountBackend()
    await backend.connect('ROOKIE-7', 7)
    api.startTransmission.mockRejectedValueOnce(
      new RadioApiError('MANGO-4 is already talking.', 409, 'channel_busy'),
    )

    await expect(backend.claimFloor(7)).resolves.toBe(false)

    expect(backend.connectionState.value).toBe('connected')
    expect(backend.connectionError.value).toBe('MANGO-4 is already talking.')
    expect(backend.currentTransmission.value).toBeNull()
  })
})
