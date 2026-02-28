import type { CodeChunk, SymbolInfo } from '../types'
import { parseJavaScript, extractJavaScriptSymbols } from './javascript'
import { parsePython, extractPythonSymbols } from './python'
import { parsePhp, extractPhpSymbols } from './php'
import { fallbackParse } from './fallback'

export const SUPPORTED_LANGUAGES = ['javascript', 'typescript', 'tsx', 'python', 'php'] as const
export type SupportedLanguage = (typeof SUPPORTED_LANGUAGES)[number]

/**
 * Publicly exposed list for the /supported-languages endpoint.
 * tsx is an internal variant of typescript, so we expose four user-facing names.
 */
export const PUBLIC_SUPPORTED_LANGUAGES = ['javascript', 'typescript', 'python', 'php']

const EXTENSION_MAP: Record<string, SupportedLanguage> = {
  '.js': 'javascript',
  '.jsx': 'javascript',
  '.ts': 'typescript',
  '.tsx': 'tsx',
  '.py': 'python',
  '.php': 'php',
}

/**
 * Detect the programming language from a file path by extension.
 * Returns the detected language or 'unknown' if not supported.
 */
export function detectLanguageFromPath(filePath: string): string {
  const ext = filePath.slice(filePath.lastIndexOf('.')).toLowerCase()
  return EXTENSION_MAP[ext] ?? 'unknown'
}

/**
 * Check whether a language string is one we have a Tree-sitter parser for.
 */
export function isSupported(language: string): boolean {
  return SUPPORTED_LANGUAGES.includes(language as SupportedLanguage)
}

/**
 * Parse source code into structured code chunks.
 * Falls back to block-based chunking for unsupported languages or parse errors.
 */
export function parseCode(content: string, language: string, filePath?: string): CodeChunk[] {
  const lang = language.toLowerCase()

  if (lang === 'javascript' || lang === 'tsx') {
    const chunks = parseJavaScript(content, lang, filePath)
    return chunks.length > 0 ? chunks : fallbackParse(content, filePath, lang)
  }

  if (lang === 'typescript') {
    const chunks = parseJavaScript(content, 'typescript', filePath)
    return chunks.length > 0 ? chunks : fallbackParse(content, filePath, lang)
  }

  if (lang === 'python') {
    const chunks = parsePython(content, filePath)
    return chunks.length > 0 ? chunks : fallbackParse(content, filePath, lang)
  }

  if (lang === 'php') {
    const chunks = parsePhp(content, filePath)
    return chunks.length > 0 ? chunks : fallbackParse(content, filePath, lang)
  }

  // Unsupported language — use fallback chunking
  return fallbackParse(content, filePath, language)
}

/**
 * Extract only symbol metadata (name, type, line range) without full content.
 */
export function extractSymbols(content: string, language: string): SymbolInfo[] {
  const lang = language.toLowerCase()

  if (lang === 'javascript' || lang === 'typescript' || lang === 'tsx') {
    return extractJavaScriptSymbols(content, lang)
  }

  if (lang === 'python') {
    return extractPythonSymbols(content)
  }

  if (lang === 'php') {
    return extractPhpSymbols(content)
  }

  // Unsupported: derive symbols from fallback blocks
  return fallbackParse(content, undefined, language).map(c => ({
    name: c.name,
    type: c.type,
    startLine: c.startLine,
    endLine: c.endLine,
  }))
}
