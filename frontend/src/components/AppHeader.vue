<template>
  <header class="bg-white border-b border-gray-200 h-16 flex items-center px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between w-full max-w-7xl mx-auto">
      <RouterLink to="/" class="flex items-center gap-2">
        <span class="text-xl font-bold text-primary-600">Codesight</span>
      </RouterLink>

      <nav class="flex items-center gap-4">
        <RouterLink
          to="/"
          class="text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors"
          active-class="text-primary-600"
        >
          Dashboard
        </RouterLink>
      </nav>
    </div>
  </header>
</template>

<script setup lang="ts">
</script>
