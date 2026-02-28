<template>
  <div class="bg-slate-900 rounded-xl border border-slate-800 p-5 hover:border-slate-700 transition-colors duration-200 flex flex-col gap-4">
    <!-- Header: name + status -->
    <div class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <RouterLink
          :to="{ name: 'repository-detail', params: { id: repository.id } }"
          class="text-base font-semibold text-slate-100 hover:text-primary-400 truncate block transition-colors"
        >
          {{ repository.name }}
        </RouterLink>
        <a
          :href="repository.git_url"
          target="_blank"
          rel="noopener noreferrer"
          class="text-xs font-mono text-slate-600 hover:text-slate-400 truncate block mt-0.5 transition-colors"
        >
          {{ repository.git_url }}
        </a>
      </div>
      <StatusBadge :status="repository.indexing_status" />
    </div>

    <!-- Indexing progress -->
    <IndexingProgress
      v-if="repository.indexing_status === 'in_progress'"
      :progress="progressPercentage"
      :indexed-files="repository.indexed_files"
      :total-files="repository.total_files"
    />

    <!-- Error message -->
    <p
      v-if="repository.indexing_status === 'failed'"
      class="text-xs text-red-400 bg-red-950/30 border border-red-900/50 rounded-lg px-3 py-2"
    >
      {{ repository.indexing_error ?? 'Indexing failed. Please try re-indexing.' }}
    </p>

    <!-- Stats row -->
    <div class="flex gap-4 text-xs font-mono text-slate-600">
      <span v-if="repository.total_files != null">
        <span class="font-medium text-slate-400">{{ repository.total_files }}</span> files
      </span>
      <span v-if="repository.total_chunks">
        <span class="font-medium text-slate-400">{{ repository.total_chunks }}</span> chunks
      </span>
      <span class="ml-auto">
        <span class="text-slate-600">branch: </span><span class="text-slate-400">{{ repository.branch }}</span>
      </span>
    </div>

    <!-- Action buttons -->
    <div class="flex gap-2 pt-1 border-t border-slate-800">
      <!-- Primary: Chat (only when indexed) -->
      <RouterLink
        v-if="repository.indexing_status === 'completed'"
        :to="{ name: 'repository-chat', params: { id: repository.id } }"
        class="flex-1 text-center text-xs font-bold text-slate-950 bg-primary-500 hover:bg-primary-400 py-1.5 rounded-lg transition-colors cursor-pointer"
      >
        Chat
      </RouterLink>

      <RouterLink
        :to="{ name: 'repository-detail', params: { id: repository.id } }"
        class="flex-1 text-center text-xs font-medium text-slate-400 hover:text-slate-200 py-1.5 rounded-lg hover:bg-slate-800 transition-colors cursor-pointer"
      >
        Details
      </RouterLink>

      <button
        :disabled="repository.indexing_status === 'in_progress'"
        class="flex-1 text-center text-xs font-medium text-slate-500 hover:text-slate-300 py-1.5 rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
        @click="emit('reindex', repository.id)"
      >
        Re-index
      </button>

      <button
        class="flex-1 text-center text-xs font-medium text-slate-500 hover:text-red-400 py-1.5 rounded-lg hover:bg-red-950/30 transition-colors cursor-pointer"
        @click="showConfirm = true"
      >
        Delete
      </button>
    </div>

    <ConfirmDialog
      v-model="showConfirm"
      title="Delete Repository"
      :description="`Are you sure you want to delete &quot;${repository.name}&quot;? This cannot be undone.`"
      confirm-label="Delete"
      variant="danger"
      @confirm="emit('delete', repository.id)"
    />
  </div>
</template>

<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import IndexingProgress from '@/components/IndexingProgress.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import type { Repository } from '@/types'
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps<{
  repository: Repository
}>()

const emit = defineEmits<{
  (e: 'delete', id: number): void
  (e: 'reindex', id: number): void
}>()

const showConfirm = ref(false)

const progressPercentage = computed(() => {
  if (props.repository.indexing_status === 'completed') return 100
  if (props.repository.indexed_files && props.repository.total_files) {
    return Math.round((props.repository.indexed_files / props.repository.total_files) * 100)
  }
  return 0
})
</script>
