import { computed, ref } from 'vue'
import { ApiError } from '../services/http'
import { authApi, type AuthUser, type LoginPayload, type RegisterPayload } from '../services/authApi'

const user = ref<AuthUser | null>(null)
const ready = ref(false)
const pending = ref(false)
const errorMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

let bootPromise: Promise<void> | null = null

function firstError(errors: Record<string, string[]>, keys: string[]): string {
  for (const key of keys) {
    const messages = errors[key]
    if (messages?.[0]) return messages[0]
  }

  return ''
}

function captureError(error: unknown, fallback: string) {
  if (error instanceof ApiError) {
    fieldErrors.value = error.errors
    errorMessage.value =
      firstError(error.errors, ['email', 'password', 'callsign', 'name']) || error.message || fallback
    return
  }

  fieldErrors.value = {}
  errorMessage.value = fallback
}

function applyUser(nextUser: AuthUser | null) {
  user.value = nextUser
  errorMessage.value = ''
  fieldErrors.value = {}
}

async function bootstrap() {
  if (bootPromise) return bootPromise

  bootPromise = (async () => {
    try {
      applyUser((await authApi.user()).data)
    } catch {
      applyUser(null)
    } finally {
      ready.value = true
    }
  })()

  return bootPromise
}

async function register(payload: RegisterPayload) {
  pending.value = true
  errorMessage.value = ''
  fieldErrors.value = {}

  try {
    applyUser((await authApi.register(payload)).data)
    return true
  } catch (error) {
    captureError(error, 'Could not create the account.')
    return false
  } finally {
    pending.value = false
  }
}

async function login(payload: LoginPayload) {
  pending.value = true
  errorMessage.value = ''
  fieldErrors.value = {}

  try {
    applyUser((await authApi.login(payload)).data)
    return true
  } catch (error) {
    captureError(error, 'Could not sign in.')
    return false
  } finally {
    pending.value = false
  }
}

async function logout() {
  try {
    await authApi.logout()
  } catch {
    // The local session is cleared even if the network request fails.
  } finally {
    applyUser(null)
  }
}

function clearSession() {
  applyUser(null)
}

export function useAuth() {
  return {
    user,
    ready,
    pending,
    errorMessage,
    fieldErrors,
    isAuthenticated: computed(() => user.value !== null),
    bootstrap,
    register,
    login,
    logout,
    clearSession,
  }
}

if (typeof window !== 'undefined') {
  window.addEventListener('poptalk:unauthenticated', () => {
    if (user.value !== null) clearSession()
  })
}
