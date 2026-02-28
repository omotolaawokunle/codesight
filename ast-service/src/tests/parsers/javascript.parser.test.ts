import { parseCode } from '../../parsers'

describe('JavaScript / TypeScript parser', () => {
  describe('function declarations', () => {
    it('parses a function declaration', () => {
      const code = `
function greet(name) {
  return 'Hello, ' + name
}
`
      const chunks = parseCode(code, 'javascript', 'src/greet.js')
      expect(chunks.length).toBeGreaterThan(0)
      const fn = chunks.find(c => c.name === 'greet')
      expect(fn).toBeDefined()
      expect(fn?.type).toBe('function')
    })

    it('parses an arrow function assigned to a const', () => {
      const code = `const add = (a, b) => a + b`
      const chunks = parseCode(code, 'javascript', 'src/math.js')
      const fn = chunks.find(c => c.name === 'add')
      expect(fn).toBeDefined()
    })

    it('parses an async function', () => {
      const code = `
async function fetchData(url) {
  const res = await fetch(url)
  return res.json()
}
`
      const chunks = parseCode(code, 'javascript', 'src/api.js')
      const fn = chunks.find(c => c.name === 'fetchData')
      expect(fn).toBeDefined()
    })
  })

  describe('class declarations', () => {
    it('parses a class declaration', () => {
      const code = `
class Animal {
  constructor(name) {
    this.name = name
  }
  speak() {
    return \`\${this.name} makes a sound\`
  }
}
`
      const chunks = parseCode(code, 'javascript', 'src/Animal.js')
      const cls = chunks.find(c => c.name === 'Animal')
      expect(cls).toBeDefined()
      expect(cls?.type).toBe('class')
    })
  })

  describe('TypeScript', () => {
    it('parses a TypeScript interface', () => {
      const code = `
interface User {
  id: number
  name: string
  email: string
}
`
      const chunks = parseCode(code, 'typescript', 'src/types.ts')
      const iface = chunks.find(c => c.name === 'User')
      expect(iface).toBeDefined()
      expect(iface?.type).toBe('interface')
    })

    it('parses a TypeScript function with types', () => {
      const code = `
function greet(name: string): string {
  return \`Hello, \${name}\`
}
`
      const chunks = parseCode(code, 'typescript', 'src/greet.ts')
      expect(chunks.some(c => c.name === 'greet')).toBe(true)
    })
  })

  describe('edge cases', () => {
    it('handles an empty file', () => {
      const chunks = parseCode('', 'javascript', 'src/empty.js')
      expect(Array.isArray(chunks)).toBe(true)
    })

    it('handles malformed syntax without throwing', () => {
      const code = `function broken( { return }`
      expect(() => parseCode(code, 'javascript', 'src/broken.js')).not.toThrow()
    })

    it('sets filePath on all chunks', () => {
      const code = `
function a() {}
function b() {}
`
      const chunks = parseCode(code, 'javascript', 'src/funcs.js')
      chunks.forEach(c => expect(c.filePath).toBe('src/funcs.js'))
    })

    it('sets correct start and end lines', () => {
      const code = `function hello() {\n  return 'hi'\n}`
      const chunks = parseCode(code, 'javascript', 'src/hello.js')
      const fn = chunks.find(c => c.name === 'hello')
      expect(fn?.startLine).toBeDefined()
      expect(fn?.endLine).toBeDefined()
      expect(fn!.endLine).toBeGreaterThanOrEqual(fn!.startLine)
    })
  })
})
