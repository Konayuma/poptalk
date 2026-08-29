<script setup lang="ts">
import { reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const { errorMessage, pending, register } = useAuth()

const form = reactive({
  name: '',
  email: '',
  callsign: '',
  password: '',
  password_confirmation: '',
})

function updateCallsign(event: Event) {
  const target = event.target as HTMLInputElement
  form.callsign = target.value
    .toUpperCase()
    .replace(/[^A-Z0-9-]/g, '')
    .slice(0, 16)
}

async function submit() {
  const ok = await register({
    name: form.name.trim(),
    email: form.email.trim(),
    callsign: form.callsign,
    password: form.password,
    password_confirmation: form.password_confirmation,
  })

  if (!ok) return
  await router.replace('/')
}
</script>

<template>
  <div class="auth-shell">
    <div class="comic-wash comic-wash--yellow" aria-hidden="true"></div>
    <div class="comic-wash comic-wash--cyan" aria-hidden="true"></div>
    <div class="comic-wash comic-wash--pink" aria-hidden="true"></div>

    <RouterLink class="brand brand--auth" to="/login" aria-label="Pop Talk home">
      <span class="brand__pop">POP!</span>
      <span class="brand__talk">TALK</span>
      <span class="brand__bolt" aria-hidden="true">ϟ</span>
    </RouterLink>

    <main class="auth-card">
      <span class="section-kicker">Get on the band</span>
      <h1>Create your operator account.</h1>
      <p class="auth-card__lede">Use a unique callsign. Passwords need 8+ characters, mixed case, and a number.</p>

      <form class="auth-form" @submit.prevent="submit">
        <p v-if="errorMessage" class="auth-alert" role="alert">{{ errorMessage }}</p>

        <label class="auth-field">
          <span>Name</span>
          <input
            v-model="form.name"
            type="text"
            name="name"
            autocomplete="name"
            required
            maxlength="255"
            :disabled="pending"
          />
        </label>

        <label class="auth-field">
          <span>Email</span>
          <input
            v-model="form.email"
            type="email"
            name="email"
            autocomplete="email"
            required
            :disabled="pending"
          />
        </label>

        <label class="auth-field">
          <span>Callsign</span>
          <input
            :value="form.callsign"
            type="text"
            name="callsign"
            autocomplete="nickname"
            spellcheck="false"
            required
            maxlength="16"
            :disabled="pending"
            @input="updateCallsign"
          />
        </label>

        <label class="auth-field">
          <span>Password</span>
          <input
            v-model="form.password"
            type="password"
            name="password"
            autocomplete="new-password"
            required
            minlength="8"
            :disabled="pending"
          />
        </label>

        <label class="auth-field">
          <span>Confirm password</span>
          <input
            v-model="form.password_confirmation"
            type="password"
            name="password_confirmation"
            autocomplete="new-password"
            required
            minlength="8"
            :disabled="pending"
          />
        </label>

        <button class="auth-submit" type="submit" :disabled="pending">
          {{ pending ? 'Creating account…' : 'Create account' }}
        </button>
      </form>

      <p class="auth-switch">
        Already keyed in?
        <RouterLink to="/login">Sign in</RouterLink>
      </p>
    </main>
  </div>
</template>
