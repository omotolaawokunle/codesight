# Codesight API Documentation

Base URL: `http://localhost:8000/api`

All authenticated endpoints require a Bearer token in the `Authorization` header:
```
Authorization: Bearer <token>
```

---

## Authentication

### Register

```
POST /auth/register
```

**Request Body**

```json
{
  "name": "Alice Example",
  "email": "alice@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Response** `201 Created`

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Alice Example",
    "email": "alice@example.com",
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Error Responses**

| Status | Condition                     |
|--------|-------------------------------|
| `422`  | Validation failed (duplicate email, weak password, etc.) |

---

### Login

```
POST /auth/login
```

**Request Body**

```json
{
  "email": "alice@example.com",
  "password": "password123"
}
```

**Response** `200 OK`

```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "Alice Example",
    "email": "alice@example.com",
    "created_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Error Responses**

| Status | Condition                     |
|--------|-------------------------------|
| `422`  | Invalid credentials           |

---

### Logout

**Requires authentication.**

```
POST /auth/logout
```

**Response** `200 OK`

```json
{
  "message": "Logged out successfully."
}
```

---

### Get Current User

**Requires authentication.**

```
GET /auth/me
```

**Response** `200 OK`

```json
{
  "id": 1,
  "name": "Alice Example",
  "email": "alice@example.com",
  "created_at": "2024-01-01T00:00:00.000000Z"
}
```

---

## Repositories

### List Repositories

**Requires authentication.**

```
GET /repositories
```

Returns all repositories belonging to the authenticated user.

**Response** `200 OK`

```json
{
  "data": [
    {
      "id": 1,
      "name": "my-project",
      "git_url": "https://github.com/owner/my-project",
      "branch": "main",
      "indexing_status": "completed",
      "total_files": 42,
      "indexed_files": 42,
      "total_chunks": 180,
      "last_indexed_commit": "abc1234",
      "indexing_error": null,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T01:00:00.000000Z"
    }
  ]
}
```

---

### Create Repository

**Requires authentication.**

```
POST /repositories
```

**Request Body**

```json
{
  "name": "my-project",
  "git_url": "https://github.com/owner/my-project",
  "branch": "main",
  "git_token": "ghp_abc123..."
}
```

| Field       | Type   | Required | Description                                          |
|-------------|--------|----------|------------------------------------------------------|
| `name`      | string | Yes      | Display name (max 255 chars)                        |
| `git_url`   | string | Yes      | HTTPS URL from GitHub, GitLab, or Bitbucket         |
| `branch`    | string | No       | Branch to index (default: `main`)                   |
| `git_token` | string | No       | Personal access token for private repositories      |

**Response** `201 Created`

```json
{
  "data": {
    "id": 1,
    "name": "my-project",
    "git_url": "https://github.com/owner/my-project",
    "branch": "main",
    "indexing_status": "pending",
    "total_files": null,
    "indexed_files": null,
    "total_chunks": null,
    "last_indexed_commit": null,
    "indexing_error": null,
    "created_at": "2024-01-01T00:00:00.000000Z",
    "updated_at": "2024-01-01T00:00:00.000000Z"
  }
}
```

**Error Responses**

| Status | Condition                                      |
|--------|------------------------------------------------|
| `422`  | Validation failed (invalid URL, missing name) |
| `422`  | User already has 10 repositories (maximum)    |

---

### Get Repository

**Requires authentication.**

```
GET /repositories/{id}
```

**Response** `200 OK` — same shape as a single item from the list endpoint.

**Error Responses**

| Status | Condition                          |
|--------|-------------------------------------|
| `403`  | Repository belongs to another user  |
| `404`  | Repository not found                |

---

### Delete Repository

**Requires authentication.**

```
DELETE /repositories/{id}
```

Deletes the repository and all associated vector data from Qdrant.

**Response** `200 OK`

```json
{
  "message": "Repository deleted."
}
```

---

### Get Indexing Status

**Requires authentication.**

```
GET /repositories/{id}/status
```

**Response** `200 OK`

```json
{
  "repository_id": 1,
  "status": "in_progress",
  "progress": 45.5,
  "total_files": 100,
  "indexed_files": 45,
  "total_chunks": 200,
  "started_at": "2024-01-01T00:00:00.000000Z",
  "completed_at": null,
  "error": null
}
```

`status` values: `pending` | `in_progress` | `completed` | `failed`

---

### Get Indexed Files

**Requires authentication.**

```
GET /repositories/{id}/files
```

**Response** `200 OK`

```json
{
  "files": [
    "src/controllers/UserController.php",
    "src/services/AuthService.php"
  ]
}
```

---

### Re-index Repository

**Requires authentication.**

```
POST /repositories/{id}/reindex
```

Resets status to `pending` and queues a new indexing job.

**Response** `200 OK`

```json
{
  "message": "Re-indexing has been queued."
}
```

---

## Chat

### Send a Query

**Requires authentication.**

```
POST /chat
```

**Request Body**

```json
{
  "repository_id": 1,
  "query": "How does the authentication system work?",
  "conversation_id": null
}
```

| Field             | Type    | Required | Description                                    |
|-------------------|---------|----------|------------------------------------------------|
| `repository_id`   | integer | Yes      | ID of an indexed repository                   |
| `query`           | string  | Yes      | Natural language question (max 2000 chars)    |
| `conversation_id` | integer | No       | Continue an existing conversation             |

**Response** `200 OK`

```json
{
  "conversation_id": 5,
  "content": "The authentication system uses Laravel Sanctum...",
  "sources": [
    {
      "file_path": "app/Http/Controllers/AuthController.php",
      "name": "login",
      "chunk_type": "function",
      "score": 0.95,
      "start_line": 36,
      "end_line": 57
    }
  ],
  "usage": {
    "input_tokens": 1200,
    "output_tokens": 350
  }
}
```

**Error Responses**

| Status | Condition                                     |
|--------|-----------------------------------------------|
| `403`  | Repository belongs to another user            |
| `422`  | Repository is not fully indexed yet           |
| `422`  | Validation failed (missing fields, too long)  |
| `500`  | AI service failed to generate a response      |

---

### Stream a Query (SSE)

**Requires authentication.**

```
POST /chat/stream
```

Same request body as `/chat`. Returns a Server-Sent Events (SSE) stream.

**Event types:**

- `data: {"type":"chunk","content":"The auth..."}` — text chunk
- `data: {"type":"sources","sources":[...]}` — code sources
- `data: [DONE]` — stream complete

---

### Analyze an Error Log

**Requires authentication.**

```
POST /chat/analyze-error
```

**Request Body**

```json
{
  "repository_id": 1,
  "error_log": "TypeError: Cannot read property 'map' of undefined\n    at processItems (src/utils.js:15:22)"
}
```

**Response** `200 OK` — same shape as `/chat`.

---

### List Conversations

**Requires authentication.**

```
GET /chat/{repositoryId}/conversations
```

**Response** `200 OK`

```json
{
  "data": [
    {
      "id": 5,
      "title": "How does authentication work",
      "messages_count": 4,
      "created_at": "2024-01-01T00:00:00.000000Z",
      "updated_at": "2024-01-01T00:10:00.000000Z"
    }
  ]
}
```

---

### Delete Conversation

**Requires authentication.**

```
DELETE /chat/conversations/{id}
```

**Response** `200 OK`

```json
{
  "message": "Conversation deleted."
}
```

**Error Responses**

| Status | Condition                               |
|--------|-----------------------------------------|
| `403`  | Conversation belongs to another user   |
| `404`  | Conversation not found                  |

---

## Health

### Health Check

```
GET /health
```

No authentication required.

**Response** `200 OK`

```json
{
  "status": "ok",
  "services": {
    "database": "ok",
    "redis": "ok",
    "qdrant": "ok",
    "ast_service": "ok"
  }
}
```

---

## AST Service API

Base URL: `http://localhost:3001/api/ast`

### Parse File

```
POST /api/ast/parse
```

**Request Body**

```json
{
  "filePath": "src/utils.js",
  "content": "function add(a, b) { return a + b }",
  "language": "javascript"
}
```

**Response** `200 OK`

```json
{
  "success": true,
  "filePath": "src/utils.js",
  "language": "javascript",
  "chunkCount": 1,
  "chunks": [
    {
      "type": "function",
      "name": "add",
      "content": "function add(a, b) { return a + b }",
      "startLine": 1,
      "endLine": 1,
      "filePath": "src/utils.js"
    }
  ]
}
```

**Supported languages:** `javascript`, `typescript`, `python`, `php`

**Error Responses**

| Status | Condition                                        |
|--------|--------------------------------------------------|
| `400`  | Missing `filePath`, `content`, or `language`    |
| `400`  | Content exceeds 1MB                              |

### Get Supported Languages

```
GET /api/ast/supported-languages
```

**Response** `200 OK`

```json
{
  "languages": ["javascript", "typescript", "python", "php"]
}
```

### AST Service Health

```
GET /health
```

**Response** `200 OK`

```json
{
  "status": "healthy",
  "service": "ast-service"
}
```
