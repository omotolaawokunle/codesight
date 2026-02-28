<template>
  <div class="space-y-1.5">
    <div class="flex justify-between text-xs text-slate-500">
      <span>Indexing files…</span>
      <span class="font-mono">{{ indexedFiles ?? 0 }} / {{ totalFiles ?? '?' }} files</span>
    </div>
    <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
      <div
        class="h-1.5 rounded-full transition-all duration-500"
        :class="progress > 0 ? 'bg-primary-500' : 'bg-primary-600 animate-pulse'"
        :style="{ width: `${Math.max(progress, 4)}%` }"
      />
    </div>
    <p v-if="progress > 0" class="text-xs text-right font-mono text-slate-500">{{ progress }}%</p>
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
