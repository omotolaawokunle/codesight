<template>
  <div role="treeitem" :aria-expanded="node.isDirectory ? isOpen : undefined">
    <!-- Node row -->
    <div
      class="flex items-center gap-1.5 py-0.5 px-2 rounded-md cursor-pointer group transition-colors duration-100"
      :style="{ paddingLeft: `${depth * 12 + 8}px` }"
      :class="node.isDirectory
        ? 'hover:bg-gray-800/60 text-gray-300'
        : 'hover:bg-gray-800/40 text-gray-400 hover:text-gray-200'"
      @click="handleClick"
    >
      <!-- Expand/collapse chevron for directories -->
      <span v-if="node.isDirectory" class="shrink-0 w-3.5 h-3.5 text-gray-600">
        <ChevronRightIcon
          class="w-3.5 h-3.5 transition-transform duration-150"
          :class="isOpen ? 'rotate-90' : ''"
        />
      </span>
      <!-- File spacer -->
      <span v-else class="shrink-0 w-3.5" />

      <!-- Icon -->
      <span class="shrink-0">
        <FolderOpenIcon v-if="node.isDirectory && isOpen" class="w-4 h-4 text-primary-400" />
        <FolderIcon v-else-if="node.isDirectory" class="w-4 h-4 text-gray-500" />
        <component :is="fileIcon(node.name)" v-else class="w-4 h-4 text-gray-500 group-hover:text-gray-300 transition-colors" />
      </span>

      <!-- Name -->
      <span class="truncate text-xs leading-relaxed">{{ node.name }}</span>
    </div>

    <!-- Children (only for open directories) -->
    <Transition
      enter-active-class="transition-all duration-150 ease-out overflow-hidden"
      enter-from-class="max-h-0 opacity-0"
      enter-to-class="max-h-[9999px] opacity-100"
      leave-active-class="transition-all duration-100 ease-in overflow-hidden"
      leave-from-class="max-h-[9999px] opacity-100"
      leave-to-class="max-h-0 opacity-0"
    >
      <div v-if="node.isDirectory && isOpen" role="group">
        <FileTreeNode
          v-for="child in node.children"
          :key="child.name"
          :node="child"
          :depth="depth + 1"
          @select-file="$emit('select-file', $event)"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import {
  ChevronRightIcon,
  FolderIcon,
  FolderOpenIcon,
  DocumentIcon,
  CodeBracketIcon,
} from '@heroicons/vue/24/outline'
import type { TreeNode } from './FileTree.vue'

interface Props {
  node: TreeNode
  depth: number
}

const props = defineProps<Props>()

defineEmits<{
  (e: 'select-file', path: string): void
}>()

const isOpen = ref(props.depth === 0)

function handleClick() {
  if (props.node.isDirectory) {
    isOpen.value = !isOpen.value
  } else {
    // Emit select-file via parent chain
  }
}

const CODE_EXTENSIONS = new Set([
  'js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'java', 'php', 'rb', 'go', 'rs',
  'cpp', 'c', 'h', 'cs', 'swift', 'kt', 'scala', 'sh', 'bash',
])

function fileIcon(name: string) {
  const ext = name.split('.').pop()?.toLowerCase() ?? ''
  return CODE_EXTENSIONS.has(ext) ? CodeBracketIcon : DocumentIcon
}
</script>
