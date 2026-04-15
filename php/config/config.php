<?php
declare(strict_types=1);

// Load .env file from php directory
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        // Strip surrounding quotes if present
        if (preg_match('/^(["\'])(.+)\1$/', $value, $m)) {
            $value = $m[2];
        }
        $_ENV[$key] = $value;
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

define('APP_ENV',      env('APP_ENV',      'development'));
define('JWT_SECRET',   env('JWT_SECRET',   'changeme-insecure-default-secret'));
define('JWT_TTL',      86400); // 24 hours in seconds

define('SMTP_HOST',    env('SMTP_HOST',    'smtp.gmail.com'));
define('SMTP_PORT',    (int) env('SMTP_PORT', 587));
define('SMTP_USER',    env('SMTP_USER',    ''));
define('SMTP_PASS',    env('SMTP_PASS',    ''));
define('EMAIL_FROM',   env('EMAIL_FROM',   'info@wei.or.tz'));
define('ADMIN_EMAIL',  env('ADMIN_EMAIL',  'admin@wei.or.tz'));

define('FRONTEND_URL', rtrim(env('FRONTEND_URL', 'http://localhost:8000'), '/'));

define('UPLOAD_DIR',   __DIR__ . '/../uploads/');
define('DATA_DIR',     __DIR__ . '/../data/');
define('DB_PATH',      DATA_DIR . 'wei.db');

// Database driver: 'sqlite' (default) or 'mysql'
define('DB_DRIVER', env('DB_DRIVER', 'sqlite'));
define('DB_HOST',   env('DB_HOST',   '127.0.0.1'));
define('DB_PORT',   (int) env('DB_PORT',   3306));
define('DB_NAME',   env('DB_NAME',   'wei'));
define('DB_USER',   env('DB_USER',   'root'));
define('DB_PASS',   env('DB_PASS',   ''));

define('MAX_UPLOAD_SIZE',  5  * 1024 * 1024);  // 5 MB
define('MAX_VIDEO_SIZE',   100 * 1024 * 1024);  // 100 MB
