#!/bin/bash
#===============================================================================
# atom-heratio: SSL configuration
#===============================================================================

configure_ssl() {
    local ssl_mode="$1"
    local ssl_domain="$2"

    case "$ssl_mode" in
        self-signed)
            configure_self_signed "$ssl_domain"
            ;;
        letsencrypt)
            configure_letsencrypt "$ssl_domain"
            ;;
        *)
            log_info "SSL not configured (HTTP only)"
            ;;
    esac
}

configure_self_signed() {
    local domain="$1"
    local cert_dir="/etc/ssl/atom-heratio"

    log_step "Generating self-signed SSL certificate..."

    mkdir -p "$cert_dir"

    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "${cert_dir}/server.key" \
        -out "${cert_dir}/server.crt" \
        -subj "/C=ZA/ST=Gauteng/L=Pretoria/O=AtoM Heratio/CN=${domain:-localhost}" \
        2>/dev/null

    chmod 600 "${cert_dir}/server.key"
    chmod 644 "${cert_dir}/server.crt"

    log_info "Self-signed certificate generated in ${cert_dir}"
}

configure_letsencrypt() {
    local domain="$1"

    if [ -z "$domain" ]; then
        log_error "Domain name required for Let's Encrypt"
        return 1
    fi

    log_step "Obtaining Let's Encrypt certificate for ${domain}..."

    if ! command -v certbot &>/dev/null; then
        log_warn "certbot not found. Install: apt install certbot python3-certbot-nginx"
        log_warn "Then run: certbot --nginx -d ${domain}"
        return 1
    fi

    certbot --nginx -d "$domain" --non-interactive --agree-tos \
        --email "admin@${domain}" --redirect 2>/dev/null || {
        log_warn "Certbot failed. Run manually: certbot --nginx -d ${domain}"
        return 1
    }

    log_info "Let's Encrypt certificate obtained for ${domain}"
}
