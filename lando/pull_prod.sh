#!/usr/bin/env bash
set -euo pipefail

# Pull prod database
platform db:dump -e main -f  temp-backup.sql -y
lando db:import temp-backup.sql -y
rm temp-backup.sql -y

# Sync files
rm -rf private/* private/*.* web/sites/default/files/* web/sites/default/files/*.* -y
rsync -avz "$(platform ssh -e main --pipe)":web/sites/default/files/ web/sites/default/files/ -y
rsync -avz "$(platform ssh -e main --pipe)":private/ private/ -y

# Deploy local config
lando drush deploy -y
