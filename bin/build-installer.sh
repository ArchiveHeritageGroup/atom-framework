#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Build Self-Extracting Installer (.run)
#===============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="$(dirname "$FRAMEWORK_PATH")"
DIST_DIR="${FRAMEWORK_PATH}/dist"

# Get version
VERSION=$(grep '"version"' "${FRAMEWORK_PATH}/version.json" 2>/dev/null | cut -d'"' -f4 || echo "1.0.0")
OUTPUT_FILE="${DIST_DIR}/atom-ahg-framework-${VERSION}.run"

echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  Building Self-Extracting Installer                              ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "Version: ${VERSION}"
echo "Output:  ${OUTPUT_FILE}"
echo ""

mkdir -p "${DIST_DIR}"

# Create temporary directory
TEMP_DIR=$(mktemp -d)
trap "rm -rf ${TEMP_DIR}" EXIT

echo "[1/4] Copying framework files..."
mkdir -p "${TEMP_DIR}/atom-framework"
rsync -a --exclude='.git' --exclude='vendor' --exclude='dist' \
    "${FRAMEWORK_PATH}/" "${TEMP_DIR}/atom-framework/"

echo "[2/4] Copying plugins..."
if [ -d "${ATOM_ROOT}/atom-ahg-plugins" ]; then
    mkdir -p "${TEMP_DIR}/atom-ahg-plugins"
    rsync -a --exclude='.git' \
        "${ATOM_ROOT}/atom-ahg-plugins/" "${TEMP_DIR}/atom-ahg-plugins/"
fi

echo "[3/4] Creating installer header..."
cat > "${TEMP_DIR}/installer.sh" << 'HEADEREOF'
#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Self-Extracting Installer
#===============================================================================

echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  AtoM AHG Framework - Self-Extracting Installer                  ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

# Default paths
ATOM_ROOT="${ATOM_ROOT:-/usr/share/nginx/atom}"
EXTRACT_DIR="${ATOM_ROOT}"

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --target) EXTRACT_DIR="$2"; shift 2 ;;
        --help) echo "Usage: $0 [--target /path/to/atom]"; exit 0 ;;
        *) shift ;;
    esac
done

echo "Target: ${EXTRACT_DIR}"
echo ""

# Find archive marker
ARCHIVE_LINE=$(awk '/^__ARCHIVE_BELOW__/ {print NR + 1; exit 0; }' "$0")

# Extract
echo "Extracting..."
tail -n+${ARCHIVE_LINE} "$0" | tar xzf - -C "${EXTRACT_DIR}"

# Run installer
if [ -f "${EXTRACT_DIR}/atom-framework/bin/install" ]; then
    echo ""
    echo "Running installer..."
    cd "${EXTRACT_DIR}/atom-framework"
    composer install --no-dev --quiet 2>/dev/null || true
    bash bin/install
fi

echo ""
echo "Installation complete!"
exit 0

__ARCHIVE_BELOW__
HEADEREOF

echo "[4/4] Creating archive..."
cd "${TEMP_DIR}"
tar czf payload.tar.gz atom-framework atom-ahg-plugins 2>/dev/null || \
tar czf payload.tar.gz atom-framework

cat installer.sh payload.tar.gz > "${OUTPUT_FILE}"
chmod +x "${OUTPUT_FILE}"

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  Build Complete                                                  ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "Output: ${OUTPUT_FILE}"
echo "Size:   $(du -h "${OUTPUT_FILE}" | cut -f1)"
echo ""
echo "Usage:"
echo "  sudo ./$(basename ${OUTPUT_FILE})"
echo "  sudo ./$(basename ${OUTPUT_FILE}) --target /path/to/atom"
echo ""
