# Hidden Gems Backend

Backend service for the Hidden Gems café discovery app. It provides a simple PHP MVC API that supports authentication, café listings, and reviews.

## Requirements
- PHP 8.2+
- Composer
- MySQL 8

## Installation

```bash
git clone <repo> hidden-gems-backend
cd hidden-gems-backend
composer install
cp .env.example .env
```

Update `.env` with database credentials and a `JWT_SECRET`.

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
| `shop` | café owners who manage listings |
| `admin` | full access to manage users and cafés |

Registration always creates `customer` accounts. Only administrators can promote users to `shop` or `admin`. Middleware enforces access:

- `AuthMiddleware` – requires a valid JWT.
- `AdminMiddleware` – restricts routes to admins.
- `ShopMiddleware` – restricts routes to shop owners.

## API

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/auth/register` | Create a customer account (name, email, password). |
| POST | `/api/auth/login` | Obtain `{access_token, user}`. |
| POST | `/api/auth/refresh` | Get a new access token using a refresh token. |
| GET | `/api/users` | List all users (admin only). |

### Cafés
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/cafes` | Paginated list of cafés. Supports `per_page` and `page` query parameters. |
| GET | `/api/cafes/search` | Full‑text search of cafés by `q`. |
| GET | `/api/cafes/{id}` | Retrieve café details. |

### Reviews
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/cafes/{id}/reviews` | List reviews for a café. |
| POST | `/api/cafes/{id}/reviews` | Create a review (requires authentication). |

The full set of routes lives in [`routes/api.php`](routes/api.php).

## Testing with Postman

1. Start the PHP server at `http://127.0.0.1:8000`.
2. In Postman, set a collection or environment variable `baseUrl` to `http://127.0.0.1:8000`.
3. **Register** – send `POST {{baseUrl}}/api/auth/register` with JSON body  
   `{ "name": "Alice", "email": "alice@example.com", "password": "secret" }`
4. **Login** – send `POST {{baseUrl}}/api/auth/login` with the same credentials to receive an `access_token`.
5. For protected endpoints, add an `Authorization` header:  
   `Bearer <access_token>`.
6. Example: request `GET {{baseUrl}}/api/users` with the token from an admin account.
7. Use `POST {{baseUrl}}/api/auth/refresh` with `{ "refresh_token": "<token>" }` to renew an access token.

## Automated Tests

Run the lightweight test suite:

```bash
composer test
```
