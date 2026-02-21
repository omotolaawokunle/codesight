import { useChatStore } from '@/stores/chat'
import { storeToRefs } from 'pinia'

export function useChat() {
  const store = useChatStore()
  const { conversations, currentConversation, messages, isLoading, isStreaming, error } =
    storeToRefs(store)

  return {
    conversations,
    currentConversation,
    messages,
    isLoading,
    isStreaming,
    error,
    fetchConversations: store.fetchConversations,
    sendMessage: store.sendMessage,
    setCurrentConversation: store.setCurrentConversation,
    clearConversation: store.clearConversation,
  }
}
