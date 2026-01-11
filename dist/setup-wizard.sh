#!/bin/bash
#===============================================================================
# AtoM Setup Wizard v2.4
# Interactive installer following official Artefactual documentation
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

# Defaults
INSTALL_MODE="complete"
ATOM_PATH="/usr/share/nginx/atom"

# Services (all default on)
SVC_NGINX="on"
SVC_PHP="on"
SVC_MYSQL="on"
SVC_ES="on"
SVC_GEARMAN="on"
SVC_MEMCACHED="on"
SVC_MEDIA="on"
SVC_FOP="on"

# Database
MYSQL_ROOT=""
DB_NAME="atom"
DB_USER="atom"
DB_PASS=""

ES_HEAP="512m"

# Site
SITE_TITLE="My Archive"
SITE_DESC="Archival Management System"
SITE_URL=""
ADMIN_EMAIL=""
ADMIN_USER="admin"
ADMIN_PASS=""

# Options (all default on)
LOAD_DEMO="on"
SETUP_WORKER="on"

# AHG Plugins (all default on)
PLG_THEME="on"
PLG_SECURITY="on"
PLG_LIBRARY="on"
PLG_BACKUP="on"
PLG_AUDIT="on"
PLG_RESEARCH="on"
PLG_DISPLAY="on"
PLG_LANDING="on"
PLG_DONOR="on"
PLG_CONDITION="on"
PLG_ACCESS="on"
PLG_PROVENANCE="on"
PLG_GRAP="on"
PLG_PRIVACY="on"
PLG_POPIA="on"
PLG_GDPR="on"
PLG_NER="on"

LOG="/var/log/atom-install-$(date +%Y%m%d%H%M%S).log"

#===============================================================================
# System Requirements Check
#===============================================================================
check_requirements() {
    local cpu_cores=$(nproc)
    local total_ram=$(free -m | awk '/^Mem:/{print $2}')
    local free_disk=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')
    local os_version=$(lsb_release -rs 2>/dev/null || echo "unknown")
    local os_name=$(lsb_release -is 2>/dev/null || echo "unknown")
    
    local report="SYSTEM REQUIREMENTS CHECK\n"
    report+="━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
    
    [ "$cpu_cores" -ge 2 ] && report+="[OK]  CPU: $cpu_cores cores\n" || report+="[!!]  CPU: $cpu_cores cores (min: 2)\n"
    [ "$total_ram" -ge 7000 ] && report+="[OK]  RAM: ${total_ram}MB\n" || report+="[!!]  RAM: ${total_ram}MB (rec: 7GB)\n"
    [ "$free_disk" -ge 50 ] && report+="[OK]  Disk: ${free_disk}GB\n" || report+="[!!]  Disk: ${free_disk}GB (rec: 50GB)\n"
    report+="[--]  OS: $os_name $os_version\n"
    
    report+="\nINSTALLED SERVICES\n"
    report+="━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
    
    command -v nginx &>/dev/null && report+="[x] Nginx\n" && SVC_NGINX="off" || report+="[ ] Nginx\n"
    command -v php &>/dev/null && report+="[x] PHP $(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)\n" && SVC_PHP="off" || report+="[ ] PHP\n"
    command -v mysql &>/dev/null && report+="[x] MySQL\n" && SVC_MYSQL="off" || report+="[ ] MySQL\n"
    curl -s http://localhost:9200 &>/dev/null && report+="[x] Elasticsearch\n" && SVC_ES="off" || report+="[ ] Elasticsearch\n"
    command -v gearman &>/dev/null && report+="[x] Gearman\n" && SVC_GEARMAN="off" || report+="[ ] Gearman\n"
    
    dialog --title "System Check" --yes-label "Continue" --no-label "Cancel" --yesno "$report" 24 50
    return $?
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

  This wizard will install and configure AtoM
  with AHG Extensions (Bootstrap 5, GLAM support)

          Press OK to continue...
" 22 55

check_requirements || exit 1

#===============================================================================
# Step 1: Installation Mode
#===============================================================================
dialog --title "Step 1 of 10: Installation Mode" \
       --menu "\nSelect installation type:\n" 15 65 3 \
       "complete" "AtoM 2.10 + AHG Extensions (Recommended)" \
       "atom" "Base AtoM 2.10 only" \
       "extensions" "AHG Extensions only (existing AtoM)" \
       2>$TEMP || exit 1
INSTALL_MODE=$(<$TEMP)

#===============================================================================
# Step 2: Installation Path
#===============================================================================
dialog --title "Step 2 of 10: Installation Path" \
       --inputbox "\nWhere should AtoM be installed?\n" 10 60 "$ATOM_PATH" \
       2>$TEMP || exit 1
ATOM_PATH=$(<$TEMP)
[ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"

#===============================================================================
# Step 3: Services
#===============================================================================
dialog --title "Step 3 of 10: Services to Install" \
       --checklist "\nSelect services (Space=toggle):\n" 18 65 8 \
       "nginx" "Nginx Web Server" $SVC_NGINX \
       "php" "PHP 8.3 + Extensions" $SVC_PHP \
       "mysql" "MySQL 8.0 Database" $SVC_MYSQL \
       "elasticsearch" "Elasticsearch 7.10 (Search)" $SVC_ES \
       "gearman" "Gearman Job Server" $SVC_GEARMAN \
       "memcached" "Memcached (Cache)" $SVC_MEMCACHED \
       "media" "ImageMagick/FFmpeg/Ghostscript" $SVC_MEDIA \
       "fop" "Apache FOP (PDF Finding Aids)" $SVC_FOP \
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
# Step 4: MySQL Configuration
#===============================================================================
if [ "$SVC_MYSQL" = "on" ]; then
    dialog --title "Step 4 of 10: MySQL Root Password" \
           --insecure --passwordbox "\nSet MySQL root password (min 8 chars):\n" 10 55 \
           2>$TEMP || exit 1
    MYSQL_ROOT=$(<$TEMP)
    [ ${#MYSQL_ROOT} -lt 8 ] && MYSQL_ROOT="rootpass123"
fi

dialog --title "Step 4 of 10: AtoM Database" \
       --form "\nDatabase settings:\n" 12 55 2 \
       "Database Name:" 1 1 "$DB_NAME" 1 16 30 50 \
       "Database User:" 2 1 "$DB_USER" 2 16 30 50 \
       2>$TEMP || exit 1
DB_NAME=$(sed -n '1p' $TEMP); DB_USER=$(sed -n '2p' $TEMP)
[ -z "$DB_NAME" ] && DB_NAME="atom"
[ -z "$DB_USER" ] && DB_USER="atom"

dialog --title "Step 4 of 10: Database Password" \
       --insecure --passwordbox "\nPassword for '$DB_USER' (min 8 chars):\n" 10 55 \
       2>$TEMP || exit 1
DB_PASS=$(<$TEMP)
[ ${#DB_PASS} -lt 8 ] && DB_PASS="atompass123"

#===============================================================================
# Step 5: Elasticsearch
#===============================================================================
if [ "$SVC_ES" = "on" ]; then
    total_ram_mb=$(free -m | awk '/^Mem:/{print $2}')
    rec_heap=$((total_ram_mb / 4))
    [ $rec_heap -gt 1024 ] && rec_heap=1024
    [ $rec_heap -lt 256 ] && rec_heap=256
    ES_HEAP="${rec_heap}m"
    
    dialog --title "Step 5 of 10: Elasticsearch Heap" \
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

dialog --title "Step 6 of 10: Site Configuration" \
       --form "\nSite settings:\n" 14 60 3 \
       "Title:" 1 1 "$SITE_TITLE" 1 14 40 100 \
       "Description:" 2 1 "$SITE_DESC" 2 14 40 200 \
       "Base URL:" 3 1 "$SITE_URL" 3 14 40 200 \
       2>$TEMP || exit 1
SITE_TITLE=$(sed -n '1p' $TEMP); SITE_DESC=$(sed -n '2p' $TEMP); SITE_URL=$(sed -n '3p' $TEMP)
[ -z "$SITE_TITLE" ] && SITE_TITLE="My Archive"
[ -z "$SITE_DESC" ] && SITE_DESC="Archival Management System"
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

#===============================================================================
# Step 7: Admin Account
#===============================================================================
dialog --title "Step 7 of 10: Administrator" \
       --form "\nAdmin account:\n" 12 55 2 \
       "Email:" 1 1 "$ADMIN_EMAIL" 1 12 40 100 \
       "Username:" 2 1 "$ADMIN_USER" 2 12 30 50 \
       2>$TEMP || exit 1
ADMIN_EMAIL=$(sed -n '1p' $TEMP); ADMIN_USER=$(sed -n '2p' $TEMP)
[ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@example.com"
[ -z "$ADMIN_USER" ] && ADMIN_USER="admin"

dialog --title "Step 7 of 10: Admin Password" \
       --insecure --passwordbox "\nAdmin password (min 8 chars):\n" 10 55 \
       2>$TEMP || exit 1
ADMIN_PASS=$(<$TEMP)
[ ${#ADMIN_PASS} -lt 8 ] && ADMIN_PASS="admin12345"

#===============================================================================
# Step 8: AHG Plugins Selection
#===============================================================================
if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then
    dialog --title "Step 8 of 10: AHG Plugins - Core" \
           --checklist "\nSelect CORE plugins (Required marked *):\n" 18 70 6 \
           "theme" "* ahgThemeB5Plugin - Bootstrap 5 Theme (Required)" $PLG_THEME \
           "security" "* ahgSecurityClearancePlugin - Security (Required)" $PLG_SECURITY \
           "display" "  ahgDisplayPlugin - Enhanced Display" $PLG_DISPLAY \
           "backup" "  ahgBackupPlugin - Backup & Restore" $PLG_BACKUP \
           "audit" "  ahgAuditTrailPlugin - Audit Logging" $PLG_AUDIT \
           "landing" "  ahgLandingPageBuilderPlugin - Landing Pages" $PLG_LANDING \
           2>$TEMP || exit 1
    
    PLUGINS_CORE=$(<$TEMP)
    PLG_THEME="off"; PLG_SECURITY="off"; PLG_DISPLAY="off"
    PLG_BACKUP="off"; PLG_AUDIT="off"; PLG_LANDING="off"
    [[ "$PLUGINS_CORE" == *theme* ]] && PLG_THEME="on"
    [[ "$PLUGINS_CORE" == *security* ]] && PLG_SECURITY="on"
    [[ "$PLUGINS_CORE" == *display* ]] && PLG_DISPLAY="on"
    [[ "$PLUGINS_CORE" == *backup* ]] && PLG_BACKUP="on"
    [[ "$PLUGINS_CORE" == *audit* ]] && PLG_AUDIT="on"
    [[ "$PLUGINS_CORE" == *landing* ]] && PLG_LANDING="on"
    
    dialog --title "Step 8 of 10: AHG Plugins - GLAM" \
           --checklist "\nSelect GLAM sector plugins:\n" 16 70 5 \
           "library" "  ahgLibraryPlugin - Library Features" $PLG_LIBRARY \
           "research" "  ahgResearchPlugin - Researcher Portal" $PLG_RESEARCH \
           "donor" "  ahgDonorPlugin - Donor Management" $PLG_DONOR \
           "condition" "  ahgConditionPlugin - Condition Assessment" $PLG_CONDITION \
           "provenance" "  ahgProvenancePlugin - Provenance Tracking" $PLG_PROVENANCE \
           2>$TEMP || exit 1
    
    PLUGINS_GLAM=$(<$TEMP)
    PLG_LIBRARY="off"; PLG_RESEARCH="off"; PLG_DONOR="off"
    PLG_CONDITION="off"; PLG_PROVENANCE="off"
    [[ "$PLUGINS_GLAM" == *library* ]] && PLG_LIBRARY="on"
    [[ "$PLUGINS_GLAM" == *research* ]] && PLG_RESEARCH="on"
    [[ "$PLUGINS_GLAM" == *donor* ]] && PLG_DONOR="on"
    [[ "$PLUGINS_GLAM" == *condition* ]] && PLG_CONDITION="on"
    [[ "$PLUGINS_GLAM" == *provenance* ]] && PLG_PROVENANCE="on"
    
    dialog --title "Step 8 of 10: AHG Plugins - Compliance" \
           --checklist "\nSelect compliance/regulatory plugins:\n" 16 70 6 \
           "grap" "  ahgGrapPlugin - GRAP 103 (SA Accounting)" $PLG_GRAP \
           "privacy" "  ahgPrivacyPlugin - Privacy Base" $PLG_PRIVACY \
           "popia" "  ahgPOPIAPlugin - POPIA (South Africa)" $PLG_POPIA \
           "gdpr" "  ahgGDPRPlugin - GDPR (Europe)" $PLG_GDPR \
           "access" "  ahgAccessRequestPlugin - Access Requests" $PLG_ACCESS \
           "ner" "  ahgNerPlugin - Named Entity Recognition" $PLG_NER \
           2>$TEMP || exit 1
    
    PLUGINS_COMP=$(<$TEMP)
    PLG_GRAP="off"; PLG_PRIVACY="off"; PLG_POPIA="off"
    PLG_GDPR="off"; PLG_ACCESS="off"; PLG_NER="off"
    [[ "$PLUGINS_COMP" == *grap* ]] && PLG_GRAP="on"
    [[ "$PLUGINS_COMP" == *privacy* ]] && PLG_PRIVACY="on"
    [[ "$PLUGINS_COMP" == *popia* ]] && PLG_POPIA="on"
    [[ "$PLUGINS_COMP" == *gdpr* ]] && PLG_GDPR="on"
    [[ "$PLUGINS_COMP" == *access* ]] && PLG_ACCESS="on"
    [[ "$PLUGINS_COMP" == *ner* ]] && PLG_NER="on"
fi

#===============================================================================
# Step 9: Options
#===============================================================================
dialog --title "Step 9 of 10: Additional Options" \
       --checklist "\nSelect options:\n" 12 55 2 \
       "demo" "Load demo/sample data" $LOAD_DEMO \
       "worker" "Setup atom-worker service" $SETUP_WORKER \
       2>$TEMP || exit 1

OPTIONS=$(<$TEMP)
LOAD_DEMO="off"; SETUP_WORKER="off"
[[ "$OPTIONS" == *demo* ]] && LOAD_DEMO="on"
[[ "$OPTIONS" == *worker* ]] && SETUP_WORKER="on"

#===============================================================================
# Step 10: Confirmation
#===============================================================================
# Build plugin list
PLUGIN_LIST=""
[ "$PLG_THEME" = "on" ] && PLUGIN_LIST+="Theme, "
[ "$PLG_SECURITY" = "on" ] && PLUGIN_LIST+="Security, "
[ "$PLG_DISPLAY" = "on" ] && PLUGIN_LIST+="Display, "
[ "$PLG_BACKUP" = "on" ] && PLUGIN_LIST+="Backup, "
[ "$PLG_AUDIT" = "on" ] && PLUGIN_LIST+="Audit, "
[ "$PLG_LANDING" = "on" ] && PLUGIN_LIST+="Landing, "
[ "$PLG_LIBRARY" = "on" ] && PLUGIN_LIST+="Library, "
[ "$PLG_RESEARCH" = "on" ] && PLUGIN_LIST+="Research, "
[ "$PLG_DONOR" = "on" ] && PLUGIN_LIST+="Donor, "
[ "$PLG_CONDITION" = "on" ] && PLUGIN_LIST+="Condition, "
[ "$PLG_PROVENANCE" = "on" ] && PLUGIN_LIST+="Provenance, "
[ "$PLG_GRAP" = "on" ] && PLUGIN_LIST+="GRAP, "
[ "$PLG_PRIVACY" = "on" ] && PLUGIN_LIST+="Privacy, "
[ "$PLG_POPIA" = "on" ] && PLUGIN_LIST+="POPIA, "
[ "$PLG_GDPR" = "on" ] && PLUGIN_LIST+="GDPR, "
[ "$PLG_ACCESS" = "on" ] && PLUGIN_LIST+="Access, "
[ "$PLG_NER" = "on" ] && PLUGIN_LIST+="NER, "
PLUGIN_LIST="${PLUGIN_LIST%, }"
[ -z "$PLUGIN_LIST" ] && PLUGIN_LIST="None"

SVC_LIST=""
[ "$SVC_NGINX" = "on" ] && SVC_LIST+="Nginx, "
[ "$SVC_PHP" = "on" ] && SVC_LIST+="PHP, "
[ "$SVC_MYSQL" = "on" ] && SVC_LIST+="MySQL, "
[ "$SVC_ES" = "on" ] && SVC_LIST+="ES, "
[ "$SVC_GEARMAN" = "on" ] && SVC_LIST+="Gearman, "
[ "$SVC_MEMCACHED" = "on" ] && SVC_LIST+="Memcached, "
[ "$SVC_MEDIA" = "on" ] && SVC_LIST+="Media, "
[ "$SVC_FOP" = "on" ] && SVC_LIST+="FOP, "
SVC_LIST="${SVC_LIST%, }"
[ -z "$SVC_LIST" ] && SVC_LIST="None (existing)"

dialog --title "Step 10 of 10: Confirm Installation" --yesno "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
              INSTALLATION SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Mode:      $INSTALL_MODE
Path:      $ATOM_PATH

Services:  $SVC_LIST

Database:  $DB_NAME (user: $DB_USER)

Site:      $SITE_TITLE
URL:       $SITE_URL
Admin:     $ADMIN_EMAIL

Plugins:   $PLUGIN_LIST

Demo:      $LOAD_DEMO
Worker:    $SETUP_WORKER

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Proceed with installation?
" 30 60 || exit 1

#===============================================================================
# INSTALLATION
#===============================================================================
{
echo 2
echo "XXX"; echo "Updating system packages..."; echo "XXX"
apt-get update -qq >>$LOG 2>&1
apt-get install -y software-properties-common curl wget gnupg git >>$LOG 2>&1

#---------------------------------------------------------------------------
# Services Installation
#---------------------------------------------------------------------------
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
    echo 18; echo "XXX"; echo "Installing MySQL 8.0..."; echo "XXX"
    debconf-set-selections <<< "mysql-server mysql-server/root_password password $MYSQL_ROOT"
    debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $MYSQL_ROOT"
    apt-get install -y mysql-server >>$LOG 2>&1
    systemctl start mysql; systemctl enable mysql
    
    cat > /etc/mysql/conf.d/atom.cnf << 'MYSQLCNF'
[mysqld]
sql_mode=ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
optimizer_switch='block_nested_loop=off'
MYSQLCNF
    systemctl restart mysql
fi

echo 22; echo "XXX"; echo "Creating database..."; echo "XXX"
[ -n "$MYSQL_ROOT" ] && MCMD="mysql -u root -p$MYSQL_ROOT" || MCMD="mysql -u root"
$MCMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;" >>$LOG 2>&1
$MCMD -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" >>$LOG 2>&1
$MCMD -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" >>$LOG 2>&1

if [ "$SVC_ES" = "on" ]; then
    echo 28; echo "XXX"; echo "Installing Java..."; echo "XXX"
    apt-get install -y openjdk-11-jre-headless >>$LOG 2>&1
    
    echo 32; echo "XXX"; echo "Installing Elasticsearch 7.10..."; echo "XXX"
    wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg 2>>$LOG
    echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/oss-7.x/apt stable main" > /etc/apt/sources.list.d/elastic-7.x.list
    apt-get update -qq >>$LOG 2>&1
    apt-get install -y elasticsearch-oss >>$LOG 2>&1
    
    cat > /etc/elasticsearch/elasticsearch.yml << ESYML
cluster.name: atom
node.name: atom-node-1
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: 127.0.0.1
http.port: 9200
discovery.type: single-node
ESYML
    
    mkdir -p /etc/elasticsearch/jvm.options.d
    echo "-Xms${ES_HEAP}" > /etc/elasticsearch/jvm.options.d/heap.options
    echo "-Xmx${ES_HEAP}" >> /etc/elasticsearch/jvm.options.d/heap.options
    
    systemctl daemon-reload; systemctl enable elasticsearch; systemctl start elasticsearch
    
    echo 38; echo "XXX"; echo "Waiting for Elasticsearch..."; echo "XXX"
    for i in {1..30}; do curl -s http://localhost:9200 &>/dev/null && break; sleep 2; done
fi

[ "$SVC_GEARMAN" = "on" ] && { echo 42; echo "XXX"; echo "Installing Gearman..."; echo "XXX"; apt-get install -y gearman-job-server php8.3-gearman >>$LOG 2>&1; systemctl enable gearman-job-server; systemctl start gearman-job-server; }
[ "$SVC_MEMCACHED" = "on" ] && { echo 44; echo "XXX"; echo "Installing Memcached..."; echo "XXX"; apt-get install -y memcached php-memcache >>$LOG 2>&1; }
[ "$SVC_MEDIA" = "on" ] && { echo 46; echo "XXX"; echo "Installing media tools..."; echo "XXX"; apt-get install -y imagemagick ghostscript poppler-utils ffmpeg >>$LOG 2>&1; }
[ "$SVC_FOP" = "on" ] && { echo 48; echo "XXX"; echo "Installing Apache FOP..."; echo "XXX"; apt-get install -y --no-install-recommends fop libsaxon-java >>$LOG 2>&1; }

echo 50; echo "XXX"; echo "Installing Node.js..."; echo "XXX"
apt-get install -y nodejs npm >>$LOG 2>&1

#---------------------------------------------------------------------------
# Download AtoM
#---------------------------------------------------------------------------
if [ "$INSTALL_MODE" != "extensions" ]; then
    echo 52; echo "XXX"; echo "Downloading AtoM..."; echo "XXX"
    rm -rf "$ATOM_PATH"
    git clone -b stable/2.10.x --depth 1 https://github.com/artefactual/atom.git "$ATOM_PATH" >>$LOG 2>&1
    
    echo 58; echo "XXX"; echo "Installing Composer dependencies..."; echo "XXX"
    cd "$ATOM_PATH"
    composer install --no-dev --no-interaction >>$LOG 2>&1
    
    echo 62; echo "XXX"; echo "Building theme..."; echo "XXX"
    npm install >>$LOG 2>&1
    npm run build >>$LOG 2>&1 || true
fi

#---------------------------------------------------------------------------
# Prepare directories
#---------------------------------------------------------------------------
echo 65; echo "XXX"; echo "Preparing directories..."; echo "XXX"
mkdir -p "$ATOM_PATH/cache" "$ATOM_PATH/log" "$ATOM_PATH/uploads" "$ATOM_PATH/downloads"
chown -R www-data:www-data "$ATOM_PATH"

#---------------------------------------------------------------------------
# PHP-FPM
#---------------------------------------------------------------------------
echo 67; echo "XXX"; echo "Configuring PHP-FPM..."; echo "XXX"
cat > /etc/php/8.3/fpm/pool.d/atom.conf << 'PHPFPM'
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
chdir = /
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 120
php_admin_value[post_max_size] = 72M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[opcache.enable] = 1
php_admin_value[apc.enabled] = 1
PHPFPM
rm -f /etc/php/8.3/fpm/pool.d/www.conf 2>/dev/null || true
systemctl restart php8.3-fpm

#---------------------------------------------------------------------------
# Nginx
#---------------------------------------------------------------------------
echo 70; echo "XXX"; echo "Configuring Nginx..."; echo "XXX"
cat > /etc/nginx/sites-available/atom << NGXCFG
upstream atom {
    server unix:/run/php-fpm.atom.sock;
}
server {
    listen 80;
    server_name _;
    root $ATOM_PATH;
    client_max_body_size 72M;
    
    location / {
        try_files \$uri /index.php?\$args;
    }
    location ~ ^/(index|qubit_dev)\.php(/|\$) {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_pass atom;
    }
    location ~* \.(js|css|png|jpg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
    }
}
NGXCFG
ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/atom
rm -f /etc/nginx/sites-enabled/default
nginx -t >>$LOG 2>&1 && systemctl reload nginx

#---------------------------------------------------------------------------
# Initialize AtoM
#---------------------------------------------------------------------------
echo 75; echo "XXX"; echo "Initializing AtoM database..."; echo "XXX"
cd "$ATOM_PATH"
apt-get install -y expect >>$LOG 2>&1 || true

expect << EXPECT_SCRIPT >>$LOG 2>&1 || true
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

# First y/N - Confirm configuration
expect -re "\(y/N\)" { send "y\r" }

# Second y/N - Database warning
expect -re "\(y/N\)" { send "y\r" }

expect eof
EXPECT_SCRIPT

#---------------------------------------------------------------------------
# AHG Extensions
#---------------------------------------------------------------------------
if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then
    echo 82; echo "XXX"; echo "Installing AHG Framework..."; echo "XXX"
    cd "$ATOM_PATH"
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git atom-framework >>$LOG 2>&1
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git atom-ahg-plugins >>$LOG 2>&1
    
    echo 85; echo "XXX"; echo "Setting up AHG Framework..."; echo "XXX"
    cd "$ATOM_PATH/atom-framework"
    composer install --no-dev --no-interaction >>$LOG 2>&1
    bash bin/install --auto >>$LOG 2>&1 || true
    
    echo 88; echo "XXX"; echo "Creating plugin symlinks..."; echo "XXX"
    cd "$ATOM_PATH"
    
    # Symlink selected plugins
    [ "$PLG_THEME" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgThemeB5Plugin" "$ATOM_PATH/plugins/ahgThemeB5Plugin"
    [ "$PLG_SECURITY" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgSecurityClearancePlugin" "$ATOM_PATH/plugins/ahgSecurityClearancePlugin"
    [ "$PLG_DISPLAY" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgDisplayPlugin" "$ATOM_PATH/plugins/ahgDisplayPlugin"
    [ "$PLG_BACKUP" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgBackupPlugin" "$ATOM_PATH/plugins/ahgBackupPlugin"
    [ "$PLG_AUDIT" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgAuditTrailPlugin" "$ATOM_PATH/plugins/ahgAuditTrailPlugin"
    [ "$PLG_LANDING" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgLandingPageBuilderPlugin" "$ATOM_PATH/plugins/ahgLandingPageBuilderPlugin"
    [ "$PLG_LIBRARY" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgLibraryPlugin" "$ATOM_PATH/plugins/ahgLibraryPlugin"
    [ "$PLG_RESEARCH" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgResearchPlugin" "$ATOM_PATH/plugins/ahgResearchPlugin"
    [ "$PLG_DONOR" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgDonorPlugin" "$ATOM_PATH/plugins/ahgDonorPlugin"
    [ "$PLG_CONDITION" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgConditionPlugin" "$ATOM_PATH/plugins/ahgConditionPlugin"
    [ "$PLG_PROVENANCE" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgProvenancePlugin" "$ATOM_PATH/plugins/ahgProvenancePlugin"
    [ "$PLG_GRAP" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgGrapPlugin" "$ATOM_PATH/plugins/ahgGrapPlugin"
    [ "$PLG_PRIVACY" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgPrivacyPlugin" "$ATOM_PATH/plugins/ahgPrivacyPlugin"
    [ "$PLG_POPIA" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgPOPIAPlugin" "$ATOM_PATH/plugins/ahgPOPIAPlugin"
    [ "$PLG_GDPR" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgGDPRPlugin" "$ATOM_PATH/plugins/ahgGDPRPlugin"
    [ "$PLG_ACCESS" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgAccessRequestPlugin" "$ATOM_PATH/plugins/ahgAccessRequestPlugin"
    [ "$PLG_NER" = "on" ] && ln -sf "$ATOM_PATH/atom-ahg-plugins/ahgNerPlugin" "$ATOM_PATH/plugins/ahgNerPlugin"
    
    echo 90; echo "XXX"; echo "Enabling plugins..."; echo "XXX"
    cd "$ATOM_PATH/atom-framework"
    php bin/atom extension:discover >>$LOG 2>&1 || true
    
    [ "$PLG_THEME" = "on" ] && php bin/atom extension:enable ahgThemeB5Plugin >>$LOG 2>&1 || true
    [ "$PLG_SECURITY" = "on" ] && php bin/atom extension:enable ahgSecurityClearancePlugin >>$LOG 2>&1 || true
    [ "$PLG_DISPLAY" = "on" ] && php bin/atom extension:enable ahgDisplayPlugin >>$LOG 2>&1 || true
    [ "$PLG_BACKUP" = "on" ] && php bin/atom extension:enable ahgBackupPlugin >>$LOG 2>&1 || true
    [ "$PLG_AUDIT" = "on" ] && php bin/atom extension:enable ahgAuditTrailPlugin >>$LOG 2>&1 || true
    [ "$PLG_LANDING" = "on" ] && php bin/atom extension:enable ahgLandingPageBuilderPlugin >>$LOG 2>&1 || true
    [ "$PLG_LIBRARY" = "on" ] && php bin/atom extension:enable ahgLibraryPlugin >>$LOG 2>&1 || true
    [ "$PLG_RESEARCH" = "on" ] && php bin/atom extension:enable ahgResearchPlugin >>$LOG 2>&1 || true
    [ "$PLG_DONOR" = "on" ] && php bin/atom extension:enable ahgDonorPlugin >>$LOG 2>&1 || true
    [ "$PLG_CONDITION" = "on" ] && php bin/atom extension:enable ahgConditionPlugin >>$LOG 2>&1 || true
    [ "$PLG_PROVENANCE" = "on" ] && php bin/atom extension:enable ahgProvenancePlugin >>$LOG 2>&1 || true
    [ "$PLG_GRAP" = "on" ] && php bin/atom extension:enable ahgGrapPlugin >>$LOG 2>&1 || true
    [ "$PLG_PRIVACY" = "on" ] && php bin/atom extension:enable ahgPrivacyPlugin >>$LOG 2>&1 || true
    [ "$PLG_POPIA" = "on" ] && php bin/atom extension:enable ahgPOPIAPlugin >>$LOG 2>&1 || true
    [ "$PLG_GDPR" = "on" ] && php bin/atom extension:enable ahgGDPRPlugin >>$LOG 2>&1 || true
    [ "$PLG_ACCESS" = "on" ] && php bin/atom extension:enable ahgAccessRequestPlugin >>$LOG 2>&1 || true
    [ "$PLG_NER" = "on" ] && php bin/atom extension:enable ahgNerPlugin >>$LOG 2>&1 || true
    
    # Clear cache
    cd "$ATOM_PATH"
    sudo -u www-data php symfony cc >>$LOG 2>&1 || true
    
    chown -R www-data:www-data "$ATOM_PATH"
fi

#---------------------------------------------------------------------------
# Worker Service
#---------------------------------------------------------------------------
if [ "$SETUP_WORKER" = "on" ]; then
    echo 94; echo "XXX"; echo "Setting up worker service..."; echo "XXX"
    cat > /usr/lib/systemd/system/atom-worker.service << WORKER
[Unit]
Description=AtoM worker
After=network.target
StartLimitIntervalSec=24h
StartLimitBurst=3

[Install]
WantedBy=multi-user.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$ATOM_PATH
ExecStart=/usr/bin/php8.3 -d memory_limit=-1 -d error_reporting="E_ALL" symfony jobs:worker
KillSignal=SIGTERM
Restart=on-failure
RestartSec=30
WORKER
    systemctl daemon-reload
    systemctl enable atom-worker
    systemctl start atom-worker
fi

#---------------------------------------------------------------------------
# Final
#---------------------------------------------------------------------------
echo 98; echo "XXX"; echo "Final cleanup..."; echo "XXX"
chown -R www-data:www-data "$ATOM_PATH"
systemctl restart php8.3-fpm nginx

echo 100; echo "XXX"; echo "Complete!"; echo "XXX"
sleep 1

} | dialog --title "Installing AtoM" --gauge "Preparing..." 8 60 0

#===============================================================================
# Complete
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Installation Complete!" --msgbox "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
      AtoM + AHG Framework Installed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Access at: http://$SERVER_IP

Login:
  Email:    $ADMIN_EMAIL
  Password: (your password)

Path:     $ATOM_PATH
Log:      $LOG

Plugins:  $PLUGIN_LIST

Commands:
  php symfony cc
  php symfony search:populate
  systemctl status atom-worker

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
" 28 58

clear
echo ""
echo "========================================"
echo "  AtoM Installation Complete!"
echo "========================================"
echo "  URL:     http://$SERVER_IP"
echo "  Admin:   $ADMIN_EMAIL"
echo "  Path:    $ATOM_PATH"
echo "  Log:     $LOG"
echo ""
echo "  Plugins: $PLUGIN_LIST"
echo ""
