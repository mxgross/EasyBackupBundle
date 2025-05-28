FROM kimai/kimai2:apache

RUN apt-get update && \
    apt-get install -y mariadb-client git unzip curl && \
    rm -rf /var/lib/apt/lists/*

# Composer installieren
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Dev-Abhängigkeiten installieren – ohne Scripts
WORKDIR /opt/kimai
RUN composer require --dev doctrine/doctrine-fixtures-bundle --no-scripts

# PHPUnit installieren
RUN curl -Ls https://phar.phpunit.de/phpunit-10.phar -o /usr/local/bin/phpunit && \
    chmod +x /usr/local/bin/phpunit
