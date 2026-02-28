import { parseCode } from '../../parsers'

describe('Python parser', () => {
  describe('function definitions', () => {
    it('parses a simple function def', () => {
      const code = `
def greet(name):
    return f"Hello, {name}"
`
      const chunks = parseCode(code, 'python', 'src/greet.py')
      const fn = chunks.find(c => c.name === 'greet')
      expect(fn).toBeDefined()
      expect(fn?.type).toBe('function')
    })

    it('parses an async def function', () => {
      const code = `
async def fetch_data(url):
    import httpx
    async with httpx.AsyncClient() as client:
        return await client.get(url)
`
      const chunks = parseCode(code, 'python', 'src/api.py')
      const fn = chunks.find(c => c.name === 'fetch_data')
      expect(fn).toBeDefined()
    })

    it('parses a function with decorators', () => {
      const code = `
@staticmethod
def compute(x, y):
    return x + y
`
      const chunks = parseCode(code, 'python', 'src/math.py')
      expect(chunks.length).toBeGreaterThan(0)
    })
  })

  describe('class definitions', () => {
    it('parses a class with methods', () => {
      const code = `
class Animal:
    def __init__(self, name):
        self.name = name

    def speak(self):
        return f"{self.name} makes a sound"
`
      const chunks = parseCode(code, 'python', 'src/Animal.py')
      const cls = chunks.find(c => c.name === 'Animal')
      expect(cls).toBeDefined()
      expect(cls?.type).toBe('class')
    })
  })

  describe('edge cases', () => {
    it('handles an empty file', () => {
      const chunks = parseCode('', 'python', 'src/empty.py')
      expect(Array.isArray(chunks)).toBe(true)
    })

    it('handles malformed python gracefully', () => {
      const code = `def broken(`
      expect(() => parseCode(code, 'python', 'src/broken.py')).not.toThrow()
    })

    it('sets filePath on all chunks', () => {
      const code = `
def a():
    pass

def b():
    pass
`
      const chunks = parseCode(code, 'python', 'src/funcs.py')
      chunks.forEach(c => expect(c.filePath).toBe('src/funcs.py'))
    })
  })
})
