#!/bin/bash
#===============================================================================
# atom-heratio shared functions
#===============================================================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Logging
log_step()  { echo -e "${CYAN}[=>]${NC} $1"; }
log_info()  { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()  { echo -e "${YELLOW}[!!]${NC} $1"; }
log_error() { echo -e "${RED}[ERR]${NC} $1"; }

# Config file management
HERATIO_CONF="/etc/atom-heratio/atom-heratio.conf"

save_config() {
    local key="$1"
    local value="$2"
    mkdir -p /etc/atom-heratio
    if [ -f "$HERATIO_CONF" ] && grep -q "^${key}=" "$HERATIO_CONF" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=\"${value}\"|" "$HERATIO_CONF"
    else
        echo "${key}=\"${value}\"" >> "$HERATIO_CONF"
    fi
}

load_config() {
    if [ -f "$HERATIO_CONF" ]; then
        # shellcheck disable=SC1090
        . "$HERATIO_CONF"
    fi
}

# PHP version detection
detect_php_version() {
    local php_ver=""
    for v in 8.3 8.2 8.1; do
        if command -v "php${v}" &>/dev/null; then
            php_ver="$v"
            break
        fi
    done
    if [ -z "$php_ver" ] && command -v php &>/dev/null; then
        php_ver=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)
    fi
    echo "$php_ver"
}

# PHP-FPM socket path
detect_fpm_socket() {
    local php_ver
    php_ver=$(detect_php_version)
    echo "/run/php/php${php_ver}-fpm.sock"
}

# Render template: replace {{VAR}} with shell variable $VAR
render_template() {
    local template="$1"
    local output="$2"

    local content
    content=$(cat "$template")

    # Replace all {{VAR}} patterns
    while IFS= read -r var; do
        local val="${!var}"
        content="${content//\{\{${var}\}\}/${val}}"
    done < <(grep -oP '\{\{\K[A-Z_]+(?=\}\})' "$template" | sort -u)

    echo "$content" > "$output"
}

# Service management
restart_service() {
    local svc="$1"
    if systemctl is-active "$svc" &>/dev/null; then
        systemctl restart "$svc" 2>/dev/null || true
    elif systemctl is-enabled "$svc" &>/dev/null; then
        systemctl start "$svc" 2>/dev/null || true
    fi
}

# Check if a command exists
require_cmd() {
    local cmd="$1"
    local pkg="$2"
    if ! command -v "$cmd" &>/dev/null; then
        log_error "$cmd not found. Install: apt install $pkg"
        return 1
    fi
    return 0
}

# Disk space check (in MB)
check_disk_space() {
    local path="$1"
    local required_mb="$2"
    local available_mb
    available_mb=$(df -BM --output=avail "$path" 2>/dev/null | tail -1 | tr -d ' M')
    if [ "$available_mb" -lt "$required_mb" ] 2>/dev/null; then
        return 1
    fi
    return 0
}

# RAM check (in MB)
check_ram() {
    local required_mb="$1"
    local available_mb
    available_mb=$(free -m | awk '/^Mem:/{print $2}')
    if [ "$available_mb" -lt "$required_mb" ] 2>/dev/null; then
        return 1
    fi
    return 0
}
