# Hidden Gems Backend — Frontend Integration Guide

This backend exposes a compact REST API for the Hidden Gems app. This guide is tailored for frontend engineers: quick setup, conventions, endpoints grouped by screens, sample requests/responses, and direct code pointers.

## Changelog
- 2025-09-04: Wallet deposits (demo) and Advertising requests with admin approval. New endpoints documented below.
- 2025-09-01: Introduced specialized requests (`JsonRequest`, `FormRequest`) and unified JSON responses via `JsonResponse`. All endpoints consistently return JSON with accurate HTTP status codes. CORS now includes the CSRF header when enabled.

## Quick Start
- Requirements: PHP 8.2+, Composer, MySQL 8
- Install: `composer install`
- Env: copy `.env.example` → `.env` and set `DB_*`, `JWT_SECRET`, `APP_URL`, `CORS_ALLOWED_ORIGIN`, contact values
- Dev server: `php -S 127.0.0.1:8000 -t public` (base URL `http://127.0.0.1:8000`)
- Migrate: `php database/migrations/migrate.php` (DROPS and recreates DB)
- Seed (optional): `php database/seeders/seed.php`

## Conventions
- Base URL: your `APP_URL` (e.g., `http://127.0.0.1:8000`)
- Auth: Bearer JWT in `Authorization` header
- Roles: `admin`, `shop`, `customer`
- Content types: JSON for POST/PATCH unless noted; uploads via multipart `file`
- Pagination: `page` (1-based), `per_page` (1–50)
- Errors: JSON `{error: string, details?: object}` with appropriate status (400/401/403/404/405/422/500)
- CORS: Allowed origin via `CORS_ALLOWED_ORIGIN`; preflight handled
- CSRF: Optional anti-CSRF for state-changing requests; see CSRF section

## Auth & Users
- Register: POST `/api/auth/register` → `{message, user_id}`
- Login: POST `/api/auth/login` → `{access_token, refresh_token, user}`
- Refresh: POST `/api/auth/refresh` → `{access_token}`
- Admin list users: GET `/api/users` (auth + admin) → `{data: User[]}`

Example (login)
```
curl -X POST "$BASE/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin123"}'
```
Attach token:
```
-H "Authorization: Bearer $ACCESS_TOKEN"
```

## Home (shared)
- Global search: GET `/api/search?q=term` → `{query, stores[], blogs[], vouchers[], promotions[]}`
- Banners: GET `/api/banners?vi_tri=home` → `{data: Banner[]}`
- Contact info: GET `/api/contact` → `{email, zalo, phone}`

Example (search)
```
curl "$BASE/api/search?q=cafe"
```

## Stores (public)
- List: GET `/api/cafes?page=&per_page=&category_id?` → `{data: {items[], total, page, per_page}}`
- Search: GET `/api/cafes/search?q=term` → `{data: {items[], total, page, per_page}}`
- Detail: GET `/api/cafes/{id}` → `{data: Store}`
- Reviews list: GET `/api/cafes/{id}/reviews?page=&per_page=` → `{data: {items[], total, page, per_page}}`
- Create review: POST `/api/cafes/{id}/reviews` (auth) body `{rating:1..5, content}` → `{message, review_id}`

Example (review)
```
curl -X POST "$BASE/api/cafes/1/reviews" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"rating":5,"content":"Great!"}'
```

## My Store (shop)
- My stores: GET `/api/me/stores` (auth) → `{data: {items[], total, page, per_page}}`
- Create store: POST `/api/stores` (auth + shop) `{ten_cua_hang, mo_ta?, id_trang_thai?, id_vi_tri?}` → `{message, id_cua_hang}` (defaults to pending status)
- Update store: PATCH `/api/stores/{id}` (auth owner/admin) → `{message}`
- Create branch: POST `/api/stores/{id}/branches` (auth + shop) → `{message, id_cua_hang}`
- Upload image: POST `/api/stores/{id}/images` (auth) multipart `file`, optional `is_avatar=1` → `{message, image_id, url}`

Example (upload image)
```
curl -X POST "$BASE/api/stores/1/images" \
  -H "Authorization: Bearer $TOKEN" \
  -F file=@/path/to/photo.jpg -F is_avatar=1
```

## Vouchers (shop/admin)
- Create: POST `/api/vouchers` `{ma_voucher, ten_voucher?, gia_tri_giam, loai_giam_gia:'percent'|'amount', ngay_het_han?, so_luong_con_lai}` → `{message, id_voucher}`
- Assign to store: POST `/api/vouchers/assign` `{id_voucher, id_cua_hang}` → `{message}`
- Store vouchers: GET `/api/stores/{id}/vouchers` → `{data: Voucher[]}`

## Promotions
- Admin create: POST `/api/promotions` `{ten_chuong_trinh, mo_ta?, ngay_bat_dau, ngay_ket_thuc, loai_ap_dung?, pham_vi_ap_dung?}` → `{message, id_khuyen_mai}`
- Shop apply: POST `/api/promotions/{id}/apply` `{id_cua_hang}` → `{message}`
- Admin review: POST `/api/promotions/{id}/review` `{id_cua_hang, trang_thai:'da_duyet'|'tu_choi'}` → `{message}`
- Store promos: GET `/api/stores/{id}/promotions` → `{data: Promotion[]}`

## Blog
- List/search: GET `/api/blog?q=&page=&per_page=` → `{data: {items[], total, page, per_page}}`
- Create: POST `/api/blog` (auth + admin) `{tieu_de, noi_dung}` → `{message, id_blog}`
- Update: PATCH `/api/blog/{id}` (auth + admin) `{tieu_de, noi_dung}` → `{message}`

## Chat
- Send: POST `/api/chat/send` (auth) `{noi_dung, to_user_id?}` → `{message, id_tin_nhan}` (defaults to first admin if `to_user_id` omitted)
- Messages: GET `/api/chat/messages?with={id_user}&limit=&offset=` (auth) → `{data: Message[]}`
- Conversations: GET `/api/chat/conversations` (auth) → `{data: {id_user, username, last_id, last_time}[]}`

## Admin
- Dashboard: GET `/api/admin/dashboard` → `{data: {users, shops, stores, reviews, vouchers, promos}}`
- Set role: POST `/api/admin/users/role` `{id_user, role}` → `{message}`
- Pending stores: GET `/api/admin/pending-stores` → `{data: Store[]}`
- Approve/reject store: POST `/api/admin/stores/{id}/approve` `{action:'approve'|'reject'}` → `{message}`

## Request/Response Details
- Always JSON responses: server uses a dedicated `JsonResponse` so all endpoints return `application/json; charset=utf-8`.
- JSON requests: send `Content-Type: application/json` for bodies; invalid JSON returns `400 {error:"Invalid JSON"}`.
- Form/multipart requests: for uploads or form posts with files, send `multipart/form-data` with field `file` (see endpoints that mention uploads).
- Pagination: `{data: {items[], total, page, per_page}}` for list endpoints.
- Error shape: `{error, details?}` and appropriate status (`400/401/403/404/405/422/500`). `405` also includes an `Allow` header.
- HEAD: handled as GET but without a response body (headers + status only).

### CSRF (optional)
- Enable by setting `CSRF_ENABLED=1` in `.env`
- Retrieve token: `GET /api/csrf-token` → sets cookie `XSRF-TOKEN` and returns `{token, expires_at}`
- Send token on state-changing requests (POST/PUT/PATCH/DELETE):
  - Header: `X-CSRF-Token: <token>` (recommended), or
  - Body field: `csrf_token`, or
  - Cookie only (double-submit) when appropriate
- Configure names/lifetime in env: `CSRF_COOKIE_NAME`, `CSRF_HEADER_NAME`, `CSRF_LIFETIME_SECONDS`

### Security Headers
- Defaults sent on every response (configurable):
  - HSTS: `Strict-Transport-Security`
  - CSP: `Content-Security-Policy`
  - No sniff: `X-Content-Type-Options: nosniff`
  - Frame deny: `X-Frame-Options: DENY`
  - Referrer: `Referrer-Policy: no-referrer`
  - Permissions-Policy: conservative defaults
- Customize via `.env` (see Security headers and CORS extras in `.env.example`)

## Environment
- `APP_URL`: base URL to prefix uploaded file links (e.g., `http://127.0.0.1:8000`)
- `CORS_ALLOWED_ORIGIN`: frontend origin (e.g., `http://localhost:5173`)
- `CORS_ALLOW_CREDENTIALS=1` (optional), `CORS_MAX_AGE=86400` (optional)
- `CONTACT_EMAIL`, `CONTACT_ZALO`, `CONTACT_PHONE`: used by `/api/contact`
- Uploads: `UPLOAD_MAX_BYTES` (e.g., `5242880`), `UPLOAD_ALLOWED_EXT` (e.g., `jpg,jpeg,png,webp`)
- Bank webhook (demo): `BANK_WEBHOOK_SECRET` (optional) to restrict the simulated bank transfer endpoint

## Mapping UI → API
- Home: banners `/api/banners?vi_tri=home`, search `/api/search?q=...`, contact `/api/contact`
- Store detail: `/api/cafes/{id}` and `/api/cafes/{id}/reviews`; review form posts to `/reviews`
- Shop dashboard: `/api/me/stores`; image uploads; vouchers/promotions
- Admin CMS: blog & banner endpoints; users/roles; store approvals; promo reviews

## Code Map (jump to files)
- Routes: `routes/api.php:1`
- Request/Response: `app/Core/Request.php:1`, `app/Core/Response.php:1`
- Specialized requests: `app/Core/JsonRequest.php:1`, `app/Core/FormRequest.php:1`
- JSON response helpers: `app/Core/JsonResponse.php:1`
- Router: `app/Core/Router.php:1`
- Auth (JWT): `app/Core/Auth.php:1`
- CORS: `app/Middlewares/CorsMiddleware.php:1`
- Upload storage: `app/Core/Storage.php:1`
- Controllers: `app/Controllers/*.php` (e.g., Search, Store, Voucher, Promotion, Blog, Banner, Chat, Admin)
- Models: `app/Models/*.php`

## Database
- Base schema: `database/migrations/2025_08_18_000000_init.sql:1`
- Extra features: `database/migrations/2025_09_01_000001_extra_features.sql:1`
  - Banners (`banner`), Chat (`tin_nhan`), Branches (`cua_hang.id_cua_hang_cha`), Promo approvals (`khuyen_mai_cua_hang` fields)
- Seeder: `database/seeders/seed.php:1` (demo data)
- Warning: `database/migrations/migrate.php:1` DROPS database before recreating

## Testing
- Run unit tests: `composer test`

## Request/Response Classes
| Type     | Class          | Description                                                                                                                                                   | Source                                          |
| -------- | -------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------- |
| Request  | `Request`      | Core HTTP request handler that captures method, URI, headers, query/body data, uploaded files, and provides helpers for JSON detection and data sanitization. | `app/Core/Request.php`:codex-file-citation      |
| Request  | `FormRequest`  | Marker subclass for form or multipart submissions; relies on base `Request` helpers.                                                                          | `app/Core/FormRequest.php`:codex-file-citation  |
| Request  | `JsonRequest`  | Marker subclass for JSON requests using `Request`'s parsing and helper methods.                                                                               | `app/Core/JsonRequest.php`:codex-file-citation  |
| Response | `Response`     | Builds HTTP responses with JSON payloads, error handling, pagination support, header/status customization, and sending logic.                                 | `app/Core/Response.php`:codex-file-citation     |
| Response | `JsonResponse` | Static convenience methods for returning JSON success or error responses.                                                                                     | `app/Core/JsonResponse.php`:codex-file-citation |

## Tips
- Prefer tolerant parsing (ignore unknown fields) and check for `{error}` in responses
- Debounce search requests client-side
- Cache banners and contact for the session when appropriate

## Wallet & Ads

### Wallet (demo deposit)
- Balance: GET `/api/me/wallet` (auth) -> `{data:{so_du:number}}`
- History: GET `/api/me/wallet/history?limit=&offset=` (auth) -> `{data: Transaction[]}`
- Deposit instructions: GET `/api/me/wallet/deposit-instructions` (auth) -> bank info and required content syntax
- Simulated bank webhook: POST `/api/simulate/bank-transfer` (demo; not real payment)
  - Headers (optional): `X-Webhook-Secret: <BANK_WEBHOOK_SECRET>` if set
  - Body (JSON): `{ "noi_dung": "HG NAP {id_user}", "so_tien": 25.00 }`
  - Behavior: Parses `noi_dung` for `HG NAP <id_user>` and credits that user's wallet

Notes
- Prefix `HG` stands for Hidden Gems. Required syntax: `HG NAP {id_user}`.
- See code: `app/Controllers/WalletController.php:1`, `app/Models/Wallet.php:1`
- DB schema: `database/migrations/2025_09_04_000010_wallet_and_ads.sql:1` (tables `vi_tien`, `giao_dich_vi`)

### Advertising (shop + admin)
- Packages: GET `/api/ads/packages` -> `{data:[{ma_goi:'1d'|'1w'|'1m', so_ngay, gia_usd, ten}]}`
  - Defaults: 1d=$1, 1w=$5, 1m=$18
- Create request (shop): POST `/api/ads/requests` (auth + shop)
  - Body: `{ id_cua_hang:number, goi:'1d'|'1w'|'1m', ngay_bat_dau:'YYYY-MM-DD' }`
  - Rules: `ngay_bat_dau` must be at least 1 day in advance. Wallet is charged immediately; request moves to `cho_duyet`.
  - Response: `{message, id_yeu_cau}` or `{error, so_du?}` when insufficient balance
- My requests: GET `/api/ads/requests/my` (auth) -> `{data:{items[], total, page, per_page}}`
- Admin pending: GET `/api/admin/ads/requests/pending` (auth + admin) -> `{data:{items[], total, page, per_page}}`
- Admin review: POST `/api/admin/ads/requests/{id}/review` (auth + admin)
  - Body: `{ trang_thai: 'da_duyet' | 'tu_choi' }`
  - Reject automatically refunds the wallet
- Active ads (for homepage): GET `/api/ads/active?tai_ngay=YYYY-MM-DD` -> `{data: Ad[]}`

Notes
- See code: `app/Controllers/AdvertisingController.php:1`, `app/Models/AdRequest.php:1`
- DB schema: `database/migrations/2025_09_04_000010_wallet_and_ads.sql:1` (table `yeu_cau_quang_cao`)

### Example flows
1) Deposit then request an ad
```
# 1. Get deposit syntax for the current user
GET /api/me/wallet/deposit-instructions

# 2. Simulate a deposit (demo)
POST /api/simulate/bank-transfer
{ "noi_dung": "HG NAP 42", "so_tien": 20 }

# 3. Buy a 1-week ad for store 123 starting next week
POST /api/ads/requests
{ "id_cua_hang": 123, "goi": "1w", "ngay_bat_dau": "2025-09-12" }

# 4. Admin approves
POST /api/admin/ads/requests/1/review
{ "trang_thai": "da_duyet" }

# 5. Frontend shows active ads on homepage
GET /api/ads/active?tai_ngay=2025-09-12
```
