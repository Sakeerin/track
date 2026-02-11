#!/bin/sh
set -eu

if [ -z "${BACKUP_FILE:-}" ]; then
  echo "BACKUP_FILE is required"
  exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup file not found: $BACKUP_FILE"
  exit 1
fi

echo "[restore] restoring from $BACKUP_FILE"
pg_restore \
  --host="$DB_HOST" \
  --port="${DB_PORT:-5432}" \
  --username="$DB_USER" \
  --dbname="$DB_NAME" \
  --clean \
  --if-exists \
  --no-owner \
  --no-privileges \
  "$BACKUP_FILE"

echo "[restore] completed"
