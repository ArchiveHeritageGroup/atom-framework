#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Build All Distribution Packages
# Creates 3 DEB packages + Ansible + Docker with 3 install modes each
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="$(dirname "$FRAMEWORK_PATH")"
DIST_DIR="${FRAMEWORK_PATH}/dist"
BUILD_DIR="/tmp/ahg-package-build-$$"

# Get version
VERSION=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['version'] ?? '2.0.0';")
ATOM_VERSION="2.10.0"
DATE=$(date +%Y-%m-%d)

# Colors
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() { echo -e "${GREEN}[✓]${NC} $1"; }
step() { echo -e "${CYAN}[→]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║         AtoM AHG Framework - Complete Package Builder            ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  Framework Version: ${VERSION}"
echo "  AtoM Version:      ${ATOM_VERSION}"
echo "  Build Date:        ${DATE}"
echo ""

# Cleanup
rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

#===============================================================================
# DEB PACKAGE 1: Extensions Only (requires existing AtoM)
#===============================================================================
build_deb_extensions() {
    step "Building DEB: Extensions Only..."
    
    local PKG_NAME="atom-ahg-extensions"
    local PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${VERSION}_all"
    
    mkdir -p "${PKG_DIR}/DEBIAN"
    mkdir -p "${PKG_DIR}/opt/atom-ahg/framework"
    mkdir -p "${PKG_DIR}/opt/atom-ahg/plugins"
    mkdir -p "${PKG_DIR}/usr/share/doc/${PKG_NAME}"
    
    cat > "${PKG_DIR}/DEBIAN/control" << CTRL
Package: ${PKG_NAME}
Version: ${VERSION}
Section: web
Priority: optional
Architecture: all
Depends: php (>= 8.1), php-mysql, composer, git
Recommends: atom (>= 2.8)
Maintainer: The Archive and Heritage Group <info@theahg.co.za>
Description: AtoM AHG Extensions - Framework + GLAM Plugins
 Laravel Query Builder integration and GLAM sector plugins for AtoM.
 Requires existing AtoM installation.
Homepage: https://github.com/ArchiveHeritageGroup/atom-framework
CTRL

    # Copy framework (excluding .git)
    rsync -a --exclude='.git' --exclude='dist' "${FRAMEWORK_PATH}/" "${PKG_DIR}/opt/atom-ahg/framework/"
    
    # Copy plugins
    if [ -d "${ATOM_ROOT}/atom-ahg-plugins" ]; then
        rsync -a --exclude='.git' "${ATOM_ROOT}/atom-ahg-plugins/" "${PKG_DIR}/opt/atom-ahg/plugins/"
    fi
    
    cat > "${PKG_DIR}/DEBIAN/postinst" << 'POSTINST'
#!/bin/bash
set -e
for path in /usr/share/nginx/archive /usr/share/nginx/atom /var/www/atom; do
    [ -f "${path}/symfony" ] && ATOM_ROOT="$path" && break
done
if [ -n "$ATOM_ROOT" ]; then
    ln -sf /opt/atom-ahg/framework "${ATOM_ROOT}/atom-framework"
    ln -sf /opt/atom-ahg/plugins "${ATOM_ROOT}/atom-ahg-plugins"
    cd /opt/atom-ahg/framework && composer install --no-dev --quiet
    [ -f /opt/atom-ahg/framework/bin/install ] && bash /opt/atom-ahg/framework/bin/install --auto
fi
echo "AtoM AHG Extensions installed!"
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
    
    dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_NAME}_${VERSION}_all.deb" 2>/dev/null
    log "Built: ${PKG_NAME}_${VERSION}_all.deb"
}

#===============================================================================
# DEB PACKAGE 2: Base AtoM Only
#===============================================================================
build_deb_atom() {
    step "Building DEB: Base AtoM Only..."
    
    local PKG_NAME="atom"
    local PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${ATOM_VERSION}_all"
    
    mkdir -p "${PKG_DIR}/DEBIAN"
    mkdir -p "${PKG_DIR}/usr/share/nginx/atom"
    mkdir -p "${PKG_DIR}/usr/share/doc/${PKG_NAME}"
    
    cat > "${PKG_DIR}/DEBIAN/control" << CTRL
Package: ${PKG_NAME}
Version: ${ATOM_VERSION}
Section: web
Priority: optional
Architecture: all
Depends: nginx, php (>= 8.1), php-fpm, php-mysql, php-mbstring, php-xml, php-curl, php-zip, php-gd, php-intl, php-xsl, mysql-server | mariadb-server, elasticsearch, gearman-job-server, php-gearman, composer, git, imagemagick, ghostscript, poppler-utils, ffmpeg
Maintainer: Artefactual Systems / AHG <info@theahg.co.za>
Description: Access to Memory (AtoM) ${ATOM_VERSION}
 Open-source archival description software.
Homepage: https://www.accesstomemory.org
CTRL

    # Copy AtoM excluding AHG stuff
    rsync -a --exclude='atom-framework' --exclude='atom-ahg-plugins' \
             --exclude='.ahg-backups' --exclude='cache/*' --exclude='log/*' \
             --exclude='.git' "${ATOM_ROOT}/" "${PKG_DIR}/usr/share/nginx/atom/"
    
    cat > "${PKG_DIR}/DEBIAN/postinst" << 'POSTINST'
#!/bin/bash
set -e
ATOM_ROOT="/usr/share/nginx/atom"
chown -R www-data:www-data "${ATOM_ROOT}"
mkdir -p "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads"
chown www-data:www-data "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads"
echo "AtoM ${ATOM_VERSION} installed to ${ATOM_ROOT}"
echo "Next: Configure database and run: php symfony tools:install"
POSTINST
    chmod 755 "${PKG_DIR}/DEBIAN/postinst"
    
    dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_NAME}_${ATOM_VERSION}_all.deb" 2>/dev/null
    log "Built: ${PKG_NAME}_${ATOM_VERSION}_all.deb"
}

#===============================================================================
# DEB PACKAGE 3: Complete (AtoM + Extensions)
#===============================================================================
build_deb_complete() {
    step "Building DEB: Complete (AtoM + Extensions)..."
    
    local PKG_NAME="atom-ahg-complete"
    local PKG_DIR="${BUILD_DIR}/${PKG_NAME}_${VERSION}_all"
    
    mkdir -p "${PKG_DIR}/DEBIAN"
    mkdir -p "${PKG_DIR}/usr/share/nginx/atom"
    mkdir -p "${PKG_DIR}/usr/share/doc/${PKG_NAME}"
    
    cat > "${PKG_DIR}/DEBIAN/control" << CTRL
Package: ${PKG_NAME}
Version: ${VERSION}
Section: web
Priority: optional
Architecture: all
Depends: nginx, php (>= 8.1), php-fpm, php-mysql, php-mbstring, php-xml, php-curl, php-zip, php-gd, php-intl, php-xsl, mysql-server | mariadb-server, elasticsearch, gearman-job-server, php-gearman, composer, git, imagemagick, ghostscript, poppler-utils, ffmpeg
Provides: atom
Conflicts: atom, atom-ahg-extensions
Replaces: atom
Maintainer: The Archive and Heritage Group <info@theahg.co.za>
Description: AtoM AHG Complete - AtoM ${ATOM_VERSION} + Framework + Extensions
 Complete installation with Bootstrap 5 theme and all GLAM plugins.
Homepage: https://github.com/ArchiveHeritageGroup/atom-framework
CTRL

    # Copy everything
    rsync -a --exclude='cache/*' --exclude='log/*' --exclude='.git' \
             "${ATOM_ROOT}/" "${PKG_DIR}/usr/share/nginx/atom/"
    
    cat > "${PKG_DIR}/DEBIAN/postinst" << 'POSTINST'
#!/bin/bash
set -e
ATOM_ROOT="/usr/share/nginx/atom"
chown -R www-data:www-data "${ATOM_ROOT}"
mkdir -p "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads"
cd "${ATOM_ROOT}/atom-framework" && composer install --no-dev --quiet 2>/dev/null || true
[ -f "${ATOM_ROOT}/atom-framework/bin/install" ] && bash "${ATOM_ROOT}/atom-framework/bin/install" --auto
systemctl restart php8.3-fpm 2>/dev/null || true
echo "AtoM AHG Complete installed!"
POSTINST
    chmod 755 "${PKG_DIR}/DEBIAN/postinst"
    
    dpkg-deb --build "$PKG_DIR" "${DIST_DIR}/${PKG_NAME}_${VERSION}_all.deb" 2>/dev/null
    log "Built: ${PKG_NAME}_${VERSION}_all.deb"
}

#===============================================================================
# ANSIBLE PLAYBOOK (extends artefactual-labs/ansible-atom)
#===============================================================================
build_ansible() {
    step "Building Ansible Playbook..."
    
    local ANSIBLE_DIR="${BUILD_DIR}/ansible"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-ahg/tasks"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-ahg/templates"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-ahg/defaults"
    mkdir -p "${ANSIBLE_DIR}/group_vars"
    
    # Main playbook with 3 modes
    cat > "${ANSIBLE_DIR}/atom-ahg-install.yml" << 'PLAYBOOK'
---
# AtoM AHG Installation Playbook
# 
# Modes (set via install_mode variable):
#   - complete:   Fresh AtoM + Framework + Extensions (default)
#   - extensions: Extensions only (existing AtoM)
#   - atom:       Base AtoM only (no extensions)
#
# Usage:
#   ansible-playbook -i inventory.yml atom-ahg-install.yml
#   ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=extensions"
#   ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=atom"

- name: AtoM AHG Installation
  hosts: atom_servers
  become: yes
  vars:
    install_mode: "complete"  # complete | extensions | atom
    
  pre_tasks:
    - name: Display installation mode
      debug:
        msg: "Installing in {{ install_mode }} mode"
        
  roles:
    - role: artefactual.atom
      when: install_mode in ['complete', 'atom']
      
    - role: atom-ahg
      when: install_mode in ['complete', 'extensions']
PLAYBOOK

    # Role defaults
    cat > "${ANSIBLE_DIR}/roles/atom-ahg/defaults/main.yml" << 'DEFAULTS'
---
# AtoM AHG Role Defaults
atom_ahg_version: "${VERSION}"
atom_path: /usr/share/nginx/atom
atom_user: www-data
atom_group: www-data

# GitHub repositories
atom_ahg_framework_repo: "https://github.com/ArchiveHeritageGroup/atom-framework.git"
atom_ahg_plugins_repo: "https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git"

# PHP settings
php_version: "8.3"
php_memory_limit: "512M"
DEFAULTS

    # Role tasks
    cat > "${ANSIBLE_DIR}/roles/atom-ahg/tasks/main.yml" << 'TASKS'
---
- name: Clone atom-framework
  git:
    repo: "{{ atom_ahg_framework_repo }}"
    dest: "{{ atom_path }}/atom-framework"
    version: main
    force: yes
  
- name: Clone atom-ahg-plugins
  git:
    repo: "{{ atom_ahg_plugins_repo }}"
    dest: "{{ atom_path }}/atom-ahg-plugins"
    version: main
    force: yes

- name: Install composer dependencies
  composer:
    command: install
    working_dir: "{{ atom_path }}/atom-framework"
    no_dev: yes

- name: Run framework install script
  command: bash bin/install --auto
  args:
    chdir: "{{ atom_path }}/atom-framework"
  
- name: Set permissions
  file:
    path: "{{ atom_path }}"
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"
    recurse: yes

- name: Restart PHP-FPM
  service:
    name: "php{{ php_version }}-fpm"
    state: restarted
TASKS

    # Inventory template
    cat > "${ANSIBLE_DIR}/inventory.yml" << 'INVENTORY'
---
all:
  children:
    atom_servers:
      hosts:
        atom1:
          ansible_host: 192.168.0.100
          ansible_user: root
          # install_mode: complete  # complete | extensions | atom
INVENTORY

    # Requirements (uses official AtoM role)
    cat > "${ANSIBLE_DIR}/requirements.yml" << 'REQS'
---
roles:
  - name: artefactual.atom
    src: https://github.com/artefactual-labs/ansible-atom
    version: main
REQS

    # README
    cat > "${ANSIBLE_DIR}/README.md" << 'README'
# AtoM AHG Ansible Playbook

## Installation Modes

| Mode | Command | Description |
|------|---------|-------------|
| **Complete** | `ansible-playbook -i inventory.yml atom-ahg-install.yml` | Fresh AtoM + Extensions |
| **Extensions** | `ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=extensions"` | Add to existing AtoM |
| **AtoM Only** | `ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=atom"` | Base AtoM only |

## Quick Start
```bash
# Install Ansible
pip install ansible

# Install required roles
ansible-galaxy install -r requirements.yml

# Edit inventory
nano inventory.yml

# Run installation
ansible-playbook -i inventory.yml atom-ahg-install.yml
```
README

    # Create tarball
    cd "${BUILD_DIR}"
    tar -czf "${DIST_DIR}/ansible-playbook.tar.gz" ansible
    log "Built: ansible-playbook.tar.gz"
}

#===============================================================================
# DOCKER COMPOSE (extends AtoM's docker setup)
#===============================================================================
build_docker() {
    step "Building Docker Compose..."
    
    local DOCKER_DIR="${BUILD_DIR}/docker"
    mkdir -p "${DOCKER_DIR}"
    
    # Environment file
    cat > "${DOCKER_DIR}/.env.example" << 'ENVFILE'
# AtoM AHG Docker Configuration
# Copy to .env and customize

# Installation mode: complete | extensions | atom
INSTALL_MODE=complete

# MySQL
MYSQL_ROOT_PASSWORD=atomroot
MYSQL_DATABASE=atom
MYSQL_USER=atom
MYSQL_PASSWORD=atompass

# AtoM
ATOM_SITE_BASE_URL=http://localhost:8000
ATOM_PHP_MEMORY_LIMIT=512M

# Elasticsearch
ES_HEAP_SIZE=512m
ENVFILE

    # Docker Compose with 3 profiles
    cat > "${DOCKER_DIR}/docker-compose.yml" << 'COMPOSE'
version: "3.8"

services:
  # ==========================================================================
  # Core Services (always run)
  # ==========================================================================
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-atomroot}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-atom}
      MYSQL_USER: ${MYSQL_USER:-atom}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-atompass}
    volumes:
      - mysql_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5

  elasticsearch:
    image: elasticsearch:8.11.0
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - ES_JAVA_OPTS=-Xms${ES_HEAP_SIZE:-512m} -Xmx${ES_HEAP_SIZE:-512m}
    volumes:
      - es_data:/usr/share/elasticsearch/data
    healthcheck:
      test: ["CMD-SHELL", "curl -s http://localhost:9200/_cluster/health | grep -q 'green\\|yellow'"]
      interval: 10s
      timeout: 5s
      retries: 5

  gearmand:
    image: artefactual/gearmand:1.1.21-alpine

  memcached:
    image: memcached:1.6-alpine

  # ==========================================================================
  # AtoM Complete (AtoM + Extensions) - Default
  # ==========================================================================
  atom-complete:
    build:
      context: .
      dockerfile: Dockerfile.complete
    profiles: ["complete", "default"]
    ports:
      - "8000:80"
    environment:
      - ATOM_SITE_BASE_URL=${ATOM_SITE_BASE_URL:-http://localhost:8000}
      - ATOM_PHP_MEMORY_LIMIT=${ATOM_PHP_MEMORY_LIMIT:-512M}
    volumes:
      - atom_uploads:/usr/share/nginx/atom/uploads
    depends_on:
      mysql:
        condition: service_healthy
      elasticsearch:
        condition: service_healthy
      gearmand:
        condition: service_started
      memcached:
        condition: service_started

  # ==========================================================================
  # AtoM Only (no extensions)
  # ==========================================================================
  atom-base:
    build:
      context: .
      dockerfile: Dockerfile.atom
    profiles: ["atom"]
    ports:
      - "8000:80"
    environment:
      - ATOM_SITE_BASE_URL=${ATOM_SITE_BASE_URL:-http://localhost:8000}
    volumes:
      - atom_uploads:/usr/share/nginx/atom/uploads
    depends_on:
      mysql:
        condition: service_healthy
      elasticsearch:
        condition: service_healthy

  # ==========================================================================
  # Worker
  # ==========================================================================
  atom-worker:
    build:
      context: .
      dockerfile: Dockerfile.complete
    profiles: ["complete", "default"]
    command: php symfony jobs:worker
    depends_on:
      - atom-complete

volumes:
  mysql_data:
  es_data:
  atom_uploads:
COMPOSE

    # Dockerfile for complete install
    cat > "${DOCKER_DIR}/Dockerfile.complete" << 'DOCKERFILE'
FROM php:8.3-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    nginx \
    git \
    composer \
    imagemagick \
    ghostscript \
    poppler-utils \
    ffmpeg \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql opcache

# Clone AtoM
RUN git clone -b stable/2.10.x https://github.com/artefactual/atom.git /usr/share/nginx/atom

# Clone AHG Framework & Plugins
RUN git clone https://github.com/ArchiveHeritageGroup/atom-framework.git /usr/share/nginx/atom/atom-framework \
    && git clone https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git /usr/share/nginx/atom/atom-ahg-plugins

WORKDIR /usr/share/nginx/atom

# Install dependencies
RUN composer install --no-dev \
    && cd atom-framework && composer install --no-dev

# Run framework install
RUN cd atom-framework && bash bin/install --auto || true

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY docker-entrypoint.sh /docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
DOCKERFILE

    # Dockerfile for base AtoM only
    cat > "${DOCKER_DIR}/Dockerfile.atom" << 'DOCKERFILE'
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx git composer imagemagick \
    && docker-php-ext-install pdo_mysql opcache

RUN git clone -b stable/2.10.x https://github.com/artefactual/atom.git /usr/share/nginx/atom

WORKDIR /usr/share/nginx/atom
RUN composer install --no-dev

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY docker-entrypoint.sh /docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["php-fpm"]
DOCKERFILE

    # Nginx config
    cat > "${DOCKER_DIR}/nginx.conf" << 'NGINX'
server {
    listen 80;
    root /usr/share/nginx/atom;
    index index.php;
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/(index|qubit_dev)\.php(/|$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
NGINX

    # Entrypoint
    cat > "${DOCKER_DIR}/docker-entrypoint.sh" << 'ENTRY'
#!/bin/sh
set -e

# Wait for MySQL
until mysql -h mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "SELECT 1" > /dev/null 2>&1; do
    echo "Waiting for MySQL..."
    sleep 2
done

# Initialize database if needed
if ! mysql -h mysql -u root -p${MYSQL_ROOT_PASSWORD} -e "USE ${MYSQL_DATABASE}; SELECT * FROM setting LIMIT 1" > /dev/null 2>&1; then
    echo "Initializing AtoM database..."
    php symfony tools:install --demo
fi

# Start nginx
nginx

exec "$@"
ENTRY
    chmod +x "${DOCKER_DIR}/docker-entrypoint.sh"

    # README
    cat > "${DOCKER_DIR}/README.md" << 'README'
# AtoM AHG Docker

## Installation Modes
```bash
# Complete (AtoM + Extensions) - Default
docker compose up -d

# AtoM Only (no extensions)
docker compose --profile atom up -d

# Extensions only - mount existing AtoM
docker compose --profile extensions up -d
```

## Quick Start
```bash
cp .env.example .env
nano .env  # Configure settings
docker compose up -d
```

Access at: http://localhost:8000
README

    # Create tarball
    cd "${BUILD_DIR}"
    tar -czf "${DIST_DIR}/docker-compose.tar.gz" docker
    log "Built: docker-compose.tar.gz"
}

#===============================================================================
# BUILD ALL
#===============================================================================
echo "Building all packages..."
echo ""

build_deb_extensions
build_deb_atom
build_deb_complete
build_ansible
build_docker

# Cleanup
rm -rf "$BUILD_DIR"

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║                    ALL PACKAGES BUILT                            ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  DEB Packages:"
echo "  ─────────────"
echo "    atom-ahg-extensions_${VERSION}_all.deb  (Extensions only)"
echo "    atom_${ATOM_VERSION}_all.deb            (Base AtoM)"
echo "    atom-ahg-complete_${VERSION}_all.deb    (AtoM + Extensions)"
echo ""
echo "  Ansible:"
echo "  ────────"
echo "    ansible-playbook.tar.gz"
echo "    Modes: complete | extensions | atom"
echo ""
echo "  Docker:"
echo "  ───────"
echo "    docker-compose.tar.gz"
echo "    Profiles: complete | atom"
echo ""
echo "  Location: ${DIST_DIR}/"
echo ""
