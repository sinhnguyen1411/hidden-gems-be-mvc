# Hidden Gems Backend
Backend service for the Hidden Gems cafÃ© discovery app. It provides a simple PHP MVC API that supports authentication, cafÃ© listings, and reviews.
## Requirements
- PHP 8.2+
- Composer
- MySQL 8
Hidden Gems is a community-driven platform for discovering and reviewing cafÃ©s.
This repository provides the lightweight PHP MVC backend that powers its RESTful API.
## API Overview
- `POST /api/auth/register`
- `POST /api/auth/login` â†’ returns `{access_token, user}`
- `POST /api/auth/refresh`
- `GET /api/users` (admin only)
- `GET /api/cafes?per_page=10&page=1`
- `GET /api/cafes/search?q=term`
- `GET /api/cafes/{id}`
- `GET /api/cafes/{id}/reviews`
- `POST /api/cafes/{id}/reviews`

More endpoints are available in `routes/api.php`.

## Authorization & Middlewares

JWT tokens authenticate requests while middlewares guard access to specific resources.

- `AuthMiddleware` extracts and verifies the token, attaching the user claims to the request.
- `RoleMiddleware` provides a base check for allowed roles.
- `AdminMiddleware` and `ShopMiddleware` extend `RoleMiddleware` to restrict routes to admins or shop owners.

### User Roles

Accounts can be one of three roles: `admin`, `shop`, or `customer`.
All registrations create `customer` accounts and only admins may promote users to `shop` or `admin`.

Attach these middlewares to routes to enforce permissions:

```php
$router->get('/admin/users', 'AdminController@index')
       ->middleware([AuthMiddleware::class, AdminMiddleware::class]);
```


## Features
- JWT authentication with register, login and refresh flows
- User profiles with favorites
- CafÃ© CRUD with media uploads and filtering by category, rating, city and distance
- Review creation and moderation
- Category management
- Search combining fullâ€‘text and geo queries
- Basic admin dashboard and user role management
- Role-based access control via middlewares

## Directory Structure
```
hidden-gems-backend/
â”œâ”€â”€ app/             # Controllers, services, models and helpers
â”œâ”€â”€ bootstrap/       # Framework bootstrap and service container
â”œâ”€â”€ config/          # Environment configuration
â”œâ”€â”€ database/        # Migrations, seeders and factories
â”œâ”€â”€ public/          # Front controller (index.php)
â”œâ”€â”€ routes/          # API routes
â”œâ”€â”€ tests/           # Minimal test runner and specs
â””â”€â”€ vendor/          # Composer dependencies
```
main


## Installation

```bash
git clone <repo> hidden-gems-backend
cd hidden-gems-backend
composer install
cp .env.example .env
```

Update `.env` with database credentials and a `JWT_SECRET`.

### Database



### Database


Create the database and load the schema and seed data:

```bash
mysql -u root -p -e "CREATE DATABASE hidden_gems CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hidden_gems < database/migrations/2025_08_18_000000_init.sql
php database/seeders/seed.php
```

The seeder inserts an initial admin account:

- Email: `admin@example.com`
- Password: `password`

### Running the server

```bash
php -S 127.0.0.1:8000 -t public
```

## Permissions

Users belong to one of three roles:

| Role     | Description |
|----------|-------------|
| `customer` | default role for new registrations |
| `shop` | cafÃ© owners who manage listings |
| `admin` | full access to manage users and cafÃ©s |

Registration always creates `customer` accounts. Only administrators can promote users to `shop` or `admin`. Middleware enforces access:

- `AuthMiddleware` â€“ requires a valid JWT.
- `AdminMiddleware` â€“ restricts routes to admins.
- `ShopMiddleware` â€“ restricts routes to shop owners.

## API

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Create a customer account (name, email, password). |
| POST | `/api/auth/login` | Obtain `{access_token, user}`. |
| POST | `/api/auth/refresh` | Get a new access token using a refresh token. |
| GET | `/api/users` | List all users (admin only). |

### CafÃ©s
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/cafes` | Paginated list of cafÃ©s. Supports `per_page` and `page` query parameters. |
| GET | `/api/cafes/search` | Fullâ€‘text search of cafÃ©s by `q`. |
| GET | `/api/cafes/{id}` | Retrieve cafÃ© details. |

### Reviews
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/cafes/{id}/reviews` | List reviews for a cafÃ©. |
| POST | `/api/cafes/{id}/reviews` | Create a review (requires authentication). |

The full set of routes lives in [`routes/api.php`](routes/api.php).

## Testing with Postman

1. Start the PHP server at `http://127.0.0.1:8000`.
2. In Postman, set a collection or environment variable `baseUrl` to `http://127.0.0.1:8000`.
3. **Register** â€“ send `POST {{baseUrl}}/api/auth/register` with JSON body  
   `{ "name": "Alice", "email": "alice@example.com", "password": "secret" }`
4. **Login** â€“ send `POST {{baseUrl}}/api/auth/login` with the same credentials to receive an `access_token`.
5. For protected endpoints, add an `Authorization` header:  
   `Bearer <access_token>`.
6. Example: request `GET {{baseUrl}}/api/users` with the token from an admin account.
7. Use `POST {{baseUrl}}/api/auth/refresh` with `{ "refresh_token": "<token>" }` to renew an access token.

## Automated Tests

Run the lightweight test suite:

```bash
composer test
```

