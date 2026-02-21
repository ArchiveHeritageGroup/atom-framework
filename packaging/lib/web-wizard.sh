#!/bin/bash
#===============================================================================
# atom-heratio: Web wizard management
#===============================================================================

WIZARD_DIR="/usr/share/atom-heratio/wizard"
WIZARD_PID="/run/atom-heratio-wizard.pid"
WIZARD_TOKEN_FILE="/etc/atom-heratio/.wizard-token"

generate_token() {
    local token
    token=$(openssl rand -hex 24 2>/dev/null || head -c 48 /dev/urandom | xxd -p | tr -d '\n' | head -c 48)
    echo "$token"
}

start_wizard() {
    local port="${1:-9090}"
    local atom_path="${2:-/usr/share/nginx/atom}"

    if [ -f "$WIZARD_PID" ] && kill -0 "$(cat "$WIZARD_PID")" 2>/dev/null; then
        log_warn "Web wizard already running (PID: $(cat "$WIZARD_PID"))"
        return 0
    fi

    if [ ! -d "$WIZARD_DIR" ]; then
        log_error "Wizard files not found: $WIZARD_DIR"
        return 1
    fi

    # Generate access token
    local token
    token=$(generate_token)
    mkdir -p /etc/atom-heratio
    echo "$token" > "$WIZARD_TOKEN_FILE"
    chmod 600 "$WIZARD_TOKEN_FILE"

    # Export config for the wizard
    export ATOM_HERATIO_TOKEN="$token"
    export ATOM_PATH="$atom_path"

    # Start PHP built-in server in background
    nohup php -S "0.0.0.0:${port}" -t "$WIZARD_DIR" \
        -d "atom_heratio.token=${token}" \
        -d "atom_heratio.atom_path=${atom_path}" \
        > /var/log/atom-heratio-wizard.log 2>&1 &

    local pid=$!
    echo "$pid" > "$WIZARD_PID"

    # Schedule auto-shutdown after 30 minutes
    (sleep 1800 && stop_wizard) &>/dev/null &

    local server_ip
    server_ip=$(hostname -I 2>/dev/null | awk '{print $1}')

    log_info "Web wizard started on port ${port}"
    echo ""
    echo "  Access the configuration wizard at:"
    echo ""
    echo "    http://${server_ip}:${port}?token=${token}"
    echo ""
    echo "  Token: ${token}"
    echo "  Auto-shutdown: 30 minutes"
    echo ""

    return 0
}

stop_wizard() {
    if [ -f "$WIZARD_PID" ]; then
        local pid
        pid=$(cat "$WIZARD_PID")
        if kill -0 "$pid" 2>/dev/null; then
            kill "$pid" 2>/dev/null
            log_info "Web wizard stopped (PID: ${pid})"
        fi
        rm -f "$WIZARD_PID"
    fi
    rm -f "$WIZARD_TOKEN_FILE"
}

wizard_status() {
    if [ -f "$WIZARD_PID" ] && kill -0 "$(cat "$WIZARD_PID")" 2>/dev/null; then
        echo "running (PID: $(cat "$WIZARD_PID"))"
        if [ -f "$WIZARD_TOKEN_FILE" ]; then
            echo "token: $(cat "$WIZARD_TOKEN_FILE")"
        fi
        return 0
    else
        echo "stopped"
        return 1
    fi
}
