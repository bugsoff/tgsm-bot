# Dockerfile
FROM php:8.4-cli

# Extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libssl-dev \
    libsqlite3-dev \
    sqlite3 \
    unzip \
    git 

RUN docker-php-ext-install pdo pdo_sqlite 

RUN apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY composer.json ./
RUN mkdir -p src

# entrypoint.sh
RUN echo '#!/bin/sh\n\
if [ ! -d "vendor" ]; then\n\
    echo "Installing dependencies..."\n\
    composer install --no-dev --optimize-autoloader\n\
    rm -rf /root/.composer\n\
# else\n\
#     echo "Updating dependencies..."\n\
#     composer update --no-dev --optimize-autoloader\n\
#     rm -rf /root/.composer\n\
fi\n\
exec "$@"' > /entrypoint.sh && chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]

CMD ["php", "app.php"]
