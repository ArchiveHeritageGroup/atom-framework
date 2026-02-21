#!/bin/bash
#===============================================================================
# atom-heratio: Install Heratio framework + plugins into AtoM
#===============================================================================

install_heratio() {
    local atom_path="$1"
    local src_framework="/usr/share/atom-heratio/atom-framework"
    local src_plugins="/usr/share/atom-heratio/atom-ahg-plugins"

    if [ ! -d "$src_framework" ]; then
        log_error "Framework source not found: $src_framework"
        return 1
    fi

    # Copy framework
    log_step "Installing Heratio framework..."
    rsync -a --delete \
        --exclude='.git' \
        --exclude='dist/' \
        --exclude='node_modules/' \
        "${src_framework}/" "${atom_path}/atom-framework/"

    # Copy plugins
    if [ -d "$src_plugins" ]; then
        log_step "Installing Heratio plugins..."
        rsync -a --delete \
            --exclude='.git' \
            "${src_plugins}/" "${atom_path}/atom-ahg-plugins/"
    fi

    # Install framework composer dependencies
    log_step "Installing framework dependencies..."
    cd "${atom_path}/atom-framework"
    if command -v composer &>/dev/null; then
        composer install --no-dev --no-interaction --quiet 2>/dev/null || {
            log_warn "Framework composer install had warnings (non-fatal)"
        }
    fi

    # Run bin/install
    log_step "Running Heratio installer (bin/install)..."
    if [ -f "${atom_path}/atom-framework/bin/install" ]; then
        cd "${atom_path}/atom-framework"
        bash bin/install --auto 2>/dev/null || {
            log_warn "bin/install completed with warnings"
        }
    fi

    log_info "Heratio framework installed"
    return 0
}
