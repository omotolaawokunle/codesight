<template>
  <div role="treeitem" :aria-expanded="node.isDirectory ? isOpen : undefined">
    <!-- Node row -->
    <div
      class="flex items-center gap-1.5 py-0.5 rounded-md cursor-pointer group transition-colors duration-100"
      :style="{ paddingLeft: `${depth * 12 + 8}px` }"
      :class="[
        node.isDirectory
          ? 'hover:bg-slate-800/70 text-slate-300'
          : 'hover:bg-slate-800/50 text-slate-500 hover:text-slate-200',
        selected && !node.isDirectory ? 'bg-slate-800 text-slate-200' : '',
      ]"
      @click="handleClick"
    >
      <!-- Expand/collapse chevron for directories -->
      <span v-if="node.isDirectory" class="shrink-0 w-3.5 h-3.5 text-slate-600">
        <ChevronRightIcon
          class="w-3.5 h-3.5 transition-transform duration-150"
          :class="isOpen ? 'rotate-90' : ''"
        />
      </span>
      <!-- File spacer -->
      <span v-else class="shrink-0 w-3.5" />

      <!-- Icon -->
      <span class="shrink-0">
        <FolderOpenIcon v-if="node.isDirectory && isOpen" class="w-3.5 h-3.5 text-primary-400" />
        <FolderIcon v-else-if="node.isDirectory" class="w-3.5 h-3.5 text-slate-600" />
        <component
          :is="fileIcon(node.name)"
          v-else
          class="w-3.5 h-3.5 transition-colors"
          :class="selected ? 'text-primary-400' : 'text-slate-600 group-hover:text-slate-400'"
        />
      </span>

      <!-- Name -->
      <span
        class="truncate text-xs leading-relaxed"
        :class="node.isDirectory ? 'font-medium' : ''"
      >{{ node.name }}</span>

      <!-- File extension badge -->
      <span
        v-if="!node.isDirectory && fileExt(node.name)"
        class="ml-auto mr-2 shrink-0 text-[10px] font-mono text-slate-700 opacity-0 group-hover:opacity-100 transition-opacity"
      >
        .{{ fileExt(node.name) }}
      </span>
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
          :selected-path="selectedPath"
          @select-file="$emit('select-file', $event)"
        />
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
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
  selectedPath?: string
}

const props = withDefaults(defineProps<Props>(), { selectedPath: '' })

defineEmits<{
  (e: 'select-file', path: string): void
}>()

const isOpen = ref(props.depth === 0)
const selected = computed(() => !props.node.isDirectory && props.node.path === props.selectedPath)

function handleClick() {
  if (props.node.isDirectory) {
    isOpen.value = !isOpen.value
  }
}

const CODE_EXTENSIONS = new Set([
  'js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'java', 'php', 'rb', 'go', 'rs',
  'cpp', 'c', 'h', 'cs', 'swift', 'kt', 'scala', 'sh', 'bash',
])

function fileExt(name: string): string {
  return name.split('.').pop()?.toLowerCase() ?? ''
}

function fileIcon(name: string) {
  return CODE_EXTENSIONS.has(fileExt(name)) ? CodeBracketIcon : DocumentIcon
}
</script>
