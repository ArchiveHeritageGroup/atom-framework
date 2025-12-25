#!/bin/bash
# Cron script for 3D model thumbnail generation
# Runs Blender rendering which can take 10-30 seconds per model

LOCKFILE="/tmp/3d-thumbnail-cron.lock"
LOGFILE="/usr/share/nginx/archive/log/3d-thumbnail-cron.log"
ARCHIVE_PATH="/usr/share/nginx/archive"

# Prevent concurrent runs
if [ -f "$LOCKFILE" ]; then
    # Check if process is still running
    PID=$(cat "$LOCKFILE")
    if ps -p $PID > /dev/null 2>&1; then
        echo "$(date): Already running (PID $PID)" >> "$LOGFILE"
        exit 0
    fi
fi

echo $$ > "$LOCKFILE"
trap "rm -f $LOCKFILE" EXIT

echo "$(date): Starting 3D thumbnail generation" >> "$LOGFILE"

cd "$ARCHIVE_PATH"
php atom-framework/bin/generate-3d-thumbnails.php >> "$LOGFILE" 2>&1

echo "$(date): Completed" >> "$LOGFILE"
echo "---" >> "$LOGFILE"
