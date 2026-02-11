#!/bin/sh
set -eu

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
ENV_FILE="${ENV_FILE:-}"

compose() {
  if [ -n "$ENV_FILE" ]; then
    docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
  else
    docker compose -f "$COMPOSE_FILE" "$@"
  fi
}

wait_for_health() {
  service="$1"
  retries="${2:-30}"
  delay="${3:-5}"
  i=1

  while [ "$i" -le "$retries" ]; do
    status="$(compose ps --format json "$service" | sed -n 's/.*"Health":"\([^"]*\)".*/\1/p')"
    if [ "$status" = "healthy" ] || [ -z "$status" ]; then
      echo "[deploy] $service is healthy"
      return 0
    fi

    echo "[deploy] waiting for $service health ($i/$retries)"
    sleep "$delay"
    i=$((i + 1))
  done

  echo "[deploy] $service did not become healthy in time"
  return 1
}

echo "[deploy] pulling latest images"
compose pull

echo "[deploy] starting stateful dependencies first"
compose up -d postgres redis
wait_for_health postgres
wait_for_health redis

echo "[deploy] running database migrations"
compose run --rm backend php artisan migrate --force

if [ "${RUN_SEED:-false}" = "true" ]; then
  echo "[deploy] running production seeders"
  compose run --rm backend php artisan db:seed --force
fi

echo "[deploy] rolling backend/frontend update"
compose up -d --no-deps backend
wait_for_health backend
compose up -d --no-deps frontend
wait_for_health frontend
compose up -d --no-deps nginx
wait_for_health nginx

echo "[deploy] starting monitoring stack"
compose up -d prometheus alertmanager blackbox loki promtail grafana db-backup

echo "[deploy] deployment completed successfully"
