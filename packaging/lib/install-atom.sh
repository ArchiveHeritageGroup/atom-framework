#!/bin/bash
#===============================================================================
# atom-heratio: Extract bundled AtoM tarball
#===============================================================================

install_atom() {
    local atom_path="$1"
    local tarball="/usr/share/atom-heratio/atom-latest.tar.gz"

    if [ ! -f "$tarball" ]; then
        log_error "AtoM tarball not found: $tarball"
        return 1
    fi

    log_step "Extracting AtoM to ${atom_path}..."

    # Create target directory
    mkdir -p "$atom_path"

    # Extract tarball (strip leading directory component if present)
    tar xzf "$tarball" -C "$atom_path" --strip-components=0 2>/dev/null || \
    tar xzf "$tarball" -C "$atom_path" 2>/dev/null

    # Verify extraction
    if [ ! -f "${atom_path}/symfony" ]; then
        # Try with strip-components=1 if files are in a subdirectory
        rm -rf "${atom_path:?}/"*
        tar xzf "$tarball" -C "$atom_path" --strip-components=1 2>/dev/null
    fi

    if [ ! -f "${atom_path}/symfony" ]; then
        log_error "AtoM extraction failed - symfony not found in ${atom_path}"
        return 1
    fi

    # Create required directories
    mkdir -p "${atom_path}/cache" "${atom_path}/log" "${atom_path}/uploads" "${atom_path}/downloads"

    # Run composer install
    log_step "Installing PHP dependencies..."
    cd "$atom_path"
    if command -v composer &>/dev/null; then
        composer install --no-dev --no-interaction --quiet 2>/dev/null || {
            log_warn "Composer install had warnings (non-fatal)"
        }
    else
        log_warn "Composer not found - run 'composer install' manually in ${atom_path}"
    fi

    log_info "AtoM extracted to ${atom_path}"
    return 0
}
