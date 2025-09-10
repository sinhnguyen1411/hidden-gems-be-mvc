# Hidden Gems Backend — Developer Guide

REST API backend for the Hidden Gems app. Lightweight PHP stack with a Laravel‑like structure (controllers, middleware, routes), JSON‑first responses, and PDO models.

## Changelog
- 2025‑09‑10
  - Migrations now read DB settings from `.env` (no hard‑coding).
  - Added PowerShell smoke test `scripts/smoke.ps1` to exercise core flows end‑to‑end.
  - Default MySQL port in `.env.example` is `3307` to match the current environment.
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
3) Migrate database (DROPS and recreates DB): `php database/migrations/migrate.php`
4) Seed demo data (optional): `php database/seeders/seed.php`
5) Start dev server: `php -S 127.0.0.1:8000 -t public`
6) Health check: `curl http://127.0.0.1:8000/` → `{"message":"Hidden Gems API"}`

## Smoke Test (End‑to‑End)
- Script (PowerShell): `scripts/smoke.ps1`
  - Windows PowerShell: `./scripts/smoke.ps1`
  - PowerShell Core (macOS/Linux): `pwsh ./scripts/smoke.ps1`
  - Override base URL: `BASE_URL=http://localhost:8000 ./scripts/smoke.ps1`
- Flow: admin/shop auth → set role → wallet deposit (simulate) → ensure store → ads (create + admin approve) → promotions (create/apply/approve) → vouchers (create/assign/list) → blog (create/update) → banners (create/list) → chat (send/list) → cafes (list/review) → wallet (me/history) → admin dashboard → search → CSRF token.

## API Overview
- Auth
  - `POST /api/auth/register` — create user
  - `POST /api/auth/login` — email or username + password → `access_token`, `refresh_token`
  - `POST /api/auth/refresh` — refresh access token
  - `GET /api/users` — list users (admin)
  - `DELETE /api/me` — delete current user (auth)
- Users & Admin
  - `GET /api/admin/dashboard` — summary stats (admin)
  - `POST /api/admin/users/role` — set user role (admin)
  - `DELETE /api/admin/users/{id}` — delete user by ID (admin)
  - `GET /api/admin/pending-stores` — stores pending approval (admin)
  - `POST /api/admin/stores/{id}/approve` — approve/reject store (admin) `{action:'approve'|'reject'}`
  - `GET /api/contact` — contact/deep-link info
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
