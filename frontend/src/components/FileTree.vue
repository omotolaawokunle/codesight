<template>
  <div class="text-sm font-mono select-none" role="tree" :aria-label="ariaLabel">
    <FileTreeNode
      v-for="node in tree"
      :key="node.name"
      :node="node"
      :depth="0"
      :selected-path="selectedPath"
      @select-file="$emit('select-file', $event)"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue'

// Lazy-load the recursive node component
const FileTreeNode = defineAsyncComponent(() => import('./FileTreeNode.vue'))

interface Props {
  filePaths: string[]
  ariaLabel?: string
  selectedPath?: string
}

const props = withDefaults(defineProps<Props>(), {
  ariaLabel: 'File tree',
  selectedPath: '',
})

defineEmits<{
  (e: 'select-file', path: string): void
}>()

export interface TreeNode {
  name: string
  path: string
  isDirectory: boolean
  children: TreeNode[]
}

function buildTree(paths: string[]): TreeNode[] {
  const root: TreeNode = { name: '', path: '', isDirectory: true, children: [] }

  for (const filePath of paths) {
    const parts = filePath.split('/').filter(Boolean)
    let current = root

    for (let i = 0; i < parts.length; i++) {
      const part = parts[i] as string
      const isLast = i === parts.length - 1
      const existing = current.children.find((c) => c.name === part)

      if (existing) {
        current = existing
      } else {
        const node: TreeNode = {
          name: part,
          path: parts.slice(0, i + 1).join('/'),
          isDirectory: !isLast,
          children: [],
        }
        current.children.push(node)
        current = node
      }
    }
  }

  // Sort: directories first, then files, both alphabetically
  function sortNodes(nodes: TreeNode[]): TreeNode[] {
    nodes.sort((a, b) => {
      if (a.isDirectory !== b.isDirectory) return a.isDirectory ? -1 : 1
      return a.name.localeCompare(b.name)
    })
    for (const node of nodes) {
      if (node.children.length) sortNodes(node.children)
    }
    return nodes
  }

  return sortNodes(root.children)
}

const tree = computed(() => buildTree(props.filePaths))
</script>
