ENV APP_ENV=dev

# Dockerfile
FROM kimai/kimai2:apache

# Install mysqldump via MariaDB client
RUN apt-get update && \
    apt-get install -y mariadb-client git unzip && \
    rm -rf /var/lib/apt/lists/*

# Install EasyBackupBundle if not set as live volume in docker-compose.override.yml
#RUN mkdir -p /opt/kimai/var/plugins && \
#    rm -rf /opt/kimai/var/plugins/EasyBackupBundle && \
#    cd /opt/kimai/var/plugins && \
#    git clone https://github.com/mxgross/EasyBackupBundle.git

# Set permissions
#RUN chown -R www-data:www-data /opt/kimai/var/plugins/EasyBackupBundle

# Install PHPUnit (adjust version to your PHP version if needed)
RUN curl -Ls https://phar.phpunit.de/phpunit-10.phar -o /usr/local/bin/phpunit && \
    chmod +x /usr/local/bin/phpunit

