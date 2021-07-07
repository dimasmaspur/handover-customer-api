FROM 192.168.7.80:12158/php:7.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends nano htop curl git-core cron \
  wget zip unzip \
  libjpeg-dev libmcrypt-dev libpng-dev libpq-dev libzip-dev libssl-dev libxrender-dev xvfb libfontconfig wkhtmltopdf \
  && rm -rf /var/lib/apt/lists/* \
  && docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr \
  && docker-php-ext-install gd mbstring mysqli pcntl sockets opcache pdo pdo_mysql zip \
  && docker-php-ext-enable opcache

# Recommended opcache settings - https://secure.php.net/manual/en/opcache.installation.php
RUN { \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=4000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.enable_cli=1'; \
  } > /usr/local/etc/php/conf.d/docker-ci-opcache.ini

#COPY php.ini "$PHP_INI_DIR/php.ini"

#  Configuring Apache
COPY vhost.conf /etc/apache2/sites-available/000-default.conf
COPY mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
COPY php.ini /usr/local/etc/php/php.ini-production
COPY php.ini /usr/local/etc/php/php.ini-development

# Enable rewrite module
RUN a2enmod rewrite

#DECLARE ENVIRONMENT DEFAULT VALUE
ENV APP_NAME UNKNOWN
ENV APP_ENV PRODUCTION
ENV APP_VERSION UNKNOWN
ENV APP_TIMEZONE UNKNOWN
ENV APP_DEBUG UNKNOWN
ENV APP_URL UNKNOWN

ENV LOG_CHANNEL UNKNOWN

ENV BASE_URL_BANNER UNKNOWN
ENV BASE_URL_CATEGORY UNKNOWN
ENV BASE_URL_PRODUCT UNKNOWN

ENV BASE_URL_MIGRATE UNKNOWN
ENV BASE_URL_PAYMENT UNKNOWN

ENV BASE_URL_GMAPS UNKNOWN
ENV GMAPS_KEY UNKNOWN

ENV DB_CONNECTION UNKNOWN
ENV DB_HOST UNKNOWN
ENV DB_PORT UNKNOWN
ENV DB_DATABASE UNKNOWN
ENV DB_USERNAME UNKNOWN
ENV DB_PASSWORD UNKNOWN

ENV DB2_HOST UNKNOWN
ENV DB2_PORT UNKNOWN
ENV DB2_DATABASE UNKNOWN
ENV DB2_USERNAME UNKNOWN
ENV DB2_PASSWORD UNKNOWN
ENV DB_TIMEZONE +07:00

ENV CACHE_DRIVER file
ENV QUEUE_CONNECTION sync

ENV BASE_URL_KS UNKNOWN

ENV BASE_URL_ICON UNKNOWN
ENV BASE_URL_SHIPMENT UNKNOWN

ENV API_NAME UNKNOWN
ENV API_PREFIX UNKNOWN
ENV API_DOMAIN UNKNOWN
ENV API_VERSION UNKNOWN
ENV API_STANDARDS_TREE UNKNOWN

ENV BASE_URL_BANK UNKNOWN

ENV BASE_URL_VOUCHER UNKNOWN

ENV BASE_URL_PAYMENT_STEP UNKNOWN
ENV API_GOPAY_URL UNKNOWN
ENV CALLBACK_GOPAY_URL UNKNOWN

ENV ONESIGNAL_APPID UNKNOWN
ENV BASE_URL_COMMUNICATION UNKNOWN

ENV MAIL_MAILER UNKNOWN
ENV MAIL_HOST UNKNOWN
ENV MAIL_PORT UNKNOWN
ENV MAIL_USERNAME UNKNOWN
ENV MAIL_PASSWORD UNKNOWN
ENV MAIL_ENCRYPTION UNKNOWN
ENV MAIL_FROM_ADDRESS UNKNOWN
ENV MAIL_FROM_NAME UNKNOWN

ENV BASE_URL_WIGDET_IMAGE UNKNOWN

#CREATE ENVIRONMENT FILE
RUN echo "APP_NAME="$APP_NAME > .env
RUN echo "APP_ENV="$APP_ENV >> .env
RUN echo "APP_VERSION="$APP_VERSION >> .env
RUN echo "APP_TIMEZONE="$APP_TIMEZONE >> .env
RUN echo "APP_DEBUG="$APP_DEBUG >> .env
RUN echo "APP_URL="$APP_URL >> .env

RUN echo "LOG_CHANNEL="$LOG_CHANNEL >> .env

RUN echo "BASE_URL_BANNER="$BASE_URL_BANNER >> .env
RUN echo "BASE_URL_CATEGORY="$BASE_URL_CATEGORY >> .env
RUN echo "BASE_URL_PRODUCT="$BASE_URL_PRODUCT >> .env

RUN echo "BASE_URL_MIGRATE="$BASE_URL_MIGRATE >> .env
RUN echo "BASE_URL_PAYMENT="$BASE_URL_PAYMENT >> .env

RUN echo "BASE_URL_GMAPS="$BASE_URL_GMAPS >> .env
RUN echo "GMAPS_KEY="$GMAPS_KEY >> .env

RUN echo "DB_CONNECTION="$DB_CONNECTION >> .env
RUN echo "DB_HOST="$DB_HOST >> .env
RUN echo "DB_PORT="$DB_PORT >> .env
RUN echo "DB_DATABASE="$DB_DATABASE >> .env
RUN echo "DB_USERNAME="$DB_USERNAME >> .env
RUN echo "DB_PASSWORD="$DB_PASSWORD >> .env

RUN echo "DB2_HOST="$DB2_HOST >> .env
RUN echo "DB2_PORT="$DB2_PORT >> .env
RUN echo "DB2_DATABASE="$DB2_DATABASE >> .env
RUN echo "DB2_USERNAME="$DB2_USERNAME >> .env
RUN echo "DB2_PASSWORD="$DB2_PASSWORD >> .env
RUN echo "DB_TIMEZONE="$DB_TIMEZONE >> .env

RUN echo "CACHE_DRIVER="$CACHE_DRIVER >> .env
RUN echo "QUEUE_CONNECTION="$QUEUE_CONNECTION >> .env

RUN echo "BASE_URL_KS="$BASE_URL_KS >> .env

RUN echo "BASE_URL_ICON="$BASE_URL_ICON >> .env
RUN echo "BASE_URL_SHIPMENT="$BASE_URL_SHIPMENT >> .env

RUN echo "API_NAME="$API_NAME >> .env
RUN echo "API_PREFIX="$API_PREFIX >> .env
RUN echo "API_DOMAIN="$API_DOMAIN >> .env
RUN echo "API_VERSION="$API_VERSION >> .env
RUN echo "API_STANDARDS_TREE="$API_STANDARDS_TREE >> .env

RUN echo "BASE_URL_BANK="$BASE_URL_BANK >> .env

RUN echo "BASE_URL_VOUCHER="$BASE_URL_VOUCHER >> .env

RUN echo "BASE_URL_PAYMENT_STEP="$BASE_URL_PAYMENT_STEP >> .env
RUN echo "API_GOPAY_URL="$API_GOPAY_URL >> .env
RUN echo "CALLBACK_GOPAY_URL="$CALLBACK_GOPAY_URL >> .env

RUN echo "ONESIGNAL_APPID="$ONESIGNAL_APPID >> .env
RUN echo "BASE_URL_COMMUNICATION="$BASE_URL_COMMUNICATION >> .env

RUN echo "MAIL_MAILER="$MAIL_MAILER >> .env
RUN echo "MAIL_HOST="$MAIL_HOST >> .env
RUN echo "MAIL_PORT="$MAIL_PORT >> .env
RUN echo "MAIL_USERNAME="$MAIL_USERNAME >> .env
RUN echo "MAIL_PASSWORD="$MAIL_PASSWORD >> .env
RUN echo "MAIL_ENCRYPTION="$MAIL_ENCRYPTION >> .env
RUN echo "MAIL_FROM_ADDRESS="$MAIL_FROM_ADDRESS >> .env
RUN echo "MAIL_FROM_NAME="$MAIL_FROM_NAME >> .env

RUN echo "BASE_URL_WIGDET_IMAGE="$BASE_URL_WIGDET_IMAGE >> .env

#RUN useradd -m -u 1000 artisan

WORKDIR /var/www/html

RUN chmod -R 777 /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 777 /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage/
RUN chmod -R 777 /var/www/html/storage

#RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

#RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer

#RUN composer update

#RUN php artisan migrate

CMD ["apache2-foreground"]
