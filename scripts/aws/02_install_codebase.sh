sudo mkdir -p /var/www
cd /var/www

# Delete all directories and files (except for build.tar)
sudo ls | grep -v build.tar | xargs rm
sudo rm -rf ./*/

# Untar the build file
sudo tar -xf build.tar
sudo mv /var/www/web /var/www/html



