#!/usr/bin/env bash
# Production deploy script for the TriLink Laravel backend.
#
# Assumes:
#   - current working directory is the app root
#   - php, composer, and artisan are on PATH
#   - .env has already been populated for the target environment
#   - the filesystem user running this script owns storage/ and bootstrap/cache/
#
# The sequence is: stop queue → migrate → rebuild caches → restart workers.
# Failing any step aborts the deploy (set -e); caches are rebuilt AFTER
# migrations so config/route/view cache reflects the post-migration state.

set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Putting app into maintenance mode"
php artisan down --render="errors::503" --retry=60 || true

echo "→ Installing composer deps (production, no dev)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "→ Running database migrations"
php artisan migrate --force

echo "→ Clearing stale caches"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear || true

echo "→ Rebuilding production caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "→ Restarting queue workers (graceful — in-flight jobs finish)"
php artisan queue:restart

echo "→ Bringing app back up"
php artisan up

echo "✓ Deploy complete: $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
