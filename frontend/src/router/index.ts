import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '../composables/useAuth'
import LoginPage from '../views/LoginPage.vue'
import RadioRoom from '../views/RadioRoom.vue'
import RegisterPage from '../views/RegisterPage.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'radio',
      component: RadioRoom,
      meta: { requiresAuth: true },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginPage,
      meta: { guest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterPage,
      meta: { guest: true },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/',
    },
  ],
  scrollBehavior() {
    return { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuth()

  if (!auth.ready.value) {
    await auth.bootstrap()
  }

  if (to.meta.requiresAuth && !auth.user.value) {
    return {
      name: 'login',
      query: to.fullPath !== '/' ? { redirect: to.fullPath } : undefined,
    }
  }

  if (to.meta.guest && auth.user.value) {
    return { name: 'radio' }
  }

  return true
})

export default router
