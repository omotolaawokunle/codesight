<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Hero greeting -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-mono font-bold text-slate-100">
          Welcome back<span v-if="userName">, {{ firstName }}</span>
        </h1>
        <p class="text-sm text-slate-500 mt-1">Your AI-powered codebase assistant</p>
      </div>
      <button
        class="inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-400 text-slate-950 font-bold px-4 py-2.5 rounded-xl shadow-sm transition-colors text-sm cursor-pointer"
        @click="showForm = true"
      >
        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
          <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
        </svg>
        Add Repository
      </button>
    </div>

    <!-- Stats bar -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div
        v-for="stat in stats"
        :key="stat.label"
        class="bg-slate-900 rounded-xl border border-slate-800 px-5 py-4 flex items-center gap-4"
      >
        <div
          class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
          :class="stat.iconBg"
        >
          <component :is="stat.icon" class="w-5 h-5" :class="stat.iconColor" />
        </div>
        <div>
          <p class="text-2xl font-mono font-bold text-slate-100 leading-tight">{{ stat.value }}</p>
          <p class="text-xs text-slate-500 mt-0.5">{{ stat.label }}</p>
        </div>
      </div>
    </div>

    <!-- Recent repositories -->
    <section>
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-slate-200">Your Repositories</h2>
        <span class="text-xs font-mono text-slate-600">{{ repositories.length }} / {{ MAX_REPOS }}</span>
      </div>

      <!-- Loading skeletons -->
      <div v-if="store.isLoading && repositories.length === 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="n in 3"
          :key="n"
          class="bg-slate-900 rounded-xl border border-slate-800 p-5 animate-pulse space-y-3"
        >
          <div class="h-4 bg-slate-800 rounded w-3/4" />
          <div class="h-3 bg-slate-800/60 rounded w-full" />
          <div class="h-3 bg-slate-800/60 rounded w-1/2" />
        </div>
      </div>

      <!-- Error -->
      <div
        v-else-if="store.error"
        class="text-center py-10 bg-red-950/30 border border-red-800/50 rounded-xl text-red-400"
      >
        <p class="font-medium text-sm">Failed to load repositories.</p>
        <button class="mt-2 text-xs text-red-500 underline cursor-pointer" @click="load">Retry</button>
      </div>

      <!-- Empty state -->
      <div v-else-if="!store.isLoading && repositories.length === 0" class="py-20 text-center">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mb-5">
          <CodeBracketIcon class="h-8 w-8 text-primary-500" />
        </div>
        <h3 class="text-sm font-mono font-semibold text-slate-200">No repositories yet</h3>
        <p class="text-sm text-slate-500 mt-2 max-w-xs mx-auto leading-relaxed">
          Add a GitHub, GitLab, or Bitbucket repository to start chatting with your codebase.
        </p>
        <div class="mt-4 font-mono text-xs text-slate-600 flex items-center justify-center gap-1">
          <span>$</span>
          <span class="text-primary-500">codesight</span>
          <span>add-repo</span>
          <span class="animate-blink">_</span>
        </div>
        <button
          class="mt-6 inline-flex items-center gap-2 bg-primary-500 hover:bg-primary-400 text-slate-950 font-bold px-4 py-2.5 rounded-xl text-sm transition-colors cursor-pointer"
          @click="showForm = true"
        >
          <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" />
          </svg>
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
    </section>

    <!-- Indexing in progress tip -->
    <section v-if="repositories.length > 0 && !hasIndexedRepo">
      <div class="rounded-2xl bg-primary-950/30 border border-primary-900/50 p-5">
        <div class="flex items-start gap-4">
          <div class="w-9 h-9 rounded-xl bg-primary-900/50 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-4 h-4 text-primary-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10" /><line x1="12" y1="8" x2="12" y2="12" /><line x1="12" y1="16" x2="12.01" y2="16" />
            </svg>
          </div>
          <div>
            <h3 class="text-sm font-semibold text-slate-200 mb-1">Indexing in progress</h3>
            <p class="text-sm text-slate-500 leading-relaxed">
              Your repository is being parsed and indexed. Once complete, you'll be able to ask natural-language questions, debug errors, and explore the architecture.
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Toast -->
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
        class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl px-4 py-3 shadow-xl text-sm font-medium"
        :class="toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-slate-800 border border-slate-700 text-slate-100'"
      >
        {{ toast.message }}
      </div>
    </Transition>

    <RepositoryForm v-model="showForm" @created="onCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRepositoryStore } from '@/stores/repository'
import { useAuthStore } from '@/stores/auth'
import type { Repository } from '@/types'
import RepositoryCard from '@/components/RepositoryCard.vue'
import RepositoryForm from '@/components/RepositoryForm.vue'
import { usePolling } from '@/composables/usePolling'
import {
  CodeBracketIcon,
  CircleStackIcon,
  CubeTransparentIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

const MAX_REPOS = 10

const store = useRepositoryStore()
const authStore = useAuthStore()
const showForm = ref(false)
const repositories = computed(() => store.repositories)

const userName = computed(() => authStore.user?.name ?? '')
const firstName = computed(() => userName.value.split(' ')[0])

const hasIndexedRepo = computed(() =>
  repositories.value.some((r) => r.indexing_status === 'completed'),
)

const stats = computed(() => [
  {
    label: 'Repositories',
    value: repositories.value.length,
    icon: CodeBracketIcon,
    iconBg: 'bg-primary-950/60',
    iconColor: 'text-primary-400',
  },
  {
    label: 'Indexed',
    value: repositories.value.filter((r) => r.indexing_status === 'completed').length,
    icon: CheckCircleIcon,
    iconBg: 'bg-green-950/60',
    iconColor: 'text-green-400',
  },
  {
    label: 'Total Files',
    value: repositories.value.reduce((s, r) => s + (r.total_files ?? 0), 0),
    icon: CircleStackIcon,
    iconBg: 'bg-amber-950/60',
    iconColor: 'text-amber-400',
  },
  {
    label: 'Code Chunks',
    value: repositories.value.reduce((s, r) => s + (r.total_chunks ?? 0), 0),
    icon: CubeTransparentIcon,
    iconBg: 'bg-violet-950/60',
    iconColor: 'text-violet-400',
  },
])

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
  const activeIds = repositories.value
    .filter((r) => r.indexing_status === 'in_progress' || r.indexing_status === 'pending')
    .map((r) => r.id)
  if (activeIds.length === 0) { stopPolling(); return }
  await Promise.all(activeIds.map((id) => store.fetchStatus(id)))
})

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
  } catch { /* Error already in store */ }
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
