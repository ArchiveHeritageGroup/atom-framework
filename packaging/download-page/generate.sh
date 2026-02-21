#!/bin/bash
#===============================================================================
# AtoM Heratio - Download Page Generator
# Renders the download page template with current version info
#
# Usage:
#   bash generate.sh                    # Generate to dist/download-page/
#   bash generate.sh /var/www/html/     # Generate to custom output path
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$(dirname "$SCRIPT_DIR")")"
OUTPUT_DIR="${1:-${FRAMEWORK_PATH}/dist/download-page}"

# Get version
HERATIO_VERSION=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['version'] ?? '2.10.0';")
RELEASE_DATE=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['release_date'] ?? date('Y-m-d');")

echo ""
echo "============================================================"
echo "  AtoM Heratio - Download Page Generator"
echo "============================================================"
echo ""
echo "  Version:  ${HERATIO_VERSION}"
echo "  Date:     ${RELEASE_DATE}"
echo "  Output:   ${OUTPUT_DIR}"
echo ""

mkdir -p "${OUTPUT_DIR}"

# Copy and render template
cp "${SCRIPT_DIR}/index.html" "${OUTPUT_DIR}/index.html"
sed -i "s/{{HERATIO_VERSION}}/${HERATIO_VERSION}/g" "${OUTPUT_DIR}/index.html"
sed -i "s/{{RELEASE_DATE}}/${RELEASE_DATE}/g" "${OUTPUT_DIR}/index.html"

echo "[OK] Download page generated: ${OUTPUT_DIR}/index.html"
echo ""
echo "  To deploy:"
echo "    1. Copy ${OUTPUT_DIR}/index.html to your web server"
echo "    2. e.g., scp ${OUTPUT_DIR}/index.html user@theahg.co.za:/var/www/html/download/"
echo ""
echo "============================================================"
echo ""
