import { ref } from 'vue'

export function useStreaming() {
  const isStreaming = ref(false)
  const streamedContent = ref('')

  async function stream(
    url: string,
    body: Record<string, unknown>,
    onChunk: (chunk: string) => void,
  ): Promise<void> {
    // TODO: Implement SSE streaming in MVP
    // Uses EventSource or fetch with ReadableStream to consume text/event-stream
    isStreaming.value = true
    streamedContent.value = ''
    try {
      const token = localStorage.getItem('auth_token')
      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'text/event-stream',
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: JSON.stringify(body),
      })

      if (!response.body) throw new Error('No response body')

      const reader = response.body.getReader()
      const decoder = new TextDecoder()

      while (true) {
        const { done, value } = await reader.read()
        if (done) break
        const chunk = decoder.decode(value, { stream: true })
        streamedContent.value += chunk
        onChunk(chunk)
      }
    } finally {
      isStreaming.value = false
    }
  }

  return { isStreaming, streamedContent, stream }
}
