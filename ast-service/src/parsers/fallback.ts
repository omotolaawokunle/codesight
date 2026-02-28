import type { CodeChunk } from '../types'

/**
 * Fallback chunker for unsupported languages or when Tree-sitter fails.
 * Splits the file into blocks by double newlines, preserving formatting.
 * Returns a single block for the entire file if it cannot be meaningfully split.
 */
export function fallbackParse(content: string, filePath?: string, language = 'unknown'): CodeChunk[] {
  if (!content.trim()) {
    return []
  }

  const lines = content.split('\n')
  const totalLines = lines.length

  // Try splitting by double newlines to get logical blocks
  const rawBlocks = content.split(/\n\s*\n/)
  const blocks: CodeChunk[] = []
  let currentLine = 1

  for (const block of rawBlocks) {
    const trimmed = block.trim()
    if (!trimmed) {
      currentLine += block.split('\n').length
      continue
    }

    const blockLines = block.split('\n')
    const startLine = currentLine
    const endLine = currentLine + blockLines.length - 1

    blocks.push({
      type: 'block',
      name: `block_${startLine}`,
      content: trimmed,
      startLine,
      endLine,
      language,
      ...(filePath ? { filePath } : {}),
    })

    currentLine = endLine + 1
  }

  // If splitting produced only one block or none, return the whole file as one block
  if (blocks.length === 0) {
    return [
      {
        type: 'block',
        name: 'file_content',
        content,
        startLine: 1,
        endLine: totalLines,
        language,
        ...(filePath ? { filePath } : {}),
      },
    ]
  }

  return blocks
}
