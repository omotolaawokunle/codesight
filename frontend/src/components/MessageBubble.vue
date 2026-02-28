<template>
  <div
    class="flex gap-3 group"
    :class="isUser ? 'flex-row-reverse' : 'flex-row'"
  >
    <!-- Avatar -->
    <div
      class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold select-none"
      :class="isUser
        ? 'bg-primary-600 text-white'
        : 'bg-gray-900 border border-gray-700 text-primary-400'"
    >
      <UserIcon v-if="isUser" class="w-4 h-4" />
      <SparklesIcon v-else class="w-4 h-4" />
    </div>

    <!-- Bubble -->
    <div class="max-w-[80%] flex flex-col gap-2" :class="isUser ? 'items-end' : 'items-start'">
      <div
        class="rounded-2xl px-4 py-3 text-sm leading-relaxed"
        :class="isUser
          ? 'bg-primary-600 text-white rounded-tr-sm'
          : 'bg-gray-800 border border-gray-700/60 text-gray-100 rounded-tl-sm'"
      >
        <!-- User: plain text -->
        <p v-if="isUser" class="whitespace-pre-wrap wrap-break-word">{{ message.content }}</p>

        <!-- Assistant: rendered Markdown -->
        <div
          v-else-if="message.content"
          class="prose prose-sm prose-invert max-w-none"
          v-html="renderedContent"
        />

        <!-- Streaming cursor -->
        <span
          v-if="isStreaming && !isUser"
          class="inline-block w-2 h-4 bg-primary-400 ml-0.5 align-middle animate-pulse rounded-sm"
        />
      </div>

      <!-- Sources / file references (assistant only) -->
      <div
        v-if="!isUser && sources.length"
        class="flex flex-wrap gap-1.5 px-1"
      >
        <button
          v-for="(source, i) in sources"
          :key="i"
          class="inline-flex items-center gap-1 text-xs font-mono text-gray-400 bg-gray-800/70 border border-gray-700/50 rounded-md px-2 py-1 hover:border-primary-500/50 hover:text-primary-300 transition-colors duration-150 cursor-pointer"
          :title="`Relevance: ${(source.relevance * 100).toFixed(0)}%`"
          @click="$emit('view-source', source)"
        >
          <DocumentIcon class="w-3 h-3 shrink-0" />
          <span class="truncate max-w-[180px]">{{ shortPath(source.file) }}</span>
          <span class="text-gray-500 shrink-0">:{{ source.lines }}</span>
        </button>
      </div>

      <!-- Timestamp + copy -->
      <div
        class="flex items-center gap-2 px-1 opacity-0 group-hover:opacity-100 transition-opacity duration-150"
        :class="isUser ? 'flex-row-reverse' : 'flex-row'"
      >
        <span class="text-xs text-gray-600">{{ formattedTime }}</span>
        <button
          v-if="!isUser && message.content"
          class="text-xs text-gray-600 hover:text-gray-300 transition-colors cursor-pointer flex items-center gap-1"
          @click="copyContent"
        >
          <CheckIcon v-if="copied" class="w-3 h-3 text-green-400" />
          <ClipboardIcon v-else class="w-3 h-3" />
          <span>{{ copied ? 'Copied' : 'Copy' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { marked } from 'marked'
import hljs from 'highlight.js'
import DOMPurify from 'dompurify'
import type { Message, CodeSource } from '@/types'
import {
  UserIcon,
  SparklesIcon,
  DocumentIcon,
  ClipboardIcon,
  CheckIcon,
} from '@heroicons/vue/24/outline'

// Configure marked with highlight.js
marked.setOptions({
  gfm: true,
  breaks: true,
})

const renderer = new marked.Renderer()
renderer.code = ({ text, lang }: { text: string; lang?: string }) => {
  const validLang = lang && hljs.getLanguage(lang) ? lang : 'plaintext'
  const highlighted = hljs.highlight(text, { language: validLang }).value
  return `<pre class="hljs-pre"><div class="hljs-header"><span class="hljs-lang">${validLang}</span><button class="hljs-copy-btn" data-code="${encodeURIComponent(text)}">Copy</button></div><code class="hljs language-${validLang}">${highlighted}</code></pre>`
}
marked.use({ renderer })

interface Props {
  message: Message
  isStreaming?: boolean
}

const props = withDefaults(defineProps<Props>(), { isStreaming: false })

defineEmits<{
  (e: 'view-source', source: CodeSource): void
}>()

const isUser = computed(() => props.message.role === 'user')

const sources = computed<CodeSource[]>(() => {
  const meta = props.message.metadata
  if (!meta) return []
  if (Array.isArray(meta.sources)) return meta.sources as CodeSource[]
  return []
})

const renderedContent = computed(() => {
  if (!props.message.content) return ''
  const raw = marked.parse(props.message.content) as string
  return DOMPurify.sanitize(raw, {
    ADD_ATTR: ['data-code'],
    ADD_TAGS: ['button'],
  })
})

const formattedTime = computed(() => {
  return new Date(props.message.created_at).toLocaleTimeString([], {
    hour: '2-digit',
    minute: '2-digit',
  })
})

function shortPath(filePath: string) {
  const parts = filePath.split('/')
  return parts.length > 2 ? `…/${parts.slice(-2).join('/')}` : filePath
}

const copied = ref(false)
async function copyContent() {
  try {
    await navigator.clipboard.writeText(props.message.content)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // Clipboard API not available
  }
}
</script>

<style>
.hljs-pre {
  border-radius: 0.75rem;
  overflow: hidden;
  border: 1px solid rgb(55 65 81 / 0.5);
  margin: 0.5rem 0;
  font-size: 0.75rem;
}
.hljs-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background-color: #111827;
  border-bottom: 1px solid rgb(55 65 81 / 0.5);
  padding: 0.5rem 1rem;
}
.hljs-lang {
  color: #6b7280;
  font-family: ui-monospace, monospace;
  font-size: 0.75rem;
}
.hljs-copy-btn {
  color: #6b7280;
  font-size: 0.75rem;
  cursor: pointer;
  transition: color 0.15s;
}
.hljs-copy-btn:hover {
  color: #d1d5db;
}
.hljs {
  background-color: #030712;
  color: #f3f4f6;
  padding: 1rem;
  overflow-x: auto;
  display: block;
  font-family: ui-monospace, monospace;
  line-height: 1.625;
}

/* Markdown prose overrides for dark assistant bubble */
.prose-invert code:not(.hljs) {
  background-color: rgb(55 65 81 / 0.6);
  color: #93c5fd;
  padding: 0.125rem 0.375rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-family: ui-monospace, monospace;
}
.prose-invert a {
  color: #60a5fa;
  text-decoration: underline;
  text-decoration-color: rgb(96 165 250 / 0.4);
}
.prose-invert a:hover {
  text-decoration-color: #60a5fa;
}
.prose-invert blockquote {
  border-left: 2px solid #3b82f6;
  padding-left: 0.75rem;
  color: #9ca3af;
  font-style: italic;
}
.prose-invert ul, .prose-invert ol {
  padding-left: 1rem;
}
.prose-invert li {
  margin-bottom: 0.25rem;
}
.prose-invert h1, .prose-invert h2, .prose-invert h3 {
  font-weight: 600;
  color: #f3f4f6;
  margin-top: 0.75rem;
  margin-bottom: 0.25rem;
}
.prose-invert p {
  margin-bottom: 0.5rem;
}
.prose-invert p:last-child {
  margin-bottom: 0;
}
.prose-invert strong {
  color: #f3f4f6;
  font-weight: 600;
}
</style>
