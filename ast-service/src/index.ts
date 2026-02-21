import express from 'express'
import cors from 'cors'
import parseRouter from './routes/parse'

const app = express()
const PORT = process.env.AST_SERVICE_PORT ?? 3001

app.use(cors())
app.use(express.json({ limit: '10mb' }))

app.get('/health', (_req, res) => {
  res.json({ status: 'healthy', service: 'ast-service' })
})

app.use('/api/ast', parseRouter)

app.listen(PORT, () => {
  console.log(`AST service running on port ${PORT}`)
})

export default app
