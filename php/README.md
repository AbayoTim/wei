# WEI PHP Backend

The sole backend for the WEI website. Serves all `/api/...` endpoints and static frontend files from the parent `wei/` directory.

## Requirements

- PHP 8.1+
- SQLite3 extension (`php-sqlite3`) for SQLite mode (default)
- MySQL 8.0+ and the PDO MySQL extension (`php-mysql`) when using MySQL mode
- Apache with `mod_rewrite` **or** PHP built-in server

## Setup

### 1. Configure environment

```bash
cp .env.example .env
```

Edit `.env` and set at minimum:

| Variable | Description |
|---|---|
| `JWT_SECRET` | Long random string (run `php -r "echo bin2hex(random_bytes(32));"`) |
| `SMTP_HOST/PORT/USER/PASS` | Your SMTP credentials |
| `EMAIL_FROM` | From address for outgoing emails |
| `ADMIN_EMAIL` | Your admin email |
| `ADMIN_PASSWORD` | Initial admin password |
| `FRONTEND_URL` | Public URL (used for CORS & email links) |
| `DB_DRIVER` | `sqlite` (default) or `mysql` |
| `DB_HOST/PORT/NAME/USER/PASS` | MySQL credentials (only when `DB_DRIVER=mysql`) |

### 2. Run (development)

```bash
cd php
php -S 0.0.0.0:8000
```

The SQLite database is created automatically at `php/data/wei.db` on first request.

To use MySQL instead, set `DB_DRIVER=mysql` and fill in the `DB_HOST/PORT/NAME/USER/PASS` variables. All tables are created automatically on first request.

### 3. Deploy (Apache)

Point your `DocumentRoot` to the `php/` directory (or a symlink). The `.htaccess` routes all requests through `index.php`.

```apache
<Directory /path/to/wei/php>
    AllowOverride All
    Require all granted
</Directory>
```

### 4. Frontend

The existing HTML frontend calls `/api/...`. If the PHP backend runs at a different base path, update `API_BASE` in `js/api.js`.

## API Endpoints

All `/api/*` routes are handled by `index.php`. See `index.php` for the full route list.

## Directory structure

```
php/
├── index.php            Entry point / front controller
├── .htaccess            Apache URL rewriting
├── .env                 Local environment (not committed)
├── config/
│   ├── config.php       Load .env, define constants
│   └── database.php     SQLite setup, migrations, seed
├── src/
│   ├── JWT.php          HS256 JWT (no dependencies)
│   ├── Router.php       HTTP router with named params
│   ├── Request.php      Request wrapper
│   ├── Response.php     JSON/file/redirect helpers
│   ├── Email.php        SMTP mailer + HTML templates
│   ├── Helpers.php      Slug, token, paginate, etc.
│   └── RateLimit.php    SQLite-backed rate limiting
├── middleware/
│   ├── Auth.php         JWT authentication
│   └── Honeypot.php     Bot prevention
├── models/              PDO model classes (one per table)
├── controllers/         Business logic (mirrors Node.js controllers)
├── uploads/             User-uploaded files
│   ├── receipts/
│   ├── images/
│   ├── videos/
│   └── docs/
└── data/
    └── wei.db           SQLite database (auto-created)
```

## Default admin credentials

Set via `.env`. After first run you will see them in the PHP error log. **Change the password after first login.**
