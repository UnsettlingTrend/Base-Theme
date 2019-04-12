# Delete the /var/www directory, recreate it, and go to it
sudo rm -rf /var/www
sudo mkdir -p /var/www
cd /var/www

# Untar the build file
sudo tar -xf /tmp/build.tar
sudo mv /var/www/web /var/www/html



