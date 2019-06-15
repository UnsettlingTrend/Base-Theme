#!/usr/bin/env bash
# Delete the /var/www directory, recreate it, and go to it
if [ -d "/var/www/s3/" ]; then
    sudo su
    sudo fusermount -u /var/www/s3/
    exit
fi
if [ -d "/var/www/" ]; then
    sudo rm -rf /var/www
fi
sudo mkdir -p /var/www
cd /var/www

# Untar the build file
sudo tar -xf /tmp/build.tar
sudo mv /var/www/web /var/www/html