sudo mkdir -p /var/www
cd /var/www

# Delete all directories and files
rm -rf  ./

# Untar the build file
sudo tar -xf /tmp/build.tar
sudo mv /var/www/web /var/www/html



