import express, { Request, Response, NextFunction } from 'express'
import cors from 'cors'
import parseRouter from './routes/parse'

const app = express()
const PORT = process.env.AST_SERVICE_PORT ?? 3001
const REQUEST_TIMEOUT_MS = 30_000

app.use(cors())
app.use(express.json({ limit: '10mb' }))

// Enforce a 30-second timeout on all /api/ast requests
app.use('/api/ast', (_req: Request, res: Response, next: NextFunction) => {
  res.setTimeout(REQUEST_TIMEOUT_MS, () => {
    res.status(408).json({ success: false, error: 'Request timeout after 30 seconds' })
  })
  next()
})

app.get('/health', (_req, res) => {
  res.json({ status: 'healthy', service: 'ast-service' })
})

app.use('/api/ast', parseRouter)

// Only start the HTTP server when running directly (not when imported by tests)
if (require.main === module) {
  app.listen(PORT, () => {
    console.log(`AST service running on port ${PORT}`)
  })
}

export default app
