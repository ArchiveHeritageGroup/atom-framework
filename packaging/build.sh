#!/bin/bash
#===============================================================================
# AtoM Heratio - DEB Package Builder
# Creates a single atom-heratio_VERSION_all.deb
#
# Usage: bash build.sh [--no-tarball]
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="$(dirname "$FRAMEWORK_PATH")"
DIST_DIR="${FRAMEWORK_PATH}/dist"
BUILD_DIR="/tmp/atom-heratio-build-$$"

# Options
INCLUDE_TARBALL=true
[ "$1" = "--no-tarball" ] && INCLUDE_TARBALL=false

# Get version from version.json
VERSION=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['version'] ?? '2.10.0';")
PKG_VERSION="${VERSION}-1"
PKG_NAME="atom-heratio"
PKG_FILE="${PKG_NAME}_${PKG_VERSION}_all.deb"

# Colors
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[OK]${NC} $1"; }
step() { echo -e "${CYAN}[=>]${NC} $1"; }
warn() { echo -e "${YELLOW}[!!]${NC} $1"; }

echo ""
echo "============================================================"
echo "  AtoM Heratio - Package Builder"
echo "============================================================"
echo ""
echo "  Package:  ${PKG_NAME}"
echo "  Version:  ${PKG_VERSION}"
echo "  Tarball:  ${INCLUDE_TARBALL}"
echo ""

# Cleanup
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

#===============================================================================
# 1. Create package directory structure
#===============================================================================
step "Creating package structure..."

PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${PKG_VERSION}_all"

mkdir -p "${PKG_DIR}/DEBIAN"
mkdir -p "${PKG_DIR}/usr/share/atom-heratio/atom-framework"
mkdir -p "${PKG_DIR}/usr/share/atom-heratio/atom-ahg-plugins"
mkdir -p "${PKG_DIR}/usr/share/atom-heratio/wizard"
mkdir -p "${PKG_DIR}/usr/share/atom-heratio/templates"
mkdir -p "${PKG_DIR}/usr/share/atom-heratio/lib"
mkdir -p "${PKG_DIR}/usr/share/doc/${PKG_NAME}"
mkdir -p "${PKG_DIR}/etc/atom-heratio"
mkdir -p "${PKG_DIR}/usr/bin"

#===============================================================================
# 2. Copy DEBIAN control files
#===============================================================================
step "Copying control files..."

# Generate binary control file (dpkg-deb needs binary format, not source+binary)
# Extract the Package stanza + Maintainer from Source stanza
MAINTAINER=$(grep '^Maintainer:' "${SCRIPT_DIR}/debian/control" | head -1)
awk '/^Package:/{found=1} found{print}' "${SCRIPT_DIR}/debian/control" > "${PKG_DIR}/DEBIAN/control"
sed -i "/^Package:/a Version: ${PKG_VERSION}" "${PKG_DIR}/DEBIAN/control"
sed -i "/^Architecture:/a ${MAINTAINER}" "${PKG_DIR}/DEBIAN/control"

for f in conffiles copyright; do
    cp "${SCRIPT_DIR}/debian/${f}" "${PKG_DIR}/DEBIAN/"
done

# Copy maintainer scripts
for f in config preinst postinst prerm postrm; do
    if [ -f "${SCRIPT_DIR}/debian/${f}" ]; then
        cp "${SCRIPT_DIR}/debian/${f}" "${PKG_DIR}/DEBIAN/"
        chmod 755 "${PKG_DIR}/DEBIAN/${f}"
    fi
done

# Copy debconf templates
cp "${SCRIPT_DIR}/debian/templates" "${PKG_DIR}/DEBIAN/"

log "Control files ready"

#===============================================================================
# 3. Bundle AtoM tarball (if available and requested)
#===============================================================================
if [ "$INCLUDE_TARBALL" = true ]; then
    step "Bundling AtoM tarball..."

    TARBALL="/usr/share/nginx/atom-latest.tar.gz"
    if [ -f "$TARBALL" ]; then
        cp "$TARBALL" "${PKG_DIR}/usr/share/atom-heratio/atom-latest.tar.gz"
        local_size=$(du -h "$TARBALL" | cut -f1)
        log "Tarball bundled (${local_size})"
    else
        warn "AtoM tarball not found at ${TARBALL}"
        warn "Package will not include AtoM tarball. 'atom-only' and 'complete' modes will fail."
    fi
fi

#===============================================================================
# 4. Copy framework (excluding .git, vendor, node_modules, dist)
#===============================================================================
step "Copying framework..."

rsync -a \
    --exclude='.git' \
    --exclude='dist/' \
    --exclude='vendor/' \
    --exclude='node_modules/' \
    --exclude='packaging/' \
    --exclude='.phpunit*' \
    --exclude='tests/' \
    "${FRAMEWORK_PATH}/" "${PKG_DIR}/usr/share/atom-heratio/atom-framework/"

log "Framework copied"

#===============================================================================
# 5. Copy plugins (excluding .git)
#===============================================================================
step "Copying plugins..."

if [ -d "${ATOM_ROOT}/atom-ahg-plugins" ]; then
    rsync -a \
        --exclude='.git' \
        --exclude='node_modules/' \
        "${ATOM_ROOT}/atom-ahg-plugins/" "${PKG_DIR}/usr/share/atom-heratio/atom-ahg-plugins/"

    plugin_count=$(find "${PKG_DIR}/usr/share/atom-heratio/atom-ahg-plugins" -maxdepth 1 -mindepth 1 -type d | wc -l)
    log "Plugins copied (${plugin_count} plugins)"
else
    warn "atom-ahg-plugins not found at ${ATOM_ROOT}/atom-ahg-plugins"
fi

#===============================================================================
# 6. Copy wizard, templates, lib, CLI tool, config
#===============================================================================
step "Copying installer components..."

# Wizard
rsync -a "${SCRIPT_DIR}/wizard/" "${PKG_DIR}/usr/share/atom-heratio/wizard/"

# Templates
rsync -a "${SCRIPT_DIR}/templates/" "${PKG_DIR}/usr/share/atom-heratio/templates/"

# Helper scripts
rsync -a "${SCRIPT_DIR}/lib/" "${PKG_DIR}/usr/share/atom-heratio/lib/"
chmod +x "${PKG_DIR}/usr/share/atom-heratio/lib/"*.sh

# CLI tool
cp "${SCRIPT_DIR}/atom-heratio.sh" "${PKG_DIR}/usr/share/atom-heratio/atom-heratio.sh"
chmod +x "${PKG_DIR}/usr/share/atom-heratio/atom-heratio.sh"

# Symlink for CLI
ln -sf /usr/share/atom-heratio/atom-heratio.sh "${PKG_DIR}/usr/bin/atom-heratio"

# Default config
cp "${SCRIPT_DIR}/atom-heratio.conf" "${PKG_DIR}/etc/atom-heratio/atom-heratio.conf"

# Documentation
cp "${FRAMEWORK_PATH}/version.json" "${PKG_DIR}/usr/share/doc/${PKG_NAME}/"

log "Installer components copied"

#===============================================================================
# 7. Generate md5sums
#===============================================================================
step "Generating checksums..."

cd "$PKG_DIR"
find . -type f ! -path './DEBIAN/*' -exec md5sum {} \; | sed 's| \./| |' > DEBIAN/md5sums

#===============================================================================
# 8. Calculate installed size
#===============================================================================
SIZE_KB=$(du -sk "$PKG_DIR" | cut -f1)
sed -i "/^Installed-Size:/d" "${PKG_DIR}/DEBIAN/control"
echo "Installed-Size: ${SIZE_KB}" >> "${PKG_DIR}/DEBIAN/control"

#===============================================================================
# 9. Build .deb
#===============================================================================
step "Building DEB package..."

dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_FILE}" 2>/dev/null

#===============================================================================
# 10. Cleanup and report
#===============================================================================
rm -rf "$BUILD_DIR"

PKG_SIZE=$(du -h "${DIST_DIR}/${PKG_FILE}" | cut -f1)

echo ""
echo "============================================================"
echo "  Package Built Successfully"
echo "============================================================"
echo ""
echo "  File:     ${DIST_DIR}/${PKG_FILE}"
echo "  Size:     ${PKG_SIZE}"
echo "  Installed: ${SIZE_KB} KB"
echo ""
echo "  Install:  sudo dpkg -i ${PKG_FILE}"
echo "            sudo apt-get install -f   # resolve dependencies"
echo ""
echo "  Or:       sudo apt install ./${PKG_FILE}"
echo ""
echo "============================================================"
echo ""
