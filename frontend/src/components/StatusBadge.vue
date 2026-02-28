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
  pending:     { label: 'Pending',     classes: 'bg-gray-100 text-gray-600 ring-gray-500/20',   ping: '', dot: '' },
  in_progress: { label: 'Indexing…',  classes: 'bg-blue-50 text-blue-700 ring-blue-600/20',    ping: 'bg-blue-400', dot: 'bg-blue-500' },
  completed:   { label: 'Indexed',    classes: 'bg-green-50 text-green-700 ring-green-600/20', ping: '', dot: '' },
  failed:      { label: 'Failed',     classes: 'bg-red-50 text-red-700 ring-red-600/20',       ping: '', dot: '' },
}

const label       = computed(() => config[props.status].label)
const pingColor   = computed(() => config[props.status].ping)
const dotColor    = computed(() => config[props.status].dot)
const badgeClasses = computed(() => [
  'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
  config[props.status].classes,
])
</script>
