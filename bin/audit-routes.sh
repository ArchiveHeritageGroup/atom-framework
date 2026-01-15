#!/bin/bash
# AtoM AHG Framework - Routing Audit Script

PLUGIN_PATH="/usr/share/nginx/archive/atom-ahg-plugins"
ARCHIVE_PATH="/usr/share/nginx/archive"

echo "=============================================="
echo "AtoM AHG Framework - Routing Audit"
echo "=============================================="
echo ""

# List all plugins with routing files
echo "=== Plugins with routing.yml ==="
for dir in $PLUGIN_PATH/*/; do
    plugin=$(basename "$dir")
    if [ -f "$dir/config/routing.yml" ]; then
        echo "  ✓ $plugin"
        route_count=$(grep -c "url:" "$dir/config/routing.yml" 2>/dev/null || echo "0")
        echo "    Routes: $route_count"
    fi
done
echo ""

echo "=== Plugins with loadRoutes() in Configuration ==="
for dir in $PLUGIN_PATH/*/; do
    plugin=$(basename "$dir")
    config_file="$dir/config/${plugin}Configuration.class.php"
    if [ -f "$config_file" ]; then
        if grep -q "loadRoutes\|prependRoute" "$config_file" 2>/dev/null; then
            echo "  ✓ $plugin"
            route_count=$(grep -c "prependRoute" "$config_file" 2>/dev/null || echo "0")
            echo "    Routes: $route_count"
        fi
    fi
done
echo ""

echo "=== POTENTIAL CONFLICTS (Both routing.yml AND Configuration.php) ==="
for dir in $PLUGIN_PATH/*/; do
    plugin=$(basename "$dir")
    config_file="$dir/config/${plugin}Configuration.class.php"
    routing_file="$dir/config/routing.yml"
    
    has_yml=false
    has_php=false
    
    [ -f "$routing_file" ] && has_yml=true
    [ -f "$config_file" ] && grep -q "prependRoute" "$config_file" 2>/dev/null && has_php=true
    
    if $has_yml && $has_php; then
        echo "  ⚠️  $plugin - HAS BOTH!"
    fi
done
echo ""

echo "=== Checking for Module Name Mismatches ==="
for dir in $PLUGIN_PATH/*/; do
    plugin=$(basename "$dir")
    routing_file="$dir/config/routing.yml"
    config_file="$dir/config/${plugin}Configuration.class.php"
    modules_dir="$dir/modules"
    
    if [ -d "$modules_dir" ]; then
        actual_modules=$(ls -1 "$modules_dir" 2>/dev/null | tr '\n' ' ')
        
        # Check routing.yml
        if [ -f "$routing_file" ]; then
            yml_modules=$(grep "module:" "$routing_file" 2>/dev/null | sed 's/.*module:\s*//' | tr -d '[:space:]' | sort -u | tr '\n' ' ')
            for mod in $yml_modules; do
                if [ ! -d "$modules_dir/$mod" ]; then
                    echo "  ⚠️  $plugin routing.yml references '$mod' but module folder doesn't exist"
                    echo "      Actual modules: $actual_modules"
                fi
            done
        fi
    fi
done
echo ""

echo "=== All Routes (php symfony app:routes qubit) ==="
cd $ARCHIVE_PATH
php symfony app:routes qubit 2>/dev/null | head -50
echo "... (truncated)"
echo ""

echo "Audit complete!"
