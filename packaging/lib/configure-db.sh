#!/bin/bash
#===============================================================================
# atom-heratio: Database configuration
#===============================================================================

configure_database() {
    local db_host="$1"
    local db_name="$2"
    local db_user="$3"
    local db_pass="$4"
    local db_root_pass="$5"

    log_step "Configuring database..."

    # Build mysql command
    local mysql_cmd="mysql"
    if [ -n "$db_root_pass" ]; then
        mysql_cmd="mysql -u root -p${db_root_pass}"
    else
        mysql_cmd="mysql -u root"
    fi

    # Test MySQL connection
    if ! $mysql_cmd -e "SELECT 1" &>/dev/null; then
        log_warn "Cannot connect to MySQL as root. Database setup may need manual intervention."
        return 1
    fi

    # Create database
    $mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;" 2>/dev/null || \
    $mysql_cmd -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null
    log_info "Database '${db_name}' ready"

    # Create user and grant privileges
    if [ "$db_user" != "root" ]; then
        $mysql_cmd -e "CREATE USER IF NOT EXISTS '${db_user}'@'${db_host}' IDENTIFIED BY '${db_pass}';" 2>/dev/null
        $mysql_cmd -e "GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'${db_host}';" 2>/dev/null
        $mysql_cmd -e "FLUSH PRIVILEGES;" 2>/dev/null
        log_info "Database user '${db_user}' configured"
    fi

    return 0
}
