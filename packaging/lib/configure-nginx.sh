#!/bin/bash
#===============================================================================
# atom-heratio: Nginx configuration
#===============================================================================

configure_nginx() {
    local atom_path="$1"
    local site_url="$2"
    local ssl_mode="$3"
    local ssl_domain="$4"

    local template_dir="/usr/share/atom-heratio/templates/nginx"

    log_step "Configuring Nginx..."

    # Detect PHP-FPM socket
    local php_ver
    php_ver=$(detect_php_version)
    local fpm_socket="/run/php/php${php_ver}-fpm.sock"

    # Extract server_name from URL
    local server_name="_"
    if [ -n "$site_url" ]; then
        server_name=$(echo "$site_url" | sed -E 's|https?://||; s|/.*||; s|:.*||')
    fi
    if [ -n "$ssl_domain" ]; then
        server_name="$ssl_domain"
    fi

    # Export variables for template rendering
    export ATOM_PATH="$atom_path"
    export SERVER_NAME="$server_name"
    export FPM_SOCKET="$fpm_socket"
    export PHP_VERSION="$php_ver"
    export SSL_DOMAIN="$ssl_domain"

    # Choose template based on SSL mode
    local conf_file="/etc/nginx/sites-available/atom-heratio"

    if [ "$ssl_mode" = "self-signed" ] || [ "$ssl_mode" = "letsencrypt" ]; then
        render_template "${template_dir}/atom-ssl.conf.tpl" "$conf_file"
    else
        render_template "${template_dir}/atom.conf.tpl" "$conf_file"
    fi

    # Copy extensions.conf if framework has it
    if [ -f "${atom_path}/atom-framework/config/nginx/extensions.conf" ]; then
        cp "${atom_path}/atom-framework/config/nginx/extensions.conf" /etc/nginx/snippets/atom-extensions.conf 2>/dev/null || true
    fi

    # Enable site, disable default
    ln -sf "$conf_file" /etc/nginx/sites-enabled/atom-heratio
    rm -f /etc/nginx/sites-enabled/default

    # Test and reload
    if nginx -t &>/dev/null; then
        restart_service nginx
        log_info "Nginx configured for ${server_name}"
    else
        log_error "Nginx configuration test failed. Check: nginx -t"
        return 1
    fi

    return 0
}
