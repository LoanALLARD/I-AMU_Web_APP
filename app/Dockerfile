# On part de l'image officielle
FROM php:8.3-apache

# On active le module rewrite
RUN a2enmod rewrite

# Note: pas de `composer dump-autoload` ici — l'autoloader utilisé en
# runtime est manuel (app/autoload.php). Composer n'est plus utilisé
# qu'en local et en CI pour installer les outils de qualité
# (PHPStan, PHPCS, PHPUnit) ; vendor/autoload.php n'est jamais
# chargé par public/index.php.

# On autorise le fichier .htaccess à fonctionner (Evite l'erreur 500)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

