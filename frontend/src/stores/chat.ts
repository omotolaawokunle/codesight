import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { Conversation, Message, CodeSource } from '@/types'
import api from '@/services/api'
import { useStreaming } from '@/composables/useStreaming'
import { useUiStore } from '@/stores/ui'

export const useChatStore = defineStore('chat', () => {
  const conversations = ref<Conversation[]>([])
  const currentConversation = ref<Conversation | null>(null)
  const messages = ref<Message[]>([])
  const isLoading = ref(false)
  const isStreaming = ref(false)
  const error = ref<string | null>(null)

  const { stream } = useStreaming()
  const ui = useUiStore()

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
        query: message,
        conversation_id: conversationId,
      })

      // Update conversation list if new conversation was created
      if (!conversationId && data.conversation_id) {
        await fetchConversations(repositoryId)
        const conv = conversations.value.find((c) => c.id === data.conversation_id)
        if (conv) currentConversation.value = conv
      }

      return data
    } catch (err: unknown) {
      error.value = 'Failed to send message'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  /**
   * Stream a message via SSE. Appends a placeholder assistant message that fills in
   * as chunks arrive, then updates it with the final content and sources.
   */
  async function streamMessage(
    repositoryId: number,
    userText: string,
    conversationId?: number,
  ): Promise<{ conversation_id: number; sources: CodeSource[] }> {
    error.value = null

    // Optimistically add the user message
    const tempUserMsg: Message = {
      id: Date.now(),
      conversation_id: conversationId ?? 0,
      role: 'user',
      content: userText,
      metadata: null,
      created_at: new Date().toISOString(),
    }
    messages.value.push(tempUserMsg)

    // Placeholder for the streaming assistant reply
    const tempAssistantMsg: Message = {
      id: Date.now() + 1,
      conversation_id: conversationId ?? 0,
      role: 'assistant',
      content: '',
      metadata: null,
      created_at: new Date().toISOString(),
    }
    messages.value.push(tempAssistantMsg)

    isStreaming.value = true
    let finalSources: CodeSource[] = []
    let finalConversationId = conversationId ?? 0

    const baseUrl = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api'

    try {
      await stream(
        `${baseUrl}/chat/stream`,
        { repository_id: repositoryId, query: userText, conversation_id: conversationId },
        (chunk) => {
          tempAssistantMsg.content += chunk
          // Vue reactivity — replace the array item to trigger updates
          const idx = messages.value.findIndex((m) => m.id === tempAssistantMsg.id)
          if (idx !== -1) messages.value[idx] = { ...tempAssistantMsg }
        },
        () => {
          // stream_end received — content is fully assembled in tempAssistantMsg
        },
      )

      // Refresh conversations to get the real IDs / updated title
      await fetchConversations(repositoryId)
      const latest = conversations.value[0]
      if (latest) {
        finalConversationId = latest.id
        currentConversation.value = latest
      }
    } catch (err: unknown) {
      error.value = 'Failed to stream response'
      ui.addToast('Failed to get a response. Please try again.', 'error')
      // Remove the incomplete assistant message on error
      messages.value = messages.value.filter((m) => m.id !== tempAssistantMsg.id)
      throw err
    } finally {
      isStreaming.value = false
    }

    return { conversation_id: finalConversationId, sources: finalSources }
  }

  async function deleteConversation(conversationId: number) {
    try {
      await api.delete(`/chat/conversations/${conversationId}`)
      conversations.value = conversations.value.filter((c) => c.id !== conversationId)
      if (currentConversation.value?.id === conversationId) {
        currentConversation.value = null
        messages.value = []
      }
      ui.addToast('Conversation deleted.', 'success')
    } catch (err: unknown) {
      error.value = 'Failed to delete conversation'
      ui.addToast('Failed to delete conversation.', 'error')
      throw err
    }
  }

  async function loadConversationMessages(conversation: Conversation) {
    currentConversation.value = conversation
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.get(`/chat/conversations/${conversation.id}/messages`)
      messages.value = data.data ?? data
    } catch {
      messages.value = conversation.messages ?? []
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
    streamMessage,
    deleteConversation,
    loadConversationMessages,
    setCurrentConversation,
    clearConversation,
  }
})
