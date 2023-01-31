#!/bin/bash

# Never hurts to clear the cache first...
echo "Clearing Cache..."
drush cr
# Run Drupal DB updates
echo "Running any database updates..."
drush updb -y
# Import configuration
echo "Importing Configuration..."
drush cim -y
# Import configuration again just in case there's more
echo "Importing Configuration again, just in case there's more..."
drush cim -y
# Clear Drupal cache
echo "Clearing Cache one last time..."
drush cr
