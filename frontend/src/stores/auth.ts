import api from '@/services/api'
import type { AuthResponse, LoginPayload, RegisterPayload, User } from '@/types'
import { defineStore } from 'pinia'
import { computed, ref } from 'vue'

const TOKEN_KEY = 'auth_token'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY))
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const isAuthenticated = computed(() => !!token.value && !!user.value)

  function setSession(data: AuthResponse) {
    token.value = data.token
    user.value = data.user
    localStorage.setItem(TOKEN_KEY, data.token)
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem(TOKEN_KEY)
  }

  async function fetchUser() {
    const { data } = await api.get<User>('/auth/me')
    user.value = data
  }

  /**
   * Restores the session on app boot if a token is stored in localStorage.
   * Silently clears the session if the token is invalid or expired.
   */
  async function initializeAuth() {
    if (!token.value) return
    try {
      await fetchUser()
    } catch {
      clearSession()
    }
  }

  async function login(payload: LoginPayload) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.post<AuthResponse>('/auth/login', payload)
      setSession(data)
    } catch (err: unknown) {
      error.value = 'Invalid credentials. Please try again.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function register(payload: RegisterPayload) {
    isLoading.value = true
    error.value = null
    try {
      const { data } = await api.post<AuthResponse>('/auth/register', payload)
      setSession(data)
    } catch (err: unknown) {
      error.value = 'Registration failed. Please check your details.'
      throw err
    } finally {
      isLoading.value = false
    }
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } catch {
      // Even if the server call fails, clear the local session
    } finally {
      clearSession()
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    isLoading,
    error,
    initializeAuth,
    login,
    register,
    logout,
    fetchUser,
  }
})
