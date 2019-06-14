#!/usr/bin/env bash
# Starting from a clean amazon linux instance? Run this

# Install nginx
sudo amazon-linux-extras install nginx1.12 -y
sudo yum update -y
sudo chkconfig nginx on
sudo service nginx start
sudo service nginx status
# TODO Change root path for nginx at /etc/nginx/nginx.conf!

# Install PHP 7.2 and extensions
sudo amazon-linux-extras install php7.2 -y
sudo yum install php-common php-opcache php-mcrypt php-cli php-gd php-curl php-mbstring -y
