<template>
  <div class="flex h-[calc(100vh-64px)] bg-gray-950 overflow-hidden">
    <!-- Sidebar overlay (mobile) -->
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="sidebarOpen"
        class="fixed inset-0 bg-black/50 z-20 lg:hidden"
        @click="sidebarOpen = false"
      />
    </Transition>

    <!-- Sidebar -->
    <aside
      class="flex flex-col border-r border-gray-800 bg-gray-900 z-30 transition-transform duration-200"
      :class="[
        sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        'fixed lg:relative w-72 h-full lg:w-64 xl:w-72',
      ]"
    >
      <!-- Sidebar header -->
      <div class="flex items-center justify-between px-4 py-4 border-b border-gray-800">
        <div class="flex items-center gap-2 min-w-0">
          <RouterLink
            to="/"
            class="text-gray-500 hover:text-gray-300 transition-colors shrink-0 cursor-pointer"
            title="Back to Dashboard"
          >
            <ChevronLeftIcon class="w-4 h-4" />
          </RouterLink>
          <div class="min-w-0">
            <p class="text-xs text-gray-600 uppercase tracking-wider mb-0.5">Repository</p>
            <p class="text-sm font-medium text-gray-200 truncate">{{ repository?.name ?? '…' }}</p>
          </div>
        </div>
        <button
          class="lg:hidden text-gray-600 hover:text-gray-300 cursor-pointer"
          @click="sidebarOpen = false"
        >
          <XMarkIcon class="w-5 h-5" />
        </button>
      </div>

      <!-- New conversation button -->
      <div class="px-3 py-3 border-b border-gray-800">
        <button
          class="w-full flex items-center justify-center gap-2 text-sm font-medium text-gray-300 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-xl px-3 py-2 transition-colors duration-150 cursor-pointer"
          @click="startNewConversation"
        >
          <PlusIcon class="w-4 h-4" />
          New conversation
        </button>
      </div>

      <!-- Conversation list -->
      <div class="flex-1 overflow-y-auto py-2 px-2">
        <!-- Loading state -->
        <div v-if="chatStore.isLoading && conversations.length === 0" class="space-y-1 px-2 py-2">
          <div v-for="n in 4" :key="n" class="h-10 bg-gray-800 rounded-lg animate-pulse" />
        </div>

        <!-- Empty state -->
        <div v-else-if="conversations.length === 0" class="flex flex-col items-center justify-center py-12 text-center px-4">
          <ChatBubbleLeftRightIcon class="w-8 h-8 text-gray-700 mb-3" />
          <p class="text-xs text-gray-600">No conversations yet.<br>Ask a question to get started.</p>
        </div>

        <!-- Conversations -->
        <div v-else class="space-y-0.5">
          <button
            v-for="conv in conversations"
            :key="conv.id"
            class="w-full group flex items-start gap-2 text-left px-3 py-2.5 rounded-xl text-sm transition-colors duration-150 cursor-pointer"
            :class="activeConversationId === conv.id
              ? 'bg-primary-600/20 text-primary-300'
              : 'text-gray-400 hover:bg-gray-800 hover:text-gray-200'"
            @click="selectConversation(conv)"
          >
            <ChatBubbleOvalLeftIcon class="w-4 h-4 shrink-0 mt-0.5 opacity-60" />
            <div class="flex-1 min-w-0">
              <p class="truncate text-xs font-medium leading-relaxed">
                {{ conv.title ?? 'Untitled conversation' }}
              </p>
              <p class="text-xs text-gray-600 mt-0.5">{{ formatDate(conv.created_at) }}</p>
            </div>

            <!-- Delete button (visible on hover) -->
            <button
              class="shrink-0 opacity-0 group-hover:opacity-100 transition-opacity text-gray-600 hover:text-red-400 cursor-pointer p-0.5"
              title="Delete conversation"
              @click.stop="deleteConversation(conv.id)"
            >
              <TrashIcon class="w-3.5 h-3.5" />
            </button>
          </button>
        </div>
      </div>

      <!-- Repo quick-actions -->
      <div class="border-t border-gray-800 px-3 py-3">
        <RouterLink
          :to="{ name: 'repository-detail', params: { id: repositoryId } }"
          class="flex items-center gap-2 text-xs text-gray-600 hover:text-gray-300 transition-colors cursor-pointer px-2 py-1.5 rounded-lg hover:bg-gray-800"
        >
          <InformationCircleIcon class="w-4 h-4" />
          Repository details
        </RouterLink>
      </div>
    </aside>

    <!-- Main chat area -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
      <!-- Mobile top bar -->
      <div class="flex items-center gap-3 px-4 py-3 border-b border-gray-800 bg-gray-900 lg:hidden">
        <button
          class="text-gray-500 hover:text-gray-200 cursor-pointer"
          title="Toggle sidebar"
          @click="sidebarOpen = true"
        >
          <Bars3Icon class="w-5 h-5" />
        </button>
        <span class="text-sm font-medium text-gray-300 truncate">{{ repository?.name }}</span>
      </div>

      <!-- Chat interface or no-repo state -->
      <div v-if="!repositoryId" class="flex-1 flex items-center justify-center">
        <div class="text-center space-y-3">
          <ExclamationCircleIcon class="w-10 h-10 text-gray-700 mx-auto" />
          <p class="text-sm text-gray-500">No repository selected.</p>
          <RouterLink to="/" class="text-sm text-primary-400 hover:underline">
            Back to Dashboard
          </RouterLink>
        </div>
      </div>

      <ChatInterface
        v-else
        :key="activeConversationId"
        :repository-id="repositoryId"
        :repository-name="repository?.name ?? ''"
        :conversation-id="activeConversationId"
        :conversation-title="activeConversation?.title ?? ''"
        class="flex-1 overflow-hidden"
        @conversation-created="onConversationCreated"
      />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useChatStore } from '@/stores/chat'
import { useRepositoryStore } from '@/stores/repository'
import type { Conversation } from '@/types'
import ChatInterface from '@/components/ChatInterface.vue'
import {
  Bars3Icon,
  ChatBubbleLeftRightIcon,
  ChatBubbleOvalLeftIcon,
  ChevronLeftIcon,
  ExclamationCircleIcon,
  InformationCircleIcon,
  PlusIcon,
  TrashIcon,
  XMarkIcon,
} from '@heroicons/vue/24/outline'

const route = useRoute()
const chatStore = useChatStore()
const repoStore = useRepositoryStore()

const props = defineProps<{ id: string }>()

const repositoryId = computed(() => Number(props.id))
const repository = computed(() => repoStore.currentRepository)
const conversations = computed(() => chatStore.conversations)
const activeConversationId = ref<number | undefined>(undefined)
const activeConversation = computed(() =>
  conversations.value.find((c) => c.id === activeConversationId.value) ?? null,
)
const sidebarOpen = ref(false)

async function selectConversation(conv: Conversation) {
  activeConversationId.value = conv.id
  await chatStore.loadConversationMessages(conv)
  sidebarOpen.value = false
}

function startNewConversation() {
  activeConversationId.value = undefined
  chatStore.clearConversation()
  sidebarOpen.value = false
}

async function deleteConversation(id: number) {
  await chatStore.deleteConversation(id)
  if (activeConversationId.value === id) startNewConversation()
}

function onConversationCreated(id: number) {
  activeConversationId.value = id
  chatStore.fetchConversations(repositoryId.value)
}

function formatDate(dateStr: string) {
  const d = new Date(dateStr)
  const now = new Date()
  const diffDays = Math.floor((now.getTime() - d.getTime()) / 86400000)
  if (diffDays === 0) return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
  if (diffDays === 1) return 'Yesterday'
  if (diffDays < 7) return d.toLocaleDateString([], { weekday: 'short' })
  return d.toLocaleDateString([], { month: 'short', day: 'numeric' })
}

onMounted(async () => {
  await Promise.all([
    repoStore.fetchRepository(repositoryId.value),
    chatStore.fetchConversations(repositoryId.value),
  ])

  // If a conversation query param was passed, activate it
  const queryConv = route.query.conversation
  if (queryConv) {
    const conv = conversations.value.find((c) => c.id === Number(queryConv))
    if (conv) await selectConversation(conv)
  }
})
</script>
