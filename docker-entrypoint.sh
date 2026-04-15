#!/bin/sh
set -e

ENV_FILE="/var/www/html/php/.env"

# Write .env from Docker/environment variables on every container start
cat > "$ENV_FILE" <<EOF
APP_ENV=${APP_ENV:-production}
JWT_SECRET=${JWT_SECRET:?JWT_SECRET is required}
SMTP_HOST=${SMTP_HOST:-mail.wei.or.tz}
SMTP_PORT=${SMTP_PORT:-587}
SMTP_USER=${SMTP_USER:-}
SMTP_PASS=${SMTP_PASS:-}
EMAIL_FROM=${EMAIL_FROM:-info@wei.or.tz}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@wei.or.tz}
ADMIN_PASSWORD=${ADMIN_PASSWORD:-WeiAdmin2024!}
FRONTEND_URL=${FRONTEND_URL:-https://wei.or.tz}
DB_DRIVER=${DB_DRIVER:-sqlite}
DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-wei}
DB_USER=${DB_USER:-wei}
DB_PASS=${DB_PASS:-}
EOF

chmod 600 "$ENV_FILE"
chown www-data:www-data "$ENV_FILE"

# Render injects $PORT — reconfigure Apache to listen on it
if [ -n "$PORT" ]; then
  sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
  sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi

exec "$@"
