#!/usr/bin/env bash
# Entry point for `lando sync-with-prod` (runs as root in the appserver
# container — see .lando.yml). Does one root-only fixup, then hands off to
# sync_with_prod_worker.sh as www-data for everything else.
set -euo pipefail

export HOME=/user

# Lando mounts the host's $HOME at /user in every container. The Platform.sh
# CLI's session at ~/.platformsh (so /user/.platformsh here) is shared with
# the host this way, which is what lets this container reuse the host's
# already-authenticated CLI login with no separate token to manage. But
# ~/.ssh/config (also mounted, at /user/.ssh/config) includes the CLI's
# generated SSH config using the *host's* absolute home directory path (e.g.
# /home/alice/.platformsh/ssh/*.config) — baked in when the CLI wrote it on
# the host, not resolved dynamically — and that path doesn't exist in this
# container; only /user does. Symlink it so the Include still resolves here.
# This only creates a container-internal path, so it's safe to redo every run.
HOST_HOME_PATH="$(grep -ohE '/[^ ]+/\.platformsh' /user/.ssh/config 2>/dev/null | head -1 | sed 's|/\.platformsh$||')"
if [ -n "$HOST_HOME_PATH" ] && [ ! -e "$HOST_HOME_PATH" ]; then
  mkdir -p "$(dirname "$HOST_HOME_PATH")"
  ln -sfn /user "$HOST_HOME_PATH"
fi

# Everything else runs as www-data (not root): it keeps synced files owned
# the same as the rest of the project, and — since /user is a bind mount of
# the host's real home directory, not a copy — it keeps the Platform.sh CLI's
# session cache from picking up root-owned files that the host user's own
# CLI could no longer read or write afterward.
exec su -s /bin/bash www-data /app/lando/sync_with_prod_worker.sh
