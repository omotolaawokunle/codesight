import { ref } from 'vue'

export interface StreamChunk {
  type: 'chunk' | 'done' | 'error' | 'sources'
  content?: string
  sources?: Array<{ file: string; lines: string; relevance: number }>
  error?: string
}

export function useStreaming() {
  const isStreaming = ref(false)
  const streamedContent = ref('')

  async function stream(
    url: string,
    body: Record<string, unknown>,
    onChunk: (chunk: string) => void,
    onDone?: (sources: StreamChunk['sources']) => void,
  ): Promise<void> {
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

            if (parsed.type === 'chunk' && parsed.content) {
              streamedContent.value += parsed.content
              onChunk(parsed.content)
            } else if (parsed.type === 'done') {
              onDone?.(parsed.sources)
            } else if (parsed.type === 'error') {
              throw new Error(parsed.error ?? 'Stream error')
            }
          } catch (parseError) {
            // Non-JSON data line — treat raw content as a chunk for compatibility
            if (jsonStr && jsonStr !== '[DONE]') {
              streamedContent.value += jsonStr
              onChunk(jsonStr)
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
