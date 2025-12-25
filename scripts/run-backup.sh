#!/bin/bash
set -e
ATOM_ROOT="/usr/share/nginx/archive"
BACKUP_DIR="/var/backups/atom"
LOG="/var/log/atom/backup.log"
TIMESTAMP=$(date '+%Y-%m-%d_%H-%M-%S')
BACKUP_ID="${TIMESTAMP}_$(head -c 4 /dev/urandom | xxd -p)"

INCLUDE_DB=1; INCLUDE_UPLOADS=1; INCLUDE_PLUGINS=1; INCLUDE_FRAMEWORK=1; RETENTION=30

while [[ $# -gt 0 ]]; do
    case $1 in
        --no-database) INCLUDE_DB=0 ;;
        --no-uploads) INCLUDE_UPLOADS=0 ;;
        --no-plugins) INCLUDE_PLUGINS=0 ;;
        --no-framework) INCLUDE_FRAMEWORK=0 ;;
        --retention=*) RETENTION="${1#*=}" ;;
        --schedule-id=*) SCHEDULE_ID="${1#*=}" ;;
    esac
    shift
done

BACKUP_PATH="${BACKUP_DIR}/${BACKUP_ID}"
mkdir -p "${BACKUP_PATH}"/{database,plugins,framework,config}

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG"; }
log "Starting backup: ${BACKUP_ID}"

CONFIG_FILE="${ATOM_ROOT}/apps/qubit/config/config.php"
[ -f "$CONFIG_FILE" ] && {
    DB_HOST=$(grep -oP "host=\K[^;']+" "$CONFIG_FILE" | head -1)
    DB_NAME=$(grep -oP "dbname=\K[^;']+" "$CONFIG_FILE" | head -1)
    DB_USER=$(grep -oP "'username'\s*=>\s*'\K[^']+" "$CONFIG_FILE" | head -1)
    DB_PASS=$(grep -oP "'password'\s*=>\s*'\K[^']+" "$CONFIG_FILE" | head -1)
}
DB_HOST=${DB_HOST:-localhost}; DB_NAME=${DB_NAME:-RIC}; DB_USER=${DB_USER:-root}

[ "$INCLUDE_DB" = "1" ] && {
    log "Backing up database: ${DB_NAME}"
    MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" --single-transaction --routines --triggers > "${BACKUP_PATH}/database/${DB_NAME}.sql"
    gzip -f "${BACKUP_PATH}/database/${DB_NAME}.sql"
}

[ "$INCLUDE_FRAMEWORK" = "1" ] && [ -d "${ATOM_ROOT}/atom-framework" ] && {
    log "Backing up framework"
    cp -r "${ATOM_ROOT}/atom-framework"/* "${BACKUP_PATH}/framework/"
}

[ "$INCLUDE_PLUGINS" = "1" ] && {
    log "Backing up plugins"
    for p in "${ATOM_ROOT}/plugins"/ar*Plugin "${ATOM_ROOT}/plugins"/sf*Plugin; do
        [ -d "$p" ] && cp -r "$p" "${BACKUP_PATH}/plugins/"
    done
}

[ "$INCLUDE_UPLOADS" = "1" ] && [ -d "${ATOM_ROOT}/uploads" ] && {
    log "Backing up uploads"
    tar -czf "${BACKUP_PATH}/uploads.tar.gz" -C "${ATOM_ROOT}" uploads
}

for cfg in apps/qubit/config/config.php apps/qubit/config/settings.yml config/propel.ini; do
    [ -f "${ATOM_ROOT}/${cfg}" ] && cp "${ATOM_ROOT}/${cfg}" "${BACKUP_PATH}/config/"
done

TOTAL_SIZE=$(du -sb "${BACKUP_PATH}" | cut -f1)
cat > "${BACKUP_PATH}/manifest.json" << EOF
{"id":"${BACKUP_ID}","path":"${BACKUP_PATH}","started_at":"${TIMESTAMP//_/ }","completed_at":"$(date '+%Y-%m-%d %H:%M:%S')","status":"completed","size":${TOTAL_SIZE}}
EOF

log "Backup completed: ${BACKUP_ID} ($(du -sh ${BACKUP_PATH} | cut -f1))"

[ "$RETENTION" -gt 0 ] && find "$BACKUP_DIR" -maxdepth 1 -type d -mtime +${RETENTION} -exec rm -rf {} \; 2>/dev/null || true

echo "${BACKUP_ID}"
