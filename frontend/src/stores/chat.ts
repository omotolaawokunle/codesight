import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Conversation, Message } from '@/types'
import api from '@/services/api'

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const currentConversation = ref<Conversation | null>(null)
  const messages = ref<Message[]>([])
  const isLoading = ref(false)
  const isStreaming = ref(false)
  const error = ref<string | null>(null)

  async function fetchConversations(repositoryId: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/chat/${repositoryId}/conversations`)
      conversations.value = data.data
    } catch (err: unknown) {
      error.value = 'Failed to load conversations'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function sendMessage(repositoryId: number, message: string, conversationId?: number) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.post('/chat', {
        repository_id: repositoryId,
        message,
        conversation_id: conversationId,
      })
      return data
    } catch (err: unknown) {
      error.value = 'Failed to send message'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  function setCurrentConversation(conversation: Conversation | null) {
    currentConversation.value = conversation
    messages.value = conversation?.messages ?? []
  }

  function clearConversation() {
    currentConversation.value = null
    messages.value = []
  }

  return {
    conversations,
    currentConversation,
    messages,
    isLoading,
    isStreaming,
    error,
    fetchConversations,
    sendMessage,
    setCurrentConversation,
    clearConversation,
  }
})
