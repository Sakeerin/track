#!/bin/sh
set -eu

if [ -z "${BACKUP_FILE:-}" ]; then
  echo "BACKUP_FILE is required"
  exit 1
fi

TMP_DB="verify_restore_$(date +%s)"

echo "[verify] creating temporary database: $TMP_DB"
createdb --host="$DB_HOST" --port="${DB_PORT:-5432}" --username="$DB_USER" "$TMP_DB"

echo "[verify] restoring backup into temporary database"
pg_restore \
  --host="$DB_HOST" \
  --port="${DB_PORT:-5432}" \
  --username="$DB_USER" \
  --dbname="$TMP_DB" \
  --no-owner \
  --no-privileges \
  "$BACKUP_FILE"

echo "[verify] running smoke query"
psql --host="$DB_HOST" --port="${DB_PORT:-5432}" --username="$DB_USER" --dbname="$TMP_DB" -v ON_ERROR_STOP=1 -c "SELECT COUNT(*) FROM shipments;" >/dev/null

echo "[verify] dropping temporary database"
dropdb --host="$DB_HOST" --port="${DB_PORT:-5432}" --username="$DB_USER" "$TMP_DB"

echo "[verify] backup verification passed"
