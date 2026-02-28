<template>
  <div class="min-h-screen bg-slate-950 flex items-center justify-center px-4">
    <div class="text-center space-y-6 max-w-md">
      <div class="mx-auto w-20 h-20 rounded-2xl bg-red-950/40 border border-red-800/50 flex items-center justify-center">
        <ExclamationTriangleIcon class="w-10 h-10 text-red-500" />
      </div>
      <div>
        <h1 class="text-xl font-mono font-bold text-slate-100">Something went wrong</h1>
        <p class="text-sm text-slate-500 mt-2 leading-relaxed">
          {{ message }}
        </p>
      </div>
      <div class="flex flex-col sm:flex-row gap-3 justify-center">
        <RouterLink
          to="/"
          class="inline-flex items-center justify-center gap-2 bg-primary-500 hover:bg-primary-400 text-slate-950 font-bold px-4 py-2.5 rounded-xl text-sm transition-colors"
        >
          <HomeIcon class="w-4 h-4" />
          Go to Dashboard
        </RouterLink>
        <button
          class="inline-flex items-center justify-center gap-2 bg-slate-800 hover:bg-slate-700 text-slate-200 font-medium px-4 py-2.5 rounded-xl text-sm transition-colors"
          @click="reload"
        >
          <ArrowPathIcon class="w-4 h-4" />
          Try again
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ExclamationTriangleIcon, HomeIcon, ArrowPathIcon } from '@heroicons/vue/24/outline'

const props = withDefaults(defineProps<{ message?: string }>(), {
  message: 'An unexpected error occurred. Please try again or return to the dashboard.',
})

function reload() {
  window.location.reload()
}
</script>
