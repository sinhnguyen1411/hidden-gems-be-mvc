# Hidden Gems Backend — Developer Guide

REST API backend for the Hidden Gems app. Lightweight PHP stack with a Laravel‑like structure (controllers, middleware, routes), JSON‑first responses, and PDO models.

## Changelog
- 2025‑09‑10
  - Migrations now read DB settings from `.env` (no hard‑coding).
  - Added PowerShell smoke test `scripts/smoke.ps1` to exercise core flows end‑to‑end (now includes caching/ETag checks).
  - Default MySQL port in `.env.example` is `3307` to match the current environment.
  - Consolidated SQL schema into `database/migrations/2025_09_10_000000_schema.sql` for easier maintenance (versioned ups/downs still supported).
  - Expanded OpenAPI docs (Swagger) to cover Vouchers, Promotions, Blog, Banners, Chat, Wallet, Ads, Admin, Policies, CSRF, Ops.
  - Added Redis service to `docker-compose.yml` and integrated app‑level caching.
  - Added `X-Cache` debug header (and optional `X-Cache-Keys`) for cache verification in dev.
- 2025‑09‑04
  - Wallet (deposit/charge/refund) and Advertising requests with admin approval.
- 2025‑09‑01
  - Specialized requests (`JsonRequest`, `FormRequest`) and unified JSON responses via `JsonResponse`.
  - Router standardized CORS, optional CSRF, and security headers for every response.

## Requirements
- PHP 8.2+ with `pdo_mysql`
- Composer
- MySQL 8 (listening on port `3307` by default)

## Quick Start
1) Install dependencies: `composer install`
2) Configure environment: copy `.env.example` → `.env`, set at minimum:
   - `DB_DRIVER=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3307`, `DB_DATABASE=hiddengems`, `DB_USERNAME=root`, `DB_PASSWORD=`
   - `JWT_SECRET=<your-secret>`
   - `APP_URL=http://127.0.0.1:8000`
   - `CORS_ALLOWED_ORIGIN=http://localhost` (or your frontend origin)
3) Migrate database (prod-safe): `php database/migrations/migrator.php up`
   - Dev reset (drops DB): `php database/migrations/migrate.php --drop` (ignored in production)
4) Seed demo data (idempotent): `php database/seeders/seed.php`
5) Start dev server: `php -S 127.0.0.1:8000 -t public`
6) Health check: `curl http://127.0.0.1:8000/` → `{"message":"Hidden Gems API"}`
   - Liveness: `GET /health` → `{status:"ok"}`
   - Readiness: `GET /ready` → `{status:"ready"}` or 500 if DB is down
   - Metrics (Prometheus): `GET /metrics` (text/plain, v0.0.4)
   - API Docs (Swagger UI): open `http://127.0.0.1:8000/docs/`

## Directory Tree

```
.
|-- app/
|   |-- Core/
|   |-- Http/
|   |   |-- Controllers/        # API controllers
|   |   `-- Middleware/         # Request middlewares
|   |-- Controllers/            # Legacy/shared controllers
|   |-- Middlewares/            # Legacy/shared middlewares
|   |-- Models/                 # PDO models
|   `-- Security/               # JWT, hashing, policies
|-- bootstrap/
|   `-- app.php                 # App bootstrap & container
|-- database/
|   |-- migrations/
|   |   |-- 2025_09_10_000000_schema.sql
|   |   |-- migrate.php
|   |   `-- migrator.php
|   `-- seeders/
|       `-- seed.php
|-- docker/
|   |-- nginx/
|   `-- php/
|-- docs/
|   `-- postman_collection.json
|-- public/
|   |-- index.php
|   `-- docs/
|       |-- index.html
|       `-- openapi.yaml
|-- routes/
|   |-- api.php
|   `-- web.php
|-- scripts/
|   |-- smoke.ps1
|   |-- backup.ps1
|   `-- backup.sh
|-- tests/
|   |-- run.php
|   |-- TestCase.php
|   |-- RootRouteTest.php
|   |-- AuthTest.php
|   `-- ValidatorTest.php
|-- vendor/
|-- .env.example
|-- docker-compose.yml
|-- composer.json
`-- README.md
```

## Database
- Versioned migrator (recommended):
  - Up: `php database/migrations/migrator.php up`
  - Down last N: `php database/migrations/migrator.php down 1`
  - Baseline (mark existing up files as applied): `php database/migrations/migrator.php baseline`
  - Convention: `YYYY_MM_DD_HHMMSS_name.up.sql` (and matching `.down.sql`).
- Dev reset (drops DB, re-creates baseline schema): `php database/migrations/migrate.php --drop` then `migrator.php up` runs automatically.
- Seeders are idempotent and can be re-run safely.
 - Consolidated baseline schema file: `database/migrations/2025_09_10_000000_schema.sql` (kept small number of files for easier management).

## Docker (PHP‑FPM + Nginx + MySQL)
- Requirements: Docker, Docker Compose
- Services: MySQL 8 (exposed on host `3307`), PHP‑FPM 8.2, Nginx (HTTPS with local certs), Redis (optional cache)

Steps (development):
- Generate local TLS certs into `docker/nginx/certs` (see `docker/nginx/certs/README.md`)
- Start stack: `docker compose up --build -d`
- App URL: `https://localhost` (self‑signed cert)
- DB inside containers: host `db:3306`; on host: `127.0.0.1:3307`
- Redis inside containers: host `redis:6379`; on host: `127.0.0.1:6379`
- Run migrations (dev reset): `docker compose exec php php database/migrations/migrate.php --drop`
- Apply versioned up: `docker compose exec php php database/migrations/migrator.php up`

Redis cache (optional):
- Compose includes a `redis` service; PHP is preconfigured with `REDIS_HOST=redis`.
- To enable app caching via Redis, set any `*_CACHE_TTL` or simply leave defaults and ensure Redis is up.
- Seed data: `docker compose exec php php database/seeders/seed.php`

Config notes:
- Nginx: HTTP→HTTPS redirect, HTTP/2, gzip; `client_max_body_size 12m`.
- PHP: `upload_max_filesize=10M`, `post_max_size=12M`, OPCache enabled; FPM pm tuned for small env.
- Env in Compose overrides `.env` for containers (DB_HOST=db, DB_PORT=3306, etc.).

Production pointers:
- Use trusted TLS certs (e.g., Let’s Encrypt) and set `APP_URL` to `https://...`.
- Set `ENABLE_BANK_SIMULATION=0` and proper `JWT_SECRET`, DB creds.
- Build a release image (no host bind mounts) and use an override compose or Helm chart.

## Developer Experience & Docs
- OpenAPI/Swagger:
  - Swagger UI: `public/docs/index.html` (serves `public/docs/openapi.yaml`)
  - Visit `/docs/` on your server to view docs.
  - Spec coverage: Auth (register/login/refresh/logout/reset/change/verify), Users (profile/consent/export), Stores/Reviews, Vouchers, Promotions, Blog, Banners, Chat, Wallet, Ads, Admin, CSRF, Ops.
- Postman:
  - Collection: `docs/postman_collection.json` (variable `BASE_URL`)
- Tests:
  - Run: `composer test` (basic unit tests). Add DB-backed integration tests as needed.
- Lint/format:
  - Syntax check: `php -l`. Editor settings in `.editorconfig`.
  - Git pre-commit hooks: `git config core.hooksPath .githooks` (runs PHP lint + tests).
- Secrets:
  - Do not commit `.env`. For production, use a proper secret manager (AWS Secrets Manager/SSM, GCP Secret Manager, etc.).
  - Docker images should receive config via env vars or orchestrator secret mounts.

## Frontend Mapping (Typical Screens → API)
- Home
  - Banners: `GET /api/banners?vi_tri=home_top` (or other positions)
  - Search: `GET /api/search?q=...`
  - Active ads: `GET /api/ads/active`
  - Contact: `GET /api/contact`
- Auth & Account
  - Register/Login/Refresh/Logout
  - Forgot/Reset password, Verify email
  - Profile: `GET/PATCH /api/me/profile`, Consent: `POST /api/me/consent`, Export: `GET /api/me/export`
- Stores & Reviews
  - List/Search/Detail: `/api/cafes`, `/api/cafes/search`, `/api/cafes/{id}`
  - Reviews: `GET/POST /api/cafes/{id}/reviews`
- My Store (Shop)
  - My stores: `GET /api/me/stores`
  - Create/Update store, Create branch, Upload image
- Vouchers & Promotions
  - Vouchers: create/assign/list by store
  - Promotions: create (admin), apply (shop), review (admin), list by store
- Wallet
  - Balance/History/Deposit instructions
  - Simulated bank webhook (dev only): `POST /api/simulate/bank-transfer`
- Advertising
  - Packages list, Create request (shop), My requests, Admin pending, Admin review, Active ads
- Chat
  - Send message, List messages, Conversations
- Admin
  - Dashboard, Set role, Pending stores, Approve store, Delete user

## Backups
- Shell: `DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=root DB_PASS= DB_NAME=hiddengems ./scripts/backup.sh`
- PowerShell: `DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=root DB_PASS= DB_NAME=hiddengems ./scripts/backup.ps1`
- Outputs: gzipped SQL dump and optional `public/uploads` archive in `backups/`.

## Smoke Test (End‑to‑End)
- Script (PowerShell): `scripts/smoke.ps1`
  - Windows PowerShell: `./scripts/smoke.ps1`
  - PowerShell Core (macOS/Linux): `pwsh ./scripts/smoke.ps1`
  - Override base URL: `BASE_URL=http://localhost:8000 ./scripts/smoke.ps1`
- Flow: admin/shop auth → set role → wallet deposit (simulate) → ensure store → ads (create + admin approve) → promotions (create/apply/approve) → vouchers (create/assign/list) → blog (create/update) → banners (create/list) → chat (send/list) → cafes (list/review) → wallet (me/history) → admin dashboard → search → CSRF token.
  - Caching check: validates ETag/304 on `/api/banners` and compares first vs second call timings (second should be faster with cache/Redis).

## API Overview
- Auth
  - `POST /api/auth/register` — create user
  - `POST /api/auth/login` — email or username + password → `access_token`, `refresh_token`
  - `POST /api/auth/refresh` — refresh access token (rotates refresh token)
  - `POST /api/auth/logout` — revoke refresh token (auth)
  - `POST /api/auth/forgot-password` — request password reset
  - `POST /api/auth/reset-password` — reset password with token
  - `POST /api/auth/change-password` — change password (auth)
  - `POST /api/auth/verify-email/request` — issue email verify token (auth)
  - `POST /api/auth/verify-email/confirm` — confirm email verify token
  - `GET /api/users` — list users (admin)
  - `DELETE /api/me` — delete current user (auth)
- Users & Admin
  - `GET /api/admin/dashboard` — summary stats (admin)
  - `POST /api/admin/users/role` — set user role (admin)
  - `DELETE /api/admin/users/{id}` — delete user by ID (admin)
  - `GET /api/admin/pending-stores` — stores pending approval (admin)
  - `POST /api/admin/stores/{id}/approve` — approve/reject store (admin) `{action:'approve'|'reject'}`
  - `GET /api/contact` — contact/deep-link info
- User
  - `GET /api/me/profile` — get profile (auth)
  - `PATCH /api/me/profile` — update `full_name`, `phone_number`, optional `email` (re-verification required)
  - `POST /api/me/consent` — record consent `{terms_version?, privacy_version?}` (auth)
  - `GET /api/me/export` — export personal data (auth)
- Stores & Reviews
  - `GET /api/cafes` — list stores; `GET /api/cafes/search?q=...`; `GET /api/cafes/{id}`
  - `GET /api/cafes/{id}/reviews` — list; `POST /api/cafes/{id}/reviews` — create (auth)
  - `POST /api/stores` — create (shop); `PATCH /api/stores/{id}` — update (owner/admin)
  - `POST /api/stores/{id}/branches` — create branch (shop)
  - `GET /api/me/stores` — my stores (auth)
  - `POST /api/stores/{id}/images` — upload image (auth, multipart/form-data)
- Search
  - `GET /api/search?q=...` — returns stores, blogs, vouchers, promotions
- Vouchers
  - `POST /api/vouchers` — create (admin/shop)
  - `POST /api/vouchers/assign` — assign to store (admin/shop)
  - `GET /api/stores/{id}/vouchers` — list by store
- Promotions
  - `POST /api/promotions` — create (admin)
  - `POST /api/promotions/{id}/apply` — shop applies (shop)
  - `POST /api/promotions/{id}/review` — admin reviews (admin)
  - `GET /api/stores/{id}/promotions` — list by store
- Blog
  - `GET /api/blog` — list/search; `POST /api/blog` — create (admin); `PATCH /api/blog/{id}` — update (admin)
- Banners
  - `GET /api/banners` — list; `POST /api/banners` — create (admin); `PATCH /api/banners/{id}` — update (admin)
- Chat
  - `POST /api/chat/send` — send message (auth)
  - `GET /api/chat/messages` — messages with a user (auth)
  - `GET /api/chat/conversations` — conversation list (auth)
- Wallet
  - `GET /api/me/wallet` — balance (auth); `GET /api/me/wallet/history` — history (auth)
  - `GET /api/me/wallet/deposit-instructions` — instructions (auth)
  - `POST /api/simulate/bank-transfer` — simulated webhook; optional header `X-Webhook-Secret`
- Advertising
  - `GET /api/ads/packages` — available packages
  - `POST /api/ads/requests` — create ad request (shop)
  - `GET /api/ads/requests/my` — my ad requests (auth)
  - `GET /api/ads/active` — currently active ads
  - Admin: `GET /api/admin/ads/requests/pending`, `POST /api/admin/ads/requests/{id}/review`
- CSRF
  - `GET /api/csrf-token` — issue CSRF token and set cookie
- Ops
  - `GET /health` — liveness
  - `GET /ready` — readiness (DB ping)
  - `GET /metrics` — Prometheus text exposition
- Policies
  - `GET /api/policies/terms` — Terms of Service (markdown content)
  - `GET /api/policies/privacy` — Privacy Policy (markdown content)

### Delete endpoints (summary)
- `DELETE /api/me` — permanently deletes the current account; returns `409` if related data prevents deletion.
- `DELETE /api/admin/users/{id}` — deletes a user by ID (admin); returns `409` on integrity violations.

## Request/Response Conventions
- JSON responses for all endpoints via `JsonResponse`; correct HTTP status codes.
- Send `Content-Type: application/json` for JSON bodies; invalid JSON → `400 {error:"Invalid JSON"}`.
- For uploads use `multipart/form-data` with field `file`.
- Pagination shape: `{data: {items[], total, page, per_page}}`.
- Errors: `{error, details?}` with statuses `400/401/403/404/405/422/500` (`405` includes `Allow`).
- `HEAD` behaves like `GET` but returns no body.
- CORS headers are sent on all responses; configure origin via `.env`.
- CSRF (optional): enable with `CSRF_ENABLED=1`; fetch at `GET /api/csrf-token`; send token via header or body for mutating requests.

### HTTP
- `app/Core/Request.php` — Base HTTP request container and parser.
  - Captures from globals via `Request::capture()` and decides subtype based on `Content-Type`.
  - Fields: `method`, `uri`, `headers`, `query`, `body`, `files`; plus per-request `attributes`.
  - Helpers: `getString`, `getInt`, `getBool`, `sanitizeArray`, `getHeaderLine`, `getQueryParams`, `getParsedBody`, `getUploadedFiles`, `withAttribute`, `getAttribute`, `isJson`, `hasJsonError`.
- `app/Core/JsonRequest.php` — Specialized request for JSON.
  - Chosen when `Content-Type` includes `application/json`.
  - Parses `php://input` as JSON; invalid JSON sets `hasJsonError()` to true so router returns `400`.
- `app/Core/FormRequest.php` — Specialized request for form/multipart.
  - Chosen for non-JSON requests.
  - Populates `body` from `$_POST` and `files` from `$_FILES`.
- `app/Core/Response.php` — HTTP response builder.
  - Methods: `json`, `raw`, `jsonError`, `paginated`, `withHeader`, `withStatus`, `send`, `getBody`, `setBody`, `getStatus`.
  - Behavior: for `HEAD` requests, `send()` omits the body (status + headers only).
- `app/Core/JsonResponse.php` — Convenience JSON wrapper.
  - Factories: `ok($data, $status=200)` and `error($message, $status=400, $details=null)`.
  - Used across controllers to standardize success/error payloads.

Where used
- `public/index.php` calls `Request::capture()` before routing.
- Controllers return `JsonResponse::ok()` / `JsonResponse::error()`; unknown routes and exceptions fall back to `Response->json()`.
- Note: `app/Models/AdRequest.php` is a domain model (advertising request), not an HTTP Request.

### Security & Throttling
- Login rate limit: `RATE_LIMIT_LOGIN_MAX` attempts per `RATE_LIMIT_LOGIN_WINDOW` seconds, per identifier+IP.
- Refresh tokens: rotation on refresh; optional binding to UA/IP (`REFRESH_BIND_UA`, `REFRESH_BIND_IP`).
- Password reset: TTL `PASSWORD_RESET_TTL_SECONDS`. In local/dev the token is returned in the API response.
- Email verification: TTL `EMAIL_VERIFY_TTL_SECONDS`. In local/dev the token is returned in the API response.

### Observability (Logging, Errors, Metrics)
- Structured JSON logs with correlation id (`X-Request-Id` header).
- Configure logging via env: `LOG_CHANNEL=stdout|file`, `LOG_PATH=storage/app.log`.
- Error tracking:
  - Rollbar: set `ROLLBAR_ACCESS_TOKEN` to send server‑side exceptions.
  - Sentry: set `SENTRY_DSN` (SDK integration recommended for production).
- Metrics endpoint: `GET /metrics` exposes counters/histograms (per‑process file‑backed aggregation) and DB up gauge.

### Performance & Caching
- HTTP caching: optional ETag + `Cache-Control` for GET responses when `CACHE_ENABLE_ETAG=1` (default). Override `CACHE_MAX_AGE` for max-age.
- Redis/file cache: integrated cache for frequent reads (banners, admin dashboard, active ads, search). Configure Redis via `REDIS_URL` or `REDIS_HOST`/`REDIS_PORT`.
- Uploads → CDN: set `UPLOADS_URL_BASE` to CDN origin (and optionally mount a cloud bucket to `UPLOADS_PATH`) so returned file URLs point to CDN.
- Pagination guards: controllers clamp `per_page` to sensible limits (e.g., 50) to avoid heavy queries.
 - Debug headers: enable `CACHE_DEBUG_HEADER=1` to send `X-Cache: HIT|MISS` (and optional `X-Cache-Keys` with `CACHE_DEBUG_HEADER_KEYS=1`) for cache verification.

#### Redis Cache (Purpose & Usage)
- Purpose: reduce database load and latency for read‑heavy endpoints.
- What we cache: banners list, admin dashboard summary, currently active ads, and aggregated search results.
  - Controllers: `BannerController`, `AdminController`, `AdvertisingController`, `SearchController`.
- How it works: `App\Core\Cache` prefers Redis (via `REDIS_URL` or `REDIS_HOST`/`REDIS_PORT`/`REDIS_PASSWORD`), and transparently falls back to file cache if Redis is unavailable.
- TTL controls (seconds): `BANNERS_CACHE_TTL` (default 60), `DASHBOARD_CACHE_TTL` (30), `ADS_ACTIVE_CACHE_TTL` (30), `SEARCH_CACHE_TTL` (15).
- Key patterns (examples): `banners:list:{pos}:{active}`, `admin:dashboard`, `ads:active:{when}`, `search:{domain}:{q}:{per}`.
- Verify behavior: enable `CACHE_DEBUG_HEADER=1` to see `X-Cache: HIT|MISS` (and optionally `X-Cache-Keys` with `CACHE_DEBUG_HEADER_KEYS=1`).
- Not the same as HTTP caching: ETag/Cache‑Control is client‑side; Redis/file cache is server‑side and applies before querying the database.


### Email
- SendGrid supported via `SENDGRID_API_KEY` (HTTP API).
- From: `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME`.
- Frontend base for links: `FRONTEND_URL` (used in verification/reset links).

## Environment
- Core
  - `APP_URL` — base URL for uploaded file links
  - `CORS_ALLOWED_ORIGIN` — frontend origin (e.g., `http://localhost:5173`)
  - `JWT_SECRET` — HMAC secret for JWT
  - Database: `DB_DRIVER`, `DB_HOST`, `DB_PORT` (default `3307`), `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- Optional
  - Contact: `CONTACT_EMAIL`, `CONTACT_ZALO`, `CONTACT_PHONE`
  - Security headers: `SECURITY_*` (see `.env.example`)
  - CORS extras: `CORS_ALLOW_CREDENTIALS`, `CORS_MAX_AGE`
  - Uploads: `UPLOAD_MAX_BYTES`, `UPLOAD_ALLOWED_EXT`
  - CSRF: `CSRF_ENABLED`, `CSRF_COOKIE_NAME`, `CSRF_HEADER_NAME`, `CSRF_LIFETIME_SECONDS`, `CSRF_SECRET`
  - Webhook: `BANK_WEBHOOK_SECRET`
  - Feature flags: `ENABLE_BANK_SIMULATION` (0/1)
  - Upload security: `UPLOAD_ALLOWED_MIME`, `UPLOAD_AV_SCAN_CMD` (optional AV command)
  - Login throttling: `RATE_LIMIT_LOGIN_MAX`, `RATE_LIMIT_LOGIN_WINDOW`
  - Refresh: `REFRESH_TOKEN_TTL_SECONDS`, `REFRESH_BIND_UA`, `REFRESH_BIND_IP`
  - Password reset: `PASSWORD_RESET_TTL_SECONDS`
  - Email verify: `EMAIL_VERIFY_TTL_SECONDS`
  - Redis & caching: `REDIS_URL`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `CACHE_ENABLE_ETAG`, `CACHE_MAX_AGE`, `BANNERS_CACHE_TTL`, `DASHBOARD_CACHE_TTL`, `ADS_ACTIVE_CACHE_TTL`, `SEARCH_CACHE_TTL`

## Project Structure
- `app/Http/Controllers`: HTTP controllers (Auth, Store/Cafe, Review, Search, Voucher, Promotion, Blog, Banner, Chat, Admin, Wallet, Advertising, CSRF)
- `app/Http/Middleware`: `AuthMiddleware`, `RoleMiddleware` (+ Admin/Shop), `CorsMiddleware`, `CsrfMiddleware` (optional), `SecurityHeadersMiddleware`
- `app/Models`: Data layer via PDO (User, Cafe, Voucher, Promotion, Wallet, AdRequest, Blog, Banner, Message, Review, Image, etc.)
- `app/Core`: Router, Request/Response, DB, Storage, Validator, Auth (JWT)
- `routes/`: `web.php` (root), `api.php` (API)
- `database/`: SQL migrations and seeders
- `public/`: `index.php` front controller

## Tests
- Run: `composer test` (alias for `php tests/run.php`)

## Troubleshooting
- Ensure MySQL is on `3307` (or change `.env`).
- Verify PHP has `pdo_mysql` enabled.
- If uploads fail (e.g., no `upload_tmp_dir`), `Storage` falls back to `rename()`.
- Migrate script DROPS the database in `.env`; use a dev DB only.
