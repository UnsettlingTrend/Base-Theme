#!/usr/bin/env bash
set -euo pipefail

SSH_URL="$(platform ssh -e main --pipe)"

echo "Syncing public files from main..."
rsync -avz --delete "${SSH_URL}":web/sites/default/files/ web/sites/default/files/

echo "Syncing private files from main..."
rsync -avz --delete "${SSH_URL}":private/ private/

echo "Media sync complete."
