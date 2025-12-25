#!/bin/bash
BACKUP_DIR="/var/backups/atom"
echo "Available AtoM Backups"
echo "======================"
printf "%-32s %-12s %s\n" "BACKUP ID" "STATUS" "SIZE"
for m in "${BACKUP_DIR}"/*/manifest.json; do
    [ -f "$m" ] && {
        ID=$(grep -oP '"id"\s*:\s*"\K[^"]+' "$m")
        ST=$(grep -oP '"status"\s*:\s*"\K[^"]+' "$m")
        SZ=$(grep -oP '"size"\s*:\s*\K[0-9]+' "$m")
        printf "%-32s %-12s %s\n" "$ID" "$ST" "$(numfmt --to=iec-i --suffix=B $SZ 2>/dev/null || echo ${SZ}B)"
    }
done
echo ""
echo "Total: $(ls -d ${BACKUP_DIR}/*/ 2>/dev/null | wc -l) backups, $(du -sh ${BACKUP_DIR} 2>/dev/null | cut -f1) used"
