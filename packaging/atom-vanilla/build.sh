#!/bin/bash
#===============================================================================
# AtoM 2.10.1 - Vanilla DEB Package Builder
# Creates atom_2.10.1-1_all.deb (no Heratio)
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")/dist"
BUILD_DIR="/tmp/atom-vanilla-build-$$"

ATOM_VERSION="2.10.1"
PKG_VERSION="${ATOM_VERSION}-1"
PKG_NAME="atom"
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
echo "  AtoM 2.10.1 - Vanilla Package Builder"
echo "============================================================"
echo ""
echo "  Package:  ${PKG_NAME}"
echo "  Version:  ${PKG_VERSION}"
echo ""

# Cleanup
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

#===============================================================================
# 1. Create package structure
#===============================================================================
step "Creating package structure..."

PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${PKG_VERSION}_all"

mkdir -p "${PKG_DIR}/DEBIAN"
mkdir -p "${PKG_DIR}/usr/share/atom-installer/lib"
mkdir -p "${PKG_DIR}/usr/share/atom-installer/templates"
mkdir -p "${PKG_DIR}/usr/share/doc/${PKG_NAME}"
mkdir -p "${PKG_DIR}/etc/atom"

#===============================================================================
# 2. Copy DEBIAN control files
#===============================================================================
step "Copying control files..."

# Generate binary control file (extract Package stanza + Maintainer from Source stanza)
MAINTAINER=$(grep '^Maintainer:' "${SCRIPT_DIR}/debian/control" | head -1)
awk '/^Package:/{found=1} found{print}' "${SCRIPT_DIR}/debian/control" > "${PKG_DIR}/DEBIAN/control"
sed -i "/^Package:/a Version: ${PKG_VERSION}" "${PKG_DIR}/DEBIAN/control"
sed -i "/^Architecture:/a ${MAINTAINER}" "${PKG_DIR}/DEBIAN/control"

for f in conffiles copyright; do
    cp "${SCRIPT_DIR}/debian/${f}" "${PKG_DIR}/DEBIAN/"
done

# Maintainer scripts
for f in config preinst postinst prerm postrm; do
    if [ -f "${SCRIPT_DIR}/debian/${f}" ]; then
        cp "${SCRIPT_DIR}/debian/${f}" "${PKG_DIR}/DEBIAN/"
        chmod 755 "${PKG_DIR}/DEBIAN/${f}"
    fi
done

cp "${SCRIPT_DIR}/debian/templates" "${PKG_DIR}/DEBIAN/"

log "Control files ready"

#===============================================================================
# 3. Bundle AtoM tarball
#===============================================================================
step "Bundling AtoM tarball..."

TARBALL="/usr/share/nginx/atom-latest.tar.gz"
if [ -f "$TARBALL" ]; then
    cp "$TARBALL" "${PKG_DIR}/usr/share/atom-installer/atom-latest.tar.gz"
    tarball_size=$(du -h "$TARBALL" | cut -f1)
    log "Tarball bundled (${tarball_size})"
else
    warn "AtoM tarball not found at ${TARBALL}"
    warn "Build will succeed but install will fail without tarball."
fi

#===============================================================================
# 4. Copy installer components
#===============================================================================
step "Copying installer components..."

# Helper scripts
cp "${SCRIPT_DIR}/lib/functions.sh" "${PKG_DIR}/usr/share/atom-installer/lib/"
chmod +x "${PKG_DIR}/usr/share/atom-installer/lib/"*.sh

# Templates
cp -r "${SCRIPT_DIR}/templates/"* "${PKG_DIR}/usr/share/atom-installer/templates/"

# Default config
cp "${SCRIPT_DIR}/atom.conf" "${PKG_DIR}/etc/atom/atom.conf"

log "Installer components copied"

#===============================================================================
# 5. Generate md5sums
#===============================================================================
step "Generating checksums..."

cd "$PKG_DIR"
find . -type f ! -path './DEBIAN/*' -exec md5sum {} \; | sed 's| \./| |' > DEBIAN/md5sums

#===============================================================================
# 6. Calculate installed size
#===============================================================================
SIZE_KB=$(du -sk "$PKG_DIR" | cut -f1)
echo "Installed-Size: ${SIZE_KB}" >> "${PKG_DIR}/DEBIAN/control"

#===============================================================================
# 7. Build .deb
#===============================================================================
step "Building DEB package..."

dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_FILE}"

#===============================================================================
# 8. Report
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
echo ""
echo "  Install:  sudo apt install ./${PKG_FILE}"
echo ""
echo "============================================================"
echo ""
