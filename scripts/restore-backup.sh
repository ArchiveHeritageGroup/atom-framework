#!/bin/bash
set -e
ATOM_ROOT="/usr/share/nginx/archive"
BACKUP_DIR="/var/backups/atom"
LOG="/var/log/atom/backup.log"

[ -z "$1" ] && { echo "Usage: $0 <backup-id>"; ls -1 "$BACKUP_DIR" 2>/dev/null | head -20; exit 1; }

BACKUP_ID="$1"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_ID}"
[ ! -d "$BACKUP_PATH" ] && { echo "Backup not found: ${BACKUP_ID}"; exit 1; }

echo "Restore backup: ${BACKUP_ID}"
echo "WARNING: This will overwrite current data!"
read -p "Continue? (yes/no): " CONFIRM
[ "$CONFIRM" != "yes" ] && { echo "Aborted."; exit 0; }

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] RESTORE: $1" | tee -a "$LOG"; }
log "Starting restore: ${BACKUP_ID}"

CONFIG_FILE="${ATOM_ROOT}/apps/qubit/config/config.php"
[ -f "$CONFIG_FILE" ] && {
    DB_HOST=$(grep -oP "host=\K[^;']+" "$CONFIG_FILE" | head -1)
    DB_NAME=$(grep -oP "dbname=\K[^;']+" "$CONFIG_FILE" | head -1)
    DB_USER=$(grep -oP "'username'\s*=>\s*'\K[^']+" "$CONFIG_FILE" | head -1)
    DB_PASS=$(grep -oP "'password'\s*=>\s*'\K[^']+" "$CONFIG_FILE" | head -1)
}
DB_HOST=${DB_HOST:-localhost}; DB_NAME=${DB_NAME:-RIC}; DB_USER=${DB_USER:-root}

DB_DUMP=$(find "${BACKUP_PATH}/database" -name "*.sql.gz" | head -1)
[ -f "$DB_DUMP" ] && { log "Restoring database"; gunzip -c "$DB_DUMP" | MYSQL_PWD="$DB_PASS" mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME"; }

[ -d "${BACKUP_PATH}/framework" ] && [ "$(ls -A ${BACKUP_PATH}/framework 2>/dev/null)" ] && { log "Restoring framework"; cp -r "${BACKUP_PATH}/framework"/* "${ATOM_ROOT}/atom-framework/"; }

[ -d "${BACKUP_PATH}/plugins" ] && for p in "${BACKUP_PATH}/plugins"/*; do
    [ -d "$p" ] && { rm -rf "${ATOM_ROOT}/plugins/$(basename $p)"; cp -r "$p" "${ATOM_ROOT}/plugins/"; }
done

[ -f "${BACKUP_PATH}/uploads.tar.gz" ] && { log "Restoring uploads"; tar -xzf "${BACKUP_PATH}/uploads.tar.gz" -C "${ATOM_ROOT}"; }

rm -rf "${ATOM_ROOT}/cache"/*
chown -R www-data:www-data "${ATOM_ROOT}/uploads" "${ATOM_ROOT}/cache" 2>/dev/null || true

log "Restore completed: ${BACKUP_ID}"
echo "Done!"
