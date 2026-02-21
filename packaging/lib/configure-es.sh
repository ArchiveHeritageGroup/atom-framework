#!/bin/bash
#===============================================================================
# atom-heratio: Elasticsearch configuration and health check
#===============================================================================

configure_elasticsearch() {
    local es_host="$1"
    local install_search="$2"

    log_step "Checking Elasticsearch..."

    # Install ES if requested (new installs only)
    if [ "$install_search" = "true" ]; then
        install_elasticsearch "$es_host"
        return $?
    fi

    # Health check for existing ES
    check_elasticsearch "$es_host"
}

install_elasticsearch() {
    local es_host="$1"

    log_step "Installing Elasticsearch 8.x..."

    # Add Elasticsearch repository
    if [ ! -f /etc/apt/sources.list.d/elastic-8.x.list ]; then
        wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch 2>/dev/null | \
            gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg 2>/dev/null

        echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" \
            > /etc/apt/sources.list.d/elastic-8.x.list

        apt-get update -qq 2>/dev/null
    fi

    apt-get install -y elasticsearch 2>/dev/null || {
        log_warn "Elasticsearch installation failed"
        log_warn "Install manually: https://www.elastic.co/guide/en/elasticsearch/reference/8.x/deb.html"
        log_warn "Or use OpenSearch: https://opensearch.org/docs/latest/install-and-configure/install-opensearch/debian/"
        return 1
    }

    # Configure ES for AtoM
    local es_yml="/etc/elasticsearch/elasticsearch.yml"
    if [ -f "$es_yml" ]; then
        cat > "$es_yml" << ESCONF
cluster.name: atom
node.name: atom-node
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: 127.0.0.1
http.port: 9200
xpack.security.enabled: false
xpack.ml.enabled: false
ESCONF
    fi

    # Set heap size (512m for small servers, 1g for 4GB+ RAM)
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

    # Wait for ES to come up
    local retries=30
    while [ $retries -gt 0 ]; do
        if curl -s "http://127.0.0.1:9200/_cluster/health" &>/dev/null; then
            log_info "Elasticsearch installed and running"
            return 0
        fi
        retries=$((retries - 1))
        sleep 2
    done

    log_warn "Elasticsearch installed but may not be fully started yet"
    return 0
}

check_elasticsearch() {
    local es_host="$1"
    [ -z "$es_host" ] && es_host="localhost:9200"

    # Add protocol if missing
    local es_url="$es_host"
    [[ "$es_url" != http* ]] && es_url="http://${es_url}"

    if curl -s "${es_url}/_cluster/health" &>/dev/null; then
        local status
        status=$(curl -s "${es_url}/_cluster/health" | grep -oP '"status"\s*:\s*"\K[^"]+' 2>/dev/null || echo "unknown")
        log_info "Elasticsearch reachable at ${es_host} (status: ${status})"
        return 0
    else
        log_warn "Elasticsearch not reachable at ${es_host}"
        log_warn "AtoM requires Elasticsearch for full-text search."
        log_warn "Install: apt install elasticsearch, or use atom-heratio reconfigure"
        return 1
    fi
}
