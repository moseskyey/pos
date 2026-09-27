#!/usr/bin/env bash
#
# DukaPOS update script. Run from the project root on the server:
#   ./deploy.sh            # update the current branch
#   ./deploy.sh main       # switch to / update a specific branch
#
set -euo pipefail

cd "$(dirname "$0")"
BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
PHP="${PHP:-php}"

echo "==> Deploying DukaPOS ($BRANCH)"

$PHP artisan down --retry=15 --refresh=15 || true
trap '$PHP artisan up' EXIT

echo "==> Pulling code"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "==> Installing PHP dependencies"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

echo "==> Migrating database"
$PHP artisan migrate --force

echo "==> Building assets"
npm ci --no-audit --no-fund
npm run build

echo "==> Caching config, routes, views and events"
$PHP artisan optimize:clear
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache
$PHP artisan storage:link --force >/dev/null 2>&1 || true

echo "==> Restarting queue workers"
$PHP artisan queue:restart

echo "==> Done. $(git log -1 --pretty='%h %s')"
