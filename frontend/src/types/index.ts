export type IndexingStatus = 'pending' | 'in_progress' | 'completed' | 'failed'

export interface Repository {
  id: number
  name: string
  git_url: string
  branch: string
  indexing_status: IndexingStatus
  total_files: number | null
  indexed_files: number | null
  total_chunks: number | null
  last_indexed_commit: string | null
  created_at: string
  updated_at: string
}

export interface RepositoryStatus {
  repository_id: number
  status: IndexingStatus
  progress: number
  total_files: number | null
  indexed_files: number | null
  total_chunks: number | null
  started_at: string | null
  completed_at: string | null
  error: string | null
}

export interface CreateRepositoryPayload {
  name: string
  git_url: string
  branch?: string
  git_token?: string
}

export interface Conversation {
  id: number
  repository_id: number
  title: string | null
  created_at: string
  updated_at: string
  messages?: Message[]
}

export type MessageRole = 'user' | 'assistant'

export interface Message {
  id: number
  conversation_id: number
  role: MessageRole
  content: string
  metadata: Record<string, unknown> | null
  created_at: string
}

export interface CodeSource {
  file: string
  lines: string
  relevance: number
}

export interface ChatResponse {
  conversation_id: number
  message: string
  sources: CodeSource[]
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    total: number
    per_page?: number
    last_page?: number
  }
}
