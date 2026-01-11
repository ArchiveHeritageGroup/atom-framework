#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Master Installer v2.0.0
#===============================================================================

set -e
VERSION="2.0.0"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="${ATOM_ROOT:-$(dirname "$FRAMEWORK_PATH")}"

GITHUB_ORG="ArchiveHeritageGroup"
FRAMEWORK_REPO="https://github.com/${GITHUB_ORG}/atom-framework.git"
PLUGINS_REPO="https://github.com/${GITHUB_ORG}/atom-ahg-plugins.git"

log() { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }
step() { echo -e "${CYAN}[→]${NC} $1"; }

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
    echo "    1) Full Install      - Interactive with prompts"
    echo "    2) Quick Install     - Automated, minimal prompts"
    echo "    3) Setup Wizard      - TUI dialog-based"
    echo ""
    echo -e "  ${MAGENTA}Complete Installation (New Server):${NC}"
    echo "    4) Full Stack        - AtoM 2.10 + Framework + Dependencies"
    echo ""
    echo -e "  ${YELLOW}Build Packages:${NC}"
    echo "    5) Build .run        - Self-extracting installer"
    echo "    6) Build .deb        - Debian/Ubuntu package"
    echo ""
    echo -e "  ${GREEN}Maintenance:${NC}"
    echo "    7) Update            - Pull from GitHub"
    echo "    8) Uninstall         - Remove framework"
    echo "    9) Docker            - Container management"
    echo ""
    echo "    0) Exit"
    echo ""
    read -p "Select [0-9]: " choice
    handle_choice "$choice"
}

handle_choice() {
    case $1 in
        1) bash "${SCRIPT_DIR}/install" --interactive ;;
        2) bash "${SCRIPT_DIR}/install" --auto ;;
        3) bash "${SCRIPT_DIR}/setup-wizard.sh" ;;
        4) install_full_stack ;;
        5) bash "${SCRIPT_DIR}/build-installer.sh" ;;
        6) bash "${SCRIPT_DIR}/build-deb.sh" ;;
        7) update_framework ;;
        8) bash "${SCRIPT_DIR}/uninstall.sh" ;;
        9) docker_menu ;;
        0) echo "Goodbye!"; exit 0 ;;
        *) error "Invalid option"; show_menu ;;
    esac
}

check_root() {
    if [ "$EUID" -ne 0 ]; then
        error "Please run as root (sudo)"
        exit 1
    fi
}

install_full_stack() {
    echo -e "\n${MAGENTA}═══════════════════════════════════════════════════════════════${NC}"
    echo -e "${MAGENTA}  FULL STACK INSTALLATION                                      ${NC}"
    echo -e "${MAGENTA}  AtoM 2.10 + AHG Framework + All Dependencies                 ${NC}"
    echo -e "${MAGENTA}═══════════════════════════════════════════════════════════════${NC}\n"
    
    check_root
    
    read -p "Installation path [/usr/share/nginx/atom]: " INSTALL_PATH
    INSTALL_PATH="${INSTALL_PATH:-/usr/share/nginx/atom}"
    
    read -p "Database name [atom]: " DB_NAME
    DB_NAME="${DB_NAME:-atom}"
    
    read -p "Database user [atom]: " DB_USER
    DB_USER="${DB_USER:-atom}"
    
    read -sp "Database password: " DB_PASS
    echo ""
    
    read -p "Site title [My Archive]: " SITE_TITLE
    SITE_TITLE="${SITE_TITLE:-My Archive}"
    
    step "Installing system packages..."
    apt-get update
    apt-get install -y nginx mysql-server php8.3-fpm php8.3-mysql php8.3-xml \
        php8.3-mbstring php8.3-curl php8.3-zip php8.3-gd php8.3-intl \
        php8.3-opcache php8.3-apcu php8.3-xsl php8.3-readline \
        composer git curl wget unzip openjdk-11-jre-headless memcached
    
    step "Configuring MySQL..."
    mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    step "Cloning AtoM..."
    mkdir -p "$INSTALL_PATH"
    cd "$INSTALL_PATH"
    
    if [ ! -f "symfony" ]; then
        git clone -b stable/2.10.x https://github.com/artefactual/atom.git .
        composer install --no-dev
    fi
    
    step "Cloning AHG Framework & Plugins..."
    [ ! -d "atom-framework" ] && git clone "$FRAMEWORK_REPO" atom-framework
    [ ! -d "atom-ahg-plugins" ] && git clone "$PLUGINS_REPO" atom-ahg-plugins
    
    cd atom-framework && composer install --no-dev
    
    step "Running framework install..."
    bash bin/install --auto
    
    step "Restarting services..."
    systemctl restart php8.3-fpm nginx memcached
    
    echo ""
    log "Installation complete!"
    echo -e "URL: http://$(hostname -I | awk '{print $1}')"
    echo -e "Admin: admin@example.com / admin ${RED}(CHANGE THIS!)${NC}"
}

update_framework() {
    step "Updating framework..."
    cd "${FRAMEWORK_PATH}" && git pull origin main
    composer install --no-dev 2>/dev/null || true
    
    [ -d "${ATOM_ROOT}/atom-ahg-plugins" ] && cd "${ATOM_ROOT}/atom-ahg-plugins" && git pull origin main
    
    cd "${ATOM_ROOT}" && php symfony cc 2>/dev/null || true
    log "Framework updated"
}

docker_menu() {
    echo -e "\n${CYAN}Docker Options:${NC}\n"
    echo "  1) Start    2) Stop    3) Logs    4) Rebuild    0) Back"
    read -p "Select: " dc
    
    case $dc in
        1) cd "${FRAMEWORK_PATH}/docker" && docker-compose up -d ;;
        2) cd "${FRAMEWORK_PATH}/docker" && docker-compose down ;;
        3) cd "${FRAMEWORK_PATH}/docker" && docker-compose logs -f ;;
        4) cd "${FRAMEWORK_PATH}/docker" && docker-compose build --no-cache ;;
        0) show_menu ;;
    esac
}

# Main
show_banner

case "${1:-}" in
    --quick|--auto) check_root; bash "${SCRIPT_DIR}/install" --auto ;;
    --full-stack) install_full_stack ;;
    --wizard) bash "${SCRIPT_DIR}/setup-wizard.sh" ;;
    --build-run) bash "${SCRIPT_DIR}/build-installer.sh" ;;
    --build-deb) bash "${SCRIPT_DIR}/build-deb.sh" ;;
    --update) update_framework ;;
    --uninstall) bash "${SCRIPT_DIR}/uninstall.sh" ;;
    --docker) docker_menu ;;
    --help|-h)
        echo "Usage: $0 [--quick|--full-stack|--wizard|--build-run|--build-deb|--update|--uninstall|--docker]"
        ;;
    *) show_menu ;;
esac
