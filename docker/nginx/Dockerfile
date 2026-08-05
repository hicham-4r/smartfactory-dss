# syntax=docker/dockerfile:1.7

ARG NODE_IMAGE=node:24.16-bookworm-slim
ARG NGINX_IMAGE=nginx:stable-alpine3.24

FROM ${NODE_IMAGE} AS laravel_frontend

WORKDIR /build

COPY laravel-app/package.json laravel-app/package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY laravel-app/resources ./resources
COPY laravel-app/vite.config.js ./
RUN mkdir -p public \
    && npm run build

FROM ${NGINX_IMAGE} AS application

COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/smartfactory.conf /etc/nginx/conf.d/default.conf

COPY laravel-app/public /srv/smartfactory/public
COPY --from=laravel_frontend /build/public/build /srv/smartfactory/public/build
COPY sage-erp-simulator/public /srv/simulator/public

RUN chown -R nginx:nginx /srv/smartfactory /srv/simulator \
    && nginx -t

EXPOSE 8080 8082

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -q -O - http://127.0.0.1:8080/nginx-health | grep -q '^ok$' || exit 1

CMD ["nginx", "-g", "daemon off;"]
