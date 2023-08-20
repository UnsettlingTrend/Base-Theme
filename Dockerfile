FROM trafex/php-nginx:3.1.0 AS php

# Change to root to get proper permissions, and add necessary php extensions
USER root
RUN apk update
RUN apk add git patch php-zip php-pdo php-xmlwriter php-tokenizer php-simplexml php-dom php-json
RUN apk update
RUN ln -s /etc/php81 /etc/php

# Copy the codebase into the appropriate directory and fix it so it works
COPY . /var/www
WORKDIR /var/www
RUN rm -rf html
RUN ln -s web html

# Install composer from the official image
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Run composer install to install the dependencies
RUN composer install --optimize-autoloader --no-interaction

EXPOSE 80/tcp
EXPOSE 8080/tcp
EXPOSE 443/tcp
