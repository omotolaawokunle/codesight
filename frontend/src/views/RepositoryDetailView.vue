<template>
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Back link -->
    <RouterLink to="/" class="inline-flex items-center gap-1 text-sm text-blue-600 hover:text-blue-700 mb-6">
      <ChevronLeftIcon class="h-4 w-4" />
      Back to Dashboard
    </RouterLink>

    <!-- Loading skeleton -->
    <div v-if="isLoading" class="space-y-4 animate-pulse">
      <div class="h-8 bg-gray-200 rounded w-1/3" />
      <div class="h-4 bg-gray-100 rounded w-1/2" />
      <div class="h-32 bg-gray-100 rounded-xl mt-6" />
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="text-center py-16 text-red-600">
      <p>Failed to load repository.</p>
      <RouterLink to="/" class="mt-3 inline-block text-sm text-blue-600 underline">
        Return to Dashboard
      </RouterLink>
    </div>

    <template v-else-if="repository">
      <!-- Header -->
      <div class="flex flex-wrap items-start justify-between gap-4 mb-6">
        <div>
          <div class="flex items-center gap-3">
            <h1 class="text-2xl font-bold text-gray-900">{{ repository.name }}</h1>
            <StatusBadge :status="repository.indexing_status" />
          </div>
          <a
            :href="repository.git_url"
            target="_blank"
            rel="noopener noreferrer"
            class="text-sm text-gray-400 hover:text-blue-500 font-mono mt-1 block"
          >
            {{ repository.git_url }}
          </a>
        </div>

        <div class="flex gap-2">
          <button
            :disabled="repository.indexing_status === 'in_progress'"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 border border-gray-300 px-3 py-2 rounded-lg hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
            @click="handleReindex"
          >
            <ArrowPathIcon class="h-4 w-4" />
            Re-index
          </button>
          <RouterLink
            :to="{ name: 'chat', query: { repo: repository.id } }"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded-lg transition-colors"
          >
            <ChatBubbleLeftEllipsisIcon class="h-4 w-4" />
            Ask AI
          </RouterLink>
        </div>
      </div>

      <!-- Indexing progress bar -->
      <div v-if="repository.indexing_status === 'in_progress'" class="mb-6">
        <IndexingProgress
          :progress="progressPercentage"
          :indexed-files="repository.indexed_files"
          :total-files="repository.total_files"
        />
      </div>

      <!-- Error message -->
      <div
        v-if="repository.indexing_status === 'failed'"
        class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700"
      >
        <p class="font-medium">Indexing failed</p>
        <p class="mt-1">{{ repository.indexing_error ?? 'An unknown error occurred.' }}</p>
        <button class="mt-2 text-red-600 underline hover:no-underline text-xs" @click="handleReindex">
          Try again
        </button>
      </div>

      <!-- Stats grid -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-xs text-gray-500 mb-1">Total Files</p>
          <p class="text-2xl font-bold text-gray-900">{{ repository.total_files ?? '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-xs text-gray-500 mb-1">Code Chunks</p>
          <p class="text-2xl font-bold text-gray-900">{{ repository.total_chunks ?? '—' }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-xs text-gray-500 mb-1">Branch</p>
          <p class="text-base font-semibold text-gray-900 font-mono truncate">{{ repository.branch }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
          <p class="text-xs text-gray-500 mb-1">Last Commit</p>
          <p class="text-xs font-semibold text-gray-900 font-mono truncate">
            {{ repository.last_indexed_commit?.slice(0, 8) ?? '—' }}
          </p>
        </div>
      </div>

      <!-- File tree placeholder -->
      <div class="bg-white rounded-xl border border-gray-200 p-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">File Tree</h2>
        <div class="flex items-center justify-center h-32 text-sm text-gray-400">
          <span>File tree will be available after AST parsing (Batch 3).</span>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import IndexingProgress from '@/components/IndexingProgress.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { usePolling } from '@/composables/usePolling'
import { useRepositoryStore } from '@/stores/repository'
import {
    ArrowPathIcon,
    ChatBubbleLeftEllipsisIcon,
    ChevronLeftIcon,
} from '@heroicons/vue/24/outline'
import { computed, onMounted, watch } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps<{ id: string }>()

const store      = useRepositoryStore()
const repoId     = computed(() => Number(props.id))
const repository = computed(() => store.currentRepository)
const isLoading  = computed(() => store.isLoading)

const progressPercentage = computed(() => {
  if (!repository.value) return 0
  if (repository.value.indexing_status === 'completed') return 100
  if (repository.value.indexed_files && repository.value.total_files) {
    return Math.round((repository.value.indexed_files / repository.value.total_files) * 100)
  }
  return 0
})

// Poll while indexing is active
const { start: startPolling, stop: stopPolling } = usePolling(
  () => store.fetchStatus(repoId.value),
)

watch(
  () => repository.value?.indexing_status,
  (status) => {
    if (status === 'in_progress' || status === 'pending') startPolling()
    else stopPolling()
  },
)

async function handleReindex() {
  await store.reindexRepository(repoId.value)
  startPolling()
}

onMounted(async () => {
  await store.fetchRepository(repoId.value)
  if (
    repository.value?.indexing_status === 'in_progress' ||
    repository.value?.indexing_status === 'pending'
  ) {
    startPolling()
  }
})
</script>
