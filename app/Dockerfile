# On part de l'image officielle
FROM php:8.3-apache

# On active le module rewrite
RUN a2enmod rewrite

# On autorise le fichier .htaccess à fonctionner (Evite l'erreur 500)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf