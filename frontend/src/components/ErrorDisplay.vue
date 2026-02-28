<template>
  <div class="rounded-xl border border-amber-500/30 bg-amber-950/20 overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between px-4 py-3 border-b border-amber-500/20 bg-amber-950/30">
      <div class="flex items-center gap-2">
        <ExclamationTriangleIcon class="w-4 h-4 text-amber-400" />
        <span class="text-sm font-medium text-amber-300">Error Analysis</span>
      </div>
      <button
        class="text-gray-500 hover:text-gray-300 transition-colors cursor-pointer"
        title="Close error panel"
        @click="$emit('close')"
      >
        <XMarkIcon class="w-4 h-4" />
      </button>
    </div>

    <!-- Textarea -->
    <div class="p-4 space-y-3">
      <p class="text-xs text-gray-500">
        Paste your error log or stack trace below. Codesight will analyse it against your codebase.
      </p>

      <textarea
        v-model="errorLog"
        rows="6"
        placeholder="Traceback (most recent call last):&#10;  File &quot;app.py&quot;, line 42, in main&#10;    result = compute()&#10;TypeError: unsupported operand type..."
        class="w-full resize-y bg-gray-900 border border-gray-700 rounded-lg px-3 py-2.5 text-xs font-mono text-gray-300 placeholder-gray-600 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500/50 transition-colors leading-relaxed"
        :class="{ 'border-red-500/50': hasError }"
        aria-label="Error log input"
      />

      <div v-if="hasError" class="text-xs text-red-400 flex items-center gap-1">
        <ExclamationCircleIcon class="w-3.5 h-3.5" />
        {{ errorMessage }}
      </div>

      <!-- Detected format -->
      <div v-if="detectedFormat" class="flex items-center gap-1.5 text-xs text-gray-500">
        <CheckCircleIcon class="w-3.5 h-3.5 text-green-500" />
        Detected: <span class="text-green-400">{{ detectedFormat }}</span> stack trace
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-2 pt-1">
        <button
          :disabled="!errorLog.trim() || isLoading"
          class="flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-lg transition-colors duration-150 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
          :class="isLoading
            ? 'bg-amber-700 text-amber-200'
            : 'bg-amber-500 hover:bg-amber-400 text-gray-900'"
          @click="analyseError"
        >
          <ArrowPathIcon v-if="isLoading" class="w-4 h-4 animate-spin" />
          <BugAntIcon v-else class="w-4 h-4" />
          {{ isLoading ? 'Analysing…' : 'Analyse Error' }}
        </button>

        <button
          class="text-sm text-gray-500 hover:text-gray-300 transition-colors cursor-pointer px-2 py-2"
          @click="clearLog"
        >
          Clear
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import api from '@/services/api'
import {
  ExclamationTriangleIcon,
  ExclamationCircleIcon,
  XMarkIcon,
  ArrowPathIcon,
  BugAntIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'

interface Props {
  repositoryId: number
  conversationId?: number
}

const props = defineProps<Props>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'analysis-complete', response: { message: string; sources: unknown[] }): void
}>()

const errorLog = ref('')
const isLoading = ref(false)
const hasError = ref(false)
const errorMessage = ref('')

const PYTHON_RE = /File ".+", line \d+/
const JS_RE = /at .+ \(.+:\d+:\d+\)/
const JAVA_RE = /at [\w.]+\([\w.]+:\d+\)/

const detectedFormat = computed(() => {
  if (!errorLog.value.trim()) return null
  if (PYTHON_RE.test(errorLog.value)) return 'Python'
  if (JS_RE.test(errorLog.value)) return 'JavaScript'
  if (JAVA_RE.test(errorLog.value)) return 'Java'
  return null
})

async function analyseError() {
  if (!errorLog.value.trim()) return

  hasError.value = false
  errorMessage.value = ''
  isLoading.value = true

  try {
    const { data } = await api.post('/chat/analyze-error', {
      repository_id: props.repositoryId,
      error_log: errorLog.value,
      conversation_id: props.conversationId,
    })
    emit('analysis-complete', data)
  } catch (err: unknown) {
    hasError.value = true
    errorMessage.value = 'Failed to analyse error. Please try again.'
  } finally {
    isLoading.value = false
  }
}

function clearLog() {
  errorLog.value = ''
  hasError.value = false
  errorMessage.value = ''
}
</script>
