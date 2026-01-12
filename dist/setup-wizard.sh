#!/bin/bash
#===============================================================================
# AtoM Setup Wizard v2.9
#===============================================================================

set -e

if [ "$EUID" -ne 0 ]; then
    echo "Please run as root: sudo $0"
    exit 1
fi

if ! command -v dialog &>/dev/null; then
    echo "Installing dialog..."
    apt-get update -qq && apt-get install -y dialog >/dev/null 2>&1
fi

#===============================================================================
# Configuration
#===============================================================================
TEMP=$(mktemp)
trap "rm -f $TEMP" EXIT

INSTALL_MODE="complete"
ATOM_PATH="/usr/share/nginx/atom"

SVC_NGINX="on"
SVC_PHP="on"
SVC_MYSQL="on"
SVC_ES="on"
SVC_GEARMAN="on"
SVC_MEMCACHED="on"
SVC_MEDIA="on"
SVC_FOP="on"

MYSQL_ROOT=""
DB_NAME="atom"
DB_USER="atom"
DB_PASS=""
ES_HEAP="512m"

SITE_TITLE="My Archive"
SITE_DESC="Archival Management System"
SITE_URL=""
ADMIN_EMAIL=""
ADMIN_USER="admin"
ADMIN_PASS=""

LOAD_DEMO="on"
SETUP_WORKER="on"

PLG_SECURITY="on"
PLG_LIBRARY="on"
PLG_MUSEUM="on"
PLG_GALLERY="on"
PLG_DAM="on"
PLG_SPECTRUM="on"
PLG_RESEARCH="on"
PLG_ACCESS="on"
PLG_PRIVACY="on"
PLG_HERITAGE="on"
PLG_EXTRIGHTS="on"
PLG_RIGHTS="on"
PLG_BACKUP="on"
PLG_AUDIT="on"
PLG_DISPLAY="on"
PLG_DONOR="on"
PLG_VENDOR="on"
PLG_CONDITION="on"
PLG_NER="on"
PLG_3DMODEL="on"
PLG_IIIF="on"
PLG_RIC="on"
PLG_DATAMIG="on"
PLG_MIGRATION="on"
PLG_API="on"

#===============================================================================
# System Check
#===============================================================================
check_requirements() {
    local cpu=$(nproc)
    local ram=$(free -m | awk '/^Mem:/{print $2}')
    local disk=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')
    local os=$(lsb_release -ds 2>/dev/null || echo "Unknown")
    
    local report="SYSTEM CHECK\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
    report+="CPU:  $cpu cores\n"
    report+="RAM:  ${ram}MB\n"
    report+="Disk: ${disk}GB free\n"
    report+="OS:   $os\n\n"
    report+="INSTALLED SERVICES\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
    
    command -v nginx &>/dev/null && report+="[x] Nginx\n" && SVC_NGINX="off" || report+="[ ] Nginx\n"
    command -v php &>/dev/null && report+="[x] PHP\n" && SVC_PHP="off" || report+="[ ] PHP\n"
    command -v mysql &>/dev/null && report+="[x] MySQL\n" && SVC_MYSQL="off" || report+="[ ] MySQL\n"
    curl -s http://localhost:9200 &>/dev/null && report+="[x] Elasticsearch\n" && SVC_ES="off" || report+="[ ] Elasticsearch\n"
    command -v gearman &>/dev/null && report+="[x] Gearman\n" && SVC_GEARMAN="off" || report+="[ ] Gearman\n"
    
    dialog --title "System Check" --yes-label "Continue" --no-label "Cancel" --yesno "$report" 22 45
}

#===============================================================================
# Welcome
#===============================================================================
dialog --title "AtoM Setup Wizard" --msgbox "\n
     █████╗ ████████╗ ██████╗ ███╗   ███╗
    ██╔══██╗╚══██╔══╝██╔═══██╗████╗ ████║
    ███████║   ██║   ██║   ██║██╔████╔██║
    ██╔══██║   ██║   ██║   ██║██║╚██╔╝██║
    ██║  ██║   ██║   ╚██████╔╝██║ ╚═╝ ██║
    ╚═╝  ╚═╝   ╚═╝    ╚═════╝ ╚═╝     ╚═╝

      Access to Memory Setup Wizard
        Version 2.10 + AHG Framework

          Press OK to continue...
" 20 55

check_requirements || exit 1

#===============================================================================
# Step 1: Installation Mode
#===============================================================================
dialog --title "Step 1: Installation Mode" \
       --menu "\nSelect installation type:\n" 15 70 3 \
       "complete" "Full install: AtoM 2.10 + Services + AHG Extensions" \
       "atom" "Base AtoM 2.10 + Services only (no extensions)" \
       "extensions" "AHG Extensions only (existing AtoM installation)" \
       2>$TEMP || exit 1
INSTALL_MODE=$(<$TEMP)

#===============================================================================
# Step 2: Installation Path
#===============================================================================
if [ "$INSTALL_MODE" = "extensions" ]; then
    dialog --title "Step 2: AtoM Path" \
           --inputbox "\nPath to existing AtoM installation:\n" 10 55 "$ATOM_PATH" \
           2>$TEMP || exit 1
    ATOM_PATH=$(<$TEMP)
    [ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"
    
    if [ ! -f "$ATOM_PATH/symfony" ]; then
        dialog --title "Error" --msgbox "\nAtoM not found at: $ATOM_PATH\n\nPlease check the path and try again." 10 50
        exit 1
    fi
else
    dialog --title "Step 2: Installation Path" \
           --inputbox "\nInstallation directory:\n" 10 55 "$ATOM_PATH" \
           2>$TEMP || exit 1
    ATOM_PATH=$(<$TEMP)
    [ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"
fi

#===============================================================================
# Steps 3-7: Only for full installs
#===============================================================================
if [ "$INSTALL_MODE" != "extensions" ]; then

#===============================================================================
# Step 3: Services
#===============================================================================
dialog --title "Step 3: Services" \
       --checklist "\nSelect services to install:\n" 18 65 8 \
       "nginx" "Nginx Web Server" $SVC_NGINX \
       "php" "PHP 8.3 + Extensions" $SVC_PHP \
       "mysql" "MySQL 8.0" $SVC_MYSQL \
       "elasticsearch" "Elasticsearch 7.10" $SVC_ES \
       "gearman" "Gearman Job Server" $SVC_GEARMAN \
       "memcached" "Memcached" $SVC_MEMCACHED \
       "media" "ImageMagick/FFmpeg/Ghostscript" $SVC_MEDIA \
       "fop" "Apache FOP (PDF)" $SVC_FOP \
       2>$TEMP || exit 1

SERVICES=$(<$TEMP)
SVC_NGINX="off"; SVC_PHP="off"; SVC_MYSQL="off"; SVC_ES="off"
SVC_GEARMAN="off"; SVC_MEMCACHED="off"; SVC_MEDIA="off"; SVC_FOP="off"
[[ "$SERVICES" == *nginx* ]] && SVC_NGINX="on"
[[ "$SERVICES" == *php* ]] && SVC_PHP="on"
[[ "$SERVICES" == *mysql* ]] && SVC_MYSQL="on"
[[ "$SERVICES" == *elasticsearch* ]] && SVC_ES="on"
[[ "$SERVICES" == *gearman* ]] && SVC_GEARMAN="on"
[[ "$SERVICES" == *memcached* ]] && SVC_MEMCACHED="on"
[[ "$SERVICES" == *media* ]] && SVC_MEDIA="on"
[[ "$SERVICES" == *fop* ]] && SVC_FOP="on"

#===============================================================================
# Step 4: MySQL
#===============================================================================
if [ "$SVC_MYSQL" = "on" ]; then
    dialog --title "Step 4: MySQL Root Password" \
           --insecure --passwordbox "\nMySQL root password (min 8 chars):\n" 10 50 \
           2>$TEMP || exit 1
    MYSQL_ROOT=$(<$TEMP)
    [ ${#MYSQL_ROOT} -lt 8 ] && MYSQL_ROOT="rootpass123"
fi

dialog --title "Step 4: AtoM Database" \
       --form "\nDatabase settings:\n" 12 55 2 \
       "Name:" 1 1 "$DB_NAME" 1 8 30 50 \
       "User:" 2 1 "$DB_USER" 2 8 30 50 \
       2>$TEMP || exit 1
DB_NAME=$(sed -n '1p' $TEMP); DB_USER=$(sed -n '2p' $TEMP)
[ -z "$DB_NAME" ] && DB_NAME="atom"
[ -z "$DB_USER" ] && DB_USER="atom"

dialog --title "Step 4: Database Password" \
       --insecure --passwordbox "\nPassword for '$DB_USER':\n" 10 50 \
       2>$TEMP || exit 1
DB_PASS=$(<$TEMP)
[ ${#DB_PASS} -lt 8 ] && DB_PASS="AtoM@123"

#===============================================================================
# Step 5: Elasticsearch
#===============================================================================
if [ "$SVC_ES" = "on" ]; then
    ram_mb=$(free -m | awk '/^Mem:/{print $2}')
    rec=$((ram_mb / 4)); [ $rec -gt 1024 ] && rec=1024; [ $rec -lt 256 ] && rec=256
    ES_HEAP="${rec}m"
    
    dialog --title "Step 5: Elasticsearch" \
           --inputbox "\nHeap size (recommended: ${ES_HEAP}):\n" 10 50 "$ES_HEAP" \
           2>$TEMP || exit 1
    ES_HEAP=$(<$TEMP)
    [ -z "$ES_HEAP" ] && ES_HEAP="512m"
fi

#===============================================================================
# Step 6: Site Settings
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

dialog --title "Step 6: Site Configuration" \
       --form "\nSite settings:\n" 14 60 3 \
       "Title:" 1 1 "$SITE_TITLE" 1 12 40 100 \
       "Description:" 2 1 "$SITE_DESC" 2 12 40 200 \
       "URL:" 3 1 "$SITE_URL" 3 12 40 200 \
       2>$TEMP || exit 1
SITE_TITLE=$(sed -n '1p' $TEMP); SITE_DESC=$(sed -n '2p' $TEMP); SITE_URL=$(sed -n '3p' $TEMP)
[ -z "$SITE_TITLE" ] && SITE_TITLE="My Archive"
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

#===============================================================================
# Step 7: Admin Account
#===============================================================================
dialog --title "Step 7: Administrator" \
       --form "\nAdmin account:\n" 12 55 2 \
       "Email:" 1 1 "$ADMIN_EMAIL" 1 10 40 100 \
       "Username:" 2 1 "$ADMIN_USER" 2 10 30 50 \
       2>$TEMP || exit 1
ADMIN_EMAIL=$(sed -n '1p' $TEMP); ADMIN_USER=$(sed -n '2p' $TEMP)
[ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@example.com"
[ -z "$ADMIN_USER" ] && ADMIN_USER="admin"

dialog --title "Step 7: Admin Password" \
       --insecure --passwordbox "\nAdmin password (min 8 chars):\n" 10 50 \
       2>$TEMP || exit 1
ADMIN_PASS=$(<$TEMP)
[ ${#ADMIN_PASS} -lt 8 ] && ADMIN_PASS="Merlot@123"

fi # End of full install steps

#===============================================================================
# Step 8: AHG Plugins (for complete and extensions modes)
#===============================================================================
if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then

dialog --title "Plugins - Core (1/5)" \
       --checklist "\nCore plugins (* = Required):\n" 13 70 3 \
       "security" "* ahgSecurityClearancePlugin - Security" $PLG_SECURITY \
       "display" "  ahgDisplayPlugin - Display Profiles" $PLG_DISPLAY \
       "backup" "  ahgBackupPlugin - Backup & Restore" $PLG_BACKUP \
       2>$TEMP || exit 1
P=$(<$TEMP)
PLG_SECURITY="off"; PLG_DISPLAY="off"; PLG_BACKUP="off"
[[ "$P" == *security* ]] && PLG_SECURITY="on"
[[ "$P" == *display* ]] && PLG_DISPLAY="on"
[[ "$P" == *backup* ]] && PLG_BACKUP="on"

dialog --title "Plugins - GLAM (2/5)" \
       --checklist "\nGLAM sector plugins:\n" 15 70 5 \
       "library" "ahgLibraryPlugin - Library (RDA, MARC21)" $PLG_LIBRARY \
       "museum" "ahgMuseumPlugin - Museum (CCO, Spectrum)" $PLG_MUSEUM \
       "gallery" "ahgGalleryPlugin - Gallery (CCO, CDWA)" $PLG_GALLERY \
       "dam" "ahgDAMPlugin - Digital Asset Management" $PLG_DAM \
       "spectrum" "ahgSpectrumPlugin - Spectrum 5.0" $PLG_SPECTRUM \
       2>$TEMP || exit 1
P=$(<$TEMP)
PLG_LIBRARY="off"; PLG_MUSEUM="off"; PLG_GALLERY="off"; PLG_DAM="off"; PLG_SPECTRUM="off"
[[ "$P" == *library* ]] && PLG_LIBRARY="on"
[[ "$P" == *museum* ]] && PLG_MUSEUM="on"
[[ "$P" == *gallery* ]] && PLG_GALLERY="on"
[[ "$P" == *dam* ]] && PLG_DAM="on"
[[ "$P" == *spectrum* ]] && PLG_SPECTRUM="on"

dialog --title "Plugins - Management (3/5)" \
       --checklist "\nManagement plugins:\n" 16 70 6 \
       "research" "ahgResearchPlugin - Researcher Portal" $PLG_RESEARCH \
       "access" "ahgAccessRequestPlugin - Access Requests" $PLG_ACCESS \
       "donor" "ahgDonorAgreementPlugin - Donor Agreements" $PLG_DONOR \
       "vendor" "ahgVendorPlugin - Vendor Management" $PLG_VENDOR \
       "condition" "ahgConditionPlugin - Condition Assessment" $PLG_CONDITION \
       "audit" "ahgAuditTrailPlugin - Audit Logging" $PLG_AUDIT \
       2>$TEMP || exit 1
P=$(<$TEMP)
PLG_RESEARCH="off"; PLG_ACCESS="off"; PLG_DONOR="off"; PLG_VENDOR="off"; PLG_CONDITION="off"; PLG_AUDIT="off"
[[ "$P" == *research* ]] && PLG_RESEARCH="on"
[[ "$P" == *access* ]] && PLG_ACCESS="on"
[[ "$P" == *donor* ]] && PLG_DONOR="on"
[[ "$P" == *vendor* ]] && PLG_VENDOR="on"
[[ "$P" == *condition* ]] && PLG_CONDITION="on"
[[ "$P" == *audit* ]] && PLG_AUDIT="on"

dialog --title "Plugins - Compliance (4/5)" \
       --checklist "\nCompliance plugins:\n" 14 70 4 \
       "privacy" "ahgPrivacyPlugin - Privacy (GDPR, POPIA)" $PLG_PRIVACY \
       "heritage" "ahgHeritageAccountingPlugin - GRAP 103" $PLG_HERITAGE \
       "extrights" "ahgExtendedRightsPlugin - Extended Rights" $PLG_EXTRIGHTS \
       "rights" "ahgRightsPlugin - Rights Management" $PLG_RIGHTS \
       2>$TEMP || exit 1
P=$(<$TEMP)
PLG_PRIVACY="off"; PLG_HERITAGE="off"; PLG_EXTRIGHTS="off"; PLG_RIGHTS="off"
[[ "$P" == *privacy* ]] && PLG_PRIVACY="on"
[[ "$P" == *heritage* ]] && PLG_HERITAGE="on"
[[ "$P" == *extrights* ]] && PLG_EXTRIGHTS="on"
[[ "$P" == *rights* ]] && PLG_RIGHTS="on"

dialog --title "Plugins - Advanced (5/5)" \
       --checklist "\nAI & Advanced plugins:\n" 17 70 7 \
       "ner" "ahgNerPlugin - Named Entity Recognition" $PLG_NER \
       "3dmodel" "ahg3DModelPlugin - 3D Model Viewer" $PLG_3DMODEL \
       "iiif" "ahgIiifCollectionPlugin - IIIF Deep Zoom" $PLG_IIIF \
       "ric" "ahgRicExplorerPlugin - Records in Contexts" $PLG_RIC \
       "datamig" "ahgDataMigrationPlugin - Data Migration" $PLG_DATAMIG \
       "migration" "ahgMigrationPlugin - Schema Migration" $PLG_MIGRATION \
       "api" "ahgAPIPlugin - REST API Extensions" $PLG_API \
       2>$TEMP || exit 1
P=$(<$TEMP)
PLG_NER="off"; PLG_3DMODEL="off"; PLG_IIIF="off"; PLG_RIC="off"; PLG_DATAMIG="off"; PLG_MIGRATION="off"; PLG_API="off"
[[ "$P" == *ner* ]] && PLG_NER="on"
[[ "$P" == *3dmodel* ]] && PLG_3DMODEL="on"
[[ "$P" == *iiif* ]] && PLG_IIIF="on"
[[ "$P" == *ric* ]] && PLG_RIC="on"
[[ "$P" == *datamig* ]] && PLG_DATAMIG="on"
[[ "$P" == *migration* ]] && PLG_MIGRATION="on"
[[ "$P" == *api* ]] && PLG_API="on"

fi

#===============================================================================
# Step 9: Options (only for full installs)
#===============================================================================
if [ "$INSTALL_MODE" != "extensions" ]; then
dialog --title "Options" \
       --checklist "\nAdditional options:\n" 12 55 2 \
       "demo" "Load demo data" $LOAD_DEMO \
       "worker" "Setup atom-worker service" $SETUP_WORKER \
       2>$TEMP || exit 1
O=$(<$TEMP)
LOAD_DEMO="off"; SETUP_WORKER="off"
[[ "$O" == *demo* ]] && LOAD_DEMO="on"
[[ "$O" == *worker* ]] && SETUP_WORKER="on"
fi

#===============================================================================
# Confirmation
#===============================================================================
PLG_COUNT=0
[ "$PLG_SECURITY" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_DISPLAY" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_BACKUP" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_LIBRARY" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_MUSEUM" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_GALLERY" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_DAM" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_SPECTRUM" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_RESEARCH" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_ACCESS" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_DONOR" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_VENDOR" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_CONDITION" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_AUDIT" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_PRIVACY" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_HERITAGE" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_EXTRIGHTS" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_RIGHTS" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_NER" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_3DMODEL" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_IIIF" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_RIC" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_DATAMIG" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_MIGRATION" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))
[ "$PLG_API" = "on" ] && PLG_COUNT=$((PLG_COUNT+1))

if [ "$INSTALL_MODE" = "extensions" ]; then
    dialog --title "Confirm Installation" --yesno "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
        AHG EXTENSIONS INSTALLATION
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

AtoM Path: $ATOM_PATH
Plugins:   $PLG_COUNT selected

This will:
  • Clone atom-framework
  • Clone atom-ahg-plugins
  • Create plugin symlinks
  • Enable selected plugins

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Proceed with installation?
" 22 55 || exit 1
else
    dialog --title "Confirm Installation" --yesno "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
           INSTALLATION SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Mode:      $INSTALL_MODE
Path:      $ATOM_PATH
Database:  $DB_NAME (user: $DB_USER)

Site:      $SITE_TITLE
URL:       $SITE_URL
Admin:     $ADMIN_EMAIL

Plugins:   $PLG_COUNT selected
Worker:    $SETUP_WORKER

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Proceed with installation?
" 24 55 || exit 1
fi

#===============================================================================
# Setup logging
#===============================================================================
mkdir -p /var/log/atom
LOG="/var/log/atom/install-$(date +%Y%m%d%H%M%S).log"
touch "$LOG"

dialog --title "Monitor Installation" --msgbox "
To watch progress in real-time, open another terminal:

  tail -f $LOG

Press OK to start installation...
" 12 58

#===============================================================================
# INSTALLATION - Extensions Only Mode
#===============================================================================
if [ "$INSTALL_MODE" = "extensions" ]; then
{
echo 10; echo "XXX"; echo "Cloning AHG Framework..."; echo "XXX"
cd "$ATOM_PATH"
rm -rf atom-framework atom-ahg-plugins
git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git atom-framework >>$LOG 2>&1
git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git atom-ahg-plugins >>$LOG 2>&1

echo 30; echo "XXX"; echo "Installing framework dependencies..."; echo "XXX"
cd "$ATOM_PATH/atom-framework"
composer install --no-dev --no-interaction >>$LOG 2>&1

echo 50; echo "XXX"; echo "Running framework install..."; echo "XXX"
bash bin/install --auto >>$LOG 2>&1 || true

echo 70; echo "XXX"; echo "Discovering plugins..."; echo "XXX"
php bin/atom extension:discover >>$LOG 2>&1 || true

echo 80; echo "XXX"; echo "Enabling selected plugins..."; echo "XXX"

[ "$PLG_SECURITY" = "on" ] && php bin/atom extension:enable ahgSecurityClearancePlugin >>$LOG 2>&1 || true
[ "$PLG_DISPLAY" = "on" ] && php bin/atom extension:enable ahgDisplayPlugin >>$LOG 2>&1 || true
[ "$PLG_BACKUP" = "on" ] && php bin/atom extension:enable ahgBackupPlugin >>$LOG 2>&1 || true
[ "$PLG_LIBRARY" = "on" ] && php bin/atom extension:enable ahgLibraryPlugin >>$LOG 2>&1 || true
[ "$PLG_MUSEUM" = "on" ] && php bin/atom extension:enable ahgMuseumPlugin >>$LOG 2>&1 || true
[ "$PLG_GALLERY" = "on" ] && php bin/atom extension:enable ahgGalleryPlugin >>$LOG 2>&1 || true
[ "$PLG_DAM" = "on" ] && php bin/atom extension:enable ahgDAMPlugin >>$LOG 2>&1 || true
[ "$PLG_SPECTRUM" = "on" ] && php bin/atom extension:enable ahgSpectrumPlugin >>$LOG 2>&1 || true
[ "$PLG_RESEARCH" = "on" ] && php bin/atom extension:enable ahgResearchPlugin >>$LOG 2>&1 || true
[ "$PLG_ACCESS" = "on" ] && php bin/atom extension:enable ahgAccessRequestPlugin >>$LOG 2>&1 || true
[ "$PLG_DONOR" = "on" ] && php bin/atom extension:enable ahgDonorAgreementPlugin >>$LOG 2>&1 || true
[ "$PLG_VENDOR" = "on" ] && php bin/atom extension:enable ahgVendorPlugin >>$LOG 2>&1 || true
[ "$PLG_CONDITION" = "on" ] && php bin/atom extension:enable ahgConditionPlugin >>$LOG 2>&1 || true
[ "$PLG_AUDIT" = "on" ] && php bin/atom extension:enable ahgAuditTrailPlugin >>$LOG 2>&1 || true
[ "$PLG_PRIVACY" = "on" ] && php bin/atom extension:enable ahgPrivacyPlugin >>$LOG 2>&1 || true
[ "$PLG_HERITAGE" = "on" ] && php bin/atom extension:enable ahgHeritageAccountingPlugin >>$LOG 2>&1 || true
[ "$PLG_EXTRIGHTS" = "on" ] && php bin/atom extension:enable ahgExtendedRightsPlugin >>$LOG 2>&1 || true
[ "$PLG_RIGHTS" = "on" ] && php bin/atom extension:enable ahgRightsPlugin >>$LOG 2>&1 || true
[ "$PLG_NER" = "on" ] && php bin/atom extension:enable ahgNerPlugin >>$LOG 2>&1 || true
[ "$PLG_3DMODEL" = "on" ] && php bin/atom extension:enable ahg3DModelPlugin >>$LOG 2>&1 || true
[ "$PLG_IIIF" = "on" ] && php bin/atom extension:enable ahgIiifCollectionPlugin >>$LOG 2>&1 || true
[ "$PLG_RIC" = "on" ] && php bin/atom extension:enable ahgRicExplorerPlugin >>$LOG 2>&1 || true
[ "$PLG_DATAMIG" = "on" ] && php bin/atom extension:enable ahgDataMigrationPlugin >>$LOG 2>&1 || true
[ "$PLG_MIGRATION" = "on" ] && php bin/atom extension:enable ahgMigrationPlugin >>$LOG 2>&1 || true
[ "$PLG_API" = "on" ] && php bin/atom extension:enable ahgAPIPlugin >>$LOG 2>&1 || true

echo 95; echo "XXX"; echo "Clearing cache..."; echo "XXX"
cd "$ATOM_PATH"
sudo -u www-data php symfony cc >>$LOG 2>&1 || true
chown -R www-data:www-data "$ATOM_PATH"

echo 100; echo "XXX"; echo "Complete!"; echo "XXX"
sleep 1

} | dialog --title "Installing AHG Extensions" --gauge "Preparing..." 8 60 0

SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Installation Complete!" --msgbox "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   AHG Extensions Installed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Path:    $ATOM_PATH
Plugins: $PLG_COUNT enabled
Log:     $LOG

Remember to clear cache if needed:
  cd $ATOM_PATH && php symfony cc

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
" 20 55

clear
echo "========================================"
echo "  AHG Extensions Installed!"
echo "========================================"
echo "  Path:    $ATOM_PATH"
echo "  Plugins: $PLG_COUNT enabled"
echo "  Log:     $LOG"
echo ""
exit 0
fi

#===============================================================================
# INSTALLATION - Full Mode (complete or atom)
#===============================================================================
{
echo 2; echo "XXX"; echo "Updating system..."; echo "XXX"
apt-get update -qq >>$LOG 2>&1
apt-get install -y software-properties-common curl wget gnupg git >>$LOG 2>&1

if [ "$SVC_NGINX" = "on" ]; then
    echo 5; echo "XXX"; echo "Installing Nginx..."; echo "XXX"
    apt-get install -y nginx >>$LOG 2>&1
fi

if [ "$SVC_PHP" = "on" ]; then
    echo 10; echo "XXX"; echo "Installing PHP 8.3..."; echo "XXX"
    add-apt-repository -y ppa:ondrej/php >>$LOG 2>&1 || true
    apt-get update -qq >>$LOG 2>&1
    apt-get install -y php-common php8.3-common php8.3-cli php8.3-fpm \
        php8.3-curl php8.3-mbstring php8.3-mysql php8.3-xml php8.3-xsl \
        php8.3-zip php8.3-gd php8.3-opcache php8.3-apcu php8.3-intl \
        php8.3-ldap php8.3-readline php8.3-gearman composer >>$LOG 2>&1
fi

if [ "$SVC_MYSQL" = "on" ]; then
    echo 18; echo "XXX"; echo "Installing MySQL..."; echo "XXX"
    debconf-set-selections <<< "mysql-server mysql-server/root_password password $MYSQL_ROOT"
    debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $MYSQL_ROOT"
    apt-get install -y mysql-server >>$LOG 2>&1
    systemctl start mysql; systemctl enable mysql
    cat > /etc/mysql/conf.d/atom.cnf << 'MYCNF'
[mysqld]
sql_mode=ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
optimizer_switch='block_nested_loop=off'
MYCNF
    systemctl restart mysql
fi

echo 22; echo "XXX"; echo "Creating database..."; echo "XXX"
if [ -n "$MYSQL_ROOT" ]; then
    MCMD="mysql -u root -p$MYSQL_ROOT"
else
    MCMD="mysql -u root"
fi
$MCMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;" >>$LOG 2>&1
$MCMD -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" >>$LOG 2>&1
$MCMD -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" >>$LOG 2>&1

if [ "$SVC_ES" = "on" ]; then
    echo 28; echo "XXX"; echo "Installing Elasticsearch..."; echo "XXX"
    apt-get install -y openjdk-11-jre-headless >>$LOG 2>&1
    wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg 2>>$LOG
    echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/oss-7.x/apt stable main" > /etc/apt/sources.list.d/elastic-7.x.list
    apt-get update -qq >>$LOG 2>&1
    apt-get install -y elasticsearch-oss >>$LOG 2>&1
    cat > /etc/elasticsearch/elasticsearch.yml << ESCFG
cluster.name: atom
node.name: atom-node-1
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: 127.0.0.1
http.port: 9200
discovery.type: single-node
ESCFG
    mkdir -p /etc/elasticsearch/jvm.options.d
    echo "-Xms${ES_HEAP}" > /etc/elasticsearch/jvm.options.d/heap.options
    echo "-Xmx${ES_HEAP}" >> /etc/elasticsearch/jvm.options.d/heap.options
    systemctl daemon-reload
    systemctl enable elasticsearch
    systemctl start elasticsearch
    for i in {1..30}; do curl -s http://localhost:9200 &>/dev/null && break; sleep 2; done
fi

if [ "$SVC_GEARMAN" = "on" ]; then
    echo 40; echo "XXX"; echo "Installing Gearman..."; echo "XXX"
    apt-get install -y gearman-job-server php8.3-gearman >>$LOG 2>&1
    systemctl enable gearman-job-server
    systemctl start gearman-job-server
fi

[ "$SVC_MEMCACHED" = "on" ] && apt-get install -y memcached php-memcache >>$LOG 2>&1

if [ "$SVC_MEDIA" = "on" ]; then
    echo 44; echo "XXX"; echo "Installing media tools..."; echo "XXX"
    apt-get install -y imagemagick ghostscript poppler-utils ffmpeg >>$LOG 2>&1
fi

[ "$SVC_FOP" = "on" ] && apt-get install -y --no-install-recommends fop libsaxon-java >>$LOG 2>&1

echo 48; echo "XXX"; echo "Installing Node.js..."; echo "XXX"
apt-get install -y nodejs npm >>$LOG 2>&1

echo 52; echo "XXX"; echo "Downloading AtoM..."; echo "XXX"
rm -rf "$ATOM_PATH"
mkdir -p "$ATOM_PATH"
wget -q https://storage.accesstomemory.org/releases/atom-latest.tar.gz -O /tmp/atom-latest.tar.gz >>$LOG 2>&1
tar -xzf /tmp/atom-latest.tar.gz -C "$ATOM_PATH" --strip-components=1 >>$LOG 2>&1
rm -f /tmp/atom-latest.tar.gz

echo 58; echo "XXX"; echo "Installing dependencies..."; echo "XXX"
cd "$ATOM_PATH"
composer install --no-dev --no-interaction >>$LOG 2>&1

echo 62; echo "XXX"; echo "Building theme..."; echo "XXX"
npm install >>$LOG 2>&1
npm run build >>$LOG 2>&1 || true

echo 65; echo "XXX"; echo "Preparing directories..."; echo "XXX"
mkdir -p "$ATOM_PATH/cache" "$ATOM_PATH/log" "$ATOM_PATH/uploads" "$ATOM_PATH/downloads"
chown -R www-data:www-data "$ATOM_PATH"

echo 67; echo "XXX"; echo "Configuring PHP-FPM..."; echo "XXX"
cat > /etc/php/8.3/fpm/pool.d/atom.conf << 'PHPCFG'
[atom]
user = www-data
group = www-data
listen = /run/php-fpm.atom.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0600
pm = dynamic
pm.max_children = 30
pm.start_servers = 10
pm.min_spare_servers = 10
pm.max_spare_servers = 10
pm.max_requests = 200
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 120
php_admin_value[post_max_size] = 72M
php_admin_value[upload_max_filesize] = 64M
PHPCFG
rm -f /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart php8.3-fpm

echo 70; echo "XXX"; echo "Configuring Nginx..."; echo "XXX"
cat > /etc/nginx/sites-available/atom << NGXCFG
##
# Access to Memory (AtoM) + AHG Framework
##

upstream atom {
    server unix:/run/php-fpm.atom.sock;
}

server {
    listen 80;
    listen [::]:80;
    server_name _;

    client_max_body_size 2G;

    root $ATOM_PATH;
    index index.php index.html;

    access_log /var/log/nginx/atom_access.log;
    error_log  /var/log/nginx/atom_error.log;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Block exploit scanners
    location ~* (eval-stdin\.php|phpunit|pearcmd|thinkphp|invokefunction|\.env|\.git|shell|cmd) {
        return 444;
    }

    # RiC Explorer
    location ^~ /ric/ {
        alias $ATOM_PATH/web/ric/;
        index index.html;
        try_files \$uri \$uri/ =404;
    }

    location ^~ /api/ric/ {
        proxy_pass http://127.0.0.1:5001/api/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }

    location ^~ /api/provenance/ {
        proxy_pass http://127.0.0.1:5003/api/provenance/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
    }

    location ^~ /api/editor/ {
        proxy_pass http://127.0.0.1:5002/api/editor/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
    }

    location ^~ /sparql/ {
        proxy_pass http://127.0.0.1:3030/ric/;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        add_header Access-Control-Allow-Origin "*" always;
        add_header Access-Control-Allow-Methods "GET, POST, OPTIONS" always;
        add_header Access-Control-Allow-Headers "Content-Type" always;
    }

    location ^~ /ric-dashboard/ {
        alias $ATOM_PATH/web/ric-dashboard/;
        index index.php index.html;
        location ~ \.php\$ {
            fastcgi_pass atom;
            fastcgi_param SCRIPT_FILENAME \$request_filename;
            include fastcgi_params;
        }
    }

    location ^~ /ric-provenance/ {
        alias $ATOM_PATH/web/ric-provenance/;
        index index.html;
        try_files \$uri \$uri/ =404;
    }

    location ^~ /ric-editor/ {
        alias $ATOM_PATH/web/ric-editor/;
        index index.html;
        try_files \$uri \$uri/ =404;
    }

    # Media API Routes
    location ~ ^/media/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/Media/public/routes.php;
        fastcgi_pass atom;
    }

    location ~ ^/media/(metadata|extract|waveform)/([0-9]+)\$ {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
        add_header Access-Control-Allow-Origin * always;
    }

    location ~ ^/media/(transcription|transcribe)/([0-9]+)(.*)\$ {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
        fastcgi_read_timeout 600;
        add_header Access-Control-Allow-Origin * always;
    }

    location ~ ^/media/search {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
        add_header Access-Control-Allow-Origin * always;
    }

    # PHP handling
    location ~ ^/(index|qubit_dev)\.php(/|\$) {
        include fastcgi_params;
        fastcgi_split_path_info ^(.+?\.php)(/.*)\$;
        fastcgi_pass atom;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param PATH_TRANSLATED \$document_root\$fastcgi_path_info;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param SCRIPT_NAME \$fastcgi_script_name;
        fastcgi_index index.php;
        fastcgi_read_timeout 3600;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
    }

    # Digital object viewers
    location ~* /index\.php/.*/digitalobject/ {
        add_header Content-Security-Policy "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:;" always;
    }

    location /zoompan/ {
        add_header Content-Security-Policy "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:;" always;
        try_files \$uri \$uri/ /index.php\$args;
    }

    location = /3D-image.php {
        root $ATOM_PATH;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root/3D-image.php;
        fastcgi_pass atom;
        add_header Content-Security-Policy "default-src 'self' data: blob:; script-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline';" always;
    }

    location ^~ /archive/3d/ {
        alias $ATOM_PATH/3d/;
        try_files \$uri =404;
    }

    # Reports extension
    location ^~ /ext/reports/ {
        alias $ATOM_PATH/atom-extensions/extensions/reports/public/;
        index index.php index.html;
        location ~ \.php\$ {
            try_files \$uri =404;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$request_filename;
            fastcgi_param DOCUMENT_ROOT $ATOM_PATH/atom-extensions/extensions/reports/public;
            fastcgi_pass atom;
        }
    }

    # IIIF routes
    location ~ ^/iiif/manifest/(.+)\$ {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
        add_header Access-Control-Allow-Origin * always;
    }

    location ~ ^/iiif/collection/(.+)\$ {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
        add_header Access-Control-Allow-Origin * always;
    }

    location ~ ^/iiif/viewer/(.+)\$ {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
    }

    location ~ ^/iiif/embed/(.+)\$ {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
    }

    location ~ ^/iiif/annotations {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
        add_header Access-Control-Allow-Origin * always;
    }

    location ~ ^/iiif/ocr {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
    }

    location ~ ^/iiif/text {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
    }

    location ~ ^/iiif/search {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/router.php;
    }

    location /atom-framework/src/Extensions/IiifViewer/public/ {
        alias $ATOM_PATH/atom-framework/src/Extensions/IiifViewer/public/;
        expires 7d;
    }

    # IIIF Cantaloupe proxy
    location /iiif/ {
        proxy_pass http://127.0.0.1:8182/iiif/;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        add_header Access-Control-Allow-Origin "*" always;
    }

    location ^~ /archive/iiif/ {
        rewrite ^/archive/iiif/(.*)\$ /iiif/\$1 break;
        proxy_pass http://127.0.0.1:8182/iiif/;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        add_header Access-Control-Allow-Origin "*" always;
    }

    location = /iiif-manifest.php {
        fastcgi_pass atom;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        add_header Access-Control-Allow-Origin * always;
    }

    # Static assets
    location ~* ^/(css|dist|js|images|plugins|vendor|arDominionB5Plugin)/.*\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|pdf)\$ {
        root $ATOM_PATH;
        try_files \$uri \$uri/ =404;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # Block direct PHP access
    location ~ \.php\$ {
        deny all;
        return 404;
    }

    location ~ /\.(ht|git|svn) {
        deny all;
    }

    # Main router
    location / {
        try_files \$uri /index.php?\$args;
    }
}
NGXCFG
ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/atom
rm -f /etc/nginx/sites-enabled/default
nginx -t >>$LOG 2>&1 && systemctl reload nginx

echo 75; echo "XXX"; echo "Initializing AtoM..."; echo "XXX"
cd "$ATOM_PATH"
apt-get install -y expect >>$LOG 2>&1 || true

expect << EXPECT_EOF >>$LOG 2>&1 || true
set timeout 600
spawn sudo -u www-data php symfony tools:install
expect "Database host" { send "localhost\r" }
expect "Database port" { send "3306\r" }
expect "Database name" { send "$DB_NAME\r" }
expect "Database user" { send "$DB_USER\r" }
expect "Database password" { send "$DB_PASS\r" }
expect "Search host" { send "localhost\r" }
expect "Search port" { send "9200\r" }
expect "Search index" { send "atom\r" }
expect "Site title" { send "$SITE_TITLE\r" }
expect "Site description" { send "$SITE_DESC\r" }
expect "Site base URL" { send "$SITE_URL\r" }
expect "Admin email" { send "$ADMIN_EMAIL\r" }
expect "Admin username" { send "$ADMIN_USER\r" }
expect "Admin password" { send "$ADMIN_PASS\r" }
expect -re "\(y/N\)" { send "y\r" }
expect -re "\(y/N\)" { send "y\r" }
expect eof
EXPECT_EOF

#---------------------------------------------------------------------------
# AHG Extensions (only for complete mode)
#---------------------------------------------------------------------------
if [ "$INSTALL_MODE" = "complete" ]; then
    echo 82; echo "XXX"; echo "Installing AHG Framework..."; echo "XXX"
    cd "$ATOM_PATH"
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git atom-framework >>$LOG 2>&1
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git atom-ahg-plugins >>$LOG 2>&1
    
    echo 85; echo "XXX"; echo "Setting up framework..."; echo "XXX"
    cd "$ATOM_PATH/atom-framework"
    composer install --no-dev --no-interaction >>$LOG 2>&1
    
    echo 88; echo "XXX"; echo "Running framework install..."; echo "XXX"
    bash bin/install --auto >>$LOG 2>&1 || true
    
    echo 90; echo "XXX"; echo "Discovering plugins..."; echo "XXX"
    php bin/atom extension:discover >>$LOG 2>&1 || true
    
    echo 92; echo "XXX"; echo "Enabling selected plugins..."; echo "XXX"
    
    [ "$PLG_SECURITY" = "on" ] && php bin/atom extension:enable ahgSecurityClearancePlugin >>$LOG 2>&1 || true
    [ "$PLG_DISPLAY" = "on" ] && php bin/atom extension:enable ahgDisplayPlugin >>$LOG 2>&1 || true
    [ "$PLG_BACKUP" = "on" ] && php bin/atom extension:enable ahgBackupPlugin >>$LOG 2>&1 || true
    [ "$PLG_LIBRARY" = "on" ] && php bin/atom extension:enable ahgLibraryPlugin >>$LOG 2>&1 || true
    [ "$PLG_MUSEUM" = "on" ] && php bin/atom extension:enable ahgMuseumPlugin >>$LOG 2>&1 || true
    [ "$PLG_GALLERY" = "on" ] && php bin/atom extension:enable ahgGalleryPlugin >>$LOG 2>&1 || true
    [ "$PLG_DAM" = "on" ] && php bin/atom extension:enable ahgDAMPlugin >>$LOG 2>&1 || true
    [ "$PLG_SPECTRUM" = "on" ] && php bin/atom extension:enable ahgSpectrumPlugin >>$LOG 2>&1 || true
    [ "$PLG_RESEARCH" = "on" ] && php bin/atom extension:enable ahgResearchPlugin >>$LOG 2>&1 || true
    [ "$PLG_ACCESS" = "on" ] && php bin/atom extension:enable ahgAccessRequestPlugin >>$LOG 2>&1 || true
    [ "$PLG_DONOR" = "on" ] && php bin/atom extension:enable ahgDonorAgreementPlugin >>$LOG 2>&1 || true
    [ "$PLG_VENDOR" = "on" ] && php bin/atom extension:enable ahgVendorPlugin >>$LOG 2>&1 || true
    [ "$PLG_CONDITION" = "on" ] && php bin/atom extension:enable ahgConditionPlugin >>$LOG 2>&1 || true
    [ "$PLG_AUDIT" = "on" ] && php bin/atom extension:enable ahgAuditTrailPlugin >>$LOG 2>&1 || true
    [ "$PLG_PRIVACY" = "on" ] && php bin/atom extension:enable ahgPrivacyPlugin >>$LOG 2>&1 || true
    [ "$PLG_HERITAGE" = "on" ] && php bin/atom extension:enable ahgHeritageAccountingPlugin >>$LOG 2>&1 || true
    [ "$PLG_EXTRIGHTS" = "on" ] && php bin/atom extension:enable ahgExtendedRightsPlugin >>$LOG 2>&1 || true
    [ "$PLG_RIGHTS" = "on" ] && php bin/atom extension:enable ahgRightsPlugin >>$LOG 2>&1 || true
    [ "$PLG_NER" = "on" ] && php bin/atom extension:enable ahgNerPlugin >>$LOG 2>&1 || true
    [ "$PLG_3DMODEL" = "on" ] && php bin/atom extension:enable ahg3DModelPlugin >>$LOG 2>&1 || true
    [ "$PLG_IIIF" = "on" ] && php bin/atom extension:enable ahgIiifCollectionPlugin >>$LOG 2>&1 || true
    [ "$PLG_RIC" = "on" ] && php bin/atom extension:enable ahgRicExplorerPlugin >>$LOG 2>&1 || true
    [ "$PLG_DATAMIG" = "on" ] && php bin/atom extension:enable ahgDataMigrationPlugin >>$LOG 2>&1 || true
    [ "$PLG_MIGRATION" = "on" ] && php bin/atom extension:enable ahgMigrationPlugin >>$LOG 2>&1 || true
    [ "$PLG_API" = "on" ] && php bin/atom extension:enable ahgAPIPlugin >>$LOG 2>&1 || true
    
    echo 94; echo "XXX"; echo "Clearing cache..."; echo "XXX"
    cd "$ATOM_PATH"
    sudo -u www-data php symfony cc >>$LOG 2>&1 || true
fi

if [ "$SETUP_WORKER" = "on" ]; then
    echo 96; echo "XXX"; echo "Setting up worker..."; echo "XXX"
    cat > /usr/lib/systemd/system/atom-worker.service << WRKR
[Unit]
Description=AtoM worker
After=network.target
[Install]
WantedBy=multi-user.target
[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$ATOM_PATH
ExecStart=/usr/bin/php8.3 -d memory_limit=-1 symfony jobs:worker
Restart=on-failure
RestartSec=30
WRKR
    systemctl daemon-reload
    systemctl enable atom-worker
    systemctl start atom-worker
fi

echo 98; echo "XXX"; echo "Finalizing..."; echo "XXX"
chown -R www-data:www-data "$ATOM_PATH"
systemctl restart php8.3-fpm nginx

echo 100; echo "XXX"; echo "Complete!"; echo "XXX"
sleep 1

} | dialog --title "Installing AtoM" --gauge "Preparing..." 8 60 0

#===============================================================================
# Done
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Installation Complete!" --msgbox "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   AtoM + AHG Framework Installed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

URL: http://$SERVER_IP

Login:
  Email:    $ADMIN_EMAIL
  Password: (your password)

Path:    $ATOM_PATH
Plugins: $PLG_COUNT enabled
Log:     $LOG

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
" 22 55

clear
echo "========================================"
echo "  AtoM Installation Complete!"
echo "========================================"
echo "  URL:     http://$SERVER_IP"
echo "  Admin:   $ADMIN_EMAIL"
echo "  Plugins: $PLG_COUNT enabled"
echo "  Log:     $LOG"
echo ""
