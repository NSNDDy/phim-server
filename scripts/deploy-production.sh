#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [[ ! -f .env ]]; then
  echo "Missing .env. Create it from .env.production.example before deploying."
  exit 1
fi

docker compose -f docker-compose.prod.yml up -d --build

docker compose -f docker-compose.prod.yml exec -T app sh -lc '
  mkdir -p /app/storage/framework/cache/data \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/logs \
    /app/bootstrap/cache
  chown -R www-data:www-data /app/storage /app/bootstrap/cache
  chmod -R 777 /app/storage /app/bootstrap/cache
'

docker compose -f docker-compose.prod.yml exec -T app composer install \
  --no-interaction \
  --prefer-dist \
  --optimize-autoloader \
  --ignore-platform-req=php

docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
docker compose -f docker-compose.prod.yml exec -T app php artisan view:cache

docker compose -f docker-compose.prod.yml ps
