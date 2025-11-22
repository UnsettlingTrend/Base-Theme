#!/bin/bash

################# Set all the initial variables for the profile #################
profile_name="UnsettlingTrend Personal"
profile_machine_name="trend_personal"
profile_description="Profile for UnsettlingTrend personal site."
profile_core_version_requirement="^10"
profile_theme="ut_base"
profile_git_url="https://gitlab.com/unsettlingtrend/trend-personal.git"

project_dir="/app"
export_directory="$project_dir/exports"
exported_profile_dir="$export_directory/$profile_machine_name"

################# Remove profile directory if it already exists #################
if [ -d "$exported_profile_dir" ]; then
  # Directory exists, remove it
  rm -rf "$exported_profile_dir"
  echo "Existing directory '$exported_profile_dir' removed."
fi
# Create the directory
git clone $profile_git_url $exported_profile_dir || exit
# Remove initial install files that this script will update
rm -rf $exported_profile_dir/config/install $exported_profile_dir/trend_personal.info.yml

################# Print the start of the .info.yml file #################
echo "name: $profile_name" > "$exported_profile_dir/$profile_machine_name.info.yml"
echo "type: profile" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "description: '$profile_description'" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "core_version_requirement: '$profile_core_version_requirement'" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "distribution:" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "  name: $profile_name" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "  install:" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "    theme: $profile_theme" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "" >> "$exported_profile_dir/$profile_machine_name.info.yml"
##################################

################# Print the modules to install #################
echo "install:" >> "$exported_profile_dir/$profile_machine_name.info.yml"
# Create an array for installed modules
mapfile -t profile_modules < <(drush pm-list --type=module --status=enabled --fields=name)
# Remove the first 3 and last 1 lines that are just formatting
profile_modules=("${profile_modules[@]:3}")
unset 'profile_modules[${#profile_modules[@]}-1]'
# Iterate over the array and trim whitespace from each element
for i in "${!profile_modules[@]}"; do
  profile_modules[$i]="${profile_modules[$i]#"${profile_modules[$i]%%[![:space:]]*}"}"   # remove leading whitespace
  profile_modules[$i]="${profile_modules[$i]%"${profile_modules[$i]##*[![:space:]]}"}"   # remove trailing whitespace
done
# Loop through the array and print each element with "  -" appended
for item in "${profile_modules[@]}"; do
  echo "  - $item" >> "$exported_profile_dir/$profile_machine_name.info.yml"
done

################# Print the themes to install #################
echo "themes:" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "  - claro" >> "$exported_profile_dir/$profile_machine_name.info.yml"
echo "  - gin" >> "$exported_profile_dir/$profile_machine_name.info.yml"

################# Export configuration files and modify them #################
mkdir -p "${exported_profile_dir}/config/install/"
drush cex --destination="${exported_profile_dir}/config/install/"

find ${exported_profile_dir}/config/install/ -type f -exec sed -i -e '/^uuid: /d' {} \;
find ${exported_profile_dir}/config/install/ -type f -exec sed -i -e '/_core:/,+1d' {} \;
rm ${exported_profile_dir}/config/install/core.extension.yml

exit 0 # Indicate success
