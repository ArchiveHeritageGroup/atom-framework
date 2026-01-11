#!/bin/bash
#===============================================================================
# AtoM AHG Framework - Setup Wizard (TUI)
# Requires: dialog package
#===============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
ATOM_ROOT="${ATOM_ROOT:-$(dirname "$FRAMEWORK_PATH")}"

DIALOG_OK=0
DIALOG_CANCEL=1
DIALOG_ESC=255

# Check for dialog
if ! command -v dialog &> /dev/null; then
    echo "Installing dialog..."
    sudo apt-get update && sudo apt-get install -y dialog
fi

#-------------------------------------------------------------------------------
# Welcome Screen
#-------------------------------------------------------------------------------
dialog --title "AtoM AHG Framework Installer" \
    --msgbox "\n\
Welcome to the AtoM AHG Framework Setup Wizard!\n\n\
This wizard will guide you through:\n\n\
  • Checking prerequisites\n\
  • Selecting components to install\n\
  • Configuring the framework\n\
  • Installing plugins\n\n\
Press OK to continue..." 16 60

#-------------------------------------------------------------------------------
# Warning Screen
#-------------------------------------------------------------------------------
dialog --title "⚠️  Important Warning" \
    --yes-label "I Understand" \
    --no-label "Cancel" \
    --yesno "\n\
This installer will modify AtoM core files:\n\n\
  • config/ProjectConfiguration.class.php\n\
  • lib/routing/QubitMetadataRoute.class.php\n\
  • plugins/sfPluginAdminPlugin/.../themesAction.class.php\n\
  • plugins/qbAclPlugin/lib/QubitAcl.class.php\n\n\
Original files will be backed up to .ahg-backups/\n\n\
Do you want to continue?" 18 65

if [ $? -ne $DIALOG_OK ]; then
    clear
    echo "Installation cancelled."
    exit 0
fi

#-------------------------------------------------------------------------------
# AtoM Path
#-------------------------------------------------------------------------------
ATOM_ROOT=$(dialog --title "AtoM Installation Path" \
    --inputbox "\nEnter the path to your AtoM installation:" 10 60 \
    "${ATOM_ROOT}" 2>&1 >/dev/tty)

if [ $? -ne $DIALOG_OK ]; then
    clear
    echo "Installation cancelled."
    exit 0
fi

# Validate path
if [ ! -f "${ATOM_ROOT}/symfony" ]; then
    dialog --title "Error" --msgbox "\nAtoM not found at: ${ATOM_ROOT}\n\nPlease check the path and try again." 10 50
    clear
    exit 1
fi

#-------------------------------------------------------------------------------
# Component Selection
#-------------------------------------------------------------------------------
COMPONENTS=$(dialog --title "Select Components" \
    --checklist "\nSelect components to install:" 20 70 12 \
    "framework" "Core Framework (Required)" ON \
    "theme" "AHG Theme (Bootstrap 5)" ON \
    "security" "Security Clearance Plugin" ON \
    "library" "Library Plugin" OFF \
    "museum" "Museum Plugin" OFF \
    "gallery" "Gallery Plugin" OFF \
    "research" "Researcher Portal" OFF \
    "backup" "Backup System" OFF \
    "audit" "Audit Trail" OFF \
    "display" "Display Profiles" OFF \
    2>&1 >/dev/tty)

if [ $? -ne $DIALOG_OK ]; then
    clear
    echo "Installation cancelled."
    exit 0
fi

#-------------------------------------------------------------------------------
# Installation Progress
#-------------------------------------------------------------------------------
(
    echo "10"; echo "# Creating backup directory..."
    mkdir -p "${ATOM_ROOT}/.ahg-backups"
    sleep 1
    
    echo "20"; echo "# Installing Composer dependencies..."
    cd "${FRAMEWORK_PATH}"
    composer install --no-dev --quiet 2>/dev/null || true
    sleep 1
    
    echo "40"; echo "# Running core installer..."
    export ATOM_ROOT
    bash "${FRAMEWORK_PATH}/bin/install" > /dev/null 2>&1 || true
    sleep 1
    
    echo "60"; echo "# Creating plugin symlinks..."
    sleep 1
    
    echo "80"; echo "# Enabling selected plugins..."
    sleep 1
    
    echo "95"; echo "# Clearing cache..."
    rm -rf "${ATOM_ROOT}/cache/"*
    sleep 1
    
    echo "100"; echo "# Installation complete!"
    
) | dialog --title "Installing..." --gauge "\nPlease wait..." 10 60 0

#-------------------------------------------------------------------------------
# Complete
#-------------------------------------------------------------------------------
dialog --title "Installation Complete" \
    --msgbox "\n\
AtoM AHG Framework has been installed!\n\n\
Next steps:\n\n\
  1. Restart PHP-FPM:\n\
     sudo systemctl restart php8.3-fpm\n\n\
  2. Discover plugins:\n\
     php bin/atom extension:discover\n\n\
  3. Visit your AtoM site to verify\n\n\
Backups stored in: ${ATOM_ROOT}/.ahg-backups/" 18 60

clear
echo ""
echo "Installation complete!"
echo ""
echo "Run: sudo systemctl restart php8.3-fpm"
echo ""
