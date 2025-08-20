# Hidden Gems Backend

Hidden Gems is a community-driven platform for discovering and reviewing cafés.
This repository provides the lightweight PHP MVC backend that powers its RESTful API.

## Features
- JWT authentication with register, login, refresh and logout flows
- User profiles with favorites
- Café CRUD with media uploads and filtering by category, rating, city and distance
- Review creation and moderation
- Category management
- Search combining full‑text and geo queries
- Basic admin dashboard and user role management
- Role-based access control via middlewares

## Directory Structure
```
hidden-gems-backend/
├── app/             # Controllers, services, models and helpers
├── bootstrap/       # Framework bootstrap and service container
├── config/          # Environment configuration
├── database/        # Migrations, seeders and factories
├── public/          # Front controller (index.php)
├── routes/          # API routes
├── tests/           # Minimal test runner and specs
└── vendor/          # Composer dependencies
```

## Installation
```bash
composer install
cp .env.example .env
# update DB_* values and JWT_SECRET in .env

# Create database (MySQL example)
mysql -u root -p -e "CREATE DATABASE hidden_gems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migration and seed data
mysql -u root -p hidden_gems < database/migrations/2025_08_18_000000_init.sql
php database/seeders/seed.php
```

## Running the Server
```bash
php -S 127.0.0.1:8000 -t public
```

## Testing
Run the lightweight test suite:
```bash
composer test
```

## API Overview
- `POST /api/auth/register`
- `POST /api/auth/login` → returns `{access_token, user}`
- `POST /api/auth/refresh`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/cafes?per_page=10&page=1`
- `GET /api/cafes/{id}`
- `POST /api/cafes/{id}/reviews`
- `GET /api/users/{id}`
- `PATCH /api/users/{id}`
- `GET /api/categories`

More endpoints are available in `routes/api.php`.

## Authorization & Middlewares

JWT tokens authenticate requests while middlewares guard access to specific resources.

- `AuthMiddleware` extracts and verifies the token, attaching the user claims to the request.
- `RoleMiddleware` provides a base check for allowed roles.
- `AdminMiddleware` and `ShopMiddleware` extend `RoleMiddleware` to restrict routes to admins or shop owners.

Attach these middlewares to routes to enforce permissions:

```php
$router->get('/admin/users', 'AdminController@index')
       ->middleware([AuthMiddleware::class, AdminMiddleware::class]);
```


