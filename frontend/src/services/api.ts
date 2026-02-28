import axios, { type AxiosError } from 'axios'

const MAX_RETRIES = 3

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  withCredentials: true,
  timeout: 30_000,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const status = error.response?.status

    if (status === 401) {
      localStorage.removeItem('auth_token')
      window.location.href = '/login'
      return Promise.reject(error)
    }

    // Retry on 5xx server errors with exponential backoff
    const config = error.config as typeof error.config & { _retryCount?: number }
    if (config && status && status >= 500) {
      config._retryCount = (config._retryCount ?? 0) + 1
      if (config._retryCount <= MAX_RETRIES) {
        const delay = 2 ** (config._retryCount - 1) * 500 // 500ms, 1s, 2s
        await new Promise((resolve) => setTimeout(resolve, delay))
        return api(config)
      }
    }

    return Promise.reject(error)
  },
)

/**
 * Extracts field-level validation errors from a 422 Unprocessable Entity response.
 * Returns a record mapping field names to their first error message.
 */
export function extractValidationErrors(error: unknown): Record<string, string> {
  if (!axios.isAxiosError(error)) return {}
  const errors = error.response?.data?.errors as Record<string, string[]> | undefined
  if (!errors) return {}
  return Object.fromEntries(
    Object.entries(errors).map(([field, messages]) => [field, messages[0]]),
  )
}

export default api
