FROM wordpress:6.5
COPY theme/ /var/www/html/wp-content/themes/area51-reunion/
COPY plugins/ /var/www/html/wp-content/plugins/
