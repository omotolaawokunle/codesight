<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <RouterLink to="/" class="text-primary-600 hover:text-primary-700 text-sm font-medium mb-4 inline-block">
      ← Back to Dashboard
    </RouterLink>
    <h1 class="text-2xl font-bold text-gray-900 mb-4">Repository Detail</h1>
    <p class="text-gray-500">Repository details — coming in MVP.</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{ id: string }>()
</script>
