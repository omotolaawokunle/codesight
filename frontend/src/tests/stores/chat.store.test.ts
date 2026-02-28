import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useChatStore } from '@/stores/chat'
import type { Conversation } from '@/types'

vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/composables/useStreaming', () => ({
  useStreaming: () => ({ stream: vi.fn() }),
}))

import api from '@/services/api'

const makeConversation = (overrides: Partial<Conversation> = {}): Conversation => ({
  id: 1,
  repository_id: 1,
  title: 'Test Conversation',
  created_at: '2024-01-01T00:00:00Z',
  updated_at: '2024-01-01T00:00:00Z',
  ...overrides,
})

describe('useChatStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.clearAllMocks()
  })

  describe('fetchConversations', () => {
    it('loads conversations and stores them', async () => {
      const convos = [makeConversation({ id: 1 }), makeConversation({ id: 2 })]
      vi.mocked(api.get).mockResolvedValueOnce({ data: { data: convos } })

      const store = useChatStore()
      await store.fetchConversations(1)

      expect(store.conversations).toHaveLength(2)
      expect(store.isLoading).toBe(false)
    })

    it('sets error and throws on failure', async () => {
      vi.mocked(api.get).mockRejectedValueOnce(new Error('Network error'))

      const store = useChatStore()
      await expect(store.fetchConversations(1)).rejects.toThrow()

      expect(store.error).not.toBeNull()
    })
  })

  describe('sendMessage', () => {
    it('returns response data on success', async () => {
      vi.mocked(api.get).mockResolvedValue({ data: { data: [] } })
      vi.mocked(api.post).mockResolvedValueOnce({
        data: {
          conversation_id: 1,
          content: 'The answer is 42.',
          sources: [],
          usage: {},
        },
      })

      const store = useChatStore()
      const result = await store.sendMessage(1, 'What is the answer?')

      expect(result.content).toBe('The answer is 42.')
    })

    it('sets error on failure', async () => {
      vi.mocked(api.post).mockRejectedValueOnce(new Error('Server error'))

      const store = useChatStore()
      await expect(store.sendMessage(1, 'Hello?')).rejects.toThrow()

      expect(store.error).not.toBeNull()
    })
  })

  describe('deleteConversation', () => {
    it('removes the conversation from the list', async () => {
      vi.mocked(api.delete).mockResolvedValueOnce({})

      const store = useChatStore()
      store.conversations = [makeConversation({ id: 1 }), makeConversation({ id: 2 })]

      await store.deleteConversation(1)

      expect(store.conversations).toHaveLength(1)
      expect(store.conversations[0].id).toBe(2)
    })

    it('clears currentConversation when the active one is deleted', async () => {
      vi.mocked(api.delete).mockResolvedValueOnce({})

      const store = useChatStore()
      store.conversations = [makeConversation({ id: 1 })]
      store.setCurrentConversation(makeConversation({ id: 1 }))

      await store.deleteConversation(1)

      expect(store.currentConversation).toBeNull()
      expect(store.messages).toHaveLength(0)
    })

    it('sets error on failure', async () => {
      vi.mocked(api.delete).mockRejectedValueOnce(new Error('Forbidden'))

      const store = useChatStore()
      store.conversations = [makeConversation({ id: 1 })]

      await expect(store.deleteConversation(1)).rejects.toThrow()
      expect(store.error).not.toBeNull()
    })
  })

  describe('setCurrentConversation', () => {
    it('sets current conversation and loads its messages', () => {
      const store = useChatStore()
      const convo = makeConversation({ messages: [
        { id: 10, conversation_id: 1, role: 'user', content: 'hi', metadata: null, created_at: '' },
      ]})

      store.setCurrentConversation(convo)

      expect(store.currentConversation?.id).toBe(1)
      expect(store.messages).toHaveLength(1)
    })

    it('clears messages when set to null', () => {
      const store = useChatStore()
      store.setCurrentConversation(null)

      expect(store.currentConversation).toBeNull()
      expect(store.messages).toHaveLength(0)
    })
  })

  describe('clearConversation', () => {
    it('resets conversation state', () => {
      const store = useChatStore()
      store.setCurrentConversation(makeConversation())

      store.clearConversation()

      expect(store.currentConversation).toBeNull()
      expect(store.messages).toHaveLength(0)
    })
  })
})
