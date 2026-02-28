# Contributing to Codesight

Thank you for your interest in contributing. This guide covers our development workflow, code standards, and testing requirements.

## Table of Contents

- [Development Setup](#development-setup)
- [Branch Naming](#branch-naming)
- [Commit Conventions](#commit-conventions)
- [Code Style](#code-style)
- [Testing Requirements](#testing-requirements)
- [Pull Request Process](#pull-request-process)

## Development Setup

Follow the [README setup instructions](README.md) to get the full stack running locally. You'll need:

- PHP 8.3 + Composer
- Node.js 20+
- Docker and Docker Compose

## Branch Naming

Use the format: `<type>/<short-description>`

| Type       | When to use                                      |
|------------|--------------------------------------------------|
| `feat/`    | New feature                                      |
| `fix/`     | Bug fix                                          |
| `refactor/`| Code improvement without behaviour change        |
| `test/`    | Adding or improving tests                        |
| `docs/`    | Documentation changes only                       |
| `chore/`   | Build, dependency, or config changes             |

Examples:
- `feat/error-analysis-endpoint`
- `fix/qdrant-connection-timeout`
- `refactor/retriever-hybrid-search`

## Commit Conventions

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short description>

[optional body]
```

Types: `feat`, `fix`, `docs`, `refactor`, `test`, `chore`

Good examples:
```
feat(chat): add streaming SSE endpoint for AI responses
fix(indexer): handle repositories with no parseable files
test(retriever): add unit tests for hybrid search boosting
docs(api): document the analyze-error endpoint
```

- Subject line should be ≤ 72 characters
- Use imperative mood ("add feature" not "added feature")
- Reference issue numbers in the body when applicable

## Code Style

### PHP (Backend)

- Follow PSR-12 coding standards
- Use PHP 8.3 features (constructor property promotion, readonly, etc.)
- Use Laravel conventions for controllers, services, models, and requests
- Keep controllers thin — business logic belongs in Service classes
- Use Form Requests for input validation
- No raw SQL — always use Eloquent or the Query Builder

### TypeScript / JavaScript (Frontend & AST Service)

- Use 2-space indentation
- Use `const` and `let` — never `var`
- Use TypeScript interfaces for all data structures
- Prefer `async/await` over raw Promises
- Use Vue 3 Composition API with `<script setup>` syntax
- Use Pinia for state management — keep components lean

### Vue Components

- One component per file
- Props must be typed with TypeScript
- Emit events using `defineEmits` with typed signatures
- Use scoped styles sparingly — prefer Tailwind utility classes
- Component file names: `PascalCase.vue`

## Testing Requirements

All changes must include appropriate tests before merging.

### Backend Tests (PHPUnit)

```bash
cd backend && php artisan test
```

- Unit tests for service classes (mock external HTTP calls with `Http::fake()`)
- Feature tests for API endpoints using `RefreshDatabase`
- Use `Queue::fake()` to prevent job execution in tests
- Aim for >70% coverage on business logic

### Frontend Tests (Vitest)

```bash
cd frontend && npm test
```

- Component tests using `@vue/test-utils`
- Store tests mocking the `api` service module
- Aim for >60% coverage

### AST Service Tests (Jest)

```bash
cd ast-service && npm test
```

- Parser unit tests for each language
- Route integration tests using `supertest`
- Aim for >80% coverage on parser logic

### Test Conventions

- Test names should read like specifications: `it('should return 404 when repository not found')`
- Test both success and error paths
- Mock all external API calls — no real network requests in tests
- Tests must be independent — no shared mutable state between tests
- Clean up all test data (use `RefreshDatabase` in Laravel feature tests)

## Pull Request Process

1. **Fork or branch** from `main`
2. **Implement** your changes following the code style above
3. **Write tests** that cover your changes
4. **Run the full test suite** and ensure all tests pass
5. **Update documentation** if you've changed API endpoints or behaviour
6. **Open a PR** with:
   - A clear title describing the change
   - Description of what changed and why
   - Screenshots for UI changes
   - Reference to any related issues

### PR Checklist

- [ ] Tests added/updated and passing
- [ ] No console errors or PHP exceptions
- [ ] Code follows project style conventions
- [ ] Documentation updated if needed
- [ ] Environment variables documented if new ones added
