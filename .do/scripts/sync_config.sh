#!/bin/bash

# Never hurts to clear the cache first...
drush cr
# Run Drupal DB updates
drush updb -y
# Import configuration
drush cim -y
# Import configuration again just in case there's more
drush cim -y
# Clear Drupal cache
drush cr
