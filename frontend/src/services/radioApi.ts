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
    session_token: string
    heartbeat_interval_seconds: number
    presence_ttl_seconds: number
    server_time: string
  }
}

export interface ResourceResponse<T> {
  data: T
  meta?: Record<string, unknown>
}

interface ApiErrorPayload {
  message?: string
  code?: string
  errors?: Record<string, string[]>
}

export class RadioApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly code = 'request_failed',
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'RadioApiError'
  }
}

const API_BASE_URL = (import.meta.env.VITE_API_URL || '/api/v1').replace(/\/$/, '')
const REQUEST_TIMEOUT_MS = 8_000

async function request<T>(
  path: string,
  options: RequestInit = {},
  token?: string,
): Promise<T> {
  const controller = new AbortController()
  const timeout = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS)
  const headers = new Headers(options.headers)
  headers.set('Accept', 'application/json')

  if (options.body !== undefined) headers.set('Content-Type', 'application/json')
  if (token) headers.set('Authorization', `Bearer ${token}`)

  try {
    const response = await fetch(`${API_BASE_URL}${path}`, {
      ...options,
      headers,
      signal: controller.signal,
    })

    if (response.status === 204) return undefined as T

    const payload = (await response.json().catch(() => ({}))) as ApiErrorPayload | T

    if (!response.ok) {
      const error = payload as ApiErrorPayload
      throw new RadioApiError(
        error.message || `The radio API returned ${response.status}.`,
        response.status,
        error.code,
        error.errors,
      )
    }

    return payload as T
  } catch (error) {
    if (error instanceof RadioApiError) throw error
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new RadioApiError('The radio server did not respond in time.', 0, 'timeout')
    }

    throw new RadioApiError(
      'The radio server is unavailable. Check that the backend is running.',
      0,
      'network_error',
    )
  } finally {
    window.clearTimeout(timeout)
  }
}

function jsonBody(data: object): Pick<RequestInit, 'body'> {
  return { body: JSON.stringify(data) }
}

export const radioApi = {
  health: () =>
    request<ResourceResponse<{ service: string; status: string; server_time: string }>>('/health'),

  createSession: (callsign: string, channel: number) =>
    request<SessionResponse>('/sessions', {
      method: 'POST',
      ...jsonBody({ callsign, channel }),
    }),

  currentSession: (token: string) =>
    request<ResourceResponse<RadioSession>>('/sessions/current', {}, token),

  updateSession: (token: string, changes: Partial<Pick<RadioSession, 'callsign' | 'channel'>>) =>
    request<ResourceResponse<RadioSession>>(
      '/sessions/current',
      {
        method: 'PATCH',
        ...jsonBody(changes),
      },
      token,
    ),

  heartbeatSession: (token: string) =>
    request<ResourceResponse<RadioSession>>(
      '/sessions/current/heartbeat',
      { method: 'POST' },
      token,
    ),

  endSession: (token: string) =>
    request<void>('/sessions/current', { method: 'DELETE' }, token),

  channel: (token: string, channel: number) =>
    request<ResourceResponse<ChannelStatus>>(`/channels/${channel}`, {}, token),

  channels: (token: string) =>
    request<ResourceResponse<ChannelStatus[]>>('/channels', {}, token),

  startTransmission: (token: string, channel: number) =>
    request<ResourceResponse<Transmission>>(
      `/channels/${channel}/transmissions`,
      { method: 'POST' },
      token,
    ),

  heartbeatTransmission: (token: string, transmissionId: string) =>
    request<ResourceResponse<Transmission>>(
      `/transmissions/${encodeURIComponent(transmissionId)}`,
      { method: 'PATCH' },
      token,
    ),

  endTransmission: (token: string, transmissionId: string) =>
    request<void>(
      `/transmissions/${encodeURIComponent(transmissionId)}`,
      { method: 'DELETE' },
      token,
    ),
}
