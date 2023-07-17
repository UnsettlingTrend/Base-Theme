FROM drupal:10.1-php8.1-apache AS deps

MAINTAINER chrisferagotti@gmail.com

RUN apt update

EXPOSE 80/tcp
EXPOSE 443/tcp
