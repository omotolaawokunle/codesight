import { describe, it, expect, vi, beforeEach } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
import { useAuthStore } from '@/stores/auth'

// Mock the api module
vi.mock('@/services/api', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
  },
}))

import api from '@/services/api'

const mockUser = { id: 1, name: 'Alice', email: 'alice@example.com', created_at: '2024-01-01' }
const mockToken = 'test-token-xyz'

describe('useAuthStore', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  describe('login', () => {
    it('sets token and user on successful login', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({
        data: { token: mockToken, user: mockUser },
      })

      const store = useAuthStore()
      await store.login({ email: 'alice@example.com', password: 'password123' })

      expect(store.token).toBe(mockToken)
      expect(store.user).toEqual(mockUser)
      expect(store.isAuthenticated).toBe(true)
    })

    it('persists token to localStorage on login', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({
        data: { token: mockToken, user: mockUser },
      })

      const store = useAuthStore()
      await store.login({ email: 'alice@example.com', password: 'password123' })

      expect(localStorage.getItem('auth_token')).toBe(mockToken)
    })

    it('sets error and throws on failed login', async () => {
      vi.mocked(api.post).mockRejectedValueOnce(new Error('Unauthorized'))

      const store = useAuthStore()
      await expect(store.login({ email: 'bad@example.com', password: 'wrong' })).rejects.toThrow()

      expect(store.error).not.toBeNull()
      expect(store.isAuthenticated).toBe(false)
    })

    it('sets isLoading correctly during login', async () => {
      let loadingDuringCall = false

      vi.mocked(api.post).mockImplementationOnce(async () => {
        loadingDuringCall = useAuthStore().isLoading
        return { data: { token: mockToken, user: mockUser } }
      })

      const store = useAuthStore()
      await store.login({ email: 'alice@example.com', password: 'password123' })

      expect(loadingDuringCall).toBe(true)
      expect(store.isLoading).toBe(false)
    })
  })

  describe('register', () => {
    it('sets token and user on successful registration', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({
        data: { token: mockToken, user: mockUser },
      })

      const store = useAuthStore()
      await store.register({
        name: 'Alice',
        email: 'alice@example.com',
        password: 'password123',
        password_confirmation: 'password123',
      })

      expect(store.isAuthenticated).toBe(true)
      expect(store.user?.name).toBe('Alice')
    })
  })

  describe('logout', () => {
    it('clears token and user on logout', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({ data: { token: mockToken, user: mockUser } })
      vi.mocked(api.post).mockResolvedValueOnce({}) // logout call

      const store = useAuthStore()
      await store.login({ email: 'alice@example.com', password: 'password123' })

      await store.logout()

      expect(store.token).toBeNull()
      expect(store.user).toBeNull()
      expect(store.isAuthenticated).toBe(false)
      expect(localStorage.getItem('auth_token')).toBeNull()
    })

    it('clears session even if server logout request fails', async () => {
      vi.mocked(api.post).mockResolvedValueOnce({ data: { token: mockToken, user: mockUser } })
      vi.mocked(api.post).mockRejectedValueOnce(new Error('Network error'))

      const store = useAuthStore()
      await store.login({ email: 'alice@example.com', password: 'password123' })

      await store.logout()

      expect(store.token).toBeNull()
      expect(store.user).toBeNull()
    })
  })

  describe('initializeAuth', () => {
    it('does nothing when no token in localStorage', async () => {
      const store = useAuthStore()
      await store.initializeAuth()

      expect(api.get).not.toHaveBeenCalled()
      expect(store.isAuthenticated).toBe(false)
    })

    it('fetches user when token exists in localStorage', async () => {
      localStorage.setItem('auth_token', mockToken)
      vi.mocked(api.get).mockResolvedValueOnce({ data: mockUser })

      const store = useAuthStore()
      await store.initializeAuth()

      expect(api.get).toHaveBeenCalledWith('/auth/me')
      expect(store.user).toEqual(mockUser)
    })

    it('clears session when token is invalid', async () => {
      localStorage.setItem('auth_token', 'invalid-token')
      vi.mocked(api.get).mockRejectedValueOnce(new Error('Unauthorized'))

      const store = useAuthStore()
      await store.initializeAuth()

      expect(store.token).toBeNull()
      expect(localStorage.getItem('auth_token')).toBeNull()
    })
  })
})
