import { computed, onBeforeUnmount, ref, shallowRef } from 'vue'
import {
  RadioApiError,
  radioApi,
  type ChannelStatus,
  type RadioSession,
  type Transmission,
} from '../services/radioApi'

export type BackendConnectionState = 'idle' | 'connecting' | 'connected' | 'disconnected'

const SESSION_TOKEN_KEY = 'poptalk.radio-session-token'
const STATUS_REFRESH_INTERVAL_MS = 4_000
const HEARTBEAT_INTERVAL_MS = 10_000
const RECONNECT_DELAY_MS = 5_000

export function useRadioBackend() {
  const connectionState = ref<BackendConnectionState>('idle')
  const connectionError = ref('')
  const isSyncingIdentity = ref(false)
  const session = shallowRef<RadioSession | null>(null)
  const channelStatus = shallowRef<ChannelStatus | null>(null)
  const currentTransmission = shallowRef<Transmission | null>(null)

  let token = readSessionToken()
  let desiredCallsign = 'ROOKIE-7'
  let desiredChannel = 7
  let connectPromise: Promise<boolean> | null = null
  let identityPromise: Promise<boolean> | null = null
  let statusTimer = 0
  let heartbeatTimer = 0
  let reconnectTimer = 0
  let disposed = false

  const remotePeerCount = computed(() => {
    const listenerCount = channelStatus.value?.listener_count ?? 0
    const includesCurrentSession =
      session.value !== null && session.value.channel === channelStatus.value?.number

    return Math.max(0, listenerCount - (includesCurrentSession ? 1 : 0))
  })

  const activeSpeaker = computed(() => channelStatus.value?.active_transmission ?? null)
  const isChannelBusy = computed(
    () =>
      activeSpeaker.value !== null &&
      activeSpeaker.value.session_id !== session.value?.id,
  )

  function readSessionToken() {
    try {
      return window.sessionStorage.getItem(SESSION_TOKEN_KEY) ?? ''
    } catch {
      return ''
    }
  }

  function storeSessionToken(nextToken: string) {
    token = nextToken
    try {
      if (nextToken) {
        window.sessionStorage.setItem(SESSION_TOKEN_KEY, nextToken)
      } else {
        window.sessionStorage.removeItem(SESSION_TOKEN_KEY)
      }
    } catch {
      // Session continuity is optional when browser storage is unavailable.
    }
  }

  function stopTimers() {
    window.clearInterval(statusTimer)
    window.clearInterval(heartbeatTimer)
    window.clearTimeout(reconnectTimer)
    statusTimer = 0
    heartbeatTimer = 0
    reconnectTimer = 0
  }

  function startTimers() {
    stopTimers()
    statusTimer = window.setInterval(() => void refreshChannel(), STATUS_REFRESH_INTERVAL_MS)
    heartbeatTimer = window.setInterval(() => void heartbeat(), HEARTBEAT_INTERVAL_MS)
  }

  function errorMessage(error: unknown) {
    return error instanceof Error ? error.message : 'The radio server is unavailable.'
  }

  function markDisconnected(error: unknown) {
    if (disposed) return
    connectionState.value = 'disconnected'
    connectionError.value = errorMessage(error)
    stopTimers()
    scheduleReconnect()
  }

  function scheduleReconnect() {
    if (disposed || reconnectTimer) return
    reconnectTimer = window.setTimeout(() => {
      reconnectTimer = 0
      void connect(desiredCallsign, desiredChannel)
    }, RECONNECT_DELAY_MS)
  }

  async function restoreSession() {
    if (!token) return null

    try {
      return (await radioApi.currentSession(token)).data
    } catch (error) {
      if (error instanceof RadioApiError && error.status === 401) {
        storeSessionToken('')
        return null
      }

      throw error
    }
  }

  async function connect(callsign: string, channel: number): Promise<boolean> {
    desiredCallsign = callsign
    desiredChannel = channel

    if (connectPromise) return connectPromise

    connectPromise = (async () => {
      connectionState.value = 'connecting'
      connectionError.value = ''
      window.clearTimeout(reconnectTimer)
      reconnectTimer = 0

      try {
        let restoredSession = await restoreSession()

        if (disposed) return false

        if (restoredSession === null) {
          const response = await radioApi.createSession(desiredCallsign, desiredChannel)
          storeSessionToken(response.meta.session_token)
          restoredSession = response.data
        }

        while (
          restoredSession.callsign !== desiredCallsign ||
          restoredSession.channel !== desiredChannel
        ) {
          restoredSession = (
            await radioApi.updateSession(token, {
              callsign: desiredCallsign,
              channel: desiredChannel,
            })
          ).data
        }

        if (disposed) return false

        session.value = restoredSession
        connectionState.value = 'connected'
        connectionError.value = ''
        startTimers()
        await refreshChannel()

        return connectionState.value === 'connected'
      } catch (error) {
        markDisconnected(error)
        return false
      } finally {
        connectPromise = null
      }
    })()

    return connectPromise
  }

  async function refreshChannel(): Promise<boolean> {
    if (!token || connectionState.value !== 'connected') return false

    try {
      const requestedChannel = desiredChannel
      const response = await radioApi.channel(token, requestedChannel)

      if (requestedChannel !== desiredChannel) return refreshChannel()

      channelStatus.value = response.data
      connectionError.value = ''
      return true
    } catch (error) {
      markDisconnected(error)
      return false
    }
  }

  async function updateIdentity(callsign: string, channel: number): Promise<boolean> {
    desiredCallsign = callsign
    desiredChannel = channel

    if (connectPromise) {
      const connected = await connectPromise
      if (!connected) return false
    }

    if (identityPromise) {
      const updated = await identityPromise
      if (!updated) return false
      return updateIdentity(desiredCallsign, desiredChannel)
    }

    if (!token || session.value === null || connectionState.value !== 'connected') {
      return connect(callsign, channel)
    }

    if (
      session.value.callsign === desiredCallsign &&
      session.value.channel === desiredChannel
    ) {
      return true
    }

    const requestedCallsign = desiredCallsign
    const requestedChannel = desiredChannel
    isSyncingIdentity.value = true

    identityPromise = (async () => {
      try {
        session.value = (
          await radioApi.updateSession(token, {
            callsign: requestedCallsign,
            channel: requestedChannel,
          })
        ).data
        currentTransmission.value = null
        channelStatus.value = null
        return refreshChannel()
      } catch (error) {
        markDisconnected(error)
        return false
      }
    })()

    const updated = await identityPromise
    identityPromise = null

    if (
      updated &&
      (requestedCallsign !== desiredCallsign || requestedChannel !== desiredChannel)
    ) {
      return updateIdentity(desiredCallsign, desiredChannel)
    }

    isSyncingIdentity.value = false
    return updated
  }

  async function claimFloor(channel: number): Promise<boolean> {
    if (!token || connectionState.value !== 'connected') {
      connectionError.value = 'Connect to the radio server before transmitting.'
      return false
    }

    if (isChannelBusy.value) {
      connectionError.value = `${activeSpeaker.value?.callsign ?? 'Another caller'} is already talking.`
      return false
    }

    if (session.value?.channel !== channel) {
      const identityUpdated = await updateIdentity(desiredCallsign, channel)
      if (!identityUpdated) return false
    }

    try {
      currentTransmission.value = (await radioApi.startTransmission(token, channel)).data
      connectionError.value = ''
      await refreshChannel()
      return true
    } catch (error) {
      if (error instanceof RadioApiError && error.status === 409) {
        await refreshChannel()
        connectionError.value = error.message
        return false
      }

      markDisconnected(error)
      return false
    }
  }

  async function releaseFloor(): Promise<void> {
    const transmission = currentTransmission.value
    currentTransmission.value = null

    if (!token || transmission === null) return

    try {
      await radioApi.endTransmission(token, transmission.id)
      connectionError.value = ''
      await refreshChannel()
    } catch (error) {
      if (error instanceof RadioApiError && error.status === 404) {
        await refreshChannel()
        return
      }

      markDisconnected(error)
    }
  }

  async function heartbeat() {
    if (!token || connectionState.value !== 'connected') return

    try {
      session.value = (await radioApi.heartbeatSession(token)).data

      if (currentTransmission.value !== null) {
        try {
          currentTransmission.value = (
            await radioApi.heartbeatTransmission(token, currentTransmission.value.id)
          ).data
        } catch (error) {
          if (!(error instanceof RadioApiError) || error.status !== 404) throw error
          currentTransmission.value = null
        }
      }

      connectionError.value = ''
    } catch (error) {
      markDisconnected(error)
    }
  }

  async function disconnect(): Promise<void> {
    stopTimers()
    currentTransmission.value = null

    if (token) {
      try {
        await radioApi.endSession(token)
      } catch {
        // Presence expires automatically if the final request cannot be delivered.
      }
    }

    storeSessionToken('')
    session.value = null
    channelStatus.value = null
    isSyncingIdentity.value = false
    connectionState.value = 'idle'
    connectionError.value = ''
  }

  onBeforeUnmount(() => {
    disposed = true
    stopTimers()
    void releaseFloor()
  })

  return {
    connectionState,
    connectionError,
    isSyncingIdentity,
    session,
    channelStatus,
    currentTransmission,
    remotePeerCount,
    activeSpeaker,
    isChannelBusy,
    connect,
    updateIdentity,
    refreshChannel,
    claimFloor,
    releaseFloor,
    disconnect,
  }
}
