export interface ParseRequest {
  filePath: string
  content: string
  language: string
}

export interface CodeChunk {
  type: string
  name: string
  content: string
  startLine: number
  endLine: number
  language: string
  signature: string
  docstring: string
}

export interface ParseResult {
  success: boolean
  filePath: string
  language: string
  chunkCount: number
  chunks: CodeChunk[]
  error?: string
}

export interface BatchParseRequest {
  files: ParseRequest[]
}

export interface BatchParseResult {
  success: boolean
  results: ParseResult[]
  totalFiles: number
  successCount: number
}
