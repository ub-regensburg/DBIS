FROM php:8.1-apache

ENV PYTHONPATH="/var/www"

# --- Install basic packages
RUN apt-get -y update \
    && apt-get -yq install curl gnupg ca-certificates \
    && curl -L https://deb.nodesource.com/setup_18.x | bash \
    && apt-get -y update \
    && apt-get install -y unzip nano ssl-cert libpq-dev libldap2-dev ldap-utils nodejs sendmail libmariadb-dev

# --- Install Composer ----
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN echo "sendmail_path=/usr/sbin/sendmail -t -i" >> /usr/local/etc/php/conf.d/sendmail.ini 

# RUN mkdir -p /etc/apache2/ssl

RUN a2enmod ssl
RUN a2ensite default-ssl
RUN a2enmod rewrite
RUN a2enmod actions

# ---- Install php Dependencies
RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql pgsql

# ---- Install ldap dependencies
RUN apt-get install libldap2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap

# ---- Install mysqli
RUN docker-php-ext-install mysqli

# Sendmail
RUN sed -i '/#!\/bin\/sh/aservice sendmail restart' /usr/local/bin/docker-php-entrypoint
RUN sed -i '/#!\/bin\/sh/aecho "$(hostname -i)\t$(hostname) $(hostname).localhost" >> /etc/hosts' /usr/local/bin/docker-php-entrypoint

# ---- Copy Apache Configs -----
COPY ./apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY ./apache/apache.conf /etc/apache2

# ---- COPY ./ssl/*.pem /etc/apache2/ssl/ -----
# COPY ./ssl/*.pem /etc/apache2/ssl/

# ---- Install Composer Dependencies ----
WORKDIR /var/www
COPY composer.json /var/www
RUN composer install --ignore-platform-reqs --no-dev

# ---- Install npm and Dependencies
# RUN npm install npm@latest -g \
#     && npm install n -g \
#     && n 16
COPY package.json /var/www
RUN npm install --legacy-peer-deps

ARG UBR_DB_HOST
ENV UBR_DB_HOST "$UBR_DB_HOST"

ARG LDAP_SERVER
ENV LDAP_SERVER=$LDAP_SERVER

EXPOSE ${UBR_DB_HOST}
EXPOSE ${DBIS_SSL_PORT}

# ---- Download CA-File for LDAP-Server ----
RUN echo -n | openssl s_client -connect $LDAP_SERVER:636 -servername $LDAP_SERVER\
    | openssl x509 > /etc/ssl/certs/$LDAP_SERVER.pem

COPY requirements.txt /var/www