#!/usr/bin/env bash
# One-time setup for the local Timesheet app.
# Usage:  cd ~/timesheet && bash setup.sh
set -e
cd "$(dirname "$0")"

command -v php >/dev/null || { echo "PHP 8.2+ is required (brew install php)"; exit 1; }
command -v composer >/dev/null || { echo "Composer is required (brew install composer)"; exit 1; }

[ -f .env ] || cp .env.example .env

echo "→ Installing PHP packages…"
composer install --no-interaction

grep -q '^APP_KEY=base64' .env || php artisan key:generate

echo "→ Creating local MySQL database (from DB_* in .env)…"
php artisan timesheet:create-db

echo "→ Running migrations…"
php artisan migrate --force

echo
echo "✓ Done. Start the app with:  php artisan serve --port=8090"
echo "  then open http://127.0.0.1:8090"
