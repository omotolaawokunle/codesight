import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('@/views/DashboardView.vue'),
    },
    {
      path: '/repositories',
      name: 'repositories',
      component: () => import('@/views/RepositoryListView.vue'),
    },
    {
      path: '/repositories/:id',
      name: 'repository-detail',
      component: () => import('@/views/RepositoryDetailView.vue'),
      props: true,
    },
    {
      path: '/repositories/:id/chat',
      name: 'repository-chat',
      component: () => import('@/views/ChatView.vue'),
      props: true,
    },
  ],
})

export default router
