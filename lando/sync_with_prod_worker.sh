#!/usr/bin/env bash
# Runs as www-data, invoked by sync_with_prod.sh. Not meant to be run
# directly — it needs the SSH config fixup that script does as root first.
set -euo pipefail

cd /app
export HOME=/user

DB_DUMP="sync-with-prod.sql"
trap 'rm -f "$DB_DUMP"' EXIT

echo "==> Pulling database dump from main..."
platform db:dump -e main -f "$DB_DUMP" -y

echo "==> Wiping local public and private files..."
mkdir -p web/sites/default/files private
rm -rf web/sites/default/files/* web/sites/default/files/*.*
rm -rf private/* private/*.*

echo "==> Syncing public and private files from main..."
TARGET="$(platform ssh -e main --pipe)"
rsync -avz -e "ssh -F /user/.ssh/config" "${TARGET}":web/sites/default/files/ web/sites/default/files/
rsync -avz -e "ssh -F /user/.ssh/config" "${TARGET}":private/ private/

echo "==> Importing database..."
eval "$(vendor/bin/drush sql:connect)" < "$DB_DUMP"

echo "==> Installing composer dependencies..."
composer install

echo "==> Running drush deploy..."
vendor/bin/drush deploy

echo "==> Unblocking uid 1..."
vendor/bin/drush user:unblock --uid=1

echo "Sync with prod complete."
