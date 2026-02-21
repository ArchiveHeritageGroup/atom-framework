#!/bin/bash
#===============================================================================
# atom-heratio CLI management tool
# Installed as /usr/bin/atom-heratio
#===============================================================================

set -e

HERATIO_LIB="/usr/share/atom-heratio/lib"
HERATIO_CONF="/etc/atom-heratio/atom-heratio.conf"
VERSION_FILE="/usr/share/atom-heratio/atom-framework/version.json"

# Source helpers
if [ -f "${HERATIO_LIB}/functions.sh" ]; then
    . "${HERATIO_LIB}/functions.sh"
fi
if [ -f "${HERATIO_LIB}/detect-atom.sh" ]; then
    . "${HERATIO_LIB}/detect-atom.sh"
fi
if [ -f "${HERATIO_LIB}/web-wizard.sh" ]; then
    . "${HERATIO_LIB}/web-wizard.sh"
fi

# Load config
load_config 2>/dev/null || true

#-------------------------------------------------------------------------------
# Commands
#-------------------------------------------------------------------------------

cmd_status() {
    echo ""
    echo "AtoM Heratio Status"
    echo "==================="
    echo ""

    # Package version
    local pkg_ver
    pkg_ver=$(dpkg-query -W -f='${Version}' atom-heratio 2>/dev/null || echo "not installed")
    echo "  Package:     atom-heratio ${pkg_ver}"

    # Framework version
    if [ -f "$VERSION_FILE" ]; then
        local fw_ver
        fw_ver=$(php -r "echo json_decode(file_get_contents('${VERSION_FILE}'),true)['version'] ?? 'unknown';" 2>/dev/null || echo "unknown")
        echo "  Framework:   v${fw_ver}"
    fi

    # Install mode and path
    echo "  Mode:        ${INSTALL_MODE:-unknown}"
    echo "  AtoM Path:   ${ATOM_PATH:-unknown}"

    # AtoM version
    if [ -n "$ATOM_PATH" ] && [ -d "$ATOM_PATH" ]; then
        local atom_ver
        atom_ver=$(detect_atom_version "$ATOM_PATH")
        echo "  AtoM:        ${atom_ver}"
    fi

    echo ""
    echo "  Config:      ${HERATIO_CONF}"
    echo "  Database:    ${DB_NAME:-?}@${DB_HOST:-?}"
    echo "  URL:         ${SITE_URL:-?}"
    echo ""

    # Service status
    echo "Services"
    echo "--------"
    for svc in nginx php8.3-fpm php8.2-fpm php8.1-fpm mysql elasticsearch gearman-job-server memcached atom-worker; do
        if systemctl list-unit-files "$svc.service" &>/dev/null 2>&1; then
            local state
            state=$(systemctl is-active "$svc" 2>/dev/null || echo "inactive")
            case "$state" in
                active)   printf "  %-25s %s\n" "$svc" "[running]" ;;
                inactive) printf "  %-25s %s\n" "$svc" "[stopped]" ;;
                *)        printf "  %-25s %s\n" "$svc" "[${state}]" ;;
            esac
        fi
    done

    echo ""

    # Web wizard status
    echo "Web Wizard"
    echo "----------"
    local wiz_stat
    wiz_stat=$(wizard_status 2>/dev/null || echo "stopped")
    echo "  Status: ${wiz_stat}"
    echo ""

    # Plugin count
    if [ -d "${ATOM_PATH}/atom-ahg-plugins" ]; then
        local plugin_count
        plugin_count=$(find "${ATOM_PATH}/atom-ahg-plugins" -maxdepth 1 -mindepth 1 -type d | wc -l)
        echo "Plugins: ${plugin_count} available"
        echo "  Run: atom-heratio plugins"
        echo ""
    fi
}

cmd_wizard() {
    local action="${1:-start}"
    local port="${2:-${WEB_WIZARD_PORT:-9090}}"

    case "$action" in
        start)
            if [ "$(id -u)" -ne 0 ]; then
                echo "Run as root: sudo atom-heratio wizard start [port]"
                exit 1
            fi
            start_wizard "$port" "${ATOM_PATH:-/usr/share/nginx/atom}"
            ;;
        stop)
            if [ "$(id -u)" -ne 0 ]; then
                echo "Run as root: sudo atom-heratio wizard stop"
                exit 1
            fi
            stop_wizard
            ;;
        status)
            wizard_status
            ;;
        *)
            echo "Usage: atom-heratio wizard [start|stop|status] [port]"
            ;;
    esac
}

cmd_plugins() {
    local atom_path="${ATOM_PATH:-/usr/share/nginx/atom}"

    if [ -f "${atom_path}/atom-framework/bin/atom" ]; then
        cd "$atom_path"
        php bin/atom extension:discover
    else
        echo "Heratio framework not found at ${atom_path}"
        exit 1
    fi
}

cmd_enable() {
    local plugin="$1"
    local atom_path="${ATOM_PATH:-/usr/share/nginx/atom}"

    if [ -z "$plugin" ]; then
        echo "Usage: atom-heratio enable <plugin-name>"
        exit 1
    fi

    if [ -f "${atom_path}/atom-framework/bin/atom" ]; then
        cd "$atom_path"
        php bin/atom extension:enable "$plugin"
    else
        echo "Heratio framework not found at ${atom_path}"
        exit 1
    fi
}

cmd_disable() {
    local plugin="$1"
    local atom_path="${ATOM_PATH:-/usr/share/nginx/atom}"

    if [ -z "$plugin" ]; then
        echo "Usage: atom-heratio disable <plugin-name>"
        exit 1
    fi

    if [ -f "${atom_path}/atom-framework/bin/atom" ]; then
        cd "$atom_path"
        php bin/atom extension:disable "$plugin"
    else
        echo "Heratio framework not found at ${atom_path}"
        exit 1
    fi
}

cmd_reconfigure() {
    if [ "$(id -u)" -ne 0 ]; then
        echo "Run as root: sudo atom-heratio reconfigure"
        exit 1
    fi
    dpkg-reconfigure atom-heratio
}

cmd_version() {
    local pkg_ver
    pkg_ver=$(dpkg-query -W -f='${Version}' atom-heratio 2>/dev/null || echo "not installed")
    echo "atom-heratio package: ${pkg_ver}"

    if [ -f "$VERSION_FILE" ]; then
        local fw_ver
        fw_ver=$(php -r "echo json_decode(file_get_contents('${VERSION_FILE}'),true)['version'] ?? 'unknown';" 2>/dev/null || echo "unknown")
        echo "Heratio framework:    v${fw_ver}"
    fi

    local atom_path="${ATOM_PATH:-/usr/share/nginx/atom}"
    if [ -d "$atom_path" ]; then
        local atom_ver
        atom_ver=$(detect_atom_version "$atom_path" 2>/dev/null || echo "unknown")
        echo "AtoM:                 ${atom_ver}"
    fi

    if [ -d "${atom_path}/atom-ahg-plugins" ]; then
        local count
        count=$(find "${atom_path}/atom-ahg-plugins" -maxdepth 1 -mindepth 1 -type d | wc -l)
        echo "Plugins available:    ${count}"
    fi
}

cmd_upgrade() {
    local atom_path="${ATOM_PATH:-/usr/share/nginx/atom}"

    if [ "$(id -u)" -ne 0 ]; then
        echo "Run as root: sudo atom-heratio upgrade"
        exit 1
    fi

    echo "Upgrading Heratio from GitHub..."

    if [ -d "${atom_path}/atom-framework/.git" ]; then
        cd "${atom_path}/atom-framework"
        git fetch origin main 2>/dev/null
        git reset --hard origin/main 2>/dev/null
        composer install --no-dev --no-interaction --quiet 2>/dev/null || true
        echo "Framework updated"
    else
        echo "Framework is package-managed (no .git). Use apt upgrade instead."
    fi

    if [ -d "${atom_path}/atom-ahg-plugins/.git" ]; then
        cd "${atom_path}/atom-ahg-plugins"
        git fetch origin main 2>/dev/null
        git reset --hard origin/main 2>/dev/null
        echo "Plugins updated"
    fi

    # Re-run installer
    if [ -f "${atom_path}/atom-framework/bin/install" ]; then
        cd "${atom_path}/atom-framework"
        bash bin/install --auto 2>/dev/null || true
    fi

    # Clear caches
    rm -rf "${atom_path}/cache/"* 2>/dev/null || true
    cd "$atom_path" && php symfony cc 2>/dev/null || true

    # Restart services
    local php_ver
    php_ver=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.3")
    systemctl restart "php${php_ver}-fpm" 2>/dev/null || true
    systemctl restart nginx 2>/dev/null || true

    echo "Upgrade complete. Run: atom-heratio version"
}

cmd_help() {
    echo ""
    echo "AtoM Heratio CLI"
    echo ""
    echo "Usage: atom-heratio <command> [options]"
    echo ""
    echo "Commands:"
    echo "  status                Show installation status and service health"
    echo "  version               Show version information"
    echo "  plugins               List available plugins"
    echo "  enable <name>         Enable a plugin"
    echo "  disable <name>        Disable a plugin"
    echo "  wizard [start|stop]   Manage web configuration wizard"
    echo "  reconfigure           Re-run installation wizard (dpkg-reconfigure)"
    echo "  upgrade               Pull latest from GitHub and re-install"
    echo "  help                  Show this help"
    echo ""
}

#-------------------------------------------------------------------------------
# Main
#-------------------------------------------------------------------------------

case "${1:-help}" in
    status)       cmd_status ;;
    wizard)       cmd_wizard "$2" "$3" ;;
    plugins)      cmd_plugins ;;
    enable)       cmd_enable "$2" ;;
    disable)      cmd_disable "$2" ;;
    reconfigure)  cmd_reconfigure ;;
    version)      cmd_version ;;
    upgrade)      cmd_upgrade ;;
    help|--help|-h) cmd_help ;;
    *)
        echo "Unknown command: $1"
        cmd_help
        exit 1
        ;;
esac
