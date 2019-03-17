cd /var/www

# Delete all directories and files (except for build.tar)
ls | grep -v build.tar | xargs rm
rm -rf ./*/

# Untar the build file
sudo tar -xf build.tar
mv /var/www/web /var/www/html

