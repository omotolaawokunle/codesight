<template>
  <div class="rounded-xl overflow-hidden border border-gray-700/60 bg-gray-950 text-sm font-mono">
    <!-- Header bar -->
    <div class="flex items-center justify-between bg-gray-900 border-b border-gray-700/60 px-4 py-2.5 gap-3">
      <div class="flex items-center gap-2 min-w-0">
        <!-- Traffic lights for visual flair -->
        <span class="w-3 h-3 rounded-full bg-red-500/60 shrink-0" />
        <span class="w-3 h-3 rounded-full bg-yellow-500/60 shrink-0" />
        <span class="w-3 h-3 rounded-full bg-green-500/60 shrink-0" />

        <span v-if="filePath" class="text-gray-400 text-xs truncate ml-2 font-mono">
          {{ filePath }}<span v-if="lineRange" class="text-gray-600">:{{ lineRange }}</span>
        </span>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <span class="text-gray-600 text-xs uppercase tracking-wide">{{ language }}</span>
        <button
          class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-200 transition-colors duration-150 cursor-pointer"
          :title="copied ? 'Copied!' : 'Copy code'"
          @click="copyCode"
        >
          <CheckIcon v-if="copied" class="w-3.5 h-3.5 text-green-400" />
          <ClipboardIcon v-else class="w-3.5 h-3.5" />
          <span>{{ copied ? 'Copied' : 'Copy' }}</span>
        </button>
      </div>
    </div>

    <!-- Code content -->
    <div class="overflow-x-auto">
      <pre class="p-4 m-0 text-xs leading-relaxed"><code
        class="hljs"
        :class="language ? `language-${language}` : ''"
        v-html="highlightedCode"
      /></pre>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import hljs from 'highlight.js'
import { ClipboardIcon, CheckIcon } from '@heroicons/vue/24/outline'

interface Props {
  code: string
  language?: string
  filePath?: string
  lineRange?: string
}

const props = withDefaults(defineProps<Props>(), {
  language: 'plaintext',
  filePath: '',
  lineRange: '',
})

const copied = ref(false)

const highlightedCode = computed(() => {
  const lang = props.language && hljs.getLanguage(props.language) ? props.language : 'plaintext'
  try {
    return hljs.highlight(props.code, { language: lang }).value
  } catch {
    return props.code
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
  }
})

async function copyCode() {
  try {
    await navigator.clipboard.writeText(props.code)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // Clipboard API not available
  }
}
</script>
