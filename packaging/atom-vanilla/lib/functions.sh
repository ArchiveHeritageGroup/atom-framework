#!/bin/bash
#===============================================================================
# AtoM 2.10.1 installer shared functions
#===============================================================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_step()  { echo -e "${CYAN}[=>]${NC} $1"; }
log_info()  { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn()  { echo -e "${YELLOW}[!!]${NC} $1"; }
log_error() { echo -e "${RED}[ERR]${NC} $1"; }

# Config file management
ATOM_CONF="/etc/atom/atom.conf"

save_config() {
    local key="$1"
    local value="$2"
    mkdir -p /etc/atom
    if [ -f "$ATOM_CONF" ] && grep -q "^${key}=" "$ATOM_CONF" 2>/dev/null; then
        sed -i "s|^${key}=.*|${key}=\"${value}\"|" "$ATOM_CONF"
    else
        echo "${key}=\"${value}\"" >> "$ATOM_CONF"
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

# Render template: replace {{VAR}} with shell variable $VAR
render_template() {
    local template="$1"
    local output="$2"

    local content
    content=$(cat "$template")

    while IFS= read -r var; do
        local val="${!var}"
        content="${content//\{\{${var}\}\}/${val}}"
    done < <(grep -oP '\{\{\K[A-Z_]+(?=\}\})' "$template" | sort -u)

    echo "$content" > "$output"
}

# Install Elasticsearch
install_elasticsearch() {
    log_step "Installing Elasticsearch 8.x..."

    if [ ! -f /etc/apt/sources.list.d/elastic-8.x.list ]; then
        wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch 2>/dev/null | \
            gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg 2>/dev/null

        echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" \
            > /etc/apt/sources.list.d/elastic-8.x.list

        apt-get update -qq 2>/dev/null
    fi

    apt-get install -y elasticsearch 2>/dev/null || {
        log_warn "Elasticsearch installation failed."
        log_warn "Install manually: https://www.elastic.co/guide/en/elasticsearch/reference/8.x/deb.html"
        return 1
    }

    # Configure for AtoM
    cat > /etc/elasticsearch/elasticsearch.yml << 'ESCONF'
cluster.name: atom
node.name: atom-node
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: 127.0.0.1
http.port: 9200
xpack.security.enabled: false
xpack.ml.enabled: false
ESCONF

    # Set heap size based on RAM
    local total_ram
    total_ram=$(free -m | awk '/^Mem:/{print $2}')
    local heap_size="512m"
    [ "$total_ram" -ge 4096 ] 2>/dev/null && heap_size="1g"
    [ "$total_ram" -ge 8192 ] 2>/dev/null && heap_size="2g"

    mkdir -p /etc/elasticsearch/jvm.options.d
    cat > /etc/elasticsearch/jvm.options.d/heap.options << EOF
-Xms${heap_size}
-Xmx${heap_size}
EOF

    systemctl daemon-reload
    systemctl enable elasticsearch 2>/dev/null
    systemctl start elasticsearch 2>/dev/null

    # Wait for ES
    local retries=30
    while [ $retries -gt 0 ]; do
        curl -s "http://127.0.0.1:9200/_cluster/health" &>/dev/null && break
        retries=$((retries - 1))
        sleep 2
    done

    log_info "Elasticsearch installed and running"
    return 0
}

# Check existing Elasticsearch
check_elasticsearch() {
    local es_host="${1:-localhost:9200}"
    [[ "$es_host" != http* ]] && es_host="http://${es_host}"

    if curl -s "${es_host}/_cluster/health" &>/dev/null; then
        log_info "Elasticsearch reachable at ${es_host}"
        return 0
    else
        log_warn "Elasticsearch not reachable at ${es_host}"
        log_warn "AtoM requires Elasticsearch for full-text search."
        return 1
    fi
}
