import { Router, Request, Response } from 'express'
import type { ParseRequest, ParseResult, BatchParseRequest, BatchParseResult } from '../types'

const router = Router()

router.post('/parse', (req: Request, res: Response) => {
  const { filePath, content, language }: ParseRequest = req.body

  if (!filePath || !content || !language) {
    res.status(400).json({ success: false, error: 'filePath, content, and language are required' })
    return
  }

  // TODO: Replace stub with real Tree-sitter parsing in MVP
  const result: ParseResult = {
    success: true,
    filePath,
    language,
    chunkCount: 0,
    chunks: [],
  }

  res.json(result)
})

router.post('/parse-batch', (req: Request, res: Response) => {
  const { files }: BatchParseRequest = req.body

  if (!Array.isArray(files) || files.length === 0) {
    res.status(400).json({ success: false, error: 'files array is required and must not be empty' })
    return
  }

  // TODO: Replace stub with real Tree-sitter parsing in MVP
  const results: ParseResult[] = files.map((file) => ({
    success: true,
    filePath: file.filePath,
    language: file.language,
    chunkCount: 0,
    chunks: [],
  }))

  const response: BatchParseResult = {
    success: true,
    results,
    totalFiles: files.length,
    successCount: files.length,
  }

  res.json(response)
})

export default router
