#!/bin/bash
#===============================================================================
# AtoM Setup Wizard v2.2
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
# System Check
#===============================================================================
check_requirements() {
    local cpu_cores=$(nproc)
    local total_ram=$(free -m | awk '/^Mem:/{print $2}')
    local free_disk=$(df -BG / | awk 'NR==2 {print $4}' | tr -d 'G')
    local os_version=$(lsb_release -rs 2>/dev/null || echo "unknown")
    
    local report="SYSTEM CHECK\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n"
    report+="CPU:  $cpu_cores cores (min: 2)\n"
    report+="RAM:  ${total_ram}MB (min: 4GB, rec: 7GB)\n"
    report+="Disk: ${free_disk}GB free (min: 50GB)\n"
    report+="OS:   Ubuntu $os_version\n\n"
    
    # Check installed services
    report+="INSTALLED SERVICES\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
    command -v nginx &>/dev/null && report+="[x] Nginx\n" && SVC_NGINX="off" || report+="[ ] Nginx\n"
    command -v php &>/dev/null && report+="[x] PHP\n" && SVC_PHP="off" || report+="[ ] PHP\n"
    command -v mysql &>/dev/null && report+="[x] MySQL\n" && SVC_MYSQL="off" || report+="[ ] MySQL\n"
    curl -s http://localhost:9200 &>/dev/null && report+="[x] Elasticsearch\n" && SVC_ES="off" || report+="[ ] Elasticsearch\n"
    command -v gearman &>/dev/null && report+="[x] Gearman\n" && SVC_GEARMAN="off" || report+="[ ] Gearman\n"
    
    dialog --title "System Requirements" --yes-label "Continue" --no-label "Cancel" --yesno "$report" 22 45
    return $?
}

#===============================================================================
# Welcome
#===============================================================================
dialog --title "AtoM Setup Wizard" --msgbox "\n
        AtoM Setup Wizard v2.2
        Access to Memory 2.10

  This wizard will install AtoM with all
  required services and dependencies.

  Press OK to continue...
" 14 45

check_requirements || exit 1

#===============================================================================
# Step 1: Mode
#===============================================================================
dialog --title "Step 1/8: Installation Mode" \
       --menu "\nSelect installation type:\n" 14 60 3 \
       "complete" "AtoM 2.10 + AHG Extensions" \
       "atom" "Base AtoM 2.10 only" \
       "extensions" "AHG Extensions only (existing AtoM)" \
       2>$TEMP || exit 1
INSTALL_MODE=$(<$TEMP)

#===============================================================================
# Step 2: Path
#===============================================================================
dialog --title "Step 2/8: Installation Path" \
       --inputbox "\nInstallation directory:\n" 10 55 "$ATOM_PATH" \
       2>$TEMP || exit 1
ATOM_PATH=$(<$TEMP)
[ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"

#===============================================================================
# Step 3: Services
#===============================================================================
dialog --title "Step 3/8: Services to Install" \
       --checklist "\nSelect services:\n" 18 60 8 \
       "nginx" "Nginx Web Server" $SVC_NGINX \
       "php" "PHP 8.3" $SVC_PHP \
       "mysql" "MySQL 8.0" $SVC_MYSQL \
       "elasticsearch" "Elasticsearch 7.10" $SVC_ES \
       "gearman" "Gearman Job Server" $SVC_GEARMAN \
       "memcached" "Memcached" $SVC_MEMCACHED \
       "media" "ImageMagick/FFmpeg" $SVC_MEDIA \
       "fop" "Apache FOP" $SVC_FOP \
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
    dialog --title "Step 4/8: MySQL Root Password" \
           --insecure --passwordbox "\nMySQL root password (min 8 chars):\n" 10 50 \
           2>$TEMP || exit 1
    MYSQL_ROOT=$(<$TEMP)
    [ ${#MYSQL_ROOT} -lt 8 ] && MYSQL_ROOT="rootpass123"
fi

dialog --title "Step 4/8: AtoM Database" \
       --form "\nDatabase settings:\n" 13 55 3 \
       "Name:" 1 1 "$DB_NAME" 1 10 30 50 \
       "User:" 2 1 "$DB_USER" 2 10 30 50 \
       2>$TEMP || exit 1
DB_NAME=$(sed -n '1p' $TEMP); DB_USER=$(sed -n '2p' $TEMP)
[ -z "$DB_NAME" ] && DB_NAME="atom"
[ -z "$DB_USER" ] && DB_USER="atom"

dialog --title "Step 4/8: Database Password" \
       --insecure --passwordbox "\nPassword for '$DB_USER' (min 8 chars):\n" 10 50 \
       2>$TEMP || exit 1
DB_PASS=$(<$TEMP)
[ ${#DB_PASS} -lt 8 ] && DB_PASS="atompass123"

#===============================================================================
# Step 5: Elasticsearch
#===============================================================================
if [ "$SVC_ES" = "on" ]; then
    dialog --title "Step 5/8: Elasticsearch" \
           --inputbox "\nHeap size (e.g., 512m, 1g):\n" 10 45 "$ES_HEAP" \
           2>$TEMP || exit 1
    ES_HEAP=$(<$TEMP)
    [ -z "$ES_HEAP" ] && ES_HEAP="512m"
fi

#===============================================================================
# Step 6: Site
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

dialog --title "Step 6/8: Site Configuration" \
       --form "\nSite settings:\n" 14 60 3 \
       "Title:" 1 1 "$SITE_TITLE" 1 12 40 100 \
       "Description:" 2 1 "$SITE_DESC" 2 12 40 200 \
       "URL:" 3 1 "$SITE_URL" 3 12 40 200 \
       2>$TEMP || exit 1
SITE_TITLE=$(sed -n '1p' $TEMP); SITE_DESC=$(sed -n '2p' $TEMP); SITE_URL=$(sed -n '3p' $TEMP)
[ -z "$SITE_TITLE" ] && SITE_TITLE="My Archive"
[ -z "$SITE_URL" ] && SITE_URL="http://${SERVER_IP}"

#===============================================================================
# Step 7: Admin
#===============================================================================
dialog --title "Step 7/8: Administrator" \
       --form "\nAdmin account:\n" 12 55 2 \
       "Email:" 1 1 "$ADMIN_EMAIL" 1 10 40 100 \
       "Username:" 2 1 "$ADMIN_USER" 2 10 30 50 \
       2>$TEMP || exit 1
ADMIN_EMAIL=$(sed -n '1p' $TEMP); ADMIN_USER=$(sed -n '2p' $TEMP)
[ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@example.com"
[ -z "$ADMIN_USER" ] && ADMIN_USER="admin"

dialog --title "Step 7/8: Admin Password" \
       --insecure --passwordbox "\nAdmin password (min 8 chars):\n" 10 50 \
       2>$TEMP || exit 1
ADMIN_PASS=$(<$TEMP)
[ ${#ADMIN_PASS} -lt 8 ] && ADMIN_PASS="admin12345"

#===============================================================================
# Step 8: Options
#===============================================================================
dialog --title "Step 8/8: Options" \
       --checklist "\nAdditional options:\n" 12 55 2 \
       "demo" "Load demo data" off \
       "worker" "Setup atom-worker service" on \
       2>$TEMP || exit 1
OPTIONS=$(<$TEMP)
[[ "$OPTIONS" == *demo* ]] && LOAD_DEMO="on"
[[ "$OPTIONS" == *worker* ]] && SETUP_WORKER="on" || SETUP_WORKER="off"

#===============================================================================
# Confirm
#===============================================================================
dialog --title "Confirm Installation" --yesno "
Mode:     $INSTALL_MODE
Path:     $ATOM_PATH
Database: $DB_NAME (user: $DB_USER)
Site:     $SITE_TITLE
Admin:    $ADMIN_EMAIL
Demo:     $LOAD_DEMO

Proceed with installation?
" 14 50 || exit 1

#===============================================================================
# INSTALLATION
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

[ "$SVC_GEARMAN" = "on" ] && { echo 42; echo "XXX"; echo "Installing Gearman..."; echo "XXX"; apt-get install -y gearman-job-server >>$LOG 2>&1; systemctl enable gearman-job-server; systemctl start gearman-job-server; }
[ "$SVC_MEMCACHED" = "on" ] && { echo 44; echo "XXX"; echo "Installing Memcached..."; echo "XXX"; apt-get install -y memcached php-memcache >>$LOG 2>&1; }
[ "$SVC_MEDIA" = "on" ] && { echo 46; echo "XXX"; echo "Installing media tools..."; echo "XXX"; apt-get install -y imagemagick ghostscript poppler-utils ffmpeg >>$LOG 2>&1; }
[ "$SVC_FOP" = "on" ] && { echo 48; echo "XXX"; echo "Installing Apache FOP..."; echo "XXX"; apt-get install -y --no-install-recommends fop libsaxon-java >>$LOG 2>&1; }

echo 50; echo "XXX"; echo "Installing Node.js..."; echo "XXX"
apt-get install -y nodejs npm >>$LOG 2>&1

if [ "$INSTALL_MODE" != "extensions" ]; then
    echo 55; echo "XXX"; echo "Downloading AtoM..."; echo "XXX"
    rm -rf "$ATOM_PATH"
    git clone -b stable/2.10.x --depth 1 https://github.com/artefactual/atom.git "$ATOM_PATH" >>$LOG 2>&1
    
    echo 62; echo "XXX"; echo "Installing Composer dependencies..."; echo "XXX"
    cd "$ATOM_PATH"
    composer install --no-dev --no-interaction >>$LOG 2>&1
    
    echo 66; echo "XXX"; echo "Building theme..."; echo "XXX"
    npm install >>$LOG 2>&1
    npm run build >>$LOG 2>&1 || true
fi

#---------------------------------------------------------------------------
# CREATE CONFIG.PHP BEFORE ANYTHING ELSE
#---------------------------------------------------------------------------
echo 70; echo "XXX"; echo "Creating configuration..."; echo "XXX"

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
echo 72; echo "XXX"; echo "Configuring PHP-FPM..."; echo "XXX"

cat > /etc/php/8.3/fpm/pool.d/atom.conf << 'PHPPOOL'
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
PHPPOOL

rm -f /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart php8.3-fpm

#---------------------------------------------------------------------------
# Nginx
#---------------------------------------------------------------------------
echo 75; echo "XXX"; echo "Configuring Nginx..."; echo "XXX"

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
# INITIALIZE ATOM DATABASE - with expect for y/N prompts
#---------------------------------------------------------------------------
echo 80; echo "XXX"; echo "Initializing AtoM database..."; echo "XXX"

cd "$ATOM_PATH"

# Install expect if needed
apt-get install -y expect >>$LOG 2>&1 || true

# Use expect to handle interactive prompts
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
expect -re "\\(y/N\\)|\\(Y/n\\)" { send "y\r" }

# Second y/N - Final confirmation
expect -re "\\(y/N\\)|\\(Y/n\\)" { send "y\r" }

# Wait for completion
expect eof
EXPECT_SCRIPT

#---------------------------------------------------------------------------
# AHG Extensions (AFTER AtoM is initialized)
#---------------------------------------------------------------------------
if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then
    echo 88; echo "XXX"; echo "Installing AHG Extensions..."; echo "XXX"
    
    cd "$ATOM_PATH"
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git atom-framework >>$LOG 2>&1
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git atom-ahg-plugins >>$LOG 2>&1
    
    cd atom-framework
    composer install --no-dev --no-interaction >>$LOG 2>&1
    
    # Now config.php exists, run the framework install
    bash bin/install --auto >>$LOG 2>&1 || true
    
    chown -R www-data:www-data "$ATOM_PATH"
fi

#---------------------------------------------------------------------------
# Worker Service
#---------------------------------------------------------------------------
if [ "$SETUP_WORKER" = "on" ]; then
    echo 92; echo "XXX"; echo "Setting up worker service..."; echo "XXX"
    
    cat > /usr/lib/systemd/system/atom-worker.service << WORKER
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
WORKER
    
    systemctl daemon-reload
    systemctl enable atom-worker
    systemctl start atom-worker
fi

echo 98; echo "XXX"; echo "Final cleanup..."; echo "XXX"
chown -R www-data:www-data "$ATOM_PATH"
systemctl restart php8.3-fpm nginx

echo 100; echo "XXX"; echo "Complete!"; echo "XXX"
sleep 1

} | dialog --title "Installing AtoM" --gauge "Preparing..." 8 55 0

#===============================================================================
# Done
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Installation Complete!" --msgbox "
AtoM has been successfully installed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

URL:      http://$SERVER_IP

Login:
  Email:    $ADMIN_EMAIL
  Password: (your password)

Path:     $ATOM_PATH
Log:      $LOG

Commands:
  php symfony cc
  php symfony search:populate
  systemctl status atom-worker
" 22 50

clear
echo ""
echo "================================"
echo "  AtoM Installation Complete!"
echo "================================"
echo "  URL:   http://$SERVER_IP"
echo "  Admin: $ADMIN_EMAIL"
echo "  Log:   $LOG"
echo ""
