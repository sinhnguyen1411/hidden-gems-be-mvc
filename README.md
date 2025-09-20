# Hidden Gems Backend

English documentation for the Hidden Gems REST API backend. The project delivers a compact PHP/Laravel-like stack that exposes JSON endpoints for authentication, stores, reviews, vouchers, promotions, content, and admin tooling.

## Overview
- **Runtime**: PHP 8.2 (custom micro-framework, PSR-4 autoloading via Composer).
- **Transport**: Nginx -> PHP-FPM (or PHP built-in server for development).
- **Database**: MariaDB (PDO models, versioned SQL migrations).
- **Cache/Queues**: Redis or filesystem cache, queue workers via CLI scripts.
- **Documentation**: OpenAPI spec (`public/docs/openapi.yaml`), Postman collection (`docs/postman_collection.json`), visual flows (`public/docs/api-flows.html`).

## Requirements
- PHP 8.2+ with `pdo_mysql`
- Composer
- MariaDB 10.6+ (default port `3307` for local dev)
- Redis 6+ (optional but recommended for caching/queue demos)
- Docker & Docker Compose (optional orchestration stack)

## Quick Start
```bash
# 1. Install dependencies
composer install

# 2. Copy env template and update credentials
cp .env.example .env
#   DB_HOST=127.0.0.1
#   DB_PORT=3307
#   DB_DATABASE=hiddengems
#   DB_USERNAME=root
#   DB_PASSWORD=secret
#   JWT_SECRET=change-me

# 3. Run database migrations & seeders
php database/migrations/migrator.php up
php database/seeders/seed.php

# 4. Start development server
php -S 127.0.0.1:8000 -t public
```

Health & tooling endpoints:
- `GET /` -> `{"message":"Hidden Gems API"}`
- `GET /health` (liveness), `GET /ready` (readiness, fails if DB down)
- `GET /metrics` (Prometheus format)
- OpenAPI UI: `http://127.0.0.1:8000/docs/`

## Project Structure (highlights)
```
app/
  Core/          # Request, Response, Cache, Auth helpers
  Http/          # Controllers, Middleware, Routes entry
  Models/        # PDO-backed models
  Services/      # Domain services (optional)
config/          # Shared configuration
public/          # Front controller, docs, assets
routes/          # Route definitions
scripts/         # Utility CLI scripts (backup, smoke tests)
database/
  migrations/    # Versioned SQL scripts + baseline schema
  seeders/       # Idempotent seed scripts
```

## Common Tasks
| Task | Command |
|------|---------|
| Run PHP lint | `php -l app/**/*.php` |
| Run unit tests | `composer test` |
| Reset dev database | `php database/migrations/migrate.php --drop` |
| Apply migrations (versioned) | `php database/migrations/migrator.php up` |
| Generate backup | `php scripts/backup.sh` or `powershell ./scripts/backup.ps1` |
| Start Docker stack | `docker compose up --build` |

## Documentation & Tools
- **API reference**: maintained in the OpenAPI YAML + Swagger UI.
- **Process diagrams**: `public/docs/api-flows.html` (Mermaid-based visuals for flows, tooling, security, performance, scalability, limitations).
- **Postman collection**: ready-to-import flows with environment variables (`BASE_URL`, JWT tokens).
- **Mermaid CLI tips**: `npm install -g @mermaid-js/mermaid-cli` to export diagrams (`mmdc`).

## Deployment Notes
- Provide environment variables via orchestrator (Docker secrets, Kubernetes, etc.).
- Use trusted TLS certificates and ensure `APP_URL` points to the public HTTPS endpoint.
- Enable Redis for better cache hit rates (`REDIS_HOST`, `REDIS_PORT` or `REDIS_URL`).
- Rotate `JWT_SECRET` and DB credentials via a secure secret manager.
- Set up MariaDB replication and backup automation before production rollout.

## Current Limitations (English)
Refer to **Known Limitations** in `public/docs/api-flows.html` for a visual summary. In short: monolithic architecture, single-primary database, limited observability, and reliance on manual scripts for automation & compliance.

## Contributing
1. Create a feature branch.
2. Ensure PHP lint/tests succeed (`composer test`).
3. Update docs (OpenAPI, diagrams) when endpoints change.
4. Open a PR with a concise summary and validation notes.

## License
Internal project distribution restricted to the Hidden Gems team.
