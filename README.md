# Codesight

An AI-powered codebase support tool. Index your Git repositories and ask natural language questions about your code — powered by RAG (Retrieval-Augmented Generation) with Claude and Gemini embeddings.

## Architecture

```
┌─────────────────────────────────────────┐
│           Vue.js 3 Frontend             │
│  (Dashboard, Chat, Repository Management)│
└──────────────┬──────────────────────────┘
               │ HTTP / REST API
┌──────────────▼──────────────────────────┐
│         Laravel 11 Backend              │
│  Services: VectorDB, Embedding,         │
│  Retriever, LLM, GitManager, Indexer    │
└──────┬────────────────────┬─────────────┘
       │                    │
┌──────▼──────┐   ┌─────────▼────────────┐
│  PostgreSQL  │   │  AST Microservice    │
│  (metadata) │   │  (Node.js/tree-sitter)│
└─────────────┘   └──────────────────────┘
       │
┌──────▼──────┐   ┌──────────┐   ┌───────┐
│   Qdrant    │   │  Redis   │   │  LLM  │
│  (vectors)  │   │  (cache/ │   │  APIs │
│             │   │   queue) │   │       │
└─────────────┘   └──────────┘   └───────┘
```

### Component Overview

| Component    | Technology                               | Port |
|-------------|------------------------------------------|------|
| Frontend     | Vue 3, Vite, TypeScript, Pinia, Tailwind | 5173 |
| Backend      | Laravel 11, PHP 8.3, Sanctum, Horizon   | 8000 |
| AST Service  | Node.js, Express, TypeScript, tree-sitter| 3001 |
| PostgreSQL   | postgres:15-alpine                       | 5432 |
| Redis        | redis:7-alpine                           | 6379 |
| Qdrant       | qdrant/qdrant:latest                     | 6333 |

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

Configure AI and service keys in `backend/.env` (see Environment Variables below).

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

## Running Locally

Start all three apps (each in its own terminal):

```bash
# Terminal 1 — Laravel API (http://localhost:8000)
cd backend && php artisan serve

# Terminal 2 — AST service (http://localhost:3001)
cd ast-service && npm run dev

# Terminal 3 — Vue frontend (http://localhost:5173)
cd frontend && npm run dev

# Terminal 4 — Queue worker (for repository indexing)
cd backend && php artisan horizon
```

## URLs

| Service     | URL                             |
|-------------|--------------------------------|
| Frontend    | http://localhost:5173           |
| Laravel API | http://localhost:8000           |
| AST Service | http://localhost:3001           |
| Qdrant UI   | http://localhost:6333/dashboard |
| Horizon     | http://localhost:8000/horizon   |

## Environment Variables

### backend/.env

| Variable              | Description                                   | Required |
|-----------------------|-----------------------------------------------|----------|
| `APP_KEY`             | Laravel encryption key (`php artisan key:generate`) | Yes |
| `DB_CONNECTION`       | Database driver (`pgsql`)                    | Yes      |
| `DB_HOST`             | PostgreSQL host (default: `127.0.0.1`)       | Yes      |
| `DB_DATABASE`         | Database name (default: `codesight`)          | Yes      |
| `DB_USERNAME`         | Database user (default: `codesight`)          | Yes      |
| `DB_PASSWORD`         | Database password                             | Yes      |
| `REDIS_HOST`          | Redis host (default: `127.0.0.1`)            | Yes      |
| `QDRANT_HOST`         | Qdrant host (default: `127.0.0.1`)           | Yes      |
| `QDRANT_PORT`         | Qdrant port (default: `6333`)                | Yes      |
| `AST_SERVICE_URL`     | AST microservice base URL                    | Yes      |
| `FRONTEND_URL`        | Vue SPA URL for CORS                          | Yes      |
| `GEMINI_API_KEY`      | Gemini API key (for embeddings)              | Yes      |
| `ANTHROPIC_API_KEY`   | Anthropic Claude API key (for LLM responses) | Yes      |

### frontend/.env

| Variable       | Description                          | Default                         |
|----------------|--------------------------------------|---------------------------------|
| `VITE_API_URL` | Laravel API base URL                 | `http://localhost:8000/api`    |

### ast-service/.env

| Variable           | Description                     | Default |
|--------------------|---------------------------------|---------|
| `AST_SERVICE_PORT` | Port to run on                 | `3001`  |

## Running Tests

### Backend

```bash
cd backend
php artisan test              # All tests
php artisan test --testsuite=Unit     # Unit tests only
php artisan test --testsuite=Feature  # Feature tests only
```

### Frontend

```bash
cd frontend
npm test                  # Run all tests once
npm run test:watch        # Watch mode
npm run test:coverage     # With coverage report
```

### AST Service

```bash
cd ast-service
npm test                  # Run all tests
npm run test:coverage     # With coverage report
```

## Queue Worker

Repository indexing is processed asynchronously via Laravel Horizon:

```bash
cd backend && php artisan horizon
```

Monitor jobs at http://localhost:8000/horizon.

## Tech Stack

| Layer       | Technology                               |
|-------------|------------------------------------------|
| Frontend    | Vue 3, Vite, TypeScript, Pinia, Tailwind |
| Backend     | Laravel 11, PHP 8.3, Sanctum, Horizon    |
| AST Service | Node.js, Express, TypeScript, tree-sitter|
| Database    | PostgreSQL 15                            |
| Vector DB   | Qdrant                                   |
| Cache/Queue | Redis 7                                  |
| AI          | Anthropic Claude + Gemini Embeddings     |

## Troubleshooting

**`Queue jobs not processing`**
Run `php artisan horizon` in the backend directory. Check that Redis is running (`docker compose ps`).

**`Qdrant connection refused`**
Ensure Docker services are running: `docker compose up -d`. Qdrant should be reachable at `http://localhost:6333`.

**`Embedding API errors`**
Verify `GEMINI_API_KEY` is set in `backend/.env`. Check API quota limits in your Google Cloud console.

**`Git clone failing`**
Only HTTPS URLs from GitHub, GitLab, and Bitbucket are supported. SSH URLs are not supported. For private repos, generate a personal access token with `read` scope.

**`AST Service not responding`**
The AST service must be running for indexing to work. Start it with `cd ast-service && npm run dev`.

**`Tests failing with database errors`**
Backend tests use SQLite in-memory. Ensure `backend/.env.testing` exists (created during setup). Run `php artisan config:clear` if you see stale config issues.

## Related Docs

- [API Documentation](API.md)
- [Deployment Guide](DEPLOYMENT.md)
- [Contributing Guide](CONTRIBUTING.md)
