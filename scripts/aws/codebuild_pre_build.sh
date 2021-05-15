#!/bin/bash
set -e

# Install needed PHP packages
yum update -y
yum install -y php-dom php-gd php-simplexml php-xml php-opcache php-mbstring
# Restart apache and enable xdebug
#- sudo systemctl restart apache2
#phpenmod xdebug
# Install composer and gulp
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php ;
php -r "unlink('composer-setup.php');" ;
mv composer.phar /usr/local/bin/composer
npm install -g gulp
