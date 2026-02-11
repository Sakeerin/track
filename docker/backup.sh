#!/bin/sh
set -eu

TIMESTAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_DIR="${BACKUP_DIR:-/backups/full}"
WAL_DIR="${WAL_DIR:-/backups/wal}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

mkdir -p "$BACKUP_DIR" "$WAL_DIR"

BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_${TIMESTAMP}.dump"

echo "[backup] creating pg_dump archive: $BACKUP_FILE"
pg_dump \
  --host="$DB_HOST" \
  --port="${DB_PORT:-5432}" \
  --username="$DB_USER" \
  --format=custom \
  --compress=9 \
  --file="$BACKUP_FILE" \
  "$DB_NAME"

sha256sum "$BACKUP_FILE" > "$BACKUP_FILE.sha256"

echo "[backup] pruning archives older than ${RETENTION_DAYS} days"
find "$BACKUP_DIR" -type f -name '*.dump' -mtime "+$RETENTION_DAYS" -delete
find "$BACKUP_DIR" -type f -name '*.sha256' -mtime "+$RETENTION_DAYS" -delete

echo "[backup] done"
