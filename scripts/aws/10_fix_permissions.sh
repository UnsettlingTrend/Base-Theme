#!/usr/bin/env bash
# Make sure all permissions are correct

# Set nginx as owner for directories
sudo chown -R root:nginx /var/www/html
sudo chown -R root:nginx /var/www/private
sudo chown -R root:nginx /var/www/config

# Set permissions for nginx on site diectory
sudo chmod -R 555        /var/www/html

# Set permissions and owners  on public and private files directories
sudo chown -R root:nginx /var/www/html/sites/default/files
sudo chmod -R 770        /var/www/html/sites/default/files
sudo chown -R root:nginx /var/www/private
sudo chmod -R 770        /var/www/private