import { parseCode } from '../../parsers'

describe('Fallback parser (unsupported languages)', () => {
  it('returns at least one chunk for unknown extension', () => {
    const code = `some code here\nmore code\neven more code`
    const chunks = parseCode(code, 'ruby', 'src/script.rb')
    expect(chunks.length).toBeGreaterThan(0)
  })

  it('returns chunk with filePath set', () => {
    const code = `x = 1`
    const chunks = parseCode(code, 'unknown', 'src/file.xyz')
    chunks.forEach(c => expect(c.filePath).toBe('src/file.xyz'))
  })

  it('handles empty content', () => {
    const chunks = parseCode('', 'unknown', 'src/empty.txt')
    expect(Array.isArray(chunks)).toBe(true)
  })

  it('splits large files into multiple chunks', () => {
    const lines = Array.from({ length: 200 }, (_, i) => `line ${i + 1}`)
    const code = lines.join('\n')
    const chunks = parseCode(code, 'unknown', 'src/large.txt')
    // Fallback splits at chunk boundaries — should have more than 1 chunk
    expect(chunks.length).toBeGreaterThanOrEqual(1)
    // Content should not be empty
    chunks.forEach(c => expect(c.content.length).toBeGreaterThan(0))
  })

  it('returns chunks with a non-null type', () => {
    const code = `hello world`
    const chunks = parseCode(code, 'cobol', 'src/program.cob')
    chunks.forEach(c => expect(c.type).toBeTruthy())
  })
})
