#!/bin/bash
#===============================================================================
# atom-heratio: Detect and validate existing AtoM installation
#===============================================================================

detect_atom() {
    local search_path="${1:-}"
    local found_path=""

    # Search order: user-specified, then common locations
    local search_paths=()
    [ -n "$search_path" ] && search_paths+=("$search_path")
    search_paths+=("/usr/share/nginx/atom" "/usr/share/nginx/archive" "/var/www/atom" "/opt/atom")

    for p in "${search_paths[@]}"; do
        if [ -f "${p}/symfony" ] && [ -f "${p}/index.php" ]; then
            found_path="$p"
            break
        fi
    done

    echo "$found_path"
}

detect_atom_version() {
    local atom_path="$1"
    local version="unknown"

    # Method 1: QubitConfiguration.class.php
    if [ -f "${atom_path}/lib/QubitConfiguration.class.php" ]; then
        version=$(grep -oP "const VERSION\s*=\s*'\K[^']+" "${atom_path}/lib/QubitConfiguration.class.php" 2>/dev/null || echo "")
    fi

    # Method 2: version.yml
    if [ -z "$version" ] || [ "$version" = "unknown" ]; then
        if [ -f "${atom_path}/config/version.yml" ]; then
            version=$(grep -oP 'version:\s*\K\S+' "${atom_path}/config/version.yml" 2>/dev/null || echo "unknown")
        fi
    fi

    echo "$version"
}

validate_atom_version() {
    local version="$1"
    local min_version="2.8"

    # Parse major.minor
    local major minor
    major=$(echo "$version" | cut -d. -f1)
    minor=$(echo "$version" | cut -d. -f2)

    local min_major min_minor
    min_major=$(echo "$min_version" | cut -d. -f1)
    min_minor=$(echo "$min_version" | cut -d. -f2)

    if [ "$major" -gt "$min_major" ] 2>/dev/null; then
        return 0
    elif [ "$major" -eq "$min_major" ] 2>/dev/null && [ "$minor" -ge "$min_minor" ] 2>/dev/null; then
        return 0
    fi

    return 1
}

# Check if Heratio is already installed
detect_heratio() {
    local atom_path="$1"

    if [ -d "${atom_path}/atom-framework" ] && [ -f "${atom_path}/atom-framework/version.json" ]; then
        local fw_version
        fw_version=$(php -r "echo json_decode(file_get_contents('${atom_path}/atom-framework/version.json'),true)['version'] ?? 'unknown';" 2>/dev/null || echo "unknown")
        echo "$fw_version"
    else
        echo ""
    fi
}
