<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">My Repositories</h1>
        <p class="text-sm text-gray-500 mt-1">
          {{ repositories.length }} of {{ MAX_REPOS }} repositories
        </p>
      </div>
      <button
        id="add-repository-btn"
        class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2.5 rounded-xl shadow-sm transition-colors text-sm"
        @click="showForm = true"
      >
        <PlusIcon class="h-4 w-4" />
        Add Repository
      </button>
    </div>

    <!-- Loading skeleton -->
    <div v-if="store.isLoading && repositories.length === 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <div
        v-for="n in 3"
        :key="n"
        class="bg-white rounded-xl border border-gray-200 p-5 animate-pulse space-y-3"
      >
        <div class="h-4 bg-gray-200 rounded w-3/4" />
        <div class="h-3 bg-gray-100 rounded w-full" />
        <div class="h-3 bg-gray-100 rounded w-1/2" />
      </div>
    </div>

    <!-- Error state -->
    <div
      v-else-if="store.error"
      class="text-center py-16 text-red-600 bg-red-50 rounded-xl border border-red-200"
    >
      <p class="font-medium">Failed to load repositories.</p>
      <p class="text-sm mt-1 text-red-500">{{ store.error }}</p>
      <button
        class="mt-4 text-sm text-red-600 underline hover:no-underline"
        @click="load"
      >
        Retry
      </button>
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!store.isLoading && repositories.length === 0"
      class="text-center py-20"
    >
      <div class="mx-auto h-16 w-16 rounded-2xl bg-blue-50 flex items-center justify-center mb-4">
        <CodeBracketIcon class="h-8 w-8 text-blue-500" />
      </div>
      <h3 class="text-base font-semibold text-gray-900">No repositories yet</h3>
      <p class="text-sm text-gray-500 mt-1 max-w-sm mx-auto">
        Add a GitHub, GitLab, or Bitbucket repository to start asking AI questions about your code.
      </p>
      <button
        class="mt-6 inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2.5 rounded-xl text-sm transition-colors"
        @click="showForm = true"
      >
        <PlusIcon class="h-4 w-4" />
        Add your first repository
      </button>
    </div>

    <!-- Repository grid -->
    <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <RepositoryCard
        v-for="repo in repositories"
        :key="repo.id"
        :repository="repo"
        @delete="handleDelete"
        @reindex="handleReindex"
      />
    </div>

    <!-- Toast notification -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0"
      enter-to-class="translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100"
      leave-to-class="translate-y-2 opacity-0"
    >
      <div
        v-if="toast.visible"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl px-4 py-3 shadow-lg text-sm font-medium"
        :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-gray-900 text-white'"
      >
        {{ toast.message }}
      </div>
    </Transition>

    <!-- Add repository modal -->
    <RepositoryForm v-model="showForm" @created="onCreated" />
  </div>
</template>

<script setup lang="ts">
import RepositoryCard from '@/components/RepositoryCard.vue'
import RepositoryForm from '@/components/RepositoryForm.vue'
import { usePolling } from '@/composables/usePolling'
import { useRepositoryStore } from '@/stores/repository'
import type { Repository } from '@/types'
import { CodeBracketIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { computed, onMounted, ref, watch } from 'vue'

const MAX_REPOS = 10

const store      = useRepositoryStore()
const showForm   = ref(false)
const repositories = computed(() => store.repositories)

// ----- Toast ----------------------------------------------------------------

const toast = ref({ visible: false, message: '', type: 'success' as 'success' | 'error' })
let toastTimer: ReturnType<typeof setTimeout>

function showToast(message: string, type: 'success' | 'error' = 'success') {
  clearTimeout(toastTimer)
  toast.value = { visible: true, message, type }
  toastTimer = setTimeout(() => { toast.value.visible = false }, 3500)
}

// ----- Polling --------------------------------------------------------------

const { start: startPolling, stop: stopPolling } = usePolling(async () => {
  const inProgressIds = repositories.value
    .filter((r) => r.indexing_status === 'in_progress' || r.indexing_status === 'pending')
    .map((r) => r.id)

  if (inProgressIds.length === 0) {
    stopPolling()
    return
  }

  await Promise.all(inProgressIds.map((id) => store.fetchStatus(id)))
})

/** Watch the repository list and start polling whenever any repo is active. */
watch(
  repositories,
  (repos) => {
    const hasActive = repos.some(
      (r) => r.indexing_status === 'in_progress' || r.indexing_status === 'pending',
    )
    if (hasActive) startPolling()
    else stopPolling()
  },
  { deep: true },
)

// ----- Actions --------------------------------------------------------------

async function load() {
  try {
    await store.fetchRepositories()
  } catch {
    // Error already set in store
  }
}

async function handleDelete(id: number) {
  try {
    await store.deleteRepository(id)
    showToast('Repository deleted.')
  } catch {
    showToast('Failed to delete repository.', 'error')
  }
}

async function handleReindex(id: number) {
  try {
    await store.reindexRepository(id)
    showToast('Re-indexing queued.')
    startPolling()
  } catch {
    showToast('Failed to start re-indexing.', 'error')
  }
}

function onCreated(repository: Repository) {
  showToast(`"${repository.name}" added and queued for indexing.`)
  startPolling()
}

onMounted(load)
</script>
