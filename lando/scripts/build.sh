#!/usr/bin/env bash

# Run all the build files, alphabetically
for filename in /app/lando/scripts/build_[0-9][0-9]_*.sh; do
    [ -e "$filename" ] || continue  # Check for no-match / glob case
    bash $filename;
done