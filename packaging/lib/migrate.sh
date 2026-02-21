#!/bin/bash
#===============================================================================
# atom-heratio: Database migration runner
#===============================================================================

run_migrations() {
    local atom_path="$1"
    local db_host="$2"
    local db_name="$3"
    local db_user="$4"
    local db_pass="$5"

    log_step "Running database migrations..."

    local mysql_cmd="mysql -h${db_host} -u${db_user}"
    [ -n "$db_pass" ] && mysql_cmd="${mysql_cmd} -p${db_pass}"
    mysql_cmd="${mysql_cmd} ${db_name}"

    # Run AtoM initial setup if tables don't exist
    if ! $mysql_cmd -e "SELECT 1 FROM setting LIMIT 1" &>/dev/null; then
        log_step "Initializing AtoM database (first install)..."
        cd "$atom_path"
        sudo -u www-data php symfony tools:install --no-confirmation 2>/dev/null || {
            log_warn "AtoM tools:install completed with warnings"
        }
    fi

    # Run Heratio framework migrations
    if [ -d "${atom_path}/atom-framework" ]; then
        # Run install.sql files from framework
        if [ -f "${atom_path}/atom-framework/database/install.sql" ]; then
            $mysql_cmd < "${atom_path}/atom-framework/database/install.sql" 2>/dev/null || true
        fi

        # Run plugin install.sql files
        if [ -d "${atom_path}/atom-ahg-plugins" ]; then
            for plugin_dir in "${atom_path}"/atom-ahg-plugins/*/; do
                local plugin_name
                plugin_name=$(basename "$plugin_dir")
                for sql_path in "${plugin_dir}database/install.sql" "${plugin_dir}data/install.sql"; do
                    if [ -f "$sql_path" ]; then
                        log_step "  Migration: ${plugin_name}"
                        $mysql_cmd < "$sql_path" 2>/dev/null || true
                    fi
                done
            done
        fi
    fi

    # Populate search index
    log_step "Populating search index..."
    cd "$atom_path"
    sudo -u www-data php symfony search:populate 2>/dev/null || {
        log_warn "Search population skipped (Elasticsearch may not be available)"
    }

    log_info "Database migrations complete"
    return 0
}
