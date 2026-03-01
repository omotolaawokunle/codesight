import { useAuthStore } from '@/stores/auth'
import { ref } from 'vue'

export interface StreamChunk {
  type: 'text_delta' | 'stream_end' | 'stream_start' | 'text_start' | 'text_end' | 'error' | string
  delta?: string
  reason?: string
  error?: string
}

function getCsrfToken(): string | null {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/)
  return match?.[1] ? decodeURIComponent(match[1]) : null
}

export function useStreaming() {
  const authStore = useAuthStore()
  const isStreaming = ref(false)
  const streamedContent = ref('')

  async function stream(
    url: string,
    body: Record<string, unknown>,
    onChunk: (chunk: string) => void,
    onDone?: () => void,
  ): Promise<void> {
    isStreaming.value = true
    streamedContent.value = ''

    try {
      const token = authStore.token
      const headers: Record<string, string> = {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
      }
      if (token) headers['Authorization'] = `Bearer ${token}`
      const csrf = getCsrfToken()
      if (csrf) headers['X-XSRF-TOKEN'] = csrf

      const response = await fetch(url, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
        credentials: 'include',
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      if (!response.body) throw new Error('No response body')

      const reader = response.body.getReader()
      const decoder = new TextDecoder()
      let buffer = ''

      while (true) {
        const { done, value } = await reader.read()
        if (done) break

        buffer += decoder.decode(value, { stream: true })

        // Process complete SSE lines from the buffer
        const lines = buffer.split('\n')
        // Keep the last (possibly incomplete) line in the buffer
        buffer = lines.pop() ?? ''

        for (const line of lines) {
          const trimmed = line.trim()
          if (!trimmed || !trimmed.startsWith('data:')) continue

          const jsonStr = trimmed.slice(5).trim()
          if (jsonStr === '[DONE]') continue

          try {
            const parsed: StreamChunk = JSON.parse(jsonStr)

            if (parsed.type === 'text_delta' && parsed.delta) {
              streamedContent.value += parsed.delta
              onChunk(parsed.delta)
            } else if (parsed.type === 'stream_end') {
              onDone?.()
            } else if (parsed.type === 'error') {
              throw new Error(parsed.error ?? 'Stream error')
            }
          } catch (parseError) {
            if (parseError instanceof Error && parseError.message.includes('Stream error')) {
              throw parseError
            }
          }
        }
      }
    } finally {
      isStreaming.value = false
    }
  }

  function reset() {
    streamedContent.value = ''
  }

  return { isStreaming, streamedContent, stream, reset }
}
