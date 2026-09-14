FROM php:8.3-apache
RUN docker-php-ext-install pdo pdo_mysql

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
                /etc/apache2/sites-available/*.conf \
                /etc/apache2/apache2.conf

RUN a2enmod rewrite

COPY . /var/www/html
WORKDIR /var/www/html

# Windows does not carry the executable bit through git, so set it here
# rather than relying on how the file arrived.
RUN chmod +x /var/www/html/docker-entrypoint.sh

# Documentation only — it does not publish anything, and the real port is
# decided at run time by the entrypoint.
EXPOSE 80

# ENTRYPOINT runs first and receives CMD as its arguments, so the script
# rewrites the port and then exec's apache2-foreground. Splitting them
# this way keeps `docker run <image> bash` working for debugging.
ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
