#!/bin/sh
set -e

# Railway injects PORT; default to 80 for local/non-Railway environments
PORT=${PORT:-80}
export PORT

# Generate nginx site config with the correct port
envsubst '${PORT}' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

cd /var/www/html

# Ensure Laravel writable dirs exist with absolute paths
mkdir -p /var/www/html/storage/framework/cache \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/framework/testing \
         /var/www/html/storage/framework/nutgram \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# The Railway volume mounts at /var/www/html/database/, replacing the entire
# directory including the migrations/ subfolder baked into the image.
# Migrations were copied to /migrations in the Dockerfile, safely outside
# the volume mount. We use --path=/migrations so artisan can find them.
DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
DB_DIR="$(dirname "$DB_FILE")"
mkdir -p "$DB_DIR"
touch "$DB_FILE"
# www-data needs write access to both the file AND the directory (SQLite
# creates journal/WAL files in the same directory during write transactions).
chown www-data:www-data "$DB_DIR" "$DB_FILE"
chmod 775 "$DB_DIR"

if sqlite3 "$DB_FILE" "SELECT 1 FROM telegram_users LIMIT 1;" > /dev/null 2>&1; then
    echo "Schema intact — running migrate."
    php artisan migrate --force --path=/migrations --realpath
else
    echo "Schema missing — running migrate:fresh."
    php artisan migrate:fresh --force --path=/migrations --realpath
fi

# Non-fatal so supervisord always starts even if seeding fails.
php artisan bot:seed-questions || echo "WARNING: bot:seed-questions failed"
php artisan bot:create-admin   || echo "WARNING: bot:create-admin failed"

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
