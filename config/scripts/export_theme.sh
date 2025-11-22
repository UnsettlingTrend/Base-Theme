#!/bin/bash

# Run this script from the app root directory!

theme_location="web/themes/contrib/ut_base"
theme_machine_name="ut_base"
theme_git_url="https://gitlab.com/unsettlingtrend/base-theme.git"

project_dir="/app"
export_directory="$project_dir/exports"
exported_theme_dir="$export_directory/$theme_machine_name"

################# Remove theme directory if it already exists#################
if [ -d "$exported_theme_dir" ]; then
  # Directory exists, remove it
  rm -rf "$exported_theme_dir"
  echo "Existing directory '$exported_theme_dir' removed."
fi

# Prepare Repo Folder
git clone $theme_git_url $exported_theme_dir
find "$exported_theme_dir" -mindepth 1 -maxdepth 1 ! -name ".git" -print0 | xargs -0 rm -rf
# Verify that .git still exists and everything else is gone
if [ -d "$exported_theme_dir/.git" ]; then
  echo "Directory '$exported_theme_dir' cleaned successfully (except .git)."
else
  echo "Something went wrong. .git directory is missing."
  exit 1
fi

# Move all files and subdirectories except node_modules
find "$theme_location" -mindepth 1 -maxdepth 1 ! -name "node_modules" -print0 | xargs -0 cp -rt "$exported_theme_dir"

echo "Theme exported without errors."
exit 0
