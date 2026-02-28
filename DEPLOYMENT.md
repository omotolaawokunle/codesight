# Deployment Guide

This guide covers deploying Codesight to a production server using Docker Compose.

## Prerequisites

- A Linux server (Ubuntu 22.04+ recommended)
- Docker Engine 24+ and Docker Compose v2
- A domain name with DNS pointing to your server
- SSL certificate (Let's Encrypt recommended)

## Production Architecture

```
Client → Nginx (SSL termination) → Frontend (Vue static files)
                                 → Laravel API (:8000)
                                 → AST Service (:3001)
```

## Step 1: Server Setup

```bash
# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Clone the repository
git clone https://github.com/your-org/codesight.git
cd codesight
```

## Step 2: Environment Configuration

Copy and configure production `.env` files:

```bash
# Backend
cp backend/.env.example backend/.env
```

Edit `backend/.env` with production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=codesight
DB_USERNAME=codesight
DB_PASSWORD=<strong-random-password>

REDIS_HOST=redis
REDIS_PORT=6379

QDRANT_HOST=qdrant
QDRANT_PORT=6333

AST_SERVICE_URL=http://ast-service:3001
FRONTEND_URL=https://yourdomain.com

GEMINI_API_KEY=<your-gemini-key>
ANTHROPIC_API_KEY=<your-anthropic-key>
```

```bash
# Frontend — build with production API URL
echo "VITE_API_URL=https://yourdomain.com/api" > frontend/.env
```

## Step 3: Build Frontend Assets

```bash
cd frontend
npm ci
npm run build
cd ..
```

The production build will be in `frontend/dist/`.

## Step 4: Production Docker Compose

Create `docker-compose.prod.yml`:

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:15-alpine
    restart: always
    environment:
      POSTGRES_DB: codesight
      POSTGRES_USER: codesight
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U codesight"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    restart: always
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes

  qdrant:
    image: qdrant/qdrant:latest
    restart: always
    volumes:
      - qdrant_data:/qdrant/storage
    ports:
      - "127.0.0.1:6333:6333"

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile.prod
    restart: always
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_started
      qdrant:
        condition: service_started
    env_file:
      - ./backend/.env
    volumes:
      - /tmp/codesight-repos:/tmp/repos

  worker:
    build:
      context: ./backend
      dockerfile: Dockerfile.prod
    restart: always
    command: php artisan horizon
    depends_on:
      - backend
    env_file:
      - ./backend/.env

  ast-service:
    build:
      context: ./ast-service
      dockerfile: Dockerfile.prod
    restart: always
    environment:
      AST_SERVICE_PORT: 3001

  nginx:
    image: nginx:alpine
    restart: always
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - ./frontend/dist:/var/www/frontend:ro
      - /etc/letsencrypt:/etc/letsencrypt:ro
    depends_on:
      - backend
      - ast-service

volumes:
  postgres_data:
  redis_data:
  qdrant_data:
```

## Step 5: Nginx Configuration

Create `nginx/conf.d/codesight.conf`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Laravel API
    location /api/ {
        proxy_pass http://backend:8000/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # SSE streaming (disable buffering)
    location /api/chat/stream {
        proxy_pass http://backend:8000/api/chat/stream;
        proxy_buffering off;
        proxy_cache off;
        proxy_read_timeout 300s;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Vue SPA (serve index.html for all routes)
    location / {
        root /var/www/frontend;
        try_files $uri $uri/ /index.html;
        gzip_static on;
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
```

## Step 6: Deploy

```bash
# Run database migrations
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

# Generate Laravel app key if not already done
docker compose -f docker-compose.prod.yml run --rm backend php artisan key:generate

# Cache config for performance
docker compose -f docker-compose.prod.yml run --rm backend php artisan config:cache
docker compose -f docker-compose.prod.yml run --rm backend php artisan route:cache

# Start all services
docker compose -f docker-compose.prod.yml up -d
```

## Step 7: SSL Certificate (Let's Encrypt)

```bash
# Install certbot
sudo apt install certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d yourdomain.com

# Auto-renewal is set up automatically; verify with:
sudo certbot renew --dry-run
```

## Database Migrations Strategy

For zero-downtime deployments:

1. Run migrations **before** deploying new code
2. Ensure migrations are backward compatible
3. Use `php artisan migrate --force` in production (skips the confirmation prompt)

```bash
# Pre-deployment migration
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force

# Then deploy new containers
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d --no-deps backend worker
```

## Backup Strategy

### PostgreSQL

```bash
# Daily backup via cron
0 2 * * * docker exec codesight-postgres pg_dump -U codesight codesight | gzip > /backups/codesight-$(date +%Y%m%d).sql.gz

# Retain last 30 days
0 3 * * * find /backups -name "codesight-*.sql.gz" -mtime +30 -delete
```

### Qdrant

Qdrant stores vectors in `/qdrant/storage` inside the container, mapped to the `qdrant_data` Docker volume.

```bash
# Snapshot via Qdrant API
curl -X POST http://localhost:6333/collections/repo_1/snapshots
```

## Monitoring

### Health Checks

```bash
# Laravel API
curl https://yourdomain.com/api/health

# AST Service (internal)
curl http://localhost:3001/health
```

### Horizon Dashboard

Monitor queue jobs at `https://yourdomain.com/horizon`.

Restrict access in `backend/config/horizon.php`:

```php
'middleware' => ['auth'],
```

### Log Monitoring

```bash
# Laravel logs
docker logs codesight-backend --tail=100 -f

# Worker logs
docker logs codesight-worker --tail=100 -f
```

## Updating

```bash
# Pull latest code
git pull origin main

# Rebuild and redeploy
cd frontend && npm ci && npm run build && cd ..
docker compose -f docker-compose.prod.yml build backend ast-service
docker compose -f docker-compose.prod.yml run --rm backend php artisan migrate --force
docker compose -f docker-compose.prod.yml up -d
```

## Environment Variable Checklist

Before going live, verify:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` is set (run `php artisan key:generate`)
- [ ] Strong, unique `DB_PASSWORD`
- [ ] Valid `GEMINI_API_KEY`
- [ ] Valid `ANTHROPIC_API_KEY`
- [ ] `FRONTEND_URL` matches your actual domain (for CORS)
- [ ] SSL certificate is valid and auto-renewing
- [ ] Horizon is running and processing jobs
- [ ] Database backups are scheduled
