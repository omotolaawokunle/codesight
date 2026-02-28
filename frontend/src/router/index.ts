import { useAuthStore } from '@/stores/auth'
import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { requiresGuest: true },
    },
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/repositories',
      name: 'repositories',
      component: () => import('@/views/RepositoryListView.vue'),
      meta: { requiresAuth: true },
    },
    {
      path: '/repositories/:id',
      name: 'repository-detail',
      component: () => import('@/views/RepositoryDetailView.vue'),
      props: true,
      meta: { requiresAuth: true },
    },
    {
      path: '/repositories/:id/chat',
      name: 'repository-chat',
      component: () => import('@/views/ChatView.vue'),
      props: true,
      meta: { requiresAuth: true },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('@/views/NotFoundView.vue'),
    },
  ],
})

router.beforeEach((to) => {
  const authStore = useAuthStore()
  const isAuthenticated = !!authStore.token

  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.requiresGuest && isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
