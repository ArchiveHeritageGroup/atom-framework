#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Build Debian Package (.deb)
#===============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="$(dirname "$FRAMEWORK_PATH")"
DIST_DIR="${FRAMEWORK_PATH}/dist"

# Get version
VERSION=$(grep '"version"' "${FRAMEWORK_PATH}/version.json" 2>/dev/null | cut -d'"' -f4 || echo "1.0.0")
PACKAGE_NAME="atom-ahg-framework"
OUTPUT_FILE="${DIST_DIR}/${PACKAGE_NAME}_${VERSION}_all.deb"

echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  Building Debian Package                                         ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "Package: ${PACKAGE_NAME}"
echo "Version: ${VERSION}"
echo "Output:  ${OUTPUT_FILE}"
echo ""

# Check for dpkg-deb
if ! command -v dpkg-deb &> /dev/null; then
    echo "Error: dpkg-deb not found. Install with: sudo apt-get install dpkg"
    exit 1
fi

mkdir -p "${DIST_DIR}"

# Create build directory
BUILD_DIR=$(mktemp -d)
trap "rm -rf ${BUILD_DIR}" EXIT

INSTALL_DIR="${BUILD_DIR}/usr/share/atom-ahg-framework"

echo "[1/5] Creating directory structure..."
mkdir -p "${BUILD_DIR}/DEBIAN"
mkdir -p "${INSTALL_DIR}"
mkdir -p "${BUILD_DIR}/usr/bin"

echo "[2/5] Copying framework files..."
rsync -a --exclude='.git' --exclude='vendor' --exclude='dist' \
    "${FRAMEWORK_PATH}/" "${INSTALL_DIR}/"

echo "[3/5] Copying plugins..."
if [ -d "${ATOM_ROOT}/atom-ahg-plugins" ]; then
    mkdir -p "${INSTALL_DIR}/plugins"
    rsync -a --exclude='.git' \
        "${ATOM_ROOT}/atom-ahg-plugins/" "${INSTALL_DIR}/plugins/"
fi

echo "[4/5] Creating control files..."

# Control file
cat > "${BUILD_DIR}/DEBIAN/control" << CTRLEOF
Package: ${PACKAGE_NAME}
Version: ${VERSION}
Section: web
Priority: optional
Architecture: all
Depends: php (>= 8.1), php-mysql, php-xml, php-mbstring, mysql-client, git
Maintainer: The Archive and Heritage Group <support@theahg.co.za>
Homepage: https://github.com/ArchiveHeritageGroup/atom-framework
Description: AtoM AHG Framework - Extension system for Access to Memory
 Provides Laravel Query Builder integration, plugin management,
 Bootstrap 5 theme, and additional functionality for AtoM archives.
CTRLEOF

# Post-install script
cat > "${BUILD_DIR}/DEBIAN/postinst" << 'POSTEOF'
#!/bin/bash
set -e

ATOM_ROOT="${ATOM_ROOT:-/usr/share/nginx/atom}"
FRAMEWORK_SRC="/usr/share/atom-ahg-framework"

echo "AtoM AHG Framework installed to: ${FRAMEWORK_SRC}"
echo ""
echo "To complete installation:"
echo "  1. cd ${ATOM_ROOT}"
echo "  2. ln -sf ${FRAMEWORK_SRC} atom-framework"
echo "  3. cd atom-framework && composer install"
echo "  4. bash bin/install"
echo ""

exit 0
POSTEOF
chmod 755 "${BUILD_DIR}/DEBIAN/postinst"

# Pre-remove script
cat > "${BUILD_DIR}/DEBIAN/prerm" << 'PRERMEOF'
#!/bin/bash
set -e
echo "Removing AtoM AHG Framework..."
exit 0
PRERMEOF
chmod 755 "${BUILD_DIR}/DEBIAN/prerm"

echo "[5/5] Building package..."
dpkg-deb --build "${BUILD_DIR}" "${OUTPUT_FILE}"

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  Build Complete                                                  ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "Output: ${OUTPUT_FILE}"
echo "Size:   $(du -h "${OUTPUT_FILE}" | cut -f1)"
echo ""
echo "Install with:"
echo "  sudo dpkg -i ${OUTPUT_FILE}"
echo "  # or"
echo "  sudo apt install ./${OUTPUT_FILE##*/}"
echo ""
