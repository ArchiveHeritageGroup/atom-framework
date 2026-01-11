#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Build Ansible & Docker Packages
# Creates Ansible playbook and Docker Compose with 3 install modes each
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
DIST_DIR="${FRAMEWORK_PATH}/dist"
BUILD_DIR="/tmp/ahg-package-build-$$"

# Get version
VERSION=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['version'] ?? '2.0.0';")
ATOM_VERSION="2.10.0"
DATE=$(date +%Y-%m-%d)

# Colors
GREEN='\033[0;32m'
CYAN='\033[0;36m'
NC='\033[0m'

log() { echo -e "${GREEN}[✓]${NC} $1"; }
step() { echo -e "${CYAN}[→]${NC} $1"; }

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║       AtoM AHG Framework - Ansible & Docker Builder              ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  Framework Version: ${VERSION}"
echo "  AtoM Version:      ${ATOM_VERSION}"
echo ""

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR"
mkdir -p "$DIST_DIR"

#===============================================================================
# ANSIBLE PLAYBOOK
#===============================================================================
build_ansible() {
    step "Building Ansible Playbook..."
    
    local ANSIBLE_DIR="${BUILD_DIR}/ansible"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-ahg/tasks"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-ahg/handlers"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-ahg/defaults"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-base/tasks"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-base/handlers"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-base/defaults"
    mkdir -p "${ANSIBLE_DIR}/roles/atom-base/templates"
    mkdir -p "${ANSIBLE_DIR}/group_vars"
    mkdir -p "${ANSIBLE_DIR}/host_vars"
    
    #---------------------------------------------------------------------------
    # Main Playbook
    #---------------------------------------------------------------------------
    cat > "${ANSIBLE_DIR}/atom-ahg-install.yml" << 'PLAYBOOK'
---
# =============================================================================
# AtoM AHG Installation Playbook
# =============================================================================
# 
# Installation Modes (set via install_mode variable):
#   - complete:   Fresh AtoM 2.10 + Framework + Extensions (default)
#   - extensions: Extensions only (requires existing AtoM)
#   - atom:       Base AtoM 2.10 only (no extensions)
#
# Usage Examples:
#   # Complete install (default)
#   ansible-playbook -i inventory.yml atom-ahg-install.yml
#
#   # Extensions only (existing AtoM)
#   ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=extensions"
#
#   # Base AtoM only
#   ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=atom"
#
# =============================================================================

- name: AtoM AHG Installation
  hosts: atom_servers
  become: yes
  
  vars:
    install_mode: "complete"
    
  pre_tasks:
    - name: Display installation mode
      debug:
        msg: |
          ╔══════════════════════════════════════════════════════════════╗
          ║  Installation Mode: {{ install_mode | upper }}
          ║  Target: {{ inventory_hostname }}
          ╚══════════════════════════════════════════════════════════════╝

    - name: Validate install_mode
      fail:
        msg: "Invalid install_mode '{{ install_mode }}'. Must be: complete, extensions, or atom"
      when: install_mode not in ['complete', 'extensions', 'atom']

    - name: Check for existing AtoM (extensions mode)
      stat:
        path: "{{ atom_path }}/symfony"
      register: atom_exists
      when: install_mode == 'extensions'

    - name: Fail if AtoM not found (extensions mode)
      fail:
        msg: "AtoM not found at {{ atom_path }}. Use 'complete' mode for fresh install."
      when: 
        - install_mode == 'extensions'
        - not atom_exists.stat.exists | default(false)

  roles:
    # Install base AtoM (complete or atom mode)
    - role: atom-base
      when: install_mode in ['complete', 'atom']
      
    # Install AHG extensions (complete or extensions mode)
    - role: atom-ahg
      when: install_mode in ['complete', 'extensions']

  post_tasks:
    - name: Display completion message
      debug:
        msg: |
          ╔══════════════════════════════════════════════════════════════╗
          ║  Installation Complete!
          ║  
          ║  Mode: {{ install_mode }}
          ║  Path: {{ atom_path }}
          ║  URL:  http://{{ ansible_host }}
          ╚══════════════════════════════════════════════════════════════╝
PLAYBOOK

    #---------------------------------------------------------------------------
    # Group Variables
    #---------------------------------------------------------------------------
    cat > "${ANSIBLE_DIR}/group_vars/all.yml" << 'GROUPVARS'
---
# =============================================================================
# Global Variables for All Hosts
# =============================================================================

# AtoM Installation
atom_path: /usr/share/nginx/atom
atom_user: www-data
atom_group: www-data
atom_version: "stable/2.10.x"

# PHP Configuration
php_version: "8.3"
php_memory_limit: "512M"
php_max_execution_time: 120
php_upload_max_filesize: "64M"
php_post_max_size: "72M"

# MySQL Configuration
mysql_root_password: "{{ vault_mysql_root_password | default('changeme') }}"
mysql_atom_database: atom
mysql_atom_user: atom
mysql_atom_password: "{{ vault_mysql_atom_password | default('atompass') }}"

# Elasticsearch
elasticsearch_version: "8.11.0"
elasticsearch_heap_size: "512m"

# AHG Framework
atom_ahg_framework_repo: "https://github.com/ArchiveHeritageGroup/atom-framework.git"
atom_ahg_plugins_repo: "https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git"
atom_ahg_branch: "main"

# Nginx
nginx_server_name: "{{ inventory_hostname }}"
GROUPVARS

    #---------------------------------------------------------------------------
    # Inventory Template
    #---------------------------------------------------------------------------
    cat > "${ANSIBLE_DIR}/inventory.yml" << 'INVENTORY'
---
# =============================================================================
# AtoM Server Inventory
# =============================================================================
# 
# Edit this file with your server details
# 
# Per-host install_mode can be set in host_vars/hostname.yml
# or passed via command line: -e "install_mode=extensions"
#
# =============================================================================

all:
  children:
    atom_servers:
      hosts:
        # Example: Single server
        atom-prod:
          ansible_host: 192.168.1.100
          ansible_user: root
          # install_mode: complete  # Override per host if needed
          
        # Example: Add extensions to existing AtoM
        # atom-existing:
        #   ansible_host: 192.168.1.101
        #   ansible_user: root
        #   install_mode: extensions
        
        # Example: Base AtoM only
        # atom-base:
        #   ansible_host: 192.168.1.102
        #   ansible_user: root
        #   install_mode: atom
INVENTORY

    #---------------------------------------------------------------------------
    # Role: atom-base (Base AtoM Installation)
    #---------------------------------------------------------------------------
    cat > "${ANSIBLE_DIR}/roles/atom-base/defaults/main.yml" << 'ATOMDEFAULTS'
---
# atom-base role defaults
atom_path: /usr/share/nginx/atom
atom_user: www-data
atom_group: www-data
atom_version: "stable/2.10.x"
atom_repo: "https://github.com/artefactual/atom.git"
ATOMDEFAULTS

    cat > "${ANSIBLE_DIR}/roles/atom-base/tasks/main.yml" << 'ATOMTASKS'
---
# =============================================================================
# atom-base: Install Base AtoM 2.10
# =============================================================================

- name: Install system dependencies
  apt:
    name:
      - nginx
      - php{{ php_version }}-fpm
      - php{{ php_version }}-mysql
      - php{{ php_version }}-xml
      - php{{ php_version }}-mbstring
      - php{{ php_version }}-curl
      - php{{ php_version }}-zip
      - php{{ php_version }}-gd
      - php{{ php_version }}-intl
      - php{{ php_version }}-xsl
      - php{{ php_version }}-opcache
      - php{{ php_version }}-apcu
      - php{{ php_version }}-memcached
      - mysql-server
      - elasticsearch
      - gearman-job-server
      - php{{ php_version }}-gearman
      - memcached
      - composer
      - git
      - imagemagick
      - ghostscript
      - poppler-utils
      - ffmpeg
      - nodejs
      - npm
    state: present
    update_cache: yes

- name: Create MySQL database
  mysql_db:
    name: "{{ mysql_atom_database }}"
    state: present
    login_unix_socket: /var/run/mysqld/mysqld.sock

- name: Create MySQL user
  mysql_user:
    name: "{{ mysql_atom_user }}"
    password: "{{ mysql_atom_password }}"
    priv: "{{ mysql_atom_database }}.*:ALL"
    state: present
    login_unix_socket: /var/run/mysqld/mysqld.sock

- name: Clone AtoM repository
  git:
    repo: "{{ atom_repo }}"
    dest: "{{ atom_path }}"
    version: "{{ atom_version }}"
    force: yes
  
- name: Install AtoM composer dependencies
  composer:
    command: install
    working_dir: "{{ atom_path }}"
    no_dev: yes

- name: Create config.php from template
  template:
    src: config.php.j2
    dest: "{{ atom_path }}/config/config.php"
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"
    mode: '0640'

- name: Create required directories
  file:
    path: "{{ atom_path }}/{{ item }}"
    state: directory
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"
    mode: '0755'
  loop:
    - cache
    - log
    - uploads
    - downloads

- name: Set AtoM ownership
  file:
    path: "{{ atom_path }}"
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"
    recurse: yes

- name: Configure nginx
  template:
    src: nginx-atom.conf.j2
    dest: /etc/nginx/sites-available/atom
  notify: Restart nginx

- name: Enable nginx site
  file:
    src: /etc/nginx/sites-available/atom
    dest: /etc/nginx/sites-enabled/atom
    state: link
  notify: Restart nginx

- name: Remove default nginx site
  file:
    path: /etc/nginx/sites-enabled/default
    state: absent
  notify: Restart nginx

- name: Initialize AtoM database
  command: php symfony tools:install --demo --no-confirmation
  args:
    chdir: "{{ atom_path }}"
    creates: "{{ atom_path }}/.installed"
  become_user: "{{ atom_user }}"

- name: Mark as installed
  file:
    path: "{{ atom_path }}/.installed"
    state: touch
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"

- name: Start and enable services
  service:
    name: "{{ item }}"
    state: started
    enabled: yes
  loop:
    - nginx
    - php{{ php_version }}-fpm
    - mysql
    - elasticsearch
    - gearman-job-server
    - memcached
ATOMTASKS

    cat > "${ANSIBLE_DIR}/roles/atom-base/handlers/main.yml" << 'ATOMHANDLERS'
---
- name: Restart nginx
  service:
    name: nginx
    state: restarted

- name: Restart php-fpm
  service:
    name: "php{{ php_version }}-fpm"
    state: restarted
ATOMHANDLERS

    cat > "${ANSIBLE_DIR}/roles/atom-base/templates/config.php.j2" << 'CONFIGPHP'
<?php
// AtoM Configuration
return [
    'all' => [
        'propel' => [
            'class' => 'sfPropelDatabase',
            'param' => [
                'encoding' => 'utf8mb4',
                'persistent' => true,
                'pooling' => true,
                'dsn' => 'mysql:host=localhost;dbname={{ mysql_atom_database }};charset=utf8mb4',
                'username' => '{{ mysql_atom_user }}',
                'password' => '{{ mysql_atom_password }}',
            ],
        ],
    ],
];
CONFIGPHP

    cat > "${ANSIBLE_DIR}/roles/atom-base/templates/nginx-atom.conf.j2" << 'NGINXCONF'
server {
    listen 80;
    server_name {{ nginx_server_name }};
    root {{ atom_path }};
    
    client_max_body_size {{ php_upload_max_filesize }};
    
    location / {
        try_files $uri /index.php$is_args$args;
    }
    
    location ~ ^/(index|qubit_dev)\.php(/|$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php{{ php_version }}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout {{ php_max_execution_time }};
    }
    
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    location ~ /\. {
        deny all;
    }
}
NGINXCONF

    #---------------------------------------------------------------------------
    # Role: atom-ahg (AHG Extensions)
    #---------------------------------------------------------------------------
    cat > "${ANSIBLE_DIR}/roles/atom-ahg/defaults/main.yml" << 'AHGDEFAULTS'
---
# atom-ahg role defaults
atom_path: /usr/share/nginx/atom
atom_user: www-data
atom_group: www-data
atom_ahg_framework_repo: "https://github.com/ArchiveHeritageGroup/atom-framework.git"
atom_ahg_plugins_repo: "https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git"
atom_ahg_branch: "main"
php_version: "8.3"
AHGDEFAULTS

    cat > "${ANSIBLE_DIR}/roles/atom-ahg/tasks/main.yml" << 'AHGTASKS'
---
# =============================================================================
# atom-ahg: Install AHG Framework & Extensions
# =============================================================================

- name: Clone atom-framework
  git:
    repo: "{{ atom_ahg_framework_repo }}"
    dest: "{{ atom_path }}/atom-framework"
    version: "{{ atom_ahg_branch }}"
    force: yes
  
- name: Clone atom-ahg-plugins
  git:
    repo: "{{ atom_ahg_plugins_repo }}"
    dest: "{{ atom_path }}/atom-ahg-plugins"
    version: "{{ atom_ahg_branch }}"
    force: yes

- name: Install framework composer dependencies
  composer:
    command: install
    working_dir: "{{ atom_path }}/atom-framework"
    no_dev: yes

- name: Run framework install script
  command: bash bin/install --auto
  args:
    chdir: "{{ atom_path }}/atom-framework"
  register: install_result
  changed_when: "'already installed' not in install_result.stdout"
  
- name: Set framework ownership
  file:
    path: "{{ atom_path }}/{{ item }}"
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"
    recurse: yes
  loop:
    - atom-framework
    - atom-ahg-plugins

- name: Clear AtoM cache
  file:
    path: "{{ atom_path }}/cache"
    state: absent
  
- name: Recreate cache directory
  file:
    path: "{{ atom_path }}/cache"
    state: directory
    owner: "{{ atom_user }}"
    group: "{{ atom_group }}"
    mode: '0755'

- name: Restart PHP-FPM
  service:
    name: "php{{ php_version }}-fpm"
    state: restarted
AHGTASKS

    cat > "${ANSIBLE_DIR}/roles/atom-ahg/handlers/main.yml" << 'AHGHANDLERS'
---
- name: Restart php-fpm
  service:
    name: "php{{ php_version }}-fpm"
    state: restarted

- name: Clear atom cache
  command: php symfony cc
  args:
    chdir: "{{ atom_path }}"
AHGHANDLERS

    #---------------------------------------------------------------------------
    # README
    #---------------------------------------------------------------------------
    cat > "${ANSIBLE_DIR}/README.md" << 'README'
# AtoM AHG Ansible Playbook

## Installation Modes

| Mode | Description | Command |
|------|-------------|---------|
| **complete** | Fresh AtoM 2.10 + Framework + Extensions | `ansible-playbook -i inventory.yml atom-ahg-install.yml` |
| **extensions** | Add extensions to existing AtoM | `ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=extensions"` |
| **atom** | Base AtoM 2.10 only | `ansible-playbook -i inventory.yml atom-ahg-install.yml -e "install_mode=atom"` |

## Quick Start
```bash
# 1. Install Ansible
pip install ansible

# 2. Edit inventory with your servers
nano inventory.yml

# 3. (Optional) Create vault for passwords
ansible-vault create group_vars/vault.yml

# 4. Run installation
ansible-playbook -i inventory.yml atom-ahg-install.yml
```

## Per-Host Configuration

Create `host_vars/hostname.yml` for host-specific settings:
```yaml
---
install_mode: extensions
atom_path: /var/www/atom
mysql_atom_password: "secretpassword"
```

## Requirements

- Ansible 2.10+
- Target: Ubuntu 22.04 LTS
- SSH access with sudo privileges
README

    # Create tarball
    cd "${BUILD_DIR}"
    tar -czf "${DIST_DIR}/ansible-playbook.tar.gz" ansible
    log "Built: ansible-playbook.tar.gz"
}

#===============================================================================
# DOCKER COMPOSE
#===============================================================================
build_docker() {
    step "Building Docker Compose..."
    
    local DOCKER_DIR="${BUILD_DIR}/docker"
    mkdir -p "${DOCKER_DIR}"
    
    #---------------------------------------------------------------------------
    # Environment File
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/.env.example" << 'ENVFILE'
# =============================================================================
# AtoM AHG Docker Configuration
# =============================================================================
# Copy to .env and customize before running docker compose

# Installation Mode: complete | extensions | atom
# - complete:   AtoM 2.10 + Framework + Extensions (default)
# - atom:       Base AtoM 2.10 only
# - extensions: Use with existing AtoM volume
INSTALL_MODE=complete

# MySQL Configuration
MYSQL_ROOT_PASSWORD=atomroot
MYSQL_DATABASE=atom
MYSQL_USER=atom
MYSQL_PASSWORD=atompass

# AtoM Configuration
ATOM_SITE_BASE_URL=http://localhost:8000
ATOM_TITLE="My Archive"
ATOM_DESCRIPTION="Powered by AtoM + AHG Extensions"

# PHP Configuration
ATOM_PHP_MEMORY_LIMIT=512M
ATOM_PHP_MAX_EXECUTION_TIME=120
ATOM_UPLOAD_LIMIT=64M

# Elasticsearch
ES_HEAP_SIZE=512m
ES_JAVA_OPTS=-Xms512m -Xmx512m
ENVFILE

    #---------------------------------------------------------------------------
    # Docker Compose
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/docker-compose.yml" << 'COMPOSE'
# =============================================================================
# AtoM AHG Docker Compose
# =============================================================================
#
# Usage:
#   # Complete (AtoM + Extensions) - Default
#   docker compose up -d
#
#   # Base AtoM only
#   docker compose --profile atom up -d
#
#   # View logs
#   docker compose logs -f atom
#
# =============================================================================

services:
  # ===========================================================================
  # Database Services
  # ===========================================================================
  mysql:
    image: mysql:8.0
    container_name: atom-mysql
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD:-atomroot}
      MYSQL_DATABASE: ${MYSQL_DATABASE:-atom}
      MYSQL_USER: ${MYSQL_USER:-atom}
      MYSQL_PASSWORD: ${MYSQL_PASSWORD:-atompass}
    volumes:
      - mysql_data:/var/lib/mysql
      - ./init.sql:/docker-entrypoint-initdb.d/init.sql:ro
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "root", "-p${MYSQL_ROOT_PASSWORD:-atomroot}"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 30s
    networks:
      - atom-network

  elasticsearch:
    image: elasticsearch:${ES_VERSION:-8.11.0}
    container_name: atom-elasticsearch
    restart: unless-stopped
    environment:
      - discovery.type=single-node
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=${ES_JAVA_OPTS:--Xms512m -Xmx512m}"
    volumes:
      - es_data:/usr/share/elasticsearch/data
    healthcheck:
      test: ["CMD-SHELL", "curl -s http://localhost:9200/_cluster/health | grep -qE 'green|yellow'"]
      interval: 10s
      timeout: 5s
      retries: 10
      start_period: 60s
    networks:
      - atom-network

  gearmand:
    image: artefactual/gearmand:1.1.21-alpine
    container_name: atom-gearmand
    restart: unless-stopped
    networks:
      - atom-network

  memcached:
    image: memcached:1.6-alpine
    container_name: atom-memcached
    restart: unless-stopped
    networks:
      - atom-network

  # ===========================================================================
  # AtoM Complete (AtoM + Extensions) - Default Profile
  # ===========================================================================
  atom:
    build:
      context: .
      dockerfile: Dockerfile.complete
      args:
        - PHP_VERSION=8.3
    container_name: atom-app
    restart: unless-stopped
    profiles: ["", "complete"]
    ports:
      - "8000:80"
    environment:
      - MYSQL_HOST=mysql
      - MYSQL_DATABASE=${MYSQL_DATABASE:-atom}
      - MYSQL_USER=${MYSQL_USER:-atom}
      - MYSQL_PASSWORD=${MYSQL_PASSWORD:-atompass}
      - ATOM_SITE_BASE_URL=${ATOM_SITE_BASE_URL:-http://localhost:8000}
      - ATOM_PHP_MEMORY_LIMIT=${ATOM_PHP_MEMORY_LIMIT:-512M}
      - ATOM_ES_HOST=elasticsearch
      - ATOM_MEMCACHE_HOST=memcached
      - ATOM_GEARMAN_HOST=gearmand
    volumes:
      - atom_uploads:/usr/share/nginx/atom/uploads
      - atom_downloads:/usr/share/nginx/atom/downloads
    depends_on:
      mysql:
        condition: service_healthy
      elasticsearch:
        condition: service_healthy
      gearmand:
        condition: service_started
      memcached:
        condition: service_started
    networks:
      - atom-network

  atom-worker:
    build:
      context: .
      dockerfile: Dockerfile.complete
    container_name: atom-worker
    restart: unless-stopped
    profiles: ["", "complete"]
    command: php symfony jobs:worker
    environment:
      - MYSQL_HOST=mysql
      - MYSQL_DATABASE=${MYSQL_DATABASE:-atom}
      - MYSQL_USER=${MYSQL_USER:-atom}
      - MYSQL_PASSWORD=${MYSQL_PASSWORD:-atompass}
      - ATOM_ES_HOST=elasticsearch
      - ATOM_GEARMAN_HOST=gearmand
    volumes:
      - atom_uploads:/usr/share/nginx/atom/uploads
      - atom_downloads:/usr/share/nginx/atom/downloads
    depends_on:
      - atom
    networks:
      - atom-network

  # ===========================================================================
  # AtoM Base Only (No Extensions) - atom Profile
  # ===========================================================================
  atom-base:
    build:
      context: .
      dockerfile: Dockerfile.atom
    container_name: atom-base-app
    restart: unless-stopped
    profiles: ["atom"]
    ports:
      - "8000:80"
    environment:
      - MYSQL_HOST=mysql
      - MYSQL_DATABASE=${MYSQL_DATABASE:-atom}
      - MYSQL_USER=${MYSQL_USER:-atom}
      - MYSQL_PASSWORD=${MYSQL_PASSWORD:-atompass}
      - ATOM_SITE_BASE_URL=${ATOM_SITE_BASE_URL:-http://localhost:8000}
      - ATOM_ES_HOST=elasticsearch
    volumes:
      - atom_uploads:/usr/share/nginx/atom/uploads
    depends_on:
      mysql:
        condition: service_healthy
      elasticsearch:
        condition: service_healthy
    networks:
      - atom-network

volumes:
  mysql_data:
  es_data:
  atom_uploads:
  atom_downloads:

networks:
  atom-network:
    driver: bridge
COMPOSE

    #---------------------------------------------------------------------------
    # Dockerfile - Complete (AtoM + Extensions)
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/Dockerfile.complete" << 'DOCKERFILE'
# =============================================================================
# AtoM AHG Complete - AtoM 2.10 + Framework + Extensions
# =============================================================================
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm-alpine

LABEL maintainer="The Archive and Heritage Group <info@theahg.co.za>"
LABEL description="AtoM 2.10 with AHG Framework and Extensions"

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    git \
    composer \
    mysql-client \
    imagemagick \
    ghostscript \
    poppler-utils \
    ffmpeg \
    nodejs \
    npm \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libxml2-dev \
    icu-dev \
    libxslt-dev \
    oniguruma-dev \
    memcached-dev \
    libmemcached-dev \
    zlib-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        opcache \
        gd \
        zip \
        intl \
        xsl \
        mbstring \
        xml \
    && pecl install apcu memcached \
    && docker-php-ext-enable apcu memcached

# Clone AtoM
RUN git clone -b stable/2.10.x --depth 1 https://github.com/artefactual/atom.git /usr/share/nginx/atom

# Clone AHG Framework & Plugins
RUN git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git /usr/share/nginx/atom/atom-framework \
    && git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git /usr/share/nginx/atom/atom-ahg-plugins

WORKDIR /usr/share/nginx/atom

# Install AtoM dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Install Framework dependencies
RUN cd atom-framework && composer install --no-dev --no-interaction --prefer-dist

# Create required directories
RUN mkdir -p cache log uploads downloads \
    && chown -R www-data:www-data /usr/share/nginx/atom

# Copy configuration files
COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /usr/local/etc/php/conf.d/atom.ini
COPY docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
DOCKERFILE

    #---------------------------------------------------------------------------
    # Dockerfile - Base AtoM Only
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/Dockerfile.atom" << 'DOCKERFILE'
# =============================================================================
# AtoM Base - AtoM 2.10 Only (No Extensions)
# =============================================================================
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm-alpine

LABEL maintainer="Artefactual Systems / AHG"
LABEL description="Base AtoM 2.10 without extensions"

RUN apk add --no-cache \
    nginx supervisor git composer mysql-client \
    imagemagick ghostscript poppler-utils ffmpeg nodejs npm \
    libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
    libxml2-dev icu-dev libxslt-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql opcache gd zip intl xsl mbstring xml \
    && pecl install apcu && docker-php-ext-enable apcu

RUN git clone -b stable/2.10.x --depth 1 https://github.com/artefactual/atom.git /usr/share/nginx/atom

WORKDIR /usr/share/nginx/atom
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p cache log uploads downloads \
    && chown -R www-data:www-data /usr/share/nginx/atom

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /usr/local/etc/php/conf.d/atom.ini
COPY docker-entrypoint.sh /docker-entrypoint.sh

RUN chmod +x /docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
DOCKERFILE

    #---------------------------------------------------------------------------
    # Nginx Configuration
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/nginx.conf" << 'NGINX'
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
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTPS off;
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
NGINX

    #---------------------------------------------------------------------------
    # Supervisord Configuration
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/supervisord.conf" << 'SUPERVISOR'
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
SUPERVISOR

    #---------------------------------------------------------------------------
    # PHP Configuration
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/php.ini" << 'PHPINI'
; AtoM PHP Configuration
memory_limit = 512M
max_execution_time = 120
upload_max_filesize = 64M
post_max_size = 72M
max_input_vars = 5000

; OPcache
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
opcache.save_comments = 1

; Session
session.save_handler = files
session.save_path = /tmp
PHPINI

    #---------------------------------------------------------------------------
    # Entrypoint Script
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/docker-entrypoint.sh" << 'ENTRYPOINT'
#!/bin/sh
set -e

ATOM_ROOT="/usr/share/nginx/atom"

echo "╔══════════════════════════════════════════════════════════════╗"
echo "║             AtoM AHG Docker Startup                          ║"
echo "╚══════════════════════════════════════════════════════════════╝"

# Wait for MySQL
echo "Waiting for MySQL..."
until mysql -h "${MYSQL_HOST:-mysql}" -u "${MYSQL_USER:-atom}" -p"${MYSQL_PASSWORD:-atompass}" -e "SELECT 1" > /dev/null 2>&1; do
    sleep 2
done
echo "✓ MySQL is ready"

# Wait for Elasticsearch
echo "Waiting for Elasticsearch..."
until curl -s "http://${ATOM_ES_HOST:-elasticsearch}:9200/_cluster/health" > /dev/null 2>&1; do
    sleep 2
done
echo "✓ Elasticsearch is ready"

# Create config.php if not exists
if [ ! -f "${ATOM_ROOT}/config/config.php" ]; then
    echo "Creating config.php..."
    cat > "${ATOM_ROOT}/config/config.php" << EOFCONFIG
<?php
return [
    'all' => [
        'propel' => [
            'class' => 'sfPropelDatabase',
            'param' => [
                'encoding' => 'utf8mb4',
                'persistent' => true,
                'pooling' => true,
                'dsn' => 'mysql:host=${MYSQL_HOST:-mysql};dbname=${MYSQL_DATABASE:-atom};charset=utf8mb4',
                'username' => '${MYSQL_USER:-atom}',
                'password' => '${MYSQL_PASSWORD:-atompass}',
            ],
        ],
    ],
];
EOFCONFIG
fi

# Initialize database if needed
if ! mysql -h "${MYSQL_HOST:-mysql}" -u "${MYSQL_USER:-atom}" -p"${MYSQL_PASSWORD:-atompass}" "${MYSQL_DATABASE:-atom}" -e "SELECT id FROM setting LIMIT 1" > /dev/null 2>&1; then
    echo "Initializing AtoM database..."
    cd "${ATOM_ROOT}"
    php symfony tools:install --demo --no-confirmation
    echo "✓ Database initialized"
fi

# Run AHG framework install if present
if [ -d "${ATOM_ROOT}/atom-framework" ] && [ -f "${ATOM_ROOT}/atom-framework/bin/install" ]; then
    echo "Running AHG Framework install..."
    cd "${ATOM_ROOT}/atom-framework"
    bash bin/install --auto 2>/dev/null || true
    echo "✓ AHG Framework configured"
fi

# Set permissions
chown -R www-data:www-data "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads" 2>/dev/null || true

echo ""
echo "╔══════════════════════════════════════════════════════════════╗"
echo "║  AtoM is starting...                                         ║"
echo "║  URL: ${ATOM_SITE_BASE_URL:-http://localhost:8000}           ║"
echo "╚══════════════════════════════════════════════════════════════╝"
echo ""

exec "$@"
ENTRYPOINT

    chmod +x "${DOCKER_DIR}/docker-entrypoint.sh"

    #---------------------------------------------------------------------------
    # Init SQL
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/init.sql" << 'INITSQL'
-- AtoM Database Initialization
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
INITSQL

    #---------------------------------------------------------------------------
    # README
    #---------------------------------------------------------------------------
    cat > "${DOCKER_DIR}/README.md" << 'README'
# AtoM AHG Docker

## Installation Modes

| Mode | Profile | Description | Command |
|------|---------|-------------|---------|
| **Complete** | default | AtoM 2.10 + Framework + Extensions | `docker compose up -d` |
| **AtoM Only** | atom | Base AtoM 2.10 (no extensions) | `docker compose --profile atom up -d` |

## Quick Start
```bash
# 1. Copy environment file
cp .env.example .env

# 2. Edit settings
nano .env

# 3. Start services (Complete mode - default)
docker compose up -d

# Or start base AtoM only
docker compose --profile atom up -d

# 4. View logs
docker compose logs -f atom

# 5. Access AtoM
open http://localhost:8000
```

## Commands
```bash
# Stop services
docker compose down

# Stop and remove volumes (WARNING: deletes data)
docker compose down -v

# Rebuild images
docker compose build --no-cache

# Shell access
docker compose exec atom sh

# Run AtoM command
docker compose exec atom php symfony help
```

## Default Credentials

- **AtoM Admin**: demo@example.com / demo
- **MySQL Root**: root / atomroot
- **MySQL User**: atom / atompass

## Volumes

| Volume | Purpose |
|--------|---------|
| mysql_data | Database storage |
| es_data | Elasticsearch indices |
| atom_uploads | Uploaded files |
| atom_downloads | Generated exports |
README

    # Create tarball
    cd "${BUILD_DIR}"
    tar -czf "${DIST_DIR}/docker-compose.tar.gz" docker
    log "Built: docker-compose.tar.gz"
}

#===============================================================================
# BUILD ALL
#===============================================================================
echo "Building packages..."
echo ""

build_ansible
build_docker

rm -rf "$BUILD_DIR"

echo ""
echo "╔══════════════════════════════════════════════════════════════════╗"
echo "║                    PACKAGES BUILT                                ║"
echo "╚══════════════════════════════════════════════════════════════════╝"
echo ""
echo "  Ansible Playbook:"
echo "  ──────────────────"
echo "    ${DIST_DIR}/ansible-playbook.tar.gz"
echo ""
echo "    Modes:"
echo "      complete   - Fresh AtoM + Extensions (default)"
echo "      extensions - Add to existing AtoM"
echo "      atom       - Base AtoM only"
echo ""
echo "  Docker Compose:"
echo "  ────────────────"
echo "    ${DIST_DIR}/docker-compose.tar.gz"
echo ""
echo "    Profiles:"
echo "      (default)  - AtoM + Extensions"
echo "      atom       - Base AtoM only"
echo ""
