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
