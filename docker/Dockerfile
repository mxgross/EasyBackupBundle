# Dockerfile
FROM kimai/kimai2:apache

# Install mysqldump via MariaDB client
RUN apt-get update && \
    apt-get install -y mariadb-client git unzip && \
    rm -rf /var/lib/apt/lists/*

# Install EasyBackupBundle
RUN mkdir -p /opt/kimai/var/plugins && \
    cd /opt/kimai/var/plugins && \
    git clone https://github.com/mxgross/EasyBackupBundle.git

# Set permissions
RUN chown -R www-data:www-data /opt/kimai/var/plugins/EasyBackupBundle
