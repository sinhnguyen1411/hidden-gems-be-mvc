hidden-gems-backend/
├─ public/
│  └─ index.php                # Front controller, vào app qua router
├─ app/
│  ├─ Controllers/
│  │  ├─ AuthController.php
│  │  ├─ UserController.php
│  │  ├─ CafeController.php
│  │  ├─ ReviewController.php
│  │  ├─ CategoryController.php
│  │  ├─ SearchController.php
│  │  └─ UploadController.php
│  ├─ Models/                  # ORM tự viết hoặc PDO models
│  │  ├─ User.php ├─ Cafe.php ├─ Review.php ├─ Category.php └─ Media.php
│  ├─ Services/                # Nghiệp vụ (AuthService, CafeService…)
│  ├─ Repositories/            # Lớp truy vấn DB (UserRepository…)
│  ├─ Middlewares/
│  │  ├─ AuthMiddleware.php    # JWT/Session guard
│  │  └─ AdminMiddleware.php
│  ├─ Policies/                # Phân quyền (optional)
│  ├─ Validators/              # Validate request
│  ├─ Helpers/                 # Hàm dùng chung (slug, paging…)
│  └─ Kernel.php               # Đăng ký middleware, providers
├─ routes/
│  └─ api.php                  # Khai báo tất cả endpoint REST
├─ config/
│  ├─ app.php ├─ database.php ├─ mail.php └─ filesystem.php
├─ database/
│  ├─ migrations/              # file tạo bảng
│  ├─ seeders/                 # dữ liệu mẫu (categories, cafés…)
│  └─ factories/               # generate dữ liệu test
├─ storage/
│  ├─ logs/                    # log app
│  └─ uploads/                 # ảnh quán, avatar (nếu lưu local)
├─ tests/
│  ├─ Feature/ └─ Unit/
├─ bootstrap/
│  └─ app.php                  # load env, autoload, khởi tạo router, DB
├─ vendor/                     # Composer
├─ .env                        # biến môi trường (DB_*, JWT_SECRET, …)
├─ composer.json               # dependency, autoload PSR-4
├─ phpunit.xml                 # cấu hình test
├─ Dockerfile / docker-compose.yml (optional)
└─ README.md / docs/

# Hidden Gems Backend (PHP MVC Minimal)

Features: Auth (register/login with JWT), Cafes (list/show), Reviews (list/create).


API endpoints khớp với frontend
Auth
POST /api/auth/register
POST /api/auth/login → trả access_token (JWT) + refresh_token
POST /api/auth/refresh
POST /api/auth/logout
GET /api/auth/me (JWT)

Users
GET /api/users/:id
PATCH /api/users/:id (self or admin)
GET /api/users/:id/favorites / POST /api/users/:id/favorites

Cafés
GET /api/cafes (paging, sort, filter: category, rating, city, distance)
GET /api/cafes/:id
POST /api/cafes (role=owner/admin)
PATCH /api/cafes/:id
DELETE /api/cafes/:id (owner/admin)
POST /api/cafes/:id/media (upload ảnh)

Reviews
GET /api/cafes/:id/reviews
POST /api/cafes/:id/reviews
PATCH /api/reviews/:id
DELETE /api/reviews/:id (owner review or admin)

Categories
GET /api/categories
POST /api/categories (admin)

Search
GET /api/search?q=…&lat=…&lng=… (full-text + geo)

Admin
GET /api/admin/dashboard/metrics
PATCH /api/admin/users/:id/role …

4) Luồng xử lý (MVC PHP thuần)
public/index.php: nhận request → bootstrap/app.php (autoload, .env, kết nối DB, đăng ký route) → routes/api.php khớp URI → Controller gọi Service → Repository/Model (PDO) thao tác DB → trả JSON.
Middlewares: AuthMiddleware kiểm tra JWT, AdminMiddleware check role.
Validators: validate body/query (VD: Respect\Validation).

5) Composer packages thường dùng
vlucas/phpdotenv (đọc .env)
firebase/php-jwt (JWT)
monolog/monolog (logging)
respect/validation (validate)
guzzlehttp/guzzle (HTTP client khi cần)
ramsey/uuid (UUID)
(Nếu muốn router gọn): nikic/fast-route
(Nếu muốn ORM nhẹ): illuminate/database (Eloquent độc lập) hoặc dùng PDO thuần.




## Setup
```bash
composer install
cp .env.example .env
# chỉnh DB_* và JWT_SECRET trong .env

# Tạo DB (MySQL)
# MySQL> CREATE DATABASE hidden_gems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Migration
mysql -u root -p hidden_gems < database/migrations/2025_08_18_000000_init.sql

# Seed
php database/seeders/seed.php

# Chạy server
php -S 127.0.0.1:8000 -t public
```

## API
- POST /api/auth/register  {name,email,password}
- POST /api/auth/login     {email,password} -> {access_token, user}
- GET  /api/cafes?per_page=10&page=1
- GET  /api/cafes/{id}
- GET  /api/cafes/{id}/reviews
- POST /api/cafes/{id}/reviews (Bearer token) {rating,content}
