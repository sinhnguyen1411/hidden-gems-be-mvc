# Hidden Gems Backend — Frontend Integration Guide

This backend exposes a compact REST API for the Hidden Gems app. This guide is tailored for frontend engineers: quick setup, conventions, endpoints grouped by screens, sample requests/responses, and direct code pointers.

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
- JSON: Use `Content-Type: application/json` for JSON bodies; invalid JSON returns `400 {error:"Invalid JSON"}`
- Uploads: multipart `file`; limits: default 5MB; allowed extensions `jpg,jpeg,png,gif,webp` (configurable)
- Pagination responses: `{data: {items[], total, page, per_page}}`
- Errors: `{error, details?}`; 404 and 405 include CORS headers
- HEAD: treated as GET without a response body

## Environment
- `APP_URL`: base URL to prefix uploaded file links (e.g., `http://127.0.0.1:8000`)
- `CORS_ALLOWED_ORIGIN`: frontend origin (e.g., `http://localhost:5173`)
- `CORS_ALLOW_CREDENTIALS=1` (optional), `CORS_MAX_AGE=86400` (optional)
- `CONTACT_EMAIL`, `CONTACT_ZALO`, `CONTACT_PHONE`: used by `/api/contact`
- Uploads: `UPLOAD_MAX_BYTES` (e.g., `5242880`), `UPLOAD_ALLOWED_EXT` (e.g., `jpg,jpeg,png,webp`)

## Mapping UI → API
- Home: banners `/api/banners?vi_tri=home`, search `/api/search?q=...`, contact `/api/contact`
- Store detail: `/api/cafes/{id}` and `/api/cafes/{id}/reviews`; review form posts to `/reviews`
- Shop dashboard: `/api/me/stores`; image uploads; vouchers/promotions
- Admin CMS: blog & banner endpoints; users/roles; store approvals; promo reviews

## Code Map (jump to files)
- Routes: `routes/api.php:1`
- Request/Response: `app/Core/Request.php:1`, `app/Core/Response.php:1`
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

## Tips
- Prefer tolerant parsing (ignore unknown fields) and check for `{error}` in responses
- Debounce search requests client-side
- Cache banners and contact for the session when appropriate

