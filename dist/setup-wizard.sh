#!/bin/bash
#===============================================================================
# AtoM Setup Wizard v2.3
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

LOAD_DEMO="off"
SETUP_WORKER="on"

LOG="/var/log/atom-install-$(date +%Y%m%d%H%M%S).log"

#===============================================================================
# System Requirements Check
#===============================================================================
check_requirements() {
    local errors=0
    local warnings=0
    local report=""
    
    local cpu_cores=$(nproc)
    local total_ram=$(free -m | awk '/^Mem:/{print $2}')
    local free_disk=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')
    local os_version=$(lsb_release -rs 2>/dev/null || echo "unknown")
    local os_name=$(lsb_release -is 2>/dev/null || echo "unknown")
    
    report="SYSTEM REQUIREMENTS CHECK\n"
    report+="━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
    
    if [ "$cpu_cores" -ge 2 ]; then
        report+="[OK]  CPU Cores: $cpu_cores (minimum: 2)\n"
    else
        report+="[!!]  CPU Cores: $cpu_cores (minimum: 2)\n"
        warnings=$((warnings + 1))
    fi
    
    if [ "$total_ram" -ge 7000 ]; then
        report+="[OK]  RAM: ${total_ram}MB (recommended: 7GB)\n"
    elif [ "$total_ram" -ge 4000 ]; then
        report+="[!!]  RAM: ${total_ram}MB (recommended: 7GB)\n"
        warnings=$((warnings + 1))
    else
        report+="[XX]  RAM: ${total_ram}MB (minimum: 4GB)\n"
        errors=$((errors + 1))
    fi
    
    if [ "$free_disk" -ge 50 ]; then
        report+="[OK]  Free Disk: ${free_disk}GB (minimum: 50GB)\n"
    elif [ "$free_disk" -ge 20 ]; then
        report+="[!!]  Free Disk: ${free_disk}GB (recommended: 50GB)\n"
        warnings=$((warnings + 1))
    else
        report+="[XX]  Free Disk: ${free_disk}GB (minimum: 20GB)\n"
        errors=$((errors + 1))
    fi
    
    report+="\n"
    if [ "$os_name" = "Ubuntu" ]; then
        if [[ "$os_version" == "22.04" ]] || [[ "$os_version" == "24.04" ]]; then
            report+="[OK]  OS: $os_name $os_version (supported)\n"
        else
            report+="[!!]  OS: $os_name $os_version (recommended: 22.04/24.04)\n"
            warnings=$((warnings + 1))
        fi
    else
        report+="[!!]  OS: $os_name $os_version (recommended: Ubuntu)\n"
        warnings=$((warnings + 1))
    fi
    
    report+="\n"
    report+="EXISTING SERVICES\n"
    report+="━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
    
    if command -v nginx &>/dev/null; then
        report+="[--]  Nginx: Installed\n"
        SVC_NGINX="off"
    else
        report+="[  ]  Nginx: Not installed\n"
    fi
    
    if command -v php &>/dev/null; then
        local php_ver=$(php -v | head -1 | cut -d' ' -f2 | cut -d'.' -f1,2)
        report+="[--]  PHP: $php_ver installed\n"
        SVC_PHP="off"
    else
        report+="[  ]  PHP: Not installed\n"
    fi
    
    if command -v mysql &>/dev/null; then
        local mysql_ver=$(mysql --version | grep -oP '\d+\.\d+\.\d+' | head -1)
        report+="[--]  MySQL: $mysql_ver installed\n"
        SVC_MYSQL="off"
    else
        report+="[  ]  MySQL: Not installed\n"
    fi
    
    if curl -s http://localhost:9200 &>/dev/null; then
        local es_ver=$(curl -s http://localhost:9200 | grep -oP '"number"\s*:\s*"\K[^"]+' || echo "unknown")
        report+="[--]  Elasticsearch: $es_ver installed\n"
        SVC_ES="off"
    else
        report+="[  ]  Elasticsearch: Not installed\n"
    fi
    
    if command -v gearman &>/dev/null || command -v gearmand &>/dev/null; then
        report+="[--]  Gearman: Installed\n"
        SVC_GEARMAN="off"
    else
        report+="[  ]  Gearman: Not installed\n"
    fi
    
    report+="\n"
    report+="━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
    
    if [ $errors -gt 0 ]; then
        report+="ERRORS: $errors  WARNINGS: $warnings\n\n"
        report+="System does not meet minimum requirements."
    elif [ $warnings -gt 0 ]; then
        report+="WARNINGS: $warnings\n\n"
        report+="System meets minimum but not recommended specs."
    else
        report+="All checks passed!\n"
    fi
    
    dialog --title "System Requirements Check" \
           --yes-label "Continue" \
           --no-label "Cancel" \
           --yesno "$report" 30 55
    
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
               Version 2.10.0

  This wizard will install and configure AtoM
  following official Artefactual documentation.

  Requirements:
  • Ubuntu 22.04 or 24.04 LTS
  • 2+ CPU cores, 7GB+ RAM, 50GB+ disk
  • Root/sudo access

          Press OK to continue...
" 24 55

#===============================================================================
# System Check
#===============================================================================
check_requirements || exit 1

#===============================================================================
# Step 1: Installation Mode
#===============================================================================
dialog --title "Step 1 of 9: Installation Mode" \
       --menu "\nSelect installation type:\n" 15 65 3 \
       "complete" "AtoM 2.10 + AHG Extensions (Bootstrap 5, GLAM)" \
       "atom" "Base AtoM 2.10 only (standard install)" \
       "extensions" "AHG Extensions only (existing AtoM required)" \
       2>$TEMP || exit 1
INSTALL_MODE=$(<$TEMP)

#===============================================================================
# Step 2: Installation Path
#===============================================================================
dialog --title "Step 2 of 9: Installation Path" \
       --inputbox "\nWhere should AtoM be installed?\n\nRecommended: /usr/share/nginx/atom\n" 12 60 "$ATOM_PATH" \
       2>$TEMP || exit 1
ATOM_PATH=$(<$TEMP)
[ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"

#===============================================================================
# Step 3: Services
#===============================================================================
dialog --title "Step 3 of 9: Services to Install" \
       --checklist "\nSelect services (Space=toggle, Enter=confirm):\n\nPre-installed services are unchecked.\n" 20 70 8 \
       "nginx" "Nginx Web Server" $SVC_NGINX \
       "php" "PHP 8.3 + Required Extensions" $SVC_PHP \
       "mysql" "MySQL 8.0 Database Server" $SVC_MYSQL \
       "elasticsearch" "Elasticsearch 7.10 (OSS - Search)" $SVC_ES \
       "gearman" "Gearman Job Server (Required)" $SVC_GEARMAN \
       "memcached" "Memcached (Optional Cache)" $SVC_MEMCACHED \
       "media" "ImageMagick/Ghostscript/FFmpeg/Poppler" $SVC_MEDIA \
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
    dialog --title "Step 4 of 9: MySQL Root Password" \
           --insecure --passwordbox "\nSet MySQL root password:\n\n(Minimum 8 characters)\n" 12 55 \
           2>$TEMP || exit 1
    MYSQL_ROOT=$(<$TEMP)
    [ ${#MYSQL_ROOT} -lt 8 ] && MYSQL_ROOT="rootpass123"
fi

dialog --title "Step 4 of 9: AtoM Database" \
       --form "\nDatabase settings for AtoM:\n" 14 60 3 \
       "Database Name:" 1 1 "$DB_NAME" 1 18 30 50 \
       "Database User:" 2 1 "$DB_USER" 2 18 30 50 \
       2>$TEMP || exit 1

DB_NAME=$(sed -n '1p' $TEMP)
DB_USER=$(sed -n '2p' $TEMP)
[ -z "$DB_NAME" ] && DB_NAME="atom"
[ -z "$DB_USER" ] && DB_USER="atom"

dialog --title "Step 4 of 9: Database User Password" \
       --insecure --passwordbox "\nSet password for database user '$DB_USER':\n\n(Minimum 8 characters)\n" 12 55 \
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
    
    dialog --title "Step 5 of 9: Elasticsearch" \
           --inputbox "\nElasticsearch heap size:\n\nRecommended for your system: ${ES_HEAP}\n(25% of RAM, adjust based on usage)\n" 14 55 "$ES_HEAP" \
           2>$TEMP || exit 1
    ES_HEAP=$(<$TEMP)
    [ -z "$ES_HEAP" ] && ES_HEAP="512m"
fi

#===============================================================================
# Step 6: Site Settings
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

dialog --title "Step 6 of 9: Site Configuration" \
       --form "\nConfigure your AtoM site:\n" 16 65 4 \
       "Site Title:" 1 1 "$SITE_TITLE" 1 18 40 100 \
       "Description:" 2 1 "$SITE_DESC" 2 18 40 200 \
       "Base URL:" 3 1 "$SITE_URL" 3 18 40 200 \
       2>$TEMP || exit 1

SITE_TITLE=$(sed -n '1p' $TEMP)
SITE_DESC=$(sed -n '2p' $TEMP)
SITE_URL=$(sed -n '3p' $TEMP)
[ -z "$SITE_TITLE" ] && SITE_TITLE="My Archive"
[ -z "$SITE_DESC" ] && SITE_DESC="Archival Management System"
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

#===============================================================================
# Step 7: Admin Account
#===============================================================================
dialog --title "Step 7 of 9: Administrator Account" \
       --form "\nCreate administrator account:\n" 14 60 2 \
       "Admin Email:" 1 1 "$ADMIN_EMAIL" 1 15 40 100 \
       "Admin Username:" 2 1 "$ADMIN_USER" 2 15 30 50 \
       2>$TEMP || exit 1

ADMIN_EMAIL=$(sed -n '1p' $TEMP)
ADMIN_USER=$(sed -n '2p' $TEMP)
[ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@example.com"
[ -z "$ADMIN_USER" ] && ADMIN_USER="admin"

dialog --title "Step 7 of 9: Admin Password" \
       --insecure --passwordbox "\nSet administrator password:\n\n(Minimum 8 characters)\n" 12 55 \
       2>$TEMP || exit 1
ADMIN_PASS=$(<$TEMP)
[ ${#ADMIN_PASS} -lt 8 ] && ADMIN_PASS="admin12345"

#===============================================================================
# Step 8: Options
#===============================================================================
dialog --title "Step 8 of 9: Additional Options" \
       --checklist "\nSelect options:\n" 14 60 3 \
       "demo" "Load demo/sample data" off \
       "worker" "Setup atom-worker systemd service" on \
       "secure" "Run mysql_secure_installation" on \
       2>$TEMP || exit 1

OPTIONS=$(<$TEMP)
[[ "$OPTIONS" == *demo* ]] && LOAD_DEMO="on"
[[ "$OPTIONS" == *worker* ]] && SETUP_WORKER="on" || SETUP_WORKER="off"
[[ "$OPTIONS" == *secure* ]] && SECURE_MYSQL="on" || SECURE_MYSQL="off"

#===============================================================================
# Step 9: Confirmation
#===============================================================================
SVC_LIST=""
[ "$SVC_NGINX" = "on" ] && SVC_LIST+="Nginx, "
[ "$SVC_PHP" = "on" ] && SVC_LIST+="PHP 8.3, "
[ "$SVC_MYSQL" = "on" ] && SVC_LIST+="MySQL 8.0, "
[ "$SVC_ES" = "on" ] && SVC_LIST+="Elasticsearch 7.10, "
[ "$SVC_GEARMAN" = "on" ] && SVC_LIST+="Gearman, "
[ "$SVC_MEMCACHED" = "on" ] && SVC_LIST+="Memcached, "
[ "$SVC_MEDIA" = "on" ] && SVC_LIST+="Media Tools, "
[ "$SVC_FOP" = "on" ] && SVC_LIST+="Apache FOP, "
SVC_LIST="${SVC_LIST%, }"
[ -z "$SVC_LIST" ] && SVC_LIST="None (using existing)"

dialog --title "Step 9 of 9: Confirm Installation" --yesno "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
            INSTALLATION SUMMARY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Mode:        $INSTALL_MODE
Path:        $ATOM_PATH

Services:    $SVC_LIST

Database:    $DB_NAME
DB User:     $DB_USER
ES Heap:     $ES_HEAP

Site Title:  $SITE_TITLE
Site URL:    $SITE_URL
Admin:       $ADMIN_EMAIL ($ADMIN_USER)

Demo Data:   $LOAD_DEMO
Worker:      $SETUP_WORKER

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Proceed with installation?
" 28 58 || exit 1

#===============================================================================
# INSTALLATION
#===============================================================================
{
echo 2
echo "XXX"; echo "Updating system packages..."; echo "XXX"
apt-get update -qq >>$LOG 2>&1
apt-get install -y software-properties-common curl wget gnupg git >>$LOG 2>&1

#---------------------------------------------------------------------------
# Nginx
#---------------------------------------------------------------------------
if [ "$SVC_NGINX" = "on" ]; then
    echo 5
    echo "XXX"; echo "Installing Nginx..."; echo "XXX"
    apt-get install -y nginx >>$LOG 2>&1
fi

#---------------------------------------------------------------------------
# PHP 8.3
#---------------------------------------------------------------------------
if [ "$SVC_PHP" = "on" ]; then
    echo 10
    echo "XXX"; echo "Installing PHP 8.3 and extensions..."; echo "XXX"
    
    if ! apt-cache show php8.3 &>/dev/null; then
        add-apt-repository -y ppa:ondrej/php >>$LOG 2>&1
        apt-get update -qq >>$LOG 2>&1
    fi
    
    apt-get install -y \
        php-common php8.3-common php8.3-cli php8.3-fpm \
        php8.3-curl php8.3-mbstring php8.3-mysql \
        php8.3-xml php8.3-xsl php8.3-zip php8.3-gd \
        php8.3-opcache php8.3-apcu php8.3-intl \
        php8.3-ldap php8.3-readline \
        composer >>$LOG 2>&1
    
    if [ "$SVC_MEMCACHED" = "on" ]; then
        apt-get install -y php-memcache >>$LOG 2>&1
    fi
fi

#---------------------------------------------------------------------------
# MySQL 8.0
#---------------------------------------------------------------------------
if [ "$SVC_MYSQL" = "on" ]; then
    echo 18
    echo "XXX"; echo "Installing MySQL 8.0..."; echo "XXX"
    
    debconf-set-selections <<< "mysql-server mysql-server/root_password password $MYSQL_ROOT"
    debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $MYSQL_ROOT"
    
    apt-get install -y mysql-server >>$LOG 2>&1
    
    systemctl start mysql
    systemctl enable mysql
    
    echo 20
    echo "XXX"; echo "Configuring MySQL..."; echo "XXX"
    
    cat > /etc/mysql/conf.d/atom.cnf << 'MYSQLCNF'
[mysqld]
sql_mode=ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION
optimizer_switch='block_nested_loop=off'
MYSQLCNF
    
    systemctl restart mysql
fi

#---------------------------------------------------------------------------
# Create Database
#---------------------------------------------------------------------------
echo 25
echo "XXX"; echo "Creating AtoM database..."; echo "XXX"

if [ -n "$MYSQL_ROOT" ]; then
    MCMD="mysql -u root -p$MYSQL_ROOT"
else
    MCMD="mysql -u root"
fi

$MCMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;" >>$LOG 2>&1
$MCMD -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" >>$LOG 2>&1
$MCMD -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';" >>$LOG 2>&1
$MCMD -e "FLUSH PRIVILEGES;" >>$LOG 2>&1

#---------------------------------------------------------------------------
# Elasticsearch 7.10 (OSS)
#---------------------------------------------------------------------------
if [ "$SVC_ES" = "on" ]; then
    echo 30
    echo "XXX"; echo "Installing Java (OpenJDK 11)..."; echo "XXX"
    apt-get install -y openjdk-11-jre-headless >>$LOG 2>&1
    
    echo 35
    echo "XXX"; echo "Installing Elasticsearch 7.10 OSS..."; echo "XXX"
    
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
    cat > /etc/elasticsearch/jvm.options.d/heap.options << ESHEAP
-Xms${ES_HEAP}
-Xmx${ES_HEAP}
ESHEAP
    
    systemctl daemon-reload
    systemctl enable elasticsearch
    systemctl start elasticsearch
    
    echo 40
    echo "XXX"; echo "Waiting for Elasticsearch to start..."; echo "XXX"
    for i in {1..30}; do
        curl -s http://localhost:9200 &>/dev/null && break
        sleep 2
    done
fi

#---------------------------------------------------------------------------
# Gearman
#---------------------------------------------------------------------------
if [ "$SVC_GEARMAN" = "on" ]; then
    echo 45
    echo "XXX"; echo "Installing Gearman Job Server..."; echo "XXX"
    apt-get install -y gearman-job-server >>$LOG 2>&1
    apt-get install -y php8.3-gearman >>$LOG 2>&1
    systemctl enable gearman-job-server
    systemctl start gearman-job-server
fi

#---------------------------------------------------------------------------
# Memcached
#---------------------------------------------------------------------------
if [ "$SVC_MEMCACHED" = "on" ]; then
    echo 48
    echo "XXX"; echo "Installing Memcached..."; echo "XXX"
    apt-get install -y memcached >>$LOG 2>&1
    systemctl enable memcached
    systemctl start memcached
fi

#---------------------------------------------------------------------------
# Media Tools
#---------------------------------------------------------------------------
if [ "$SVC_MEDIA" = "on" ]; then
    echo 50
    echo "XXX"; echo "Installing media processing tools..."; echo "XXX"
    apt-get install -y imagemagick ghostscript poppler-utils ffmpeg >>$LOG 2>&1
fi

#---------------------------------------------------------------------------
# Apache FOP
#---------------------------------------------------------------------------
if [ "$SVC_FOP" = "on" ]; then
    echo 52
    echo "XXX"; echo "Installing Apache FOP..."; echo "XXX"
    apt-get install -y --no-install-recommends fop libsaxon-java >>$LOG 2>&1
    update-java-alternatives -s java-1.11.0-openjdk-amd64 2>>$LOG || true
fi

#---------------------------------------------------------------------------
# Node.js
#---------------------------------------------------------------------------
echo 55
echo "XXX"; echo "Installing Node.js..."; echo "XXX"
apt-get install -y nodejs npm >>$LOG 2>&1

#---------------------------------------------------------------------------
# Download AtoM
#---------------------------------------------------------------------------
if [ "$INSTALL_MODE" != "extensions" ]; then
    echo 58
    echo "XXX"; echo "Downloading AtoM from GitHub..."; echo "XXX"
    
    rm -rf "$ATOM_PATH"
    mkdir -p "$ATOM_PATH"
    
    git clone -b stable/2.10.x --depth 1 https://github.com/artefactual/atom.git "$ATOM_PATH" >>$LOG 2>&1
    
    echo 65
    echo "XXX"; echo "Installing Composer dependencies..."; echo "XXX"
    cd "$ATOM_PATH"
    composer install --no-dev --no-interaction --prefer-dist >>$LOG 2>&1
    
    echo 68
    echo "XXX"; echo "Compiling theme files..."; echo "XXX"
    cd "$ATOM_PATH"
    npm install >>$LOG 2>&1
    npm run build >>$LOG 2>&1 || true
fi

#---------------------------------------------------------------------------
# Create config.php BEFORE tools:install
#---------------------------------------------------------------------------
echo 70
echo "XXX"; echo "Creating configuration files..."; echo "XXX"

mkdir -p "$ATOM_PATH/config"
cat > "$ATOM_PATH/config/config.php" << CFGPHP
<?php
return [
    'all' => [
        'propel' => [
            'class' => 'sfPropelDatabase',
            'param' => [
                'encoding' => 'utf8mb4',
                'persistent' => true,
                'pooling' => true,
                'dsn' => 'mysql:host=localhost;dbname=$DB_NAME;charset=utf8mb4',
                'username' => '$DB_USER',
                'password' => '$DB_PASS',
            ],
        ],
    ],
];
CFGPHP

mkdir -p "$ATOM_PATH/cache" "$ATOM_PATH/log" "$ATOM_PATH/uploads" "$ATOM_PATH/downloads"
chown -R www-data:www-data "$ATOM_PATH"

#---------------------------------------------------------------------------
# PHP-FPM Pool
#---------------------------------------------------------------------------
echo 72
echo "XXX"; echo "Configuring PHP-FPM pool..."; echo "XXX"

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
php_admin_value[expose_php] = off
php_admin_value[allow_url_fopen] = on
php_admin_value[memory_limit] = 512M
php_admin_value[max_execution_time] = 120
php_admin_value[post_max_size] = 72M
php_admin_value[upload_max_filesize] = 64M
php_admin_value[max_file_uploads] = 10
php_admin_value[cgi.fix_pathinfo] = 0
php_admin_value[display_errors] = off
php_admin_value[display_startup_errors] = off
php_admin_value[html_errors] = off
php_admin_value[session.use_only_cookies] = 0
php_admin_value[apc.enabled] = 1
php_admin_value[apc.shm_size] = 64M
php_admin_value[apc.num_files_hint] = 5000
php_admin_value[apc.stat] = 0
php_admin_value[opcache.enable] = 1
php_admin_value[opcache.memory_consumption] = 192
php_admin_value[opcache.interned_strings_buffer] = 16
php_admin_value[opcache.max_accelerated_files] = 4000
php_admin_value[opcache.validate_timestamps] = 0
php_admin_value[opcache.fast_shutdown] = 1
env[ATOM_DEBUG_IP] = "127.0.0.1"
env[ATOM_READ_ONLY] = "off"
PHPFPM

rm -f /etc/php/8.3/fpm/pool.d/www.conf 2>/dev/null || true
systemctl restart php8.3-fpm

#---------------------------------------------------------------------------
# Nginx
#---------------------------------------------------------------------------
echo 75
echo "XXX"; echo "Configuring Nginx..."; echo "XXX"

cat > /etc/nginx/sites-available/atom << NGXCFG
upstream atom {
    server unix:/run/php-fpm.atom.sock;
}

server {
    listen 80;
    server_name _;
    root $ATOM_PATH;
    
    client_max_body_size 72M;
    
    location ~* ^/(css|dist|js|images|plugins|vendor)/.*\.(css|png|jpg|js|svg|ico|gif|pdf|woff|woff2|otf|ttf)$ {
    }
    
    location ~* ^/(downloads)/.*\.(pdf|xml|html|csv|zip|rtf)$ {
    }
    
    location ~ ^/(ead.dtd|favicon.ico|robots.txt|sitemap.*)$ {
    }
    
    location / {
        try_files \$uri /index.php?\$args;
        if (-f \$request_filename) {
            return 403;
        }
    }
    
    location ~* /uploads/r/(.*)/conf/ {
    }
    
    location ~* ^/uploads/r/(.*)\$ {
        include /etc/nginx/fastcgi_params;
        set \$index /index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$index;
        fastcgi_param SCRIPT_NAME \$index;
        fastcgi_pass atom;
    }
    
    location ~ ^/private/(.*)\$ {
        internal;
        alias $ATOM_PATH/\$1;
    }
    
    location ~ ^/(index|qubit_dev)\.php(/|\$) {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_split_path_info ^(.+\.php)(/.*)\$;
        fastcgi_pass atom;
    }
}
NGXCFG

ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/atom
rm -f /etc/nginx/sites-enabled/default
nginx -t >>$LOG 2>&1 && systemctl reload nginx

#---------------------------------------------------------------------------
# Initialize AtoM Database with expect
#---------------------------------------------------------------------------
echo 80
echo "XXX"; echo "Initializing AtoM database..."; echo "XXX"

cd "$ATOM_PATH"

# Install expect
apt-get install -y expect >>$LOG 2>&1 || true

# Use expect to handle interactive prompts
# 2 x y/N: 1) Database check/drop  2) Final confirmation
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

# First y/N - Database exists/drop check
expect -re "\(y/N\)" { send "y\r" }

# Second y/N - Final confirmation to proceed
expect -re "\(y/N\)" { send "y\r" }

# Wait for completion (can take a while for DB setup)
expect eof
EXPECT_SCRIPT

#---------------------------------------------------------------------------
# AHG Extensions (AFTER AtoM is initialized)
#---------------------------------------------------------------------------
if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then
    echo 88
    echo "XXX"; echo "Installing AHG Extensions..."; echo "XXX"
    
    cd "$ATOM_PATH"
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git atom-framework >>$LOG 2>&1
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git atom-ahg-plugins >>$LOG 2>&1
    
    echo 90
    echo "XXX"; echo "Setting up AHG Framework..."; echo "XXX"
    cd "$ATOM_PATH/atom-framework"
    composer install --no-dev --no-interaction >>$LOG 2>&1
    
    # Now config.php exists, run the framework install
    bash bin/install --auto >>$LOG 2>&1 || true
    
    chown -R www-data:www-data "$ATOM_PATH"
fi

#---------------------------------------------------------------------------
# Worker Service
#---------------------------------------------------------------------------
if [ "$SETUP_WORKER" = "on" ]; then
    echo 92
    echo "XXX"; echo "Setting up atom-worker service..."; echo "XXX"
    
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
echo 98
echo "XXX"; echo "Final cleanup and verification..."; echo "XXX"

chown -R www-data:www-data "$ATOM_PATH"
systemctl restart php8.3-fpm nginx

# Verify services
systemctl is-active --quiet nginx || systemctl start nginx
systemctl is-active --quiet php8.3-fpm || systemctl start php8.3-fpm
systemctl is-active --quiet mysql || systemctl start mysql

echo 100
echo "XXX"; echo "Installation complete!"; echo "XXX"
sleep 1

} | dialog --title "Installing AtoM" --gauge "Preparing installation..." 8 60 0

#===============================================================================
# Complete
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Installation Complete!" --msgbox "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
      AtoM has been successfully installed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Access your installation at:

    $SITE_URL

    or http://$SERVER_IP

Login credentials:
    Email:    $ADMIN_EMAIL
    Username: $ADMIN_USER
    Password: (password you entered)

Installation Details:
    Path:     $ATOM_PATH
    Database: $DB_NAME
    Log:      $LOG

Useful Commands:
    Clear cache:       php symfony cc
    Rebuild search:    php symfony search:populate
    Worker status:     systemctl status atom-worker
    View logs:         tail -f $ATOM_PATH/log/*.log

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
" 32 58

clear
echo ""
echo "========================================"
echo "  AtoM Installation Complete!"
echo "========================================"
echo ""
echo "  URL:      $SITE_URL"
echo "  Admin:    $ADMIN_EMAIL"
echo "  Path:     $ATOM_PATH"
echo ""
echo "  Log:      $LOG"
echo ""
echo "  Services:"
echo "    systemctl status nginx"
echo "    systemctl status php8.3-fpm"
echo "    systemctl status mysql"
echo "    systemctl status elasticsearch"
echo "    systemctl status atom-worker"
echo ""
