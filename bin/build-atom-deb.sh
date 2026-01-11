#!/bin/bash
#===============================================================================
# AtoM Base - DEB Package Builder
# Creates a meta-package that installs AtoM 2.10 from GitHub
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
DIST_DIR="${FRAMEWORK_PATH}/dist"
BUILD_DIR="/tmp/atom-deb-build-$$"

ATOM_VERSION="2.10.0"
ATOM_BRANCH="stable/2.10.x"

GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

log() { echo -e "${GREEN}[✓]${NC} $1"; }
step() { echo -e "${CYAN}[→]${NC} $1"; }

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║         AtoM Base - DEB Package Builder                          ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  AtoM Version: ${ATOM_VERSION}"
echo "  Branch:       ${ATOM_BRANCH}"
echo ""

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

step "Building AtoM base package..."

PKG_NAME="atom"
PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${ATOM_VERSION}_all"

mkdir -p "${PKG_DIR}/DEBIAN"
mkdir -p "${PKG_DIR}/usr/share/doc/${PKG_NAME}"
mkdir -p "${PKG_DIR}/etc/nginx/sites-available"
mkdir -p "${PKG_DIR}/usr/local/bin"

#-------------------------------------------------------------------------------
# Control file
#-------------------------------------------------------------------------------
cat > "${PKG_DIR}/DEBIAN/control" << CTRL
Package: ${PKG_NAME}
Version: ${ATOM_VERSION}
Section: web
Priority: optional
Architecture: all
Depends: nginx, php8.3-fpm, php8.3-mysql, php8.3-xml, php8.3-mbstring, php8.3-curl, php8.3-zip, php8.3-gd, php8.3-intl, php8.3-xsl, php8.3-opcache, php8.3-apcu, php8.3-memcached, php8.3-gearman, mysql-server, elasticsearch, gearman-job-server, memcached, composer, git, imagemagick, ghostscript, poppler-utils, ffmpeg, nodejs, npm
Maintainer: Artefactual Systems / The Archive and Heritage Group <info@theahg.co.za>
Description: Access to Memory (AtoM) ${ATOM_VERSION} - Archival Description Software
 AtoM is a web-based, open source application for standards-based
 archival description and access in a multilingual, multi-repository
 environment. First commissioned by ICA (International Council on Archives).
 .
 This package downloads and installs AtoM from the official GitHub repository.
 .
 Features:
  - ISAD(G), RAD, DACS, Dublin Core, MODS templates
  - Multi-level hierarchical descriptions
  - Authority records (ISAAR-CPF)
  - Digital object management
  - Full-text search with Elasticsearch
  - Import/Export (CSV, EAD, Dublin Core)
  - Multi-language support
Homepage: https://www.accesstomemory.org
CTRL

#-------------------------------------------------------------------------------
# Pre-install script
#-------------------------------------------------------------------------------
cat > "${PKG_DIR}/DEBIAN/preinst" << 'PREINST'
#!/bin/bash
set -e

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║             AtoM 2.10 - Pre-Installation Check                   ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

# Check if AtoM already exists
if [ -f "/usr/share/nginx/atom/symfony" ]; then
    echo "WARNING: AtoM already exists at /usr/share/nginx/atom"
    echo "This installation will update it."
    echo ""
fi

# Check PHP version
if command -v php &> /dev/null; then
    PHP_VER=$(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)
    echo "PHP Version: ${PHP_VER}"
else
    echo "PHP not found - will be installed as dependency"
fi

# Check MySQL
if command -v mysql &> /dev/null; then
    echo "MySQL: Installed"
else
    echo "MySQL: Will be installed"
fi

echo ""
echo "Proceeding with installation..."
PREINST
chmod 755 "${PKG_DIR}/DEBIAN/preinst"

#-------------------------------------------------------------------------------
# Post-install script (main installation logic)
#-------------------------------------------------------------------------------
cat > "${PKG_DIR}/DEBIAN/postinst" << 'POSTINST'
#!/bin/bash
set -e

ATOM_ROOT="/usr/share/nginx/atom"
ATOM_BRANCH="stable/2.10.x"
ATOM_REPO="https://github.com/artefactual/atom.git"
ATOM_USER="www-data"
ATOM_GROUP="www-data"

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║             AtoM 2.10 - Installation                             ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""

#-------------------------------------------------------------------------------
# Step 1: Clone or update AtoM
#-------------------------------------------------------------------------------
echo "[1/8] Downloading AtoM from GitHub..."
if [ -d "${ATOM_ROOT}/.git" ]; then
    echo "  Updating existing installation..."
    cd "${ATOM_ROOT}"
    git fetch origin
    git checkout "${ATOM_BRANCH}"
    git pull origin "${ATOM_BRANCH}"
else
    echo "  Cloning fresh installation..."
    rm -rf "${ATOM_ROOT}"
    git clone -b "${ATOM_BRANCH}" --depth 1 "${ATOM_REPO}" "${ATOM_ROOT}"
fi
echo "  ✓ AtoM source downloaded"

#-------------------------------------------------------------------------------
# Step 2: Install Composer dependencies
#-------------------------------------------------------------------------------
echo ""
echo "[2/8] Installing PHP dependencies..."
cd "${ATOM_ROOT}"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
echo "  ✓ Composer dependencies installed"

#-------------------------------------------------------------------------------
# Step 3: Create directories
#-------------------------------------------------------------------------------
echo ""
echo "[3/8] Creating directories..."
mkdir -p "${ATOM_ROOT}/cache"
mkdir -p "${ATOM_ROOT}/log"
mkdir -p "${ATOM_ROOT}/uploads"
mkdir -p "${ATOM_ROOT}/downloads"
echo "  ✓ Directories created"

#-------------------------------------------------------------------------------
# Step 4: Set permissions
#-------------------------------------------------------------------------------
echo ""
echo "[4/8] Setting permissions..."
chown -R ${ATOM_USER}:${ATOM_GROUP} "${ATOM_ROOT}"
chmod -R 755 "${ATOM_ROOT}"
chmod -R 775 "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads" "${ATOM_ROOT}/downloads"
echo "  ✓ Permissions set"

#-------------------------------------------------------------------------------
# Step 5: Configure MySQL
#-------------------------------------------------------------------------------
echo ""
echo "[5/8] Configuring MySQL..."

# Start MySQL if not running
systemctl start mysql 2>/dev/null || true

# Create database if not exists
if ! mysql -u root -e "USE atom" 2>/dev/null; then
    echo "  Creating database 'atom'..."
    mysql -u root << EOSQL
CREATE DATABASE IF NOT EXISTS atom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'atom'@'localhost' IDENTIFIED BY 'atompass';
GRANT ALL PRIVILEGES ON atom.* TO 'atom'@'localhost';
FLUSH PRIVILEGES;
EOSQL
    echo "  ✓ Database created (user: atom, pass: atompass)"
else
    echo "  ✓ Database 'atom' already exists"
fi

#-------------------------------------------------------------------------------
# Step 6: Create config.php
#-------------------------------------------------------------------------------
echo ""
echo "[6/8] Creating configuration..."
if [ ! -f "${ATOM_ROOT}/config/config.php" ]; then
    cat > "${ATOM_ROOT}/config/config.php" << 'EOFCONFIG'
<?php
return [
    'all' => [
        'propel' => [
            'class' => 'sfPropelDatabase',
            'param' => [
                'encoding' => 'utf8mb4',
                'persistent' => true,
                'pooling' => true,
                'dsn' => 'mysql:host=localhost;dbname=atom;charset=utf8mb4',
                'username' => 'atom',
                'password' => 'atompass',
            ],
        ],
    ],
];
EOFCONFIG
    chown ${ATOM_USER}:${ATOM_GROUP} "${ATOM_ROOT}/config/config.php"
    chmod 640 "${ATOM_ROOT}/config/config.php"
    echo "  ✓ config.php created"
else
    echo "  ✓ config.php already exists"
fi

#-------------------------------------------------------------------------------
# Step 7: Configure Nginx
#-------------------------------------------------------------------------------
echo ""
echo "[7/8] Configuring Nginx..."

cat > /etc/nginx/sites-available/atom << 'EOFNGINX'
server {
    listen 80;
    server_name _;
    root /usr/share/nginx/atom;
    index index.php;
    
    client_max_body_size 64M;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/(index|qubit_dev)\.php(/|$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 120;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }
    
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
    
    location ~ /\. {
        deny all;
    }
}
EOFNGINX

# Enable site
ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/atom
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

# Test and reload nginx
nginx -t && systemctl reload nginx
echo "  ✓ Nginx configured"

#-------------------------------------------------------------------------------
# Step 8: Start services
#-------------------------------------------------------------------------------
echo ""
echo "[8/8] Starting services..."
systemctl enable --now mysql
systemctl enable --now elasticsearch
systemctl enable --now gearman-job-server
systemctl enable --now memcached
systemctl enable --now php8.3-fpm
systemctl enable --now nginx
echo "  ✓ Services started"

#-------------------------------------------------------------------------------
# Final message
#-------------------------------------------------------------------------------
echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║             AtoM 2.10 Installation Complete!                     ║"
echo "╠══════════════════════════════════════════════════════════════════╣"
echo "║                                                                  ║"
echo "║  Location:  /usr/share/nginx/atom                                ║"
echo "║  Database:  atom (user: atom, pass: atompass)                    ║"
echo "║                                                                  ║"
echo "║  NEXT STEP - Initialize the database:                           ║"
echo "║                                                                  ║"
echo "║    cd /usr/share/nginx/atom                                      ║"
echo "║    php symfony tools:install                                     ║"
echo "║                                                                  ║"
echo "║  Then access AtoM at: http://your-server-ip                      ║"
echo "║                                                                  ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
POSTINST
chmod 755 "${PKG_DIR}/DEBIAN/postinst"

#-------------------------------------------------------------------------------
# Pre-remove script
#-------------------------------------------------------------------------------
cat > "${PKG_DIR}/DEBIAN/prerm" << 'PRERM'
#!/bin/bash
echo "AtoM will be removed. Database and uploads will be preserved."
echo "To completely remove, manually delete:"
echo "  - /usr/share/nginx/atom"
echo "  - MySQL database 'atom'"
PRERM
chmod 755 "${PKG_DIR}/DEBIAN/prerm"

#-------------------------------------------------------------------------------
# Post-remove script
#-------------------------------------------------------------------------------
cat > "${PKG_DIR}/DEBIAN/postrm" << 'POSTRM'
#!/bin/bash
if [ "$1" = "purge" ]; then
    rm -rf /usr/share/nginx/atom
    rm -f /etc/nginx/sites-available/atom
    rm -f /etc/nginx/sites-enabled/atom
    mysql -u root -e "DROP DATABASE IF EXISTS atom; DROP USER IF EXISTS 'atom'@'localhost';" 2>/dev/null || true
    echo "AtoM completely removed."
fi
POSTRM
chmod 755 "${PKG_DIR}/DEBIAN/postrm"

#-------------------------------------------------------------------------------
# Documentation
#-------------------------------------------------------------------------------
cat > "${PKG_DIR}/usr/share/doc/${PKG_NAME}/README.Debian" << 'README'
AtoM (Access to Memory) for Debian/Ubuntu
==========================================

This package installs AtoM 2.10 from the official GitHub repository.

After installation, initialize the database:
  cd /usr/share/nginx/atom
  php symfony tools:install

Default database credentials:
  Database: atom
  Username: atom
  Password: atompass

To change the password, edit:
  /usr/share/nginx/atom/config/config.php

Documentation:
  https://www.accesstomemory.org/docs/

Source:
  https://github.com/artefactual/atom
README

cat > "${PKG_DIR}/usr/share/doc/${PKG_NAME}/copyright" << 'COPYRIGHT'
Format: https://www.debian.org/doc/packaging-manuals/copyright-format/1.0/
Upstream-Name: AtoM
Upstream-Contact: info@artefactual.com
Source: https://github.com/artefactual/atom

Files: *
Copyright: 2006-2026 Artefactual Systems Inc.
License: AGPL-3.0
COPYRIGHT

#-------------------------------------------------------------------------------
# Build package
#-------------------------------------------------------------------------------
step "Building package..."
dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_NAME}_${ATOM_VERSION}_all.deb"

rm -rf "$BUILD_DIR"

SIZE=$(du -h "${DIST_DIR}/${PKG_NAME}_${ATOM_VERSION}_all.deb" | cut -f1)

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║                    PACKAGE BUILT                                 ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  Package: ${DIST_DIR}/${PKG_NAME}_${ATOM_VERSION}_all.deb"
echo "  Size:    ${SIZE}"
echo ""
echo "  This is a META-PACKAGE that downloads AtoM during installation."
echo ""
echo "  Install:"
echo "    sudo apt install ./${PKG_NAME}_${ATOM_VERSION}_all.deb"
echo ""
echo "  After install, run:"
echo "    cd /usr/share/nginx/atom && php symfony tools:install"
echo ""
log "Done!"
