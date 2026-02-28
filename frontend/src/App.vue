<template>
  <div class="min-h-screen bg-slate-950 flex flex-col">
    <AppHeader v-if="showHeader" />
    <main class="flex-1">
      <RouterView />
    </main>
    <ToastNotification />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import AppHeader from '@/components/AppHeader.vue'
import ToastNotification from '@/components/ToastNotification.vue'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const route = useRoute()

const showHeader = computed(() => !['login', 'register'].includes(route.name as string))

onMounted(() => {
  authStore.initializeAuth()
})
</script>
