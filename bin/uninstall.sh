#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Uninstall Script
# Restores AtoM to original state
#===============================================================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="${ATOM_ROOT:-$(dirname "$FRAMEWORK_PATH")}"
BACKUP_DIR="${ATOM_ROOT}/.ahg-backups"

log() { echo -e "${GREEN}[✓]${NC} $1"; }
warn() { echo -e "${YELLOW}[!]${NC} $1"; }
error() { echo -e "${RED}[✗]${NC} $1"; }
step() { echo -e "${BLUE}[→]${NC} $1"; }

confirm() {
    echo -en "${YELLOW}[?]${NC} $1 [y/N]: "
    read -r response
    [[ "$response" =~ ^[Yy]$ ]]
}

echo ""
echo -e "${RED}╔══════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${RED}║          AtoM AHG Framework - UNINSTALL                          ║${NC}"
echo -e "${RED}╚══════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "AtoM Root: ${ATOM_ROOT}"
echo "Backups:   ${BACKUP_DIR}"
echo ""

# Validate
[ ! -f "${ATOM_ROOT}/symfony" ] && { error "AtoM not found at ${ATOM_ROOT}"; exit 1; }

echo "This will:"
echo "  1. Restore original AtoM core files (from backups)"
echo "  2. Remove AHG plugin symlinks"
echo "  3. Disable AHG plugins in database"
echo "  4. Optionally remove database tables"
echo "  5. Optionally remove framework directories"
echo ""

if ! confirm "Do you want to proceed with uninstallation?"; then
    echo "Cancelled."
    exit 0
fi

#-------------------------------------------------------------------------------
# Get database credentials
#-------------------------------------------------------------------------------
CONFIG_FILE="${ATOM_ROOT}/config/config.php"
if [ -f "$CONFIG_FILE" ]; then
    DB_HOST=$(php -r "\$c=require('${CONFIG_FILE}'); preg_match('/host=([^;]+)/', \$c['all']['propel']['param']['dsn'] ?? '', \$m); echo \$m[1] ?? 'localhost';")
    DB_USER=$(php -r "\$c=require('${CONFIG_FILE}'); echo \$c['all']['propel']['param']['username'] ?? 'atom';")
    DB_PASS=$(php -r "\$c=require('${CONFIG_FILE}'); echo \$c['all']['propel']['param']['password'] ?? '';")
    DB_NAME=$(php -r "\$c=require('${CONFIG_FILE}'); preg_match('/dbname=([^;]+)/', \$c['all']['propel']['param']['dsn'] ?? '', \$m); echo \$m[1] ?? 'atom';")
    
    if [ -n "$DB_PASS" ]; then
        MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER -p$DB_PASS $DB_NAME"
    else
        MYSQL_CMD="mysql -h $DB_HOST -u $DB_USER $DB_NAME"
    fi
fi

#-------------------------------------------------------------------------------
# Step 1: Restore patched files
#-------------------------------------------------------------------------------
step "Step 1: Restoring original files..."

FILES=(
    "config/ProjectConfiguration.class.php"
    "lib/routing/QubitMetadataRoute.class.php"
    "plugins/sfPluginAdminPlugin/modules/sfPluginAdminPlugin/actions/themesAction.class.php"
    "plugins/qbAclPlugin/lib/QubitAcl.class.php"
)

for f in "${FILES[@]}"; do
    file="${ATOM_ROOT}/${f}"
    name=$(basename "$f")
    backup="${BACKUP_DIR}/${name}.original"
    
    if [ -f "$backup" ]; then
        cp "$backup" "$file"
        log "Restored: ${name}"
    else
        warn "No backup for: ${name}"
    fi
done

#-------------------------------------------------------------------------------
# Step 2: Remove symlinks
#-------------------------------------------------------------------------------
step "Step 2: Removing plugin symlinks..."

for link in "${ATOM_ROOT}/plugins"/ahg*Plugin; do
    if [ -L "$link" ]; then
        rm "$link"
        log "Removed: $(basename $link)"
    fi
done

#-------------------------------------------------------------------------------
# Step 3: Disable plugins in database
#-------------------------------------------------------------------------------
step "Step 3: Disabling plugins in database..."

if [ -n "$MYSQL_CMD" ]; then
    $MYSQL_CMD -e "UPDATE atom_plugin SET is_enabled=0 WHERE name LIKE 'ahg%';" 2>/dev/null || true
    log "Plugins disabled"
fi

#-------------------------------------------------------------------------------
# Step 4: Optional - Remove database tables
#-------------------------------------------------------------------------------
echo ""
if confirm "Remove AHG Framework database tables?"; then
    step "Removing database tables..."
    
    $MYSQL_CMD << 'EOSQL'
DROP TABLE IF EXISTS atom_plugin_audit;
DROP TABLE IF EXISTS atom_plugin;
DROP TABLE IF EXISTS ahg_settings;
EOSQL
    log "Tables removed"
else
    log "Tables kept"
fi

#-------------------------------------------------------------------------------
# Step 5: Optional - Remove directories
#-------------------------------------------------------------------------------
echo ""
if confirm "Remove framework directories (atom-framework, atom-ahg-plugins)?"; then
    step "Removing directories..."
    
    rm -rf "${ATOM_ROOT}/atom-framework"
    rm -rf "${ATOM_ROOT}/atom-ahg-plugins"
    rm -rf "${BACKUP_DIR}"
    
    log "Directories removed"
else
    log "Directories kept"
fi

#-------------------------------------------------------------------------------
# Step 6: Clear cache
#-------------------------------------------------------------------------------
step "Clearing cache..."
rm -rf "${ATOM_ROOT}/cache/"*
log "Cache cleared"

#-------------------------------------------------------------------------------
# Complete
#-------------------------------------------------------------------------------
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              UNINSTALL COMPLETE                                  ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo "AtoM has been restored to its original state."
echo ""
echo "Restart PHP-FPM: sudo systemctl restart php8.3-fpm"
echo ""
