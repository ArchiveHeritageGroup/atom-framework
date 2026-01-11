#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Build DEB Packages
# Creates: Extensions only + Complete (AtoM + Extensions)
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="$(dirname "$FRAMEWORK_PATH")"
DIST_DIR="${FRAMEWORK_PATH}/dist"
BUILD_DIR="/tmp/ahg-deb-build-$$"

VERSION=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['version'] ?? '2.0.0';")
DATE=$(date +%Y-%m-%d)

GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

log() { echo -e "${GREEN}[✓]${NC} $1"; }
step() { echo -e "${CYAN}[→]${NC} $1"; }

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║         AtoM AHG Framework - DEB Package Builder                 ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  Version: ${VERSION}"
echo ""

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

#===============================================================================
# Extensions Only Package
#===============================================================================
build_extensions() {
    step "Building: atom-ahg-extensions (Framework + Plugins only)..."
    
    local PKG_NAME="atom-ahg-extensions"
    local PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${VERSION}_all"
    
    mkdir -p "${PKG_DIR}/DEBIAN"
    mkdir -p "${PKG_DIR}/opt/atom-ahg/framework"
    mkdir -p "${PKG_DIR}/opt/atom-ahg/plugins"
    
    cat > "${PKG_DIR}/DEBIAN/control" << CTRL
Package: ${PKG_NAME}
Version: ${VERSION}
Section: web
Priority: optional
Architecture: all
Depends: php (>= 8.1), php-mysql, composer, git
Recommends: nginx, mysql-server, elasticsearch
Maintainer: The Archive and Heritage Group <info@theahg.co.za>
Description: AtoM AHG Extensions - Framework + GLAM Plugins
 Laravel Query Builder integration and GLAM sector plugins for AtoM 2.10.
 Requires existing AtoM installation.
Homepage: https://github.com/ArchiveHeritageGroup/atom-framework
CTRL

    # Copy framework (small - just our code)
    echo "  Copying framework..."
    rsync -a --exclude='.git' --exclude='dist' --exclude='vendor' \
        "${FRAMEWORK_PATH}/" "${PKG_DIR}/opt/atom-ahg/framework/"
    
    # Copy plugins
    if [ -d "${ATOM_ROOT}/atom-ahg-plugins" ]; then
        echo "  Copying plugins..."
        rsync -a --exclude='.git' \
            "${ATOM_ROOT}/atom-ahg-plugins/" "${PKG_DIR}/opt/atom-ahg/plugins/"
    fi
    
    cat > "${PKG_DIR}/DEBIAN/postinst" << 'POSTINST'
#!/bin/bash
set -e

# Find AtoM installation
for path in /usr/share/nginx/archive /usr/share/nginx/atom /var/www/atom; do
    if [ -f "${path}/symfony" ]; then
        ATOM_ROOT="$path"
        break
    fi
done

if [ -z "$ATOM_ROOT" ]; then
    echo ""
    echo "╔══════════════════════════════════════════════════════════════╗"
    echo "║  WARNING: AtoM installation not found!                       ║"
    echo "║                                                              ║"
    echo "║  Files installed to /opt/atom-ahg/                           ║"
    echo "║  Manual setup required:                                      ║"
    echo "║    ln -s /opt/atom-ahg/framework /path/to/atom/atom-framework║"
    echo "║    ln -s /opt/atom-ahg/plugins /path/to/atom/atom-ahg-plugins║"
    echo "║    cd /opt/atom-ahg/framework && bash bin/install            ║"
    echo "╚══════════════════════════════════════════════════════════════╝"
    exit 0
fi

echo "Found AtoM at: ${ATOM_ROOT}"

# Create symlinks
ln -sf /opt/atom-ahg/framework "${ATOM_ROOT}/atom-framework"
ln -sf /opt/atom-ahg/plugins "${ATOM_ROOT}/atom-ahg-plugins"

# Install composer dependencies
echo "Installing dependencies..."
cd /opt/atom-ahg/framework
composer install --no-dev --quiet 2>/dev/null || true

# Run framework install
echo "Running framework install..."
if [ -f /opt/atom-ahg/framework/bin/install ]; then
    bash /opt/atom-ahg/framework/bin/install --auto 2>/dev/null || true
fi

# Restart PHP-FPM
systemctl restart php8.3-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║  AtoM AHG Extensions installed successfully!                     ║"
echo "║                                                                  ║"
echo "║  AtoM Root: ${ATOM_ROOT}                                         ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
POSTINST
    chmod 755 "${PKG_DIR}/DEBIAN/postinst"
    
    cat > "${PKG_DIR}/DEBIAN/prerm" << 'PRERM'
#!/bin/bash
for path in /usr/share/nginx/archive /usr/share/nginx/atom /var/www/atom; do
    [ -L "${path}/atom-framework" ] && rm -f "${path}/atom-framework"
    [ -L "${path}/atom-ahg-plugins" ] && rm -f "${path}/atom-ahg-plugins"
done
exit 0
PRERM
    chmod 755 "${PKG_DIR}/DEBIAN/prerm"
    
    echo "  Building package..."
    dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_NAME}_${VERSION}_all.deb"
    log "Built: ${PKG_NAME}_${VERSION}_all.deb ($(du -h "${DIST_DIR}/${PKG_NAME}_${VERSION}_all.deb" | cut -f1))"
}

#===============================================================================
# Build
#===============================================================================
build_extensions

rm -rf "$BUILD_DIR"

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║                    PACKAGE BUILT                                 ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  ${DIST_DIR}/atom-ahg-extensions_${VERSION}_all.deb"
echo ""
echo "  Install on server with existing AtoM:"
echo "    sudo apt install ./atom-ahg-extensions_${VERSION}_all.deb"
echo ""
