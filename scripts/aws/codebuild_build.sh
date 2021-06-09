#!/bin/bash
set -e

#composer -vvv about
#ls /etc/php/
#php -v
echo Build started on `date`
echo Installing composer packages...
composer install --no-progress --no-suggest
echo Installing nodejs packages...
npm install

# Compile theme assets.
echo Compile theme assets...
cd web/themes/custom/chris
npm install
gulp build

#todo Delete all unnecessary code; like node_modules
# Delete all unnecessary code.
echo Return to source root and delete unneeded code...
cd CODEBUILD_SRC_DIR
find . -name 'node_modules' -type d -prune -exec rm -rf '{}' +


# Do you need to do this? In many cases phpunit will use sqllite or similar to avoid the need for a real DB.
# If you don't need it delete it
#/usr/bin/mysql  -u root -e "GRANT ALL ON *.* TO 'test'@'localhost' IDENTIFIED BY '' WITH GRANT OPTION"
#mysqladmin -u test create test
#./vendor/bin/phpunit
