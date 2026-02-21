# Codesight

An AI-powered codebase support tool. Index your repositories and ask questions about your code in natural language.

## Architecture

```
codesight/
├── backend/        # Laravel 11 API (PHP 8.3)
├── frontend/       # Vue 3 SPA (Vite + TypeScript)
├── ast-service/    # AST parsing microservice (Node.js + Express)
└── docker-compose.yml  # Infrastructure (PostgreSQL, Redis, Qdrant)
```

## Prerequisites

- PHP 8.3 + Composer
- Node.js 20+
- Docker and Docker Compose

## Setup

### 1. Start infrastructure

```bash
docker compose up -d
```

This starts PostgreSQL (port 5432), Redis (port 6379), and Qdrant (port 6333).

### 2. Backend (Laravel API)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

### 3. Frontend (Vue SPA)

```bash
cd frontend
npm install
cp .env.example .env
```

### 4. AST Service (Node.js)

```bash
cd ast-service
npm install
cp .env.example .env
```

## Running

Start all three apps (each in its own terminal):

```bash
# Terminal 1 — Laravel API (http://localhost:8000)
cd backend && php artisan serve

# Terminal 2 — AST service (http://localhost:3001)
cd ast-service && npm run dev

# Terminal 3 — Vue frontend (http://localhost:5173)
cd frontend && npm run dev
```

## URLs

| Service     | URL                             |
| ----------- | ------------------------------- |
| Frontend    | http://localhost:5173           |
| Laravel API | http://localhost:8000           |
| AST Service | http://localhost:3001           |
| Qdrant UI   | http://localhost:6333/dashboard |
| Horizon     | http://localhost:8000/horizon   |

## Environment Variables

### backend/.env

| Variable            | Description                      |
| ------------------- | -------------------------------- |
| `DB_*`              | PostgreSQL connection settings   |
| `REDIS_HOST`        | Redis host (default: 127.0.0.1)  |
| `QDRANT_HOST`       | Qdrant host (default: 127.0.0.1) |
| `QDRANT_PORT`       | Qdrant port (default: 6333)      |
| `AST_SERVICE_URL`   | AST microservice URL             |
| `FRONTEND_URL`      | Vue SPA URL for CORS             |
| `ANTHROPIC_API_KEY` | Anthropic Claude API key (MVP)   |
| `OPENAI_API_KEY`    | OpenAI embeddings API key (MVP)  |

### frontend/.env

| Variable       | Description          |
| -------------- | -------------------- |
| `VITE_API_URL` | Laravel API base URL |

### ast-service/.env

| Variable           | Description                    |
| ------------------ | ------------------------------ |
| `AST_SERVICE_PORT` | Port to run on (default: 3001) |

## Queue Worker (for indexing)

```bash
cd backend
php artisan horizon
```

## Tech Stack

| Layer       | Technology                               |
| ----------- | ---------------------------------------- |
| Frontend    | Vue 3, Vite, TypeScript, Pinia, Tailwind |
| Backend     | Laravel 11, PHP 8.3, Sanctum, Horizon    |
| AST Service | Node.js, Express, TypeScript             |
| Database    | PostgreSQL 15                            |
| Vector DB   | Qdrant                                   |
| Cache/Queue | Redis 7                                  |
| AI          | Anthropic Claude + OpenAI Embeddings     |
