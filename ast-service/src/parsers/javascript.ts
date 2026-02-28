import Parser from 'tree-sitter'
// eslint-disable-next-line @typescript-eslint/no-require-imports
const { typescript, tsx } = require('tree-sitter-typescript')
// eslint-disable-next-line @typescript-eslint/no-require-imports
const JavaScript = require('tree-sitter-javascript')

import type { CodeChunk, SymbolInfo } from '../types'

type ChunkType = CodeChunk['type']

/**
 * Node types that represent top-level or class-level declarations we want to extract.
 */
const EXTRACTABLE_TYPES = new Set([
  'function_declaration',
  'function_expression',
  'generator_function_declaration',
  'arrow_function',
  'class_declaration',
  'class_expression',
  'method_definition',
  'lexical_declaration',  // const/let with arrow functions
  'variable_declaration', // var with arrow functions
  // TypeScript-specific
  'interface_declaration',
  'type_alias_declaration',
  'abstract_class_declaration',
])

function mapNodeTypeToChunkType(nodeType: string): ChunkType {
  if (nodeType.includes('class')) return 'class'
  if (nodeType === 'method_definition') return 'method'
  if (nodeType === 'interface_declaration') return 'interface'
  return 'function'
}

/**
 * Extract the name of a declaration node.
 */
function getNodeName(node: Parser.SyntaxNode): string | null {
  // Named declarations have a 'name' field
  const nameNode = node.childForFieldName('name')
  if (nameNode) return nameNode.text

  // const/let/var with a single declarator
  if (node.type === 'lexical_declaration' || node.type === 'variable_declaration') {
    const declarator = node.childForFieldName('declarator') ?? node.namedChildren.find(c => c.type === 'variable_declarator')
    if (declarator) {
      const varName = declarator.childForFieldName('name')
      if (varName) return varName.text
    }
  }

  return null
}

/**
 * Extract value node for variable declarators to check if it's an arrow function.
 */
function isArrowFunctionDeclarator(node: Parser.SyntaxNode): boolean {
  if (node.type !== 'lexical_declaration' && node.type !== 'variable_declaration') return false
  const declarator = node.namedChildren.find(c => c.type === 'variable_declarator')
  if (!declarator) return false
  const value = declarator.childForFieldName('value')
  return value?.type === 'arrow_function'
}

/**
 * Extract the signature (first line / header) of the node.
 */
function getSignature(node: Parser.SyntaxNode, content: string): string {
  const lines = content.split('\n')
  const startLine = node.startPosition.row

  // For functions/methods/classes, try to get the opening header up to the body
  const bodyNode = node.childForFieldName('body')
  if (bodyNode) {
    const bodyStart = bodyNode.startPosition.row
    if (bodyStart > startLine) {
      return lines.slice(startLine, bodyStart + 1).join('\n').split('{')[0].trim() + ' {'
    }
  }
  return lines[startLine]?.trim() ?? ''
}

/**
 * Extract JSDoc comment immediately preceding the node.
 */
function extractJSDoc(node: Parser.SyntaxNode, sourceCode: string): string | undefined {
  // Comments are siblings in the tree; walk backwards from the node's start
  let sibling = node.previousSibling
  while (sibling && (sibling.type === 'comment' || sibling.isExtra)) {
    if (sibling.type === 'comment' && sibling.text.startsWith('/**')) {
      return sibling.text
    }
    sibling = sibling.previousSibling
  }

  // Fallback: search the source text for a JSDoc block just before this node
  const nodeStart = node.startIndex
  const precedingText = sourceCode.slice(Math.max(0, nodeStart - 500), nodeStart)
  const jsdocMatch = precedingText.match(/\/\*\*[\s\S]*?\*\/\s*$/)
  return jsdocMatch?.[0]?.trim()
}

/**
 * Recursively walk the syntax tree and collect extractable chunks.
 * Descends into class bodies to find methods but does not double-extract.
 */
function collectChunks(
  node: Parser.SyntaxNode,
  sourceCode: string,
  language: string,
  filePath: string | undefined,
  chunks: CodeChunk[],
  depth = 0,
): void {
  const isExtractable = EXTRACTABLE_TYPES.has(node.type)

  // For variable declarations, only extract if they contain arrow functions
  if (
    (node.type === 'lexical_declaration' || node.type === 'variable_declaration') &&
    !isArrowFunctionDeclarator(node)
  ) {
    for (const child of node.namedChildren) {
      collectChunks(child, sourceCode, language, filePath, chunks, depth + 1)
    }
    return
  }

  if (isExtractable) {
    const name = getNodeName(node)
    if (name) {
      const startLine = node.startPosition.row + 1
      const endLine = node.endPosition.row + 1
      const signature = getSignature(node, sourceCode)
      const docstring = extractJSDoc(node, sourceCode)

      chunks.push({
        type: mapNodeTypeToChunkType(node.type),
        name,
        content: node.text,
        startLine,
        endLine,
        language,
        signature,
        ...(docstring ? { docstring } : {}),
        ...(filePath ? { filePath } : {}),
      })

      // Descend into class bodies to extract methods
      if (node.type === 'class_declaration' || node.type === 'class_expression' || node.type === 'abstract_class_declaration') {
        const body = node.childForFieldName('body')
        if (body) {
          for (const child of body.namedChildren) {
            collectChunks(child, sourceCode, language, filePath, chunks, depth + 1)
          }
        }
        return
      }
    }
  }

  // Continue traversal for top-level nodes (program, export statements, etc.)
  if (!isExtractable || depth === 0) {
    for (const child of node.namedChildren) {
      // Don't descend into function bodies looking for nested functions at depth 0+
      if (child.type === 'statement_block' || child.type === 'class_body') continue
      collectChunks(child, sourceCode, language, filePath, chunks, depth + 1)
    }
  }
}

function createParser(grammar: unknown): Parser {
  const parser = new Parser()
  parser.setLanguage(grammar as Parser.Language)
  return parser
}

const jsParser = createParser(JavaScript)
const tsParser = createParser(typescript)
const tsxParser = createParser(tsx)

function selectParser(language: string): Parser {
  if (language === 'typescript') return tsParser
  if (language === 'tsx') return tsxParser
  return jsParser
}

export function parseJavaScript(content: string, language: string, filePath?: string): CodeChunk[] {
  try {
    const parser = selectParser(language)
    const tree = parser.parse(content)
    const chunks: CodeChunk[] = []
    collectChunks(tree.rootNode, content, language, filePath, chunks)
    return chunks
  } catch (err) {
    console.error(`[javascript parser] error parsing ${filePath ?? 'unknown'}:`, err)
    return []
  }
}

export function extractJavaScriptSymbols(content: string, language: string): SymbolInfo[] {
  const chunks = parseJavaScript(content, language)
  return chunks.map(c => ({
    name: c.name,
    type: c.type,
    startLine: c.startLine,
    endLine: c.endLine,
  }))
}
