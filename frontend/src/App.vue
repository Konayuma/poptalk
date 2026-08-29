<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useAuth } from './composables/useAuth'

const { ready } = useAuth()
</script>

<template>
  <div v-if="!ready" class="auth-boot" role="status">
    <strong>POP! TALK</strong>
    <span>Checking your session…</span>
  </div>
  <RouterView v-else />
</template>
