<template>
  <div class="flex h-[calc(100vh-64px)]">
    <aside class="w-64 border-r border-gray-200 bg-white flex flex-col">
      <div class="p-4 border-b border-gray-200">
        <h2 class="font-semibold text-gray-900 text-sm">Conversations</h2>
      </div>
      <div class="flex-1 overflow-y-auto p-4">
        <p class="text-gray-400 text-sm">No conversations yet.</p>
      </div>
    </aside>

    <main class="flex-1 flex flex-col bg-gray-50">
      <div class="flex-1 overflow-y-auto p-6">
        <p class="text-center text-gray-400 mt-16">
          Ask a question about this repository to get started.
        </p>
      </div>

      <div class="border-t border-gray-200 bg-white p-4">
        <div class="flex items-end gap-3">
          <textarea
            rows="2"
            placeholder="Ask a question about this codebase..."
            class="flex-1 resize-none border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          />
          <button class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-lg font-medium text-sm transition-colors">
            Send
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
defineProps<{ id: string }>()
</script>
