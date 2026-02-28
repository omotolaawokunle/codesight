import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'

export function useAuth() {
  const store = useAuthStore()
  const { user, token, isAuthenticated, isLoading, error } = storeToRefs(store)

  return {
    user,
    token,
    isAuthenticated,
    isLoading,
    error,
    initializeAuth: store.initializeAuth,
    login: store.login,
    register: store.register,
    logout: store.logout,
    fetchUser: store.fetchUser,
  }
}
