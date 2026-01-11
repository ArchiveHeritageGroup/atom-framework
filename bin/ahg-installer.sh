#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Master Installer v2.0.0
#===============================================================================

set -e
VERSION="2.0.0"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; MAGENTA='\033[0;35m'; NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ATOM_ROOT="${ATOM_ROOT:-/usr/share/nginx/atom}"

show_banner() {
    echo -e "${CYAN}"
    echo "╔══════════════════════════════════════════════════════════════════════╗"
    echo "║     AtoM AHG Framework Installer v${VERSION}                             ║"
    echo "╚══════════════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

show_menu() {
    echo -e "\n${CYAN}Installation Options:${NC}\n"
    echo "  Framework Only (Existing AtoM):"
    echo "    1) Full Install      - Interactive"
    echo "    2) Quick Install     - Automated"
    echo "    3) Setup Wizard      - TUI dialog"
    echo ""
    echo -e "  ${MAGENTA}Complete Installation (New Server):${NC}"
    echo "    4) Full Stack        - AtoM 2.10 + Framework"
    echo ""
    echo -e "  ${YELLOW}Build Packages:${NC}"
    echo "    5) Build .run        - Self-extracting"
    echo "    6) Build .deb        - Debian package"
    echo ""
    echo -e "  ${GREEN}Maintenance:${NC}"
    echo "    7) Update            - Pull from GitHub"
    echo "    8) Uninstall         - Remove framework"
    echo "    9) Docker            - Start with Docker"
    echo ""
    echo "    0) Exit"
    echo ""
    read -p "Select [0-9]: " choice
    
    case $choice in
        1) bash "${SCRIPT_DIR}/install" --interactive ;;
        2) bash "${SCRIPT_DIR}/install" --auto ;;
        3) bash "${SCRIPT_DIR}/setup-wizard.sh" 2>/dev/null || echo -e "${RED}Wizard not found${NC}" ;;
        4) install_full_stack ;;
        5) bash "${SCRIPT_DIR}/build-installer.sh" 2>/dev/null || echo -e "${RED}Build script not found${NC}" ;;
        6) bash "${SCRIPT_DIR}/build-deb.sh" 2>/dev/null || echo -e "${RED}Build script not found${NC}" ;;
        7) update_framework ;;
        8) bash "${SCRIPT_DIR}/uninstall.sh" 2>/dev/null || echo -e "${RED}Uninstall script not found${NC}" ;;
        9) docker_menu ;;
        0) exit 0 ;;
        *) echo -e "${RED}Invalid option${NC}"; show_menu ;;
    esac
}

install_full_stack() {
    echo -e "\n${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${MAGENTA}  FULL STACK INSTALLATION                                      ${NC}"
    echo -e "${MAGENTA}  AtoM 2.10 + AHG Framework + All Dependencies                 ${NC}"
    echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}\n"
    
    echo "This will install:"
    echo "  • nginx web server"
    echo "  • PHP 8.3 with extensions"
    echo "  • MySQL 8 database"
    echo "  • Elasticsearch 5.6"
    echo "  • AtoM 2.10"
    echo "  • AHG Framework + Plugins"
    echo ""
    
    read -p "Installation path [${ATOM_ROOT}]: " path
    ATOM_ROOT="${path:-$ATOM_ROOT}"
    read -p "Database name [atom]: " db_name
    DB_NAME="${db_name:-atom}"
    read -p "Database user [atom]: " db_user
    DB_USER="${db_user:-atom}"
    read -sp "Database password: " db_pass; echo
    DB_PASS="$db_pass"
    read -p "Site title [AtoM Archive]: " title
    SITE_TITLE="${title:-AtoM Archive}"
    
    echo -e "\n${YELLOW}Starting installation...${NC}\n"
    
    # System deps
    echo -e "${CYAN}[1/8] Installing system dependencies...${NC}"
    apt-get update -qq
    apt-get install -y -qq curl wget git unzip imagemagick ghostscript poppler-utils ffmpeg openjdk-11-jre-headless gnupg2 software-properties-common
    
    # PHP 8.3
    echo -e "${CYAN}[2/8] Installing PHP 8.3...${NC}"
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1
    apt-get update -qq
    apt-get install -y -qq php8.3-{fpm,cli,curl,mysql,xml,mbstring,zip,gd,intl,opcache,apcu,xsl,memcached}
    
    # Configure PHP
    sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.3/fpm/php.ini
    sed -i 's/upload_max_filesize = .*/upload_max_filesize = 100M/' /etc/php/8.3/fpm/php.ini
    sed -i 's/post_max_size = .*/post_max_size = 100M/' /etc/php/8.3/fpm/php.ini
    systemctl restart php8.3-fpm
    
    # MySQL
    echo -e "${CYAN}[3/8] Installing MySQL 8...${NC}"
    apt-get install -y -qq mysql-server
    systemctl start mysql
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"
    
    # Nginx
    echo -e "${CYAN}[4/8] Installing nginx...${NC}"
    apt-get install -y -qq nginx
    
    cat > /etc/nginx/sites-available/atom << NGINXCONF
server {
    listen 80;
    server_name _;
    root ${ATOM_ROOT};
    client_max_body_size 100M;
    
    location / { try_files \$uri /index.php?\$args; }
    location ~ ^/(index|qubit_dev)\\.php(/|\$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    location ~ /\\. { deny all; }
    location ~ ^/uploads/r/ { rewrite ^/uploads/r/(.*)\$ /index.php?r=\$1 last; }
}
NGINXCONF
    
    rm -f /etc/nginx/sites-enabled/default
    ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/
    nginx -t && systemctl restart nginx
    
    # Elasticsearch
    echo -e "${CYAN}[5/8] Installing Elasticsearch 5.6...${NC}"
    wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | apt-key add - 2>/dev/null
    echo "deb https://artifacts.elastic.co/packages/5.x/apt stable main" > /etc/apt/sources.list.d/elastic-5.x.list
    apt-get update -qq
    apt-get install -y -qq elasticsearch=5.6.16
    sed -i 's/-Xms.*/-Xms512m/' /etc/elasticsearch/jvm.options
    sed -i 's/-Xmx.*/-Xmx512m/' /etc/elasticsearch/jvm.options
    systemctl enable elasticsearch
    systemctl start elasticsearch
    
    # AtoM
    echo -e "${CYAN}[6/8] Installing AtoM 2.10...${NC}"
    mkdir -p "${ATOM_ROOT}"
    git clone -b stable/2.10 --depth 1 https://github.com/artefactual/atom.git "${ATOM_ROOT}"
    
    # Composer
    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php
        mv composer.phar /usr/local/bin/composer
        chmod +x /usr/local/bin/composer
    fi
    
    cd "${ATOM_ROOT}"
    composer install --no-dev --no-interaction --quiet
    
    # Config
    cat > config/config.php << CONFIGPHP
<?php
return array (
  'all' => array (
    'propel' => array (
      'class' => 'sfPropelDatabase',
      'param' => array (
        'encoding' => 'utf8mb4',
        'persistent' => true,
        'pooling' => true,
        'dsn' => 'mysql:host=localhost;dbname=${DB_NAME};charset=utf8mb4',
        'username' => '${DB_USER}',
        'password' => '${DB_PASS}',
      ),
    ),
  ),
);
CONFIGPHP
    
    chown -R www-data:www-data "${ATOM_ROOT}"
    chmod -R 775 cache log
    mkdir -p uploads && chmod 775 uploads
    
    echo -e "${CYAN}[7/8] Running AtoM installation...${NC}"
    sudo -u www-data php symfony tools:install \
        --db-host=localhost \
        --db-port=3306 \
        --db-name="${DB_NAME}" \
        --db-user="${DB_USER}" \
        --db-pass="${DB_PASS}" \
        --site-title="${SITE_TITLE}" \
        --site-base-url="http://localhost" \
        --admin-email="admin@example.com" \
        --admin-password="admin" \
        --no-confirmation
    
    # AHG Framework
    echo -e "${CYAN}[8/8] Installing AHG Framework...${NC}"
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git
    cd atom-framework
    composer install --no-dev --no-interaction --quiet
    bash bin/install --auto
    
    # Final cleanup
    cd "${ATOM_ROOT}"
    chown -R www-data:www-data .
    sudo -u www-data php symfony cc
    systemctl restart php8.3-fpm nginx
    
    # Populate search (background)
    nohup sudo -u www-data php symfony search:populate > /var/log/atom-search.log 2>&1 &
    
    echo -e "\n${GREEN}╔══════════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                    INSTALLATION COMPLETE                         ║${NC}"
    echo -e "${GREEN}╠══════════════════════════════════════════════════════════════════╣${NC}"
    echo -e "${GREEN}║                                                                  ║${NC}"
    echo -e "${GREEN}║  URL:        http://localhost                                    ║${NC}"
    echo -e "${GREEN}║  Admin:      admin@example.com                                   ║${NC}"
    echo -e "${GREEN}║  Password:   admin  ${RED}(CHANGE THIS!)${GREEN}                             ║${NC}"
    echo -e "${GREEN}║                                                                  ║${NC}"
    echo -e "${GREEN}║  Path:       ${ATOM_ROOT}${NC}"
    echo -e "${GREEN}║  Database:   ${DB_NAME}${NC}"
    echo -e "${GREEN}║                                                                  ║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════════════════════╝${NC}"
}

update_framework() {
    echo -e "${CYAN}Updating framework...${NC}"
    cd "${ATOM_ROOT}/atom-framework" && git pull origin main && composer install --no-dev --no-interaction
    cd "${ATOM_ROOT}/atom-ahg-plugins" && git pull origin main
    cd "${ATOM_ROOT}" && php symfony cc
    echo -e "${GREEN}Updated successfully!${NC}"
}

docker_menu() {
    echo -e "\n${CYAN}Docker Options:${NC}"
    echo "  1) Start all services"
    echo "  2) Start with RIC (Fuseki)"
    echo "  3) Start with IIIF (Cantaloupe)"
    echo "  4) Stop all services"
    echo "  5) View logs"
    echo "  6) Rebuild containers"
    echo "  0) Back to main menu"
    echo ""
    read -p "Select: " opt
    
    DOCKER_DIR="$(dirname "$SCRIPT_DIR")/docker"
    
    case $opt in
        1) cd "$DOCKER_DIR" && docker-compose up -d && echo -e "${GREEN}Started!${NC}" ;;
        2) cd "$DOCKER_DIR" && docker-compose --profile ric up -d && echo -e "${GREEN}Started with Fuseki!${NC}" ;;
        3) cd "$DOCKER_DIR" && docker-compose --profile iiif up -d && echo -e "${GREEN}Started with Cantaloupe!${NC}" ;;
        4) cd "$DOCKER_DIR" && docker-compose down && echo -e "${GREEN}Stopped!${NC}" ;;
        5) cd "$DOCKER_DIR" && docker-compose logs -f ;;
        6) cd "$DOCKER_DIR" && docker-compose up -d --build && echo -e "${GREEN}Rebuilt!${NC}" ;;
        0) show_menu ;;
        *) echo -e "${RED}Invalid option${NC}"; docker_menu ;;
    esac
}

# Check root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root (sudo)${NC}"
    exit 1
fi

show_banner

# Handle command line arguments
case "${1:-}" in
    --full-stack) install_full_stack ;;
    --quick|--auto) bash "${SCRIPT_DIR}/install" --auto ;;
    --update) update_framework ;;
    --docker) docker_menu ;;
    --help|-h)
        echo "Usage: $0 [option]"
        echo ""
        echo "Options:"
        echo "  --full-stack    Install complete AtoM + Framework stack"
        echo "  --quick         Quick framework install (existing AtoM)"
        echo "  --update        Update framework from GitHub"
        echo "  --docker        Docker management menu"
        echo "  --help          Show this help"
        echo ""
        echo "Without options, shows interactive menu."
        ;;
    "") show_menu ;;
    *) echo -e "${RED}Unknown option: $1${NC}"; exit 1 ;;
esac
