<template>
  <div class="min-h-screen bg-slate-950">
    <!-- Top nav bar -->
    <div class="border-b border-slate-800/80 bg-slate-950/95 backdrop-blur-sm sticky top-0 z-20">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-11 flex items-center gap-3">
        <RouterLink
          to="/"
          class="flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-300 transition-colors cursor-pointer group"
        >
          <ChevronLeftIcon class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-0.5" />
          Dashboard
        </RouterLink>
        <span class="text-slate-700">/</span>
        <span class="text-xs text-slate-400 font-mono truncate">{{ repository?.name ?? '…' }}</span>

        <div class="ml-auto flex items-center gap-2">
          <StatusBadge v-if="repository" :status="repository.indexing_status" />
        </div>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isLoading" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6 animate-pulse">
      <div class="h-9 bg-slate-800 rounded-lg w-64" />
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div v-for="n in 4" :key="n" class="h-24 bg-slate-900 border border-slate-800 rounded-xl" />
      </div>
      <div class="h-96 bg-slate-900 border border-slate-800 rounded-xl" />
    </div>

    <!-- Error -->
    <div v-else-if="store.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center">
      <div class="w-14 h-14 rounded-2xl bg-red-950/40 border border-red-900/50 flex items-center justify-center mx-auto mb-4">
        <ExclamationCircleIcon class="w-7 h-7 text-red-400" />
      </div>
      <p class="text-slate-400 text-sm">Failed to load repository.</p>
      <RouterLink to="/" class="mt-3 inline-block text-sm text-primary-400 hover:text-primary-300 transition-colors">
        Return to Dashboard
      </RouterLink>
    </div>

    <template v-else-if="repository">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

        <!-- ── Hero header ── -->
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
              <div class="w-10 h-10 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center shrink-0">
                <CodeBracketIcon class="w-5 h-5 text-primary-400" />
              </div>
              <div class="min-w-0">
                <h1 class="text-xl font-mono font-bold text-slate-100 truncate">{{ repository.name }}</h1>
                <a
                  :href="repository.git_url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="inline-flex items-center gap-1 text-xs font-mono text-slate-600 hover:text-slate-400 transition-colors mt-0.5"
                >
                  <svg class="w-3 h-3 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0 0 24 12c0-6.63-5.37-12-12-12z"/>
                  </svg>
                  {{ shortUrl(repository.git_url) }}
                  <ArrowTopRightOnSquareIcon class="w-3 h-3 opacity-50" />
                </a>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <button
              :disabled="repository.indexing_status === 'in_progress' || repository.indexing_status === 'pending'"
              class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 border border-slate-700 px-3 py-2 rounded-lg hover:bg-slate-800 hover:text-slate-200 disabled:opacity-40 disabled:cursor-not-allowed transition-colors cursor-pointer"
              @click="handleReindex"
            >
              <ArrowPathIcon class="h-3.5 w-3.5" :class="isReindexing ? 'animate-spin' : ''" />
              Re-index
            </button>

            <RouterLink
              v-if="repository.indexing_status === 'completed'"
              :to="{ name: 'repository-chat', params: { id: repository.id } }"
              class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-950 bg-primary-500 hover:bg-primary-400 px-3 py-2 rounded-lg transition-colors cursor-pointer"
            >
              <SparklesIcon class="h-3.5 w-3.5" />
              Chat with AI
            </RouterLink>
          </div>
        </div>

        <!-- Indexing progress bar -->
        <div v-if="repository.indexing_status === 'in_progress' || repository.indexing_status === 'pending'" class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent-400 opacity-75" />
                <span class="relative inline-flex rounded-full h-2 w-2 bg-accent-500" />
              </span>
              <span class="text-xs font-medium text-slate-300">Indexing in progress…</span>
            </div>
            <span class="text-xs font-mono text-slate-500">{{ progressPercentage }}%</span>
          </div>
          <IndexingProgress
            :progress="progressPercentage"
            :indexed-files="repository.indexed_files"
            :total-files="repository.total_files"
          />
        </div>

        <!-- Indexing failed -->
        <div
          v-if="repository.indexing_status === 'failed'"
          class="bg-red-950/20 border border-red-900/40 rounded-xl p-4"
        >
          <div class="flex items-start gap-3">
            <ExclamationCircleIcon class="w-4 h-4 text-red-400 shrink-0 mt-0.5" />
            <div>
              <p class="text-sm font-medium text-red-300">Indexing failed</p>
              <p class="text-xs text-red-500 mt-0.5 font-mono">{{ repository.indexing_error ?? 'An unknown error occurred.' }}</p>
              <button class="mt-2 text-xs text-red-400 hover:text-red-300 underline decoration-red-800 cursor-pointer transition-colors" @click="handleReindex">
                Try again
              </button>
            </div>
          </div>
        </div>

        <!-- ── Bento stats grid ── -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <div
            v-for="stat in stats"
            :key="stat.label"
            class="bg-slate-900 border border-slate-800 rounded-xl p-4 group hover:border-slate-700 transition-colors"
          >
            <div class="flex items-center justify-between mb-2">
              <span class="text-xs text-slate-600 uppercase tracking-wider font-medium">{{ stat.label }}</span>
              <component :is="stat.icon" class="w-3.5 h-3.5 text-slate-700 group-hover:text-slate-600 transition-colors" />
            </div>
            <p class="text-2xl font-mono font-bold" :class="stat.color">{{ stat.value }}</p>
            <p v-if="stat.sub" class="text-xs font-mono text-slate-600 mt-1">{{ stat.sub }}</p>
          </div>
        </div>

        <!-- ── Main content: File tree + Details panel ── -->
        <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] xl:grid-cols-[320px_1fr] gap-4">

          <!-- File tree panel -->
          <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden flex flex-col min-h-64 lg:max-h-[calc(100vh-320px)]">
            <!-- Panel header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800 shrink-0">
              <div class="flex items-center gap-2">
                <FolderOpenIcon class="w-3.5 h-3.5 text-slate-500" />
                <span class="text-xs font-semibold text-slate-400">Explorer</span>
              </div>
              <div class="flex items-center gap-1.5">
                <span v-if="filePaths.length" class="text-[10px] font-mono text-slate-700">{{ filePaths.length }} files</span>
                <button
                  v-if="filePaths.length"
                  class="text-[10px] text-slate-600 hover:text-slate-400 transition-colors cursor-pointer px-1.5 py-0.5 rounded hover:bg-slate-800"
                  @click="collapseAll"
                >
                  collapse
                </button>
              </div>
            </div>

            <!-- Tree content -->
            <div class="flex-1 overflow-y-auto py-1.5">
              <!-- Loading state -->
              <div v-if="filesLoading" class="px-4 py-6 space-y-2 animate-pulse">
                <div v-for="n in 8" :key="n" class="h-4 bg-slate-800 rounded" :style="{ width: `${40 + Math.random() * 50}%`, marginLeft: `${(n % 3) * 12}px` }" />
              </div>

              <!-- Not indexed yet -->
              <div v-else-if="repository.indexing_status !== 'completed'" class="flex flex-col items-center justify-center py-10 px-4 text-center">
                <div class="w-10 h-10 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center mb-3">
                  <FolderIcon class="w-5 h-5 text-slate-600" />
                </div>
                <p class="text-xs text-slate-600 font-mono leading-relaxed">
                  {{ repository.indexing_status === 'in_progress' || repository.indexing_status === 'pending'
                    ? '// indexing…'
                    : '// not indexed' }}
                </p>
              </div>

              <!-- Empty after indexing -->
              <div v-else-if="filePaths.length === 0" class="flex flex-col items-center justify-center py-10 px-4 text-center">
                <p class="text-xs text-slate-600 font-mono">// no files found</p>
              </div>

              <!-- Actual tree -->
              <FileTree
                v-else
                :key="treeKey"
                :file-paths="filePaths"
                :selected-path="selectedFile"
                aria-label="Repository file tree"
                @select-file="selectedFile = $event"
              />
            </div>
          </div>

          <!-- Details panel -->
          <div class="space-y-4">
            <!-- Quick info card -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
              <div class="px-4 py-3 border-b border-slate-800 flex items-center gap-2">
                <InformationCircleIcon class="w-3.5 h-3.5 text-slate-500" />
                <span class="text-xs font-semibold text-slate-400">Repository Info</span>
              </div>

              <dl class="divide-y divide-slate-800/60">
                <div v-for="detail in repoDetails" :key="detail.label" class="flex items-center justify-between px-4 py-3 group">
                  <dt class="text-xs text-slate-600">{{ detail.label }}</dt>
                  <dd class="text-xs font-mono text-slate-300 flex items-center gap-1.5 max-w-[60%] truncate">
                    <component :is="detail.icon" v-if="detail.icon" class="w-3 h-3 text-slate-600 shrink-0" />
                    <span class="truncate" :class="detail.class ?? ''">{{ detail.value }}</span>
                  </dd>
                </div>
              </dl>
            </div>

            <!-- Language breakdown (from file extensions) -->
            <div v-if="languageStats.length" class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
              <div class="px-4 py-3 border-b border-slate-800 flex items-center gap-2">
                <CodeBracketIcon class="w-3.5 h-3.5 text-slate-500" />
                <span class="text-xs font-semibold text-slate-400">Languages</span>
              </div>

              <!-- Bar chart -->
              <div class="px-4 py-4 space-y-3">
                <div
                  v-for="lang in languageStats"
                  :key="lang.ext"
                  class="space-y-1.5"
                >
                  <div class="flex items-center justify-between text-xs">
                    <span class="font-mono text-slate-400">{{ lang.ext }}</span>
                    <span class="text-slate-600">{{ lang.count }} files · {{ lang.pct }}%</span>
                  </div>
                  <div class="w-full bg-slate-800 rounded-full h-1 overflow-hidden">
                    <div
                      class="h-1 rounded-full transition-all duration-700"
                      :class="lang.color"
                      :style="{ width: `${lang.pct}%` }"
                    />
                  </div>
                </div>
              </div>
            </div>

            <!-- Selected file info -->
            <div v-if="selectedFile" class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
              <div class="flex items-center justify-between px-4 py-3 border-b border-slate-800">
                <div class="flex items-center gap-2 min-w-0">
                  <DocumentIcon class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                  <span class="text-xs font-mono text-slate-300 truncate">{{ selectedFile }}</span>
                </div>
                <button
                  class="shrink-0 text-slate-600 hover:text-slate-400 transition-colors cursor-pointer ml-2"
                  @click="selectedFile = ''"
                >
                  <XMarkIcon class="w-3.5 h-3.5" />
                </button>
              </div>
              <div class="px-4 py-3 flex items-center justify-between">
                <span class="text-xs font-mono text-slate-600">.{{ selectedFile.split('.').pop() }}</span>
                <RouterLink
                  :to="{ name: 'repository-chat', params: { id: repository.id }, query: { file: selectedFile } }"
                  class="text-xs text-primary-400 hover:text-primary-300 transition-colors flex items-center gap-1 cursor-pointer"
                >
                  <SparklesIcon class="w-3 h-3" />
                  Ask about this file
                </RouterLink>
              </div>
            </div>

            <!-- Quick actions -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <RouterLink
                :to="{ name: 'repository-chat', params: { id: repository.id } }"
                class="group flex items-center gap-3 bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-xl p-4 transition-colors cursor-pointer"
                :class="repository.indexing_status !== 'completed' ? 'opacity-40 pointer-events-none' : ''"
              >
                <div class="w-9 h-9 rounded-lg bg-primary-950/50 border border-primary-900/50 flex items-center justify-center shrink-0 group-hover:bg-primary-950 transition-colors">
                  <SparklesIcon class="w-4 h-4 text-primary-400" />
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-slate-200">Chat with AI</p>
                  <p class="text-xs text-slate-600 mt-0.5">Ask questions about this codebase</p>
                </div>
                <ChevronRightIcon class="w-4 h-4 text-slate-700 group-hover:text-slate-500 transition-colors ml-auto shrink-0" />
              </RouterLink>

              <button
                :disabled="repository.indexing_status === 'in_progress' || repository.indexing_status === 'pending'"
                class="group flex items-center gap-3 bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-xl p-4 transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed text-left"
                @click="handleReindex"
              >
                <div class="w-9 h-9 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center shrink-0 group-hover:bg-slate-700 transition-colors">
                  <ArrowPathIcon class="w-4 h-4 text-slate-400" :class="isReindexing ? 'animate-spin' : ''" />
                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-slate-200">Re-index</p>
                  <p class="text-xs text-slate-600 mt-0.5">Sync with latest commits</p>
                </div>
                <ChevronRightIcon class="w-4 h-4 text-slate-700 group-hover:text-slate-500 transition-colors ml-auto shrink-0" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import FileTree from '@/components/FileTree.vue'
import IndexingProgress from '@/components/IndexingProgress.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { usePolling } from '@/composables/usePolling'
import api from '@/services/api'
import { useRepositoryStore } from '@/stores/repository'
import {
  ArrowPathIcon,
  ArrowTopRightOnSquareIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  CodeBracketIcon,
  DocumentIcon,
  ExclamationCircleIcon,
  FolderIcon,
  FolderOpenIcon,
  InformationCircleIcon,
  SparklesIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps<{ id: string }>()

const store      = useRepositoryStore()
const repoId     = computed(() => Number(props.id))
const repository = computed(() => store.currentRepository)
const isLoading  = computed(() => store.isLoading)

const filePaths   = ref<string[]>([])
const filesLoading = ref(false)
const selectedFile = ref('')
const isReindexing = ref(false)
const treeKey = ref(0)

// ── Progress ──────────────────────────────────────────────────────────────────

const progressPercentage = computed(() => {
  if (!repository.value) return 0
  if (repository.value.indexing_status === 'completed') return 100
  if (repository.value.indexed_files && repository.value.total_files) {
    return Math.round((repository.value.indexed_files / repository.value.total_files) * 100)
  }
  return 0
})

// ── Stats bento ───────────────────────────────────────────────────────────────

const stats = computed(() => {
  const r = repository.value
  if (!r) return []
  return [
    {
      label: 'Files',
      value: r.total_files?.toLocaleString() ?? '—',
      sub: r.indexed_files != null ? `${r.indexed_files} indexed` : undefined,
      icon: DocumentIcon,
      color: 'text-slate-100',
    },
    {
      label: 'Chunks',
      value: r.total_chunks?.toLocaleString() ?? '—',
      sub: 'code segments',
      icon: CodeBracketIcon,
      color: 'text-primary-400',
    },
    {
      label: 'Branch',
      value: r.branch,
      sub: undefined,
      icon: null,
      color: 'text-slate-100 text-base',
    },
    {
      label: 'Commit',
      value: r.last_indexed_commit?.slice(0, 8) ?? '—',
      sub: 'last indexed',
      icon: null,
      color: 'text-slate-100 text-base',
    },
  ]
})

// ── Repository detail rows ────────────────────────────────────────────────────

const repoDetails = computed(() => {
  const r = repository.value
  if (!r) return []

  const rows = [
    { label: 'Status', value: r.indexing_status.replace('_', ' '), icon: null, class: statusClass(r.indexing_status) },
    { label: 'Branch', value: r.branch, icon: null, class: '' },
    { label: 'Git URL', value: shortUrl(r.git_url), icon: ArrowTopRightOnSquareIcon, class: '' },
    { label: 'Added', value: new Date(r.created_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }), icon: null, class: '' },
    { label: 'Last indexed', value: r.indexing_status === 'completed' && r.updated_at
        ? new Date(r.updated_at).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        : '—', icon: null, class: '' },
  ]
  return rows
})

function statusClass(status: string) {
  return {
    completed: 'text-primary-400',
    in_progress: 'text-accent-400',
    pending: 'text-slate-400',
    failed: 'text-red-400',
  }[status] ?? 'text-slate-400'
}

// ── Language breakdown ────────────────────────────────────────────────────────

const LANG_COLORS: Record<string, string> = {
  ts: 'bg-accent-500', tsx: 'bg-accent-400', js: 'bg-yellow-400', jsx: 'bg-yellow-500',
  vue: 'bg-green-500', py: 'bg-blue-400', php: 'bg-violet-500', java: 'bg-orange-500',
  rb: 'bg-red-500', go: 'bg-cyan-400', rs: 'bg-orange-400', css: 'bg-pink-400',
  html: 'bg-red-400', md: 'bg-slate-500', json: 'bg-yellow-600', sh: 'bg-green-600',
}

const languageStats = computed(() => {
  if (!filePaths.value.length) return []

  const counts: Record<string, number> = {}
  for (const p of filePaths.value) {
    const ext = p.split('.').pop()?.toLowerCase() ?? 'other'
    counts[ext] = (counts[ext] ?? 0) + 1
  }

  const total = filePaths.value.length
  return Object.entries(counts)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 6)
    .map(([ext, count]) => ({
      ext,
      count,
      pct: Math.round((count / total) * 100),
      color: LANG_COLORS[ext] ?? 'bg-slate-600',
    }))
})

// ── Helpers ───────────────────────────────────────────────────────────────────

function shortUrl(url: string) {
  return url.replace(/^https?:\/\//, '').replace(/\.git$/, '')
}

function collapseAll() {
  treeKey.value++
}

// ── Data loading ──────────────────────────────────────────────────────────────

async function loadFiles() {
  if (repository.value?.indexing_status !== 'completed') return
  filesLoading.value = true
  try {
    const { data } = await api.get<{ file_paths: string[] }>(`/repositories/${repoId.value}/files`)
    filePaths.value = data.file_paths
  } catch {
    // Non-critical — tree stays empty
  } finally {
    filesLoading.value = false
  }
}

async function handleReindex() {
  isReindexing.value = true
  try {
    await store.reindexRepository(repoId.value)
    filePaths.value = []
    selectedFile.value = ''
    startPolling()
  } finally {
    isReindexing.value = false
  }
}

// ── Polling ───────────────────────────────────────────────────────────────────

const { start: startPolling, stop: stopPolling } = usePolling(
  () => store.fetchStatus(repoId.value),
)

watch(
  () => repository.value?.indexing_status,
  async (status, prev) => {
    if (status === 'in_progress' || status === 'pending') {
      startPolling()
    } else {
      stopPolling()
      // Load file tree as soon as indexing completes
      if (status === 'completed' && prev !== 'completed') {
        await loadFiles()
      }
    }
  },
)

onMounted(async () => {
  await store.fetchRepository(repoId.value)
  if (
    repository.value?.indexing_status === 'in_progress' ||
    repository.value?.indexing_status === 'pending'
  ) {
    startPolling()
  } else {
    await loadFiles()
  }
})
</script>
