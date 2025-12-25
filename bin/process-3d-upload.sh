#!/bin/bash
# Called after 3D file upload to queue thumbnail generation
# Usage: process-3d-upload.sh <digital_object_id>

DIGITAL_OBJECT_ID="$1"
ARCHIVE_PATH="/usr/share/nginx/archive"
LOGFILE="/usr/share/nginx/archive/log/3d-thumbnail.log"

if [ -z "$DIGITAL_OBJECT_ID" ]; then
    echo "Usage: $0 <digital_object_id>"
    exit 1
fi

echo "$(date): Processing upload for DO ID: $DIGITAL_OBJECT_ID" >> "$LOGFILE"

cd "$ARCHIVE_PATH"
# Run in background with nice priority (low)
nohup nice -n 19 php atom-framework/bin/generate-3d-thumbnails.php --id="$DIGITAL_OBJECT_ID" >> "$LOGFILE" 2>&1 &

echo "Thumbnail generation queued for digital object: $DIGITAL_OBJECT_ID"
