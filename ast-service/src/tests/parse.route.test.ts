import request from 'supertest'
import app from '../index'

describe('POST /api/ast/parse', () => {
  describe('valid requests', () => {
    it('parses a JavaScript file and returns chunks', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({
          filePath: 'src/utils.js',
          content: `function add(a, b) { return a + b }\nfunction subtract(a, b) { return a - b }`,
          language: 'javascript',
        })

      expect(res.status).toBe(200)
      expect(res.body.success).toBe(true)
      expect(res.body.filePath).toBe('src/utils.js')
      expect(Array.isArray(res.body.chunks)).toBe(true)
      expect(res.body.chunkCount).toBe(res.body.chunks.length)
    })

    it('parses a Python file and returns chunks', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({
          filePath: 'src/utils.py',
          content: `def add(a, b):\n    return a + b\n\ndef subtract(a, b):\n    return a - b`,
          language: 'python',
        })

      expect(res.status).toBe(200)
      expect(res.body.success).toBe(true)
      expect(res.body.chunks.length).toBeGreaterThan(0)
    })

    it('parses a PHP file and returns chunks', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({
          filePath: 'src/Utils.php',
          content: `<?php\nfunction add($a, $b) { return $a + $b; }`,
          language: 'php',
        })

      expect(res.status).toBe(200)
      expect(res.body.success).toBe(true)
    })

    it('handles an empty file gracefully', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({
          filePath: 'src/empty.js',
          content: '',
          language: 'javascript',
        })

      expect(res.status).toBe(200)
      expect(res.body.success).toBe(true)
    })

    it('falls back to chunk mode for unsupported language', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({
          filePath: 'src/script.rb',
          content: `def hello\n  puts "Hello"\nend`,
          language: 'ruby',
        })

      expect(res.status).toBe(200)
      expect(res.body.success).toBe(true)
    })

    it('handles a large file without crashing (>1000 lines)', async () => {
      const lines = Array.from({ length: 1001 }, (_, i) => `// line ${i + 1}`)
      const content = lines.join('\n')

      const res = await request(app)
        .post('/api/ast/parse')
        .send({
          filePath: 'src/large.js',
          content,
          language: 'javascript',
        })

      expect(res.status).toBe(200)
      expect(res.body.success).toBe(true)
    })
  })

  describe('validation errors', () => {
    it('returns 400 when filePath is missing', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({ content: 'code', language: 'javascript' })

      expect(res.status).toBe(400)
      expect(res.body.success).toBe(false)
      expect(res.body.error).toMatch(/filePath/i)
    })

    it('returns 400 when content is missing', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({ filePath: 'src/file.js', language: 'javascript' })

      expect(res.status).toBe(400)
      expect(res.body.success).toBe(false)
      expect(res.body.error).toMatch(/content/i)
    })

    it('returns 400 when language is missing', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({ filePath: 'src/file.js', content: 'code' })

      expect(res.status).toBe(400)
      expect(res.body.success).toBe(false)
      expect(res.body.error).toMatch(/language/i)
    })

    it('returns 400 when content exceeds 1MB', async () => {
      const bigContent = 'x'.repeat(1_048_577) // 1MB + 1 byte

      const res = await request(app)
        .post('/api/ast/parse')
        .send({ filePath: 'src/huge.js', content: bigContent, language: 'javascript' })

      expect(res.status).toBe(400)
      expect(res.body.success).toBe(false)
    })

    it('returns 400 when body is empty', async () => {
      const res = await request(app)
        .post('/api/ast/parse')
        .send({})

      expect(res.status).toBe(400)
      expect(res.body.success).toBe(false)
    })
  })
})

describe('GET /health', () => {
  it('returns healthy status', async () => {
    const res = await request(app).get('/health')

    expect(res.status).toBe(200)
    expect(res.body.status).toBe('healthy')
    expect(res.body.service).toBe('ast-service')
  })
})
