<template>
  <div class="flex flex-col h-full bg-gray-950">
    <!-- Repository context bar -->
    <div class="shrink-0 flex items-center justify-between px-4 py-3 bg-gray-900 border-b border-gray-800">
      <div class="flex items-center gap-2 min-w-0">
        <CodeBracketSquareIcon class="w-4 h-4 text-primary-400 shrink-0" />
        <span class="text-sm font-medium text-gray-300 truncate">{{ repositoryName }}</span>
        <span v-if="conversationTitle" class="text-gray-600">/</span>
        <span v-if="conversationTitle" class="text-xs text-gray-500 truncate">{{ conversationTitle }}</span>
      </div>

      <div class="flex items-center gap-1.5 shrink-0">
        <!-- Error analysis toggle -->
        <button
          class="flex items-center gap-1.5 text-xs text-gray-500 hover:text-amber-400 transition-colors duration-150 cursor-pointer px-2 py-1.5 rounded-lg hover:bg-amber-950/30"
          :class="{ 'text-amber-400 bg-amber-950/30': showErrorPanel }"
          title="Analyse an error log"
          @click="showErrorPanel = !showErrorPanel"
        >
          <BugAntIcon class="w-4 h-4" />
          <span class="hidden sm:inline">Debug Error</span>
        </button>
      </div>
    </div>

    <!-- Error panel (collapsible) -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div v-if="showErrorPanel" class="shrink-0 p-4 border-b border-gray-800">
        <ErrorDisplay
          :repository-id="repositoryId"
          :conversation-id="conversationId"
          @close="showErrorPanel = false"
          @analysis-complete="onErrorAnalysis"
        />
      </div>
    </Transition>

    <!-- Message list -->
    <div
      ref="messageList"
      class="flex-1 overflow-y-auto px-4 py-6 space-y-6"
      role="log"
      aria-live="polite"
      aria-label="Conversation messages"
    >
      <!-- Empty state -->
      <div v-if="messages.length === 0 && !isLoading" class="flex flex-col items-center justify-center h-full gap-6 text-center">
        <div class="w-16 h-16 rounded-2xl bg-gray-900 border border-gray-800 flex items-center justify-center">
          <SparklesIcon class="w-8 h-8 text-primary-400" />
        </div>
        <div class="space-y-2 max-w-sm">
          <h3 class="text-base font-semibold text-gray-200">Ask anything about this codebase</h3>
          <p class="text-sm text-gray-500 leading-relaxed">
            Explain architecture, debug issues, find functions, understand patterns — all grounded in your actual code.
          </p>
        </div>
        <!-- Prompt suggestions -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 w-full max-w-md">
          <button
            v-for="suggestion in suggestions"
            :key="suggestion"
            class="text-left text-xs text-gray-400 bg-gray-900 border border-gray-800 hover:border-primary-500/50 hover:text-primary-300 rounded-xl px-3 py-2.5 transition-colors duration-150 cursor-pointer leading-relaxed"
            @click="inputText = suggestion"
          >
            {{ suggestion }}
          </button>
        </div>
      </div>

      <!-- Loading skeleton (initial load) -->
      <div v-if="isLoading && messages.length === 0" class="space-y-6">
        <div v-for="n in 3" :key="n" class="flex gap-3 animate-pulse" :class="n % 2 === 0 ? 'flex-row-reverse' : ''">
          <div class="w-8 h-8 rounded-full bg-gray-800 shrink-0" />
          <div class="space-y-2 flex-1 max-w-[70%]">
            <div class="h-4 bg-gray-800 rounded-lg" :style="{ width: `${60 + Math.random() * 30}%` }" />
            <div class="h-4 bg-gray-800 rounded-lg" :style="{ width: `${40 + Math.random() * 40}%` }" />
          </div>
        </div>
      </div>

      <!-- Messages -->
      <MessageBubble
        v-for="msg in messages"
        :key="msg.id"
        :message="msg"
        :is-streaming="isStreaming && msg.id === lastMessageId"
        @view-source="onViewSource"
      />
    </div>

    <!-- Source viewer (slide-up panel) -->
    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      enter-from-class="opacity-0 translate-y-4"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition-all duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 translate-y-4"
    >
      <div v-if="activeSource" class="shrink-0 border-t border-gray-800 bg-gray-950 max-h-72 overflow-y-auto">
        <div class="flex items-center justify-between px-4 py-2 sticky top-0 bg-gray-950 border-b border-gray-800 z-10">
          <span class="text-xs font-mono text-gray-400">{{ activeSource.file }}</span>
          <button class="text-gray-600 hover:text-gray-300 cursor-pointer" @click="activeSource = null">
            <XMarkIcon class="w-4 h-4" />
          </button>
        </div>
        <div class="p-4">
          <CodeViewer
            :code="activeSourceContent"
            :language="activeSourceLang"
            :file-path="activeSource.file"
            :line-range="activeSource.lines"
          />
        </div>
      </div>
    </Transition>

    <!-- Input bar -->
    <div class="shrink-0 border-t border-gray-800 bg-gray-900 p-4">
      <div
        class="flex items-end gap-3 bg-gray-950 border rounded-2xl px-4 py-3 transition-colors duration-150"
        :class="isFocused ? 'border-primary-500/60 ring-1 ring-primary-500/20' : 'border-gray-800'"
      >
        <textarea
          ref="inputRef"
          v-model="inputText"
          rows="1"
          placeholder="Ask a question about this codebase… (Enter to send, Shift+Enter for newline)"
          class="flex-1 bg-transparent text-sm text-gray-200 placeholder-gray-600 resize-none focus:outline-none leading-relaxed max-h-40 overflow-y-auto"
          aria-label="Message input"
          :disabled="isStreaming"
          @focus="isFocused = true"
          @blur="isFocused = false"
          @keydown.enter.exact.prevent="handleSend"
          @input="autoResize"
        />

        <button
          :disabled="!inputText.trim() || isStreaming"
          class="shrink-0 w-9 h-9 rounded-xl flex items-center justify-center transition-all duration-150 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
          :class="inputText.trim() && !isStreaming
            ? 'bg-primary-600 hover:bg-primary-500 text-white'
            : 'bg-gray-800 text-gray-600'"
          title="Send message (Enter)"
          @click="handleSend"
        >
          <ArrowUpIcon v-if="!isStreaming" class="w-4 h-4" />
          <StopIcon v-else class="w-4 h-4" />
        </button>
      </div>

      <p class="text-xs text-gray-700 text-center mt-2">
        Answers are based on the indexed code. Re-index to pick up new changes.
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useChatStore } from '@/stores/chat'
import type { CodeSource, Message } from '@/types'
import MessageBubble from './MessageBubble.vue'
import ErrorDisplay from './ErrorDisplay.vue'
import CodeViewer from './CodeViewer.vue'
import {
  ArrowUpIcon,
  BugAntIcon,
  CodeBracketSquareIcon,
  SparklesIcon,
  StopIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

interface Props {
  repositoryId: number
  repositoryName: string
  conversationId?: number
  conversationTitle?: string
}

const props = withDefaults(defineProps<Props>(), {
  conversationTitle: '',
})

const emit = defineEmits<{
  (e: 'conversation-created', id: number): void
}>()

const store = useChatStore()

const messages = computed(() => store.messages)
const isLoading = computed(() => store.isLoading)
const isStreaming = computed(() => store.isStreaming)
const lastMessageId = computed(() => messages.value[messages.value.length - 1]?.id)

const inputText = ref('')
const inputRef = ref<HTMLTextAreaElement | null>(null)
const messageList = ref<HTMLDivElement | null>(null)
const isFocused = ref(false)
const showErrorPanel = ref(false)
const activeSource = ref<CodeSource | null>(null)
const activeSourceContent = ref('')
const activeSourceLang = ref('plaintext')

const suggestions = [
  'How is authentication handled?',
  'Explain the main entry point',
  'Where are API routes defined?',
  'What design patterns are used?',
]

// Auto-scroll to bottom when messages change
watch(
  messages,
  async () => {
    await nextTick()
    if (messageList.value) {
      messageList.value.scrollTo({ top: messageList.value.scrollHeight, behavior: 'smooth' })
    }
  },
  { deep: true },
)

function autoResize() {
  const el = inputRef.value
  if (!el) return
  el.style.height = 'auto'
  el.style.height = `${Math.min(el.scrollHeight, 160)}px`
}

async function handleSend() {
  const text = inputText.value.trim()
  if (!text || isStreaming.value) return

  inputText.value = ''
  await nextTick()
  autoResize()

  try {
    const result = await store.streamMessage(props.repositoryId, text, props.conversationId)
    if (!props.conversationId && result.conversation_id) {
      emit('conversation-created', result.conversation_id)
    }
  } catch {
    // Error already set in store
  }
}

function onViewSource(source: CodeSource) {
  activeSource.value = source
  activeSourceContent.value = `# ${source.file}\n# Lines: ${source.lines}\n# (fetch full content via API in production)`
  const ext = source.file.split('.').pop()?.toLowerCase() ?? 'plaintext'
  activeSourceLang.value = ext
}

function onErrorAnalysis(response: { message: string; sources: unknown[] }) {
  showErrorPanel.value = false
  // Push the analysis result as an assistant message
  const msg: Message = {
    id: Date.now(),
    conversation_id: props.conversationId ?? 0,
    role: 'assistant',
    content: response.message,
    metadata: { sources: response.sources },
    created_at: new Date().toISOString(),
  }
  store.messages.push(msg)
}
</script>
