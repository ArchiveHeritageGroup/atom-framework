#!/bin/bash
BACKUP_DIR="/var/backups/atom"
LOG="/var/log/atom/backup.log"
RETENTION=${1:-30}

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleanup: removing backups older than ${RETENTION} days" >> $LOG
find "$BACKUP_DIR" -maxdepth 1 -type d -mtime +${RETENTION} -exec rm -rf {} \; 2>/dev/null
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Cleanup complete" >> $LOG
