<template>
  <div class="space-y-1.5">
    <div class="flex justify-between text-xs text-gray-500">
      <span>Indexing files…</span>
      <span>{{ indexedFiles ?? 0 }} / {{ totalFiles ?? '?' }} files</span>
    </div>
    <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
      <div
        class="h-1.5 rounded-full transition-all duration-500"
        :class="progress > 0 ? 'bg-blue-500' : 'bg-blue-400 animate-pulse'"
        :style="{ width: `${Math.max(progress, 4)}%` }"
      />
    </div>
    <p v-if="progress > 0" class="text-xs text-right text-gray-400">{{ progress }}%</p>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  /** 0–100 completion percentage */
  progress: number
  indexedFiles?: number | null
  totalFiles?: number | null
}>()
</script>
