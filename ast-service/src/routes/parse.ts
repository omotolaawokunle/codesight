import { Router, Request, Response } from 'express'
import type {
  ParseRequest,
  ParseResult,
  BatchParseRequest,
  BatchParseResult,
  ExtractSymbolsRequest,
  ExtractSymbolsResponse,
} from '../types'
import { parseCode, extractSymbols, detectLanguageFromPath, PUBLIC_SUPPORTED_LANGUAGES } from '../parsers'

const router = Router()

const MAX_FILE_SIZE_BYTES = 1_048_576   // 1 MB
const MAX_BATCH_SIZE = 50

/**
 * Validate that a parse request body is well-formed.
 * Returns an error message string, or null if valid.
 */
function validateParseRequest(body: Partial<ParseRequest>): string | null {
  if (!body.filePath) return 'filePath is required'
  if (!body.content && body.content !== '') return 'content is required'
  if (!body.language) return 'language is required'

  const byteLength = Buffer.byteLength(body.content, 'utf8')
  if (byteLength > MAX_FILE_SIZE_BYTES) {
    return `content exceeds maximum allowed size of 1MB (got ${byteLength} bytes)`
  }
  return null
}

/**
 * Validate that the file content is valid UTF-8.
 * Attempts to encode/decode; gracefully handles issues instead of crashing.
 */
function sanitizeContent(content: string): string {
  // Replace any invalid UTF-16 surrogate pairs that might have slipped through JSON
  return content.replace(/[\uD800-\uDBFF](?![\uDC00-\uDFFF])|(?<![\uD800-\uDBFF])[\uDC00-\uDFFF]/g, '\uFFFD')
}

// ─── POST /api/ast/parse ─────────────────────────────────────────────────────

router.post('/parse', (req: Request, res: Response) => {
  const body = req.body as Partial<ParseRequest>

  const validationError = validateParseRequest(body)
  if (validationError) {
    res.status(400).json({ success: false, error: validationError })
    return
  }

  const { filePath, content, language } = body as ParseRequest

  // Auto-detect language from path if needed, but trust what was sent
  const resolvedLanguage = language || detectLanguageFromPath(filePath)
  const safeContent = sanitizeContent(content)

  try {
    const chunks = parseCode(safeContent, resolvedLanguage, filePath)
    const result: ParseResult = {
      success: true,
      filePath,
      language: resolvedLanguage,
      chunkCount: chunks.length,
      chunks,
    }
    res.json(result)
  } catch (err) {
    console.error('[/parse] unexpected error:', err)
    const result: ParseResult = {
      success: true,
      filePath,
      language: resolvedLanguage,
      chunkCount: 0,
      chunks: [],
      error: err instanceof Error ? err.message : 'Parse failed',
    }
    res.json(result)
  }
})

// ─── POST /api/ast/parse-batch ───────────────────────────────────────────────

router.post('/parse-batch', (req: Request, res: Response) => {
  const { files } = req.body as Partial<BatchParseRequest>

  if (!Array.isArray(files) || files.length === 0) {
    res.status(400).json({ success: false, error: 'files must be a non-empty array' })
    return
  }

  if (files.length > MAX_BATCH_SIZE) {
    res.status(400).json({
      success: false,
      error: `Batch size ${files.length} exceeds maximum of ${MAX_BATCH_SIZE} files`,
    })
    return
  }

  const results: ParseResult[] = files.map(file => {
    const validationError = validateParseRequest(file)
    if (validationError) {
      return {
        success: false,
        filePath: file.filePath ?? 'unknown',
        language: file.language ?? 'unknown',
        chunkCount: 0,
        chunks: [],
        error: validationError,
      } satisfies ParseResult
    }

    const resolvedLanguage = file.language || detectLanguageFromPath(file.filePath)
    const safeContent = sanitizeContent(file.content)

    try {
      const chunks = parseCode(safeContent, resolvedLanguage, file.filePath)
      return {
        success: true,
        filePath: file.filePath,
        language: resolvedLanguage,
        chunkCount: chunks.length,
        chunks,
      } satisfies ParseResult
    } catch (err) {
      console.error(`[/parse-batch] error parsing ${file.filePath}:`, err)
      return {
        success: true,
        filePath: file.filePath,
        language: resolvedLanguage,
        chunkCount: 0,
        chunks: [],
        error: err instanceof Error ? err.message : 'Parse failed',
      } satisfies ParseResult
    }
  })

  const response: BatchParseResult = {
    success: true,
    results,
    totalFiles: files.length,
    successCount: results.filter(r => r.success).length,
  }

  res.json(response)
})

// ─── POST /api/ast/extract-symbols ───────────────────────────────────────────

router.post('/extract-symbols', (req: Request, res: Response) => {
  const body = req.body as Partial<ExtractSymbolsRequest>

  const validationError = validateParseRequest(body)
  if (validationError) {
    res.status(400).json({ success: false, error: validationError })
    return
  }

  const { filePath, content, language } = body as ExtractSymbolsRequest
  const resolvedLanguage = language || detectLanguageFromPath(filePath)
  const safeContent = sanitizeContent(content)

  try {
    const symbols = extractSymbols(safeContent, resolvedLanguage)
    const response: ExtractSymbolsResponse = {
      success: true,
      filePath,
      language: resolvedLanguage,
      symbols,
    }
    res.json(response)
  } catch (err) {
    console.error('[/extract-symbols] unexpected error:', err)
    const response: ExtractSymbolsResponse = {
      success: false,
      filePath,
      language: resolvedLanguage,
      symbols: [],
      error: err instanceof Error ? err.message : 'Extraction failed',
    }
    res.json(response)
  }
})

// ─── GET /api/ast/supported-languages ────────────────────────────────────────

router.get('/supported-languages', (_req: Request, res: Response) => {
  res.json({ languages: PUBLIC_SUPPORTED_LANGUAGES })
})

export default router
