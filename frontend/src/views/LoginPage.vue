<script setup lang="ts">
import { reactive } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth'

const router = useRouter()
const route = useRoute()
const { errorMessage, pending, login } = useAuth()

const form = reactive({
  email: '',
  password: '',
  remember: true,
})

async function submit() {
  const ok = await login({
    email: form.email,
    password: form.password,
    remember: form.remember,
  })

  if (!ok) return

  const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/'
  await router.replace(redirect)
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
      <span class="section-kicker">Operator check-in</span>
      <h1>Sign in and grab the mic.</h1>
      <p class="auth-card__lede">Your session stays on this device until you sign out or it expires.</p>

      <form class="auth-form" @submit.prevent="submit">
        <p v-if="errorMessage" class="auth-alert" role="alert">{{ errorMessage }}</p>

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
          <span>Password</span>
          <input
            v-model="form.password"
            type="password"
            name="password"
            autocomplete="current-password"
            required
            :disabled="pending"
          />
        </label>

        <label class="auth-remember">
          <input v-model="form.remember" type="checkbox" :disabled="pending" />
          Keep me signed in on this device
        </label>

        <button class="auth-submit" type="submit" :disabled="pending">
          {{ pending ? 'Checking the line…' : 'Sign in' }}
        </button>
      </form>

      <p class="auth-switch">
        New operator?
        <RouterLink to="/register">Create an account</RouterLink>
      </p>
    </main>
  </div>
</template>
