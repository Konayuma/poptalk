export interface ApiErrorPayload {
  message?: string
  code?: string
  errors?: Record<string, string[]>
}

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly code = 'request_failed',
    readonly errors: Record<string, string[]> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }
}

const REQUEST_TIMEOUT_MS = 8_000

function readCookie(name: string): string {
  const encoded = encodeURIComponent(name)
  const parts = document.cookie.split(';')

  for (const part of parts) {
    const trimmed = part.trim()
    if (trimmed.startsWith(`${encoded}=`) || trimmed.startsWith(`${name}=`)) {
      return decodeURIComponent(trimmed.slice(trimmed.indexOf('=') + 1))
    }
  }

  return ''
}

function csrfHeaders(): Headers {
  const headers = new Headers()
  const token = readCookie('XSRF-TOKEN')

  if (token) {
    headers.set('X-XSRF-TOKEN', token)
  }

  return headers
}

export async function ensureCsrfCookie(): Promise<void> {
  if (readCookie('XSRF-TOKEN')) return

  await fetch('/sanctum/csrf-cookie', {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  })
}

export async function apiRequest<T>(path: string, options: RequestInit = {}, retried = false): Promise<T> {
  await ensureCsrfCookie()

  const controller = new AbortController()
  const timeout = window.setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS)
  const headers = csrfHeaders()

  new Headers(options.headers).forEach((value, key) => {
    headers.set(key, value)
  })
  headers.set('Accept', 'application/json')
  if (options.body !== undefined) headers.set('Content-Type', 'application/json')

  try {
    const response = await fetch(path, {
      ...options,
      credentials: 'include',
      headers,
      signal: controller.signal,
    })

    if (response.status === 204) return undefined as T

    if (response.status === 419 && !retried) {
      document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/'
      await ensureCsrfCookie()
      return apiRequest<T>(path, options, true)
    }

    const payload = (await response.json().catch(() => ({}))) as ApiErrorPayload | T

    if (!response.ok) {
      const error = payload as ApiErrorPayload
      const apiError = new ApiError(
        error.message || `The server returned ${response.status}.`,
        response.status,
        error.code,
        error.errors,
      )

      if (
        response.status === 401 &&
        apiError.code !== 'radio_session_expired' &&
        apiError.code !== 'invalid_session_token'
      ) {
        window.dispatchEvent(new CustomEvent('poptalk:unauthenticated'))
      }

      throw apiError
    }

    return payload as T
  } catch (error) {
    if (error instanceof ApiError) throw error
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new ApiError('The radio server did not respond in time.', 0, 'timeout')
    }

    throw new ApiError(
      'The radio server is unavailable. Check that the backend is running.',
      0,
      'network_error',
    )
  } finally {
    window.clearTimeout(timeout)
  }
}

export function jsonBody(data: object): Pick<RequestInit, 'body'> {
  return { body: JSON.stringify(data) }
}
