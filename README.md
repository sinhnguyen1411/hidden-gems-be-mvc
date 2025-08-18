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
