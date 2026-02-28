import Parser from 'tree-sitter'
// The tree-sitter-php grammar includes full PHP (with opening tags).
// eslint-disable-next-line @typescript-eslint/no-require-imports
const { php } = require('tree-sitter-php')

import type { CodeChunk, SymbolInfo } from '../types'

type ChunkType = CodeChunk['type']

const parser = new Parser()
parser.setLanguage(php as Parser.Language)

/**
 * Extract a PHPDoc comment (/** ... *\/) preceding the node.
 */
function extractPhpDoc(node: Parser.SyntaxNode, sourceCode: string): string | undefined {
  let sibling = node.previousSibling
  while (sibling) {
    if (sibling.type === 'comment') {
      const text = sibling.text.trim()
      if (text.startsWith('/**')) return text
      break
    }
    // Skip whitespace/newline text nodes
    if (sibling.isNamed) break
    sibling = sibling.previousSibling
  }

  // Fallback: search backwards in source text
  const nodeStart = node.startIndex
  const preceding = sourceCode.slice(Math.max(0, nodeStart - 600), nodeStart)
  const match = preceding.match(/\/\*\*[\s\S]*?\*\/\s*$/)
  return match?.[0]?.trim()
}

/**
 * Extract PHP 8 attributes (e.g. #[Route('/path')]) preceding the node.
 */
function extractAttributes(node: Parser.SyntaxNode): string | undefined {
  const attrs: string[] = []
  let sibling = node.previousNamedSibling
  while (sibling?.type === 'attribute_list') {
    attrs.unshift(sibling.text)
    sibling = sibling.previousNamedSibling
  }
  return attrs.length > 0 ? attrs.join('\n') : undefined
}

/**
 * Build the function/method signature: attributes + first line up to opening brace or semicolon.
 */
function buildSignature(node: Parser.SyntaxNode, content: string): string {
  const lines = content.split('\n')
  const startRow = node.startPosition.row
  const line = lines[startRow]?.trim() ?? ''
  const attrs = extractAttributes(node)

  // Strip the body brace if on the same line
  const braceIdx = line.indexOf('{')
  const header = braceIdx !== -1 ? line.slice(0, braceIdx).trim() : line

  return attrs ? `${attrs}\n${header}` : header
}

function nodeToChunkType(nodeType: string, insideClass: boolean): ChunkType {
  if (nodeType === 'class_declaration' || nodeType === 'enum_declaration') return 'class'
  if (insideClass) return 'method'
  return 'function'
}

/**
 * Collect PHP functions, classes, and methods from the AST.
 */
function collectChunks(
  node: Parser.SyntaxNode,
  sourceCode: string,
  filePath: string | undefined,
  chunks: CodeChunk[],
  insideClass = false,
): void {
  const isFunctionDef = node.type === 'function_definition'
  const isMethodDef = node.type === 'method_declaration'
  const isClassDef = node.type === 'class_declaration' || node.type === 'enum_declaration'

  if (isFunctionDef || isMethodDef || isClassDef) {
    const nameNode = node.childForFieldName('name')
    if (nameNode) {
      const startLine = node.startPosition.row + 1
      const endLine = node.endPosition.row + 1
      const signature = buildSignature(node, sourceCode)
      const docstring = extractPhpDoc(node, sourceCode)
      const chunkType = nodeToChunkType(node.type, insideClass)

      chunks.push({
        type: chunkType,
        name: nameNode.text,
        content: node.text,
        startLine,
        endLine,
        language: 'php',
        signature,
        ...(docstring ? { docstring } : {}),
        ...(filePath ? { filePath } : {}),
      })

      // Descend into class bodies to extract methods
      if (isClassDef) {
        const body = node.childForFieldName('body')
        if (body) {
          for (const child of body.namedChildren) {
            collectChunks(child, sourceCode, filePath, chunks, true)
          }
        }
        return
      }
    }
  }

  if (!isFunctionDef && !isMethodDef && !isClassDef) {
    for (const child of node.namedChildren) {
      collectChunks(child, sourceCode, filePath, chunks, insideClass)
    }
  }
}

export function parsePhp(content: string, filePath?: string): CodeChunk[] {
  try {
    const tree = parser.parse(content)
    const chunks: CodeChunk[] = []
    collectChunks(tree.rootNode, content, filePath, chunks)
    return chunks
  } catch (err) {
    console.error(`[php parser] error parsing ${filePath ?? 'unknown'}:`, err)
    return []
  }
}

export function extractPhpSymbols(content: string): SymbolInfo[] {
  const chunks = parsePhp(content)
  return chunks.map(c => ({
    name: c.name,
    type: c.type,
    startLine: c.startLine,
    endLine: c.endLine,
  }))
}
