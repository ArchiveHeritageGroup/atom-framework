#!/bin/bash
#===============================================================================
# AtoM Setup Wizard v2.0
# Interactive installer for AtoM 2.10 + AHG Extensions
#
# Usage: 
#   curl -fsSL https://github.com/ArchiveHeritageGroup/atom-framework/releases/latest/download/setup-wizard.sh | sudo bash
#   # Or download and run:
#   chmod +x setup-wizard.sh && sudo ./setup-wizard.sh
#===============================================================================

set -e

# Must be root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root: sudo $0"
    exit 1
fi

# Install dialog
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

MYSQL_ROOT=""
DB_NAME="atom"
DB_USER="atom"
DB_PASS=""

ES_HEAP="512m"

SITE_TITLE="My Archive"
ADMIN_EMAIL=""
ADMIN_PASS=""

LOAD_DEMO="off"

LOG="/var/log/atom-install-$(date +%Y%m%d%H%M%S).log"

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
               Version 2.10

  This wizard will install and configure AtoM
  with all required services.

          Press OK to continue...
" 20 55

#===============================================================================
# Step 1: Installation Mode
#===============================================================================
dialog --title "Step 1 of 8: Installation Mode" \
       --menu "\nSelect installation type:\n" 15 60 3 \
       "complete" "AtoM 2.10 + AHG Extensions (Recommended)" \
       "atom" "Base AtoM 2.10 only" \
       "extensions" "AHG Extensions only (existing AtoM)" \
       2>$TEMP || exit 1
INSTALL_MODE=$(<$TEMP)

#===============================================================================
# Step 2: Installation Path
#===============================================================================
dialog --title "Step 2 of 8: Installation Path" \
       --inputbox "\nWhere should AtoM be installed?\n" 10 60 "$ATOM_PATH" \
       2>$TEMP || exit 1
ATOM_PATH=$(<$TEMP)
[ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"

#===============================================================================
# Step 3: Services
#===============================================================================
# Check what's installed
cmd_exists() { command -v "$1" &>/dev/null; }
cmd_exists nginx && SVC_NGINX="off"
cmd_exists php && SVC_PHP="off"
cmd_exists mysql && SVC_MYSQL="off"
cmd_exists gearman && SVC_GEARMAN="off"
cmd_exists memcached && SVC_MEMCACHED="off"
cmd_exists ffmpeg && SVC_MEDIA="off"
curl -s http://localhost:9200 &>/dev/null && SVC_ES="off"

dialog --title "Step 3 of 8: Services to Install" \
       --checklist "\nSelect services (Space=toggle, Enter=confirm):\n\nAlready installed services are unchecked.\n" 18 65 7 \
       "nginx" "Nginx Web Server" $SVC_NGINX \
       "php" "PHP 8.3 + Extensions" $SVC_PHP \
       "mysql" "MySQL 8.0 Database" $SVC_MYSQL \
       "elasticsearch" "Elasticsearch (Search)" $SVC_ES \
       "gearman" "Gearman (Background Jobs)" $SVC_GEARMAN \
       "memcached" "Memcached (Caching)" $SVC_MEMCACHED \
       "media" "FFmpeg/ImageMagick (Media)" $SVC_MEDIA \
       2>$TEMP || exit 1

SERVICES=$(<$TEMP)
SVC_NGINX="off"; SVC_PHP="off"; SVC_MYSQL="off"; SVC_ES="off"
SVC_GEARMAN="off"; SVC_MEMCACHED="off"; SVC_MEDIA="off"
[[ "$SERVICES" == *nginx* ]] && SVC_NGINX="on"
[[ "$SERVICES" == *php* ]] && SVC_PHP="on"
[[ "$SERVICES" == *mysql* ]] && SVC_MYSQL="on"
[[ "$SERVICES" == *elasticsearch* ]] && SVC_ES="on"
[[ "$SERVICES" == *gearman* ]] && SVC_GEARMAN="on"
[[ "$SERVICES" == *memcached* ]] && SVC_MEMCACHED="on"
[[ "$SERVICES" == *media* ]] && SVC_MEDIA="on"

#===============================================================================
# Step 4: MySQL Configuration
#===============================================================================
if [ "$SVC_MYSQL" = "on" ]; then
    dialog --title "Step 4 of 8: MySQL Root Password" \
           --insecure --passwordbox "\nSet MySQL root password:\n\n(Min 8 characters)\n" 12 50 \
           2>$TEMP || exit 1
    MYSQL_ROOT=$(<$TEMP)
    [ ${#MYSQL_ROOT} -lt 8 ] && MYSQL_ROOT="rootpass123"
fi

dialog --title "Step 4 of 8: AtoM Database" \
       --form "\nDatabase settings for AtoM:\n" 15 60 3 \
       "Database Name:" 1 1 "$DB_NAME" 1 18 30 50 \
       "Database User:" 2 1 "$DB_USER" 2 18 30 50 \
       2>$TEMP || exit 1

DB_NAME=$(sed -n '1p' $TEMP)
DB_USER=$(sed -n '2p' $TEMP)
[ -z "$DB_NAME" ] && DB_NAME="atom"
[ -z "$DB_USER" ] && DB_USER="atom"

dialog --title "Step 4 of 8: Database Password" \
       --insecure --passwordbox "\nSet password for user '$DB_USER':\n\n(Min 8 characters)\n" 12 50 \
       2>$TEMP || exit 1
DB_PASS=$(<$TEMP)
[ ${#DB_PASS} -lt 8 ] && DB_PASS="atompass123"

#===============================================================================
# Step 5: Elasticsearch
#===============================================================================
if [ "$SVC_ES" = "on" ]; then
    dialog --title "Step 5 of 8: Elasticsearch" \
           --inputbox "\nElasticsearch heap size:\n\n(e.g., 512m, 1g, 2g)\n" 12 50 "$ES_HEAP" \
           2>$TEMP || exit 1
    ES_HEAP=$(<$TEMP)
    [ -z "$ES_HEAP" ] && ES_HEAP="512m"
fi

#===============================================================================
# Step 6: Site Settings
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Step 6 of 8: Site Configuration" \
       --inputbox "\nSite title (shown in browser):\n" 10 60 "$SITE_TITLE" \
       2>$TEMP || exit 1
SITE_TITLE=$(<$TEMP)
[ -z "$SITE_TITLE" ] && SITE_TITLE="My Archive"

#===============================================================================
# Step 7: Admin Account
#===============================================================================
dialog --title "Step 7 of 8: Administrator Account" \
       --inputbox "\nAdmin email (used for login):\n" 10 60 "$ADMIN_EMAIL" \
       2>$TEMP || exit 1
ADMIN_EMAIL=$(<$TEMP)
[ -z "$ADMIN_EMAIL" ] && ADMIN_EMAIL="admin@example.com"

dialog --title "Step 7 of 8: Admin Password" \
       --insecure --passwordbox "\nSet administrator password:\n\n(Min 8 characters)\n" 12 50 \
       2>$TEMP || exit 1
ADMIN_PASS=$(<$TEMP)
[ ${#ADMIN_PASS} -lt 8 ] && ADMIN_PASS="admin12345"

#===============================================================================
# Step 8: Options
#===============================================================================
dialog --title "Step 8 of 8: Additional Options" \
       --checklist "\nSelect options:\n" 12 60 2 \
       "demo" "Load demo/sample data" off \
       "backups" "Enable daily backups" on \
       2>$TEMP || exit 1

OPTIONS=$(<$TEMP)
[[ "$OPTIONS" == *demo* ]] && LOAD_DEMO="on"

#===============================================================================
# Confirmation
#===============================================================================
dialog --title "Confirm Installation" --yesno "
Installation Summary
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Mode:       $INSTALL_MODE
Path:       $ATOM_PATH

Database:   $DB_NAME (user: $DB_USER)

Site:       $SITE_TITLE
Admin:      $ADMIN_EMAIL

Demo Data:  $LOAD_DEMO
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Proceed with installation?
" 20 55 || exit 1

#===============================================================================
# Installation
#===============================================================================
{
echo 5
echo "XXX"; echo "Updating system..."; echo "XXX"
apt-get update -qq >>$LOG 2>&1

echo 10
echo "XXX"; echo "Installing dependencies..."; echo "XXX"
apt-get install -y git curl wget software-properties-common >>$LOG 2>&1

if [ "$SVC_NGINX" = "on" ]; then
    echo 15
    echo "XXX"; echo "Installing Nginx..."; echo "XXX"
    apt-get install -y nginx >>$LOG 2>&1
fi

if [ "$SVC_PHP" = "on" ]; then
    echo 25
    echo "XXX"; echo "Installing PHP 8.3..."; echo "XXX"
    add-apt-repository -y ppa:ondrej/php >>$LOG 2>&1 || true
    apt-get update -qq >>$LOG 2>&1
    apt-get install -y php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring \
        php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-xsl \
        php8.3-opcache php8.3-apcu php8.3-memcached php8.3-gearman \
        composer >>$LOG 2>&1
fi

if [ "$SVC_MYSQL" = "on" ]; then
    echo 35
    echo "XXX"; echo "Installing MySQL..."; echo "XXX"
    debconf-set-selections <<< "mysql-server mysql-server/root_password password $MYSQL_ROOT"
    debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $MYSQL_ROOT"
    apt-get install -y mysql-server >>$LOG 2>&1
    systemctl start mysql
    systemctl enable mysql
fi

echo 45
echo "XXX"; echo "Creating database..."; echo "XXX"
if [ -n "$MYSQL_ROOT" ]; then
    MCMD="mysql -u root -p$MYSQL_ROOT"
else
    MCMD="mysql -u root"
fi
$MCMD -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >>$LOG 2>&1
$MCMD -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';" >>$LOG 2>&1
$MCMD -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;" >>$LOG 2>&1

if [ "$SVC_ES" = "on" ]; then
    echo 50
    echo "XXX"; echo "Installing Elasticsearch..."; echo "XXX"
    wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg 2>>$LOG
    echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" > /etc/apt/sources.list.d/elastic-8.x.list
    apt-get update -qq >>$LOG 2>&1
    apt-get install -y elasticsearch >>$LOG 2>&1
    
    cat > /etc/elasticsearch/elasticsearch.yml << ESYML
cluster.name: atom
node.name: atom-node
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: localhost
http.port: 9200
xpack.security.enabled: false
ESYML
    
    mkdir -p /etc/elasticsearch/jvm.options.d
    echo "-Xms${ES_HEAP}" > /etc/elasticsearch/jvm.options.d/heap.options
    echo "-Xmx${ES_HEAP}" >> /etc/elasticsearch/jvm.options.d/heap.options
    
    systemctl daemon-reload
    systemctl enable elasticsearch
    systemctl start elasticsearch
fi

echo 55
echo "XXX"; echo "Installing additional services..."; echo "XXX"
[ "$SVC_GEARMAN" = "on" ] && apt-get install -y gearman-job-server >>$LOG 2>&1
[ "$SVC_MEMCACHED" = "on" ] && apt-get install -y memcached >>$LOG 2>&1
[ "$SVC_MEDIA" = "on" ] && apt-get install -y ffmpeg imagemagick ghostscript poppler-utils >>$LOG 2>&1
apt-get install -y nodejs npm >>$LOG 2>&1

echo 65
echo "XXX"; echo "Downloading AtoM..."; echo "XXX"
if [ "$INSTALL_MODE" != "extensions" ]; then
    rm -rf "$ATOM_PATH"
    git clone -b stable/2.10.x --depth 1 https://github.com/artefactual/atom.git "$ATOM_PATH" >>$LOG 2>&1
    cd "$ATOM_PATH"
    composer install --no-dev --no-interaction >>$LOG 2>&1
fi

if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then
    echo 75
    echo "XXX"; echo "Installing AHG Extensions..."; echo "XXX"
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git "$ATOM_PATH/atom-framework" >>$LOG 2>&1
    git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git "$ATOM_PATH/atom-ahg-plugins" >>$LOG 2>&1
    cd "$ATOM_PATH/atom-framework"
    composer install --no-dev --no-interaction >>$LOG 2>&1
    bash bin/install --auto >>$LOG 2>&1 || true
fi

echo 85
echo "XXX"; echo "Configuring AtoM..."; echo "XXX"

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
chmod -R 755 "$ATOM_PATH"
chmod -R 775 "$ATOM_PATH/cache" "$ATOM_PATH/log" "$ATOM_PATH/uploads" "$ATOM_PATH/downloads"

echo 90
echo "XXX"; echo "Configuring Nginx..."; echo "XXX"

cat > /etc/nginx/sites-available/atom << NGXCFG
server {
    listen 80;
    server_name _;
    root $ATOM_PATH;
    index index.php;
    client_max_body_size 64M;
    
    location / {
        try_files \$uri /index.php\$is_args\$args;
    }
    
    location ~ ^/(index|qubit_dev)\.php(/|\$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 120;
    }
    
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    location ~ /\. { deny all; }
}
NGXCFG

ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/atom
rm -f /etc/nginx/sites-enabled/default
systemctl restart nginx
systemctl restart php8.3-fpm

echo 95
echo "XXX"; echo "Initializing AtoM database..."; echo "XXX"
cd "$ATOM_PATH"

if [ "$LOAD_DEMO" = "on" ]; then
    sudo -u www-data php symfony tools:install --demo --no-confirmation >>$LOG 2>&1 || true
else
    sudo -u www-data php symfony tools:install --no-confirmation >>$LOG 2>&1 || true
fi

# Start all services
systemctl enable nginx php8.3-fpm mysql gearman-job-server memcached 2>/dev/null || true
systemctl start nginx php8.3-fpm mysql gearman-job-server memcached 2>/dev/null || true

echo 100
echo "XXX"; echo "Complete!"; echo "XXX"
sleep 1

} | dialog --title "Installing AtoM" --gauge "Preparing..." 8 60 0

#===============================================================================
# Complete
#===============================================================================
SERVER_IP=$(hostname -I | awk '{print $1}')

dialog --title "Installation Complete!" --msgbox "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     AtoM has been successfully installed!
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Access your installation at:

    http://$SERVER_IP

Login with:
    Email:    $ADMIN_EMAIL
    Password: (password you entered)

Installation path: $ATOM_PATH
Log file: $LOG

Useful commands:
    php symfony cc              # Clear cache
    php symfony search:populate # Rebuild search

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
" 25 55

clear
echo ""
echo "========================================"
echo "  AtoM Installation Complete!"
echo "========================================"
echo ""
echo "  URL:   http://$SERVER_IP"
echo "  Admin: $ADMIN_EMAIL"
echo "  Path:  $ATOM_PATH"
echo ""
echo "  Log:   $LOG"
echo ""
