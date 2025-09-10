#!/usr/bin/env bash
set -euo pipefail

# Usage: DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=root DB_PASS= DB_NAME=hiddengems ./scripts/backup.sh

DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=${DB_PORT:-3307}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-}
DB_NAME=${DB_NAME:-hiddengems}
UPLOADS_DIR=${UPLOADS_DIR:-public/uploads}
OUT_DIR=${OUT_DIR:-backups}

mkdir -p "$OUT_DIR"
TS=$(date +%Y%m%d_%H%M%S)

SQL_FILE="$OUT_DIR/${DB_NAME}_${TS}.sql.gz"
echo "Dumping MySQL to $SQL_FILE"
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" ${DB_PASS:+-p$DB_PASS} \
  --single-transaction --quick --routines --triggers "$DB_NAME" | gzip -9 > "$SQL_FILE"

if [ -d "$UPLOADS_DIR" ]; then
  UP_ZIP="$OUT_DIR/uploads_${TS}.tar.gz"
  echo "Archiving uploads to $UP_ZIP"
  tar -czf "$UP_ZIP" "$UPLOADS_DIR"
fi

echo "Backup completed."

