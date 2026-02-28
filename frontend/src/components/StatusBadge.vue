<template>
  <span :class="badgeClasses">
    <span v-if="status === 'in_progress'" class="relative flex h-2 w-2 mr-1.5">
      <span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" :class="pingColor" />
      <span class="relative inline-flex rounded-full h-2 w-2" :class="dotColor" />
    </span>
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { IndexingStatus } from '@/types'

const props = defineProps<{
  status: IndexingStatus
}>()

const config: Record<IndexingStatus, { label: string; classes: string; ping: string; dot: string }> = {
  pending:     { label: 'Pending',    classes: 'bg-slate-800 text-slate-400 ring-slate-600/30',      ping: '', dot: '' },
  in_progress: { label: 'Indexing…', classes: 'bg-accent-900/40 text-accent-400 ring-accent-500/30', ping: 'bg-accent-400', dot: 'bg-accent-500' },
  completed:   { label: 'Indexed',   classes: 'bg-primary-950/60 text-primary-400 ring-primary-500/30', ping: '', dot: '' },
  failed:      { label: 'Failed',    classes: 'bg-red-950/40 text-red-400 ring-red-500/30',          ping: '', dot: '' },
}

const label       = computed(() => config[props.status].label)
const pingColor   = computed(() => config[props.status].ping)
const dotColor    = computed(() => config[props.status].dot)
const badgeClasses = computed(() => [
  'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
  config[props.status].classes,
])
</script>
