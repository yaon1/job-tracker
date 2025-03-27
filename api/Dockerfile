FROM composer:2 AS deps
WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-interaction --optimize-autoloader --no-scripts

FROM php:8.4-cli-alpine
WORKDIR /app
COPY --from=deps /app/vendor vendor
COPY src src
COPY public public
RUN mkdir /data && chown www-data /data
ENV DATABASE_PATH=/data/tracker.sqlite PHP_CLI_SERVER_WORKERS=4
USER www-data
EXPOSE 8000
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
