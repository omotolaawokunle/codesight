export interface ParseRequest {
  filePath: string
  content: string
  language: string
}

export interface CodeChunk {
  type: 'function' | 'class' | 'method' | 'interface' | 'block'
  name: string
  content: string
  startLine: number
  endLine: number
  language: string
  signature?: string
  docstring?: string
  filePath?: string
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

export type ExtractSymbolsRequest = ParseRequest

export interface SymbolInfo {
  name: string
  type: string
  startLine: number
  endLine?: number
}

export interface ExtractSymbolsResponse {
  success: boolean
  filePath: string
  language: string
  symbols: SymbolInfo[]
  error?: string
}
