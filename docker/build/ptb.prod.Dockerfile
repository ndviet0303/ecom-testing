FROM registry.gitlab.com/bantool/devops/frankenphp:latest AS runner
COPY --from=registry.gitlab.com/bantool/ziet-projects/ecom/api:latest /app /app/ecm-api

COPY env/ptb-api.prod.env /app/ecm-api/.env
COPY configs/ptb.prod.Caddyfile /etc/frankenphp/Caddyfile
COPY configs/php.ini-production "$PHP_INI_DIR/php.ini"

RUN php /app/ecm-api/artisan config:cache
