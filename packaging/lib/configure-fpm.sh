#!/bin/bash
#===============================================================================
# atom-heratio: PHP-FPM pool configuration
#===============================================================================

configure_fpm() {
    local atom_path="$1"
    local template_dir="/usr/share/atom-heratio/templates/php"

    log_step "Configuring PHP-FPM..."

    local php_ver
    php_ver=$(detect_php_version)

    if [ -z "$php_ver" ]; then
        log_error "PHP not found"
        return 1
    fi

    # Export for template rendering
    export ATOM_PATH="$atom_path"
    export PHP_VERSION="$php_ver"

    # Render pool config
    local pool_dir="/etc/php/${php_ver}/fpm/pool.d"
    if [ -d "$pool_dir" ]; then
        render_template "${template_dir}/atom-pool.conf.tpl" "${pool_dir}/atom-heratio.conf"

        # Disable default www pool if it exists and is stock
        if [ -f "${pool_dir}/www.conf" ]; then
            mv "${pool_dir}/www.conf" "${pool_dir}/www.conf.disabled" 2>/dev/null || true
        fi
    fi

    # Render PHP ini overrides
    local ini_dir="/etc/php/${php_ver}/fpm/conf.d"
    if [ -d "$ini_dir" ]; then
        render_template "${template_dir}/atom-php.ini.tpl" "${ini_dir}/99-atom-heratio.ini"
    fi

    # Restart PHP-FPM
    restart_service "php${php_ver}-fpm"
    log_info "PHP-FPM ${php_ver} configured"

    return 0
}
