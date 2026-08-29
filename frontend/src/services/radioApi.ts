import { ApiError, apiRequest, jsonBody } from './http'

export { ApiError as RadioApiError }

export interface RadioSession {
  id: string
  callsign: string
  channel: number
  last_seen_at: string
  connected_at: string
}

export interface Transmission {
  id: string
  session_id: string
  callsign: string
  channel: number
  started_at: string
  last_seen_at: string
  ended_at: string | null
}

export interface ChannelStatus {
  number: number
  listener_count: number
  is_busy: boolean
  active_transmission: Transmission | null
}

export interface SessionResponse {
  data: RadioSession
  meta: {
    heartbeat_interval_seconds: number
    presence_ttl_seconds: number
    server_time: string
  }
}

export interface ResourceResponse<T> {
  data: T
  meta?: Record<string, unknown>
}

const API_BASE_URL = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/$/, '')

function radioPath(path: string): string {
  return `${API_BASE_URL}${path}`
}

export const radioApi = {
  health: () =>
    apiRequest<ResourceResponse<{ service: string; status: string; server_time: string }>>(
      radioPath('/health'),
    ),

  createSession: (callsign: string, channel: number) =>
    apiRequest<SessionResponse>(radioPath('/sessions'), {
      method: 'POST',
      ...jsonBody({ callsign, channel }),
    }),

  currentSession: () => apiRequest<ResourceResponse<RadioSession>>(radioPath('/sessions/current')),

  updateSession: (changes: Partial<Pick<RadioSession, 'callsign' | 'channel'>>) =>
    apiRequest<ResourceResponse<RadioSession>>(radioPath('/sessions/current'), {
      method: 'PATCH',
      ...jsonBody(changes),
    }),

  heartbeatSession: () =>
    apiRequest<ResourceResponse<RadioSession>>(radioPath('/sessions/current/heartbeat'), {
      method: 'POST',
    }),

  endSession: () => apiRequest<void>(radioPath('/sessions/current'), { method: 'DELETE' }),

  channel: (channel: number) =>
    apiRequest<ResourceResponse<ChannelStatus>>(radioPath(`/channels/${channel}`)),

  channels: () => apiRequest<ResourceResponse<ChannelStatus[]>>(radioPath('/channels')),

  startTransmission: (channel: number) =>
    apiRequest<ResourceResponse<Transmission>>(radioPath(`/channels/${channel}/transmissions`), {
      method: 'POST',
    }),

  heartbeatTransmission: (transmissionId: string) =>
    apiRequest<ResourceResponse<Transmission>>(
      radioPath(`/transmissions/${encodeURIComponent(transmissionId)}`),
      { method: 'PATCH' },
    ),

  endTransmission: (transmissionId: string) =>
    apiRequest<void>(radioPath(`/transmissions/${encodeURIComponent(transmissionId)}`), {
      method: 'DELETE',
    }),
}
