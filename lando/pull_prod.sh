#!/usr/bin/env bash
set -euo pipefail

# Pull prod database
platform db:dump -e main -f  temp-backup.sql -y
lando db-import temp-backup.sql -y
rm temp-backup.sql

# Sync files
rm -rf private/* private/*.* web/sites/default/files/* web/sites/default/files/*.*
rsync -avz "$(platform ssh -e main --pipe)":web/sites/default/files/ web/sites/default/files/
rsync -avz "$(platform ssh -e main --pipe)":private/ private/

# Deploy local config
lando drush deploy

# Re-index search indexes
lando drush search-api:reset-tracker
lando drush search-api:index

# Prep the admin user and login
lando drush user:unblock --uid=1
lando drush uli
