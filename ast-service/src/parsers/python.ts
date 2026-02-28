import Parser from 'tree-sitter'
// eslint-disable-next-line @typescript-eslint/no-require-imports
const Python = require('tree-sitter-python')

import type { CodeChunk, SymbolInfo } from '../types'

type ChunkType = CodeChunk['type']

const parser = new Parser()
parser.setLanguage(Python as Parser.Language)

/**
 * Extract the docstring from the first statement of a function/class body, if it is a string.
 */
function extractDocstring(bodyNode: Parser.SyntaxNode): string | undefined {
  // Python docstrings are the first expression_statement containing a string
  const firstStmt = bodyNode.namedChildren[0]
  if (!firstStmt) return undefined

  if (firstStmt.type === 'expression_statement') {
    const expr = firstStmt.namedChildren[0]
    if (expr?.type === 'string') {
      return expr.text
    }
  }
  return undefined
}

/**
 * Extract decorators that appear immediately before a function or class definition.
 */
function extractDecorators(node: Parser.SyntaxNode): string | undefined {
  const decoratorLines: string[] = []
  let sibling = node.previousNamedSibling
  // Collect consecutive decorator nodes
  while (sibling?.type === 'decorator') {
    decoratorLines.unshift(sibling.text)
    sibling = sibling.previousNamedSibling
  }
  return decoratorLines.length > 0 ? decoratorLines.join('\n') : undefined
}

/**
 * Build the signature: decorators + def/class line (up to the colon).
 */
function buildSignature(node: Parser.SyntaxNode, content: string): string {
  const lines = content.split('\n')
  const startRow = node.startPosition.row
  const line = lines[startRow] ?? ''
  const decorators = extractDecorators(node)

  // Grab the def/class header up to the first colon
  const colonIdx = line.indexOf(':')
  const header = colonIdx !== -1 ? line.slice(0, colonIdx + 1).trim() : line.trim()
  return decorators ? `${decorators}\n${header}` : header
}

function nodeToChunkType(nodeType: string): ChunkType {
  if (nodeType === 'class_definition') return 'class'
  return 'function'
}

/**
 * Collect functions and classes, descending into class bodies for methods.
 */
function collectChunks(
  node: Parser.SyntaxNode,
  sourceCode: string,
  filePath: string | undefined,
  chunks: CodeChunk[],
  insideClass = false,
): void {
  const isFunctionDef = node.type === 'function_definition' || node.type === 'async_function_definition'
  const isClassDef = node.type === 'class_definition'

  if (isFunctionDef || isClassDef) {
    const nameNode = node.childForFieldName('name')
    if (nameNode) {
      const startLine = node.startPosition.row + 1
      const endLine = node.endPosition.row + 1
      const signature = buildSignature(node, sourceCode)

      const bodyNode = node.childForFieldName('body')
      const docstring = bodyNode ? extractDocstring(bodyNode) : undefined

      const chunkType: ChunkType = isClassDef ? 'class' : insideClass ? 'method' : 'function'

      chunks.push({
        type: chunkType,
        name: nameNode.text,
        content: node.text,
        startLine,
        endLine,
        language: 'python',
        signature,
        ...(docstring ? { docstring } : {}),
        ...(filePath ? { filePath } : {}),
      })

      // Descend into class body to extract methods
      if (isClassDef && bodyNode) {
        for (const child of bodyNode.namedChildren) {
          collectChunks(child, sourceCode, filePath, chunks, true)
        }
        return
      }
    }
  }

  // Walk top-level nodes and decorated definitions
  if (!isFunctionDef && !isClassDef) {
    for (const child of node.namedChildren) {
      collectChunks(child, sourceCode, filePath, chunks, insideClass)
    }
  }
}

export function parsePython(content: string, filePath?: string): CodeChunk[] {
  try {
    const tree = parser.parse(content)
    const chunks: CodeChunk[] = []
    collectChunks(tree.rootNode, content, filePath, chunks)
    return chunks
  } catch (err) {
    console.error(`[python parser] error parsing ${filePath ?? 'unknown'}:`, err)
    return []
  }
}

export function extractPythonSymbols(content: string): SymbolInfo[] {
  const chunks = parsePython(content)
  return chunks.map(c => ({
    name: c.name,
    type: c.type,
    startLine: c.startLine,
    endLine: c.endLine,
  }))
}
