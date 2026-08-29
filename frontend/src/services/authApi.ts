import { apiRequest, jsonBody } from './http'

export interface AuthUser {
  id: string
  name: string
  email: string
  callsign: string
}

export interface AuthResponse {
  data: AuthUser
  meta: {
    session_lifetime_minutes: number
  }
}

export interface RegisterPayload {
  name: string
  email: string
  callsign: string
  password: string
  password_confirmation: string
}

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export const authApi = {
  register: (payload: RegisterPayload) =>
    apiRequest<AuthResponse>('/api/auth/register', {
      method: 'POST',
      ...jsonBody(payload),
    }),

  login: (payload: LoginPayload) =>
    apiRequest<AuthResponse>('/api/auth/login', {
      method: 'POST',
      ...jsonBody(payload),
    }),

  user: () => apiRequest<AuthResponse>('/api/auth/user'),

  logout: () => apiRequest<void>('/api/auth/logout', { method: 'POST' }),
}
