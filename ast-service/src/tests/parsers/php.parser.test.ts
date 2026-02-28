import { parseCode } from '../../parsers'

describe('PHP parser', () => {
  describe('function declarations', () => {
    it('parses a top-level function', () => {
      const code = `<?php\nfunction greet($name) {\n    return "Hello, " . $name;\n}`
      const chunks = parseCode(code, 'php', 'src/greet.php')
      const fn = chunks.find(c => c.name === 'greet')
      expect(fn).toBeDefined()
      expect(fn?.type).toBe('function')
    })
  })

  describe('class declarations', () => {
    it('parses a class with a method', () => {
      const code = `
<?php
class Animal {
    protected string $name;

    public function __construct(string $name) {
        $this->name = $name;
    }

    public function speak(): string {
        return "{$this->name} makes a sound";
    }
}
`
      const chunks = parseCode(code, 'php', 'src/Animal.php')
      const cls = chunks.find(c => c.name === 'Animal')
      expect(cls).toBeDefined()
      expect(cls?.type).toBe('class')
    })

    it('parses class methods', () => {
      const code = `
<?php
class Calculator {
    public function add(int $a, int $b): int {
        return $a + $b;
    }
}
`
      const chunks = parseCode(code, 'php', 'src/Calculator.php')
      const method = chunks.find(c => c.name === 'add')
      expect(method).toBeDefined()
    })
  })

  describe('edge cases', () => {
    it('handles an empty file', () => {
      const chunks = parseCode('', 'php', 'src/empty.php')
      expect(Array.isArray(chunks)).toBe(true)
    })

    it('handles malformed PHP without throwing', () => {
      const code = `<?php function broken( {`
      expect(() => parseCode(code, 'php', 'src/broken.php')).not.toThrow()
    })

    it('sets filePath on all chunks', () => {
      const code = `<?php\nfunction a() {}\nfunction b() {}`
      const chunks = parseCode(code, 'php', 'src/funcs.php')
      chunks.forEach(c => expect(c.filePath).toBe('src/funcs.php'))
    })
  })
})
