#!/bin/bash
#===============================================================================
# AtoM Setup Wizard - Complete Interactive Installer
# Guides user through all configuration options including service installation
#===============================================================================

set -e

# Check for root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root: sudo $0"
    exit 1
fi

# Check for dialog or whiptail
if command -v dialog &> /dev/null; then
    DIALOG="dialog"
elif command -v whiptail &> /dev/null; then
    DIALOG="whiptail"
else
    echo "Installing dialog..."
    apt-get update -qq && apt-get install -y dialog
    DIALOG="dialog"
fi

# Dialog dimensions
HEIGHT=22
WIDTH=76
CHOICE_HEIGHT=12

# Temp file for dialog output
TEMP_FILE=$(mktemp)
trap "rm -f $TEMP_FILE" EXIT

# Configuration variables with defaults
INSTALL_MODE="complete"
ATOM_PATH="/usr/share/nginx/atom"
ATOM_BRANCH="stable/2.10.x"

# Services to install
INSTALL_NGINX="yes"
INSTALL_PHP="yes"
INSTALL_MYSQL="yes"
INSTALL_ES="yes"
INSTALL_GEARMAN="yes"
INSTALL_MEMCACHED="yes"
INSTALL_FFMPEG="yes"

# MySQL settings
MYSQL_ROOT_PASS=""
DB_HOST="localhost"
DB_NAME="atom"
DB_USER="atom"
DB_PASS=""

# Elasticsearch
ES_HOST="localhost"
ES_PORT="9200"
ES_HEAP="512m"

# Site settings
SITE_TITLE="My Archive"
SITE_DESCRIPTION="Archival Management System"
SITE_URL=""

# Admin settings
ADMIN_EMAIL=""
ADMIN_USER="admin"
ADMIN_PASS=""

# Options
LOAD_DEMO_DATA="no"
CONFIGURE_SSL="no"
ENABLE_BACKUPS="yes"

# Colors for non-dialog output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

#===============================================================================
# Welcome Screen
#===============================================================================
show_welcome() {
    $DIALOG --title "AtoM Setup Wizard" \
            --msgbox "
   ╔═══════════════════════════════════════════════════════════╗
   ║                                                           ║
   ║       █████╗ ████████╗ ██████╗ ███╗   ███╗               ║
   ║      ██╔══██╗╚══██╔══╝██╔═══██╗████╗ ████║               ║
   ║      ███████║   ██║   ██║   ██║██╔████╔██║               ║
   ║      ██╔══██║   ██║   ██║   ██║██║╚██╔╝██║               ║
   ║      ██║  ██║   ██║   ╚██████╔╝██║ ╚═╝ ██║               ║
   ║      ╚═╝  ╚═╝   ╚═╝    ╚═════╝ ╚═╝     ╚═╝               ║
   ║                                                           ║
   ║           Access to Memory - Setup Wizard                 ║
   ║                   Version 2.10.0                          ║
   ║                                                           ║
   ║   This wizard will guide you through installing and       ║
   ║   configuring AtoM on your server.                        ║
   ║                                                           ║
   ╚═══════════════════════════════════════════════════════════╝

                    Press ENTER to continue...
" $HEIGHT $WIDTH
}

#===============================================================================
# Installation Mode
#===============================================================================
select_install_mode() {
    $DIALOG --title "Step 1: Installation Mode" \
            --menu "
What would you like to install?

" $HEIGHT $WIDTH $CHOICE_HEIGHT \
            "complete" "AtoM 2.10 + AHG Extensions (Bootstrap 5, GLAM plugins)" \
            "atom" "Base AtoM 2.10 only (standard installation)" \
            "extensions" "AHG Extensions only (requires existing AtoM)" \
            2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    INSTALL_MODE=$(cat $TEMP_FILE)
}

#===============================================================================
# Installation Path
#===============================================================================
get_install_path() {
    $DIALOG --title "Step 2: Installation Path" \
            --inputbox "
Where should AtoM be installed?

Common locations:
  • /usr/share/nginx/atom (recommended)
  • /var/www/atom
  • /opt/atom

" $HEIGHT $WIDTH "$ATOM_PATH" 2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    ATOM_PATH=$(cat $TEMP_FILE)
    [ -z "$ATOM_PATH" ] && ATOM_PATH="/usr/share/nginx/atom"
}

#===============================================================================
# Service Selection
#===============================================================================
select_services() {
    # Check what's already installed
    local nginx_status="OFF"
    local php_status="OFF"
    local mysql_status="OFF"
    local es_status="OFF"
    local gearman_status="OFF"
    local memcached_status="OFF"
    local ffmpeg_status="OFF"
    
    command -v nginx &>/dev/null && nginx_status="INSTALLED" || nginx_status="ON"
    command -v php &>/dev/null && php_status="INSTALLED" || php_status="ON"
    command -v mysql &>/dev/null && mysql_status="INSTALLED" || mysql_status="ON"
    curl -s http://localhost:9200 &>/dev/null && es_status="INSTALLED" || es_status="ON"
    command -v gearman &>/dev/null && gearman_status="INSTALLED" || gearman_status="ON"
    command -v memcached &>/dev/null && memcached_status="INSTALLED" || memcached_status="ON"
    command -v ffmpeg &>/dev/null && ffmpeg_status="INSTALLED" || ffmpeg_status="ON"
    
    $DIALOG --title "Step 3: Required Services" \
            --checklist "
Select services to install:

(Already installed services are marked. Uncheck to skip.)
Use SPACE to toggle, ENTER to confirm.

" $HEIGHT $WIDTH $CHOICE_HEIGHT \
            "nginx" "Web Server (required)" $nginx_status \
            "php" "PHP 8.3 + Extensions (required)" $php_status \
            "mysql" "MySQL 8.0 Database Server" $mysql_status \
            "elasticsearch" "Elasticsearch (full-text search)" $es_status \
            "gearman" "Gearman (background jobs)" $gearman_status \
            "memcached" "Memcached (caching)" $memcached_status \
            "ffmpeg" "FFmpeg + ImageMagick (media processing)" $ffmpeg_status \
            2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    
    local selected=$(cat $TEMP_FILE)
    
    INSTALL_NGINX="no"
    INSTALL_PHP="no"
    INSTALL_MYSQL="no"
    INSTALL_ES="no"
    INSTALL_GEARMAN="no"
    INSTALL_MEMCACHED="no"
    INSTALL_FFMPEG="no"
    
    [[ "$selected" == *"nginx"* ]] && INSTALL_NGINX="yes"
    [[ "$selected" == *"php"* ]] && INSTALL_PHP="yes"
    [[ "$selected" == *"mysql"* ]] && INSTALL_MYSQL="yes"
    [[ "$selected" == *"elasticsearch"* ]] && INSTALL_ES="yes"
    [[ "$selected" == *"gearman"* ]] && INSTALL_GEARMAN="yes"
    [[ "$selected" == *"memcached"* ]] && INSTALL_MEMCACHED="yes"
    [[ "$selected" == *"ffmpeg"* ]] && INSTALL_FFMPEG="yes"
}

#===============================================================================
# MySQL Configuration
#===============================================================================
configure_mysql() {
    # Check if MySQL is already installed
    if command -v mysql &>/dev/null; then
        $DIALOG --title "Step 4a: MySQL - Existing Installation" \
                --yesno "
MySQL is already installed on this system.

Do you want to:
  • YES - Use existing MySQL installation
  • NO  - Configure new database settings anyway

" $HEIGHT $WIDTH
        
        if [ $? -eq 0 ]; then
            # Test existing connection
            if mysql -u root -e "SELECT 1" &>/dev/null; then
                MYSQL_ROOT_PASS=""
            else
                $DIALOG --title "MySQL Root Password" \
                        --passwordbox "
Enter your existing MySQL root password:

" $HEIGHT $WIDTH 2>$TEMP_FILE
                MYSQL_ROOT_PASS=$(cat $TEMP_FILE)
            fi
        fi
    fi
    
    # MySQL Root Password (for new installation)
    if [ "$INSTALL_MYSQL" = "yes" ] && ! command -v mysql &>/dev/null; then
        while true; do
            $DIALOG --title "Step 4a: MySQL Root Password" \
                    --passwordbox "
Set a password for the MySQL root user:

(Minimum 8 characters, used for database administration)

" $HEIGHT $WIDTH 2>$TEMP_FILE
            
            [ $? -ne 0 ] && exit 1
            MYSQL_ROOT_PASS=$(cat $TEMP_FILE)
            
            if [ ${#MYSQL_ROOT_PASS} -lt 8 ]; then
                $DIALOG --title "Error" --msgbox "Password must be at least 8 characters!" 8 50
                continue
            fi
            
            $DIALOG --title "Step 4a: Confirm MySQL Root Password" \
                    --passwordbox "
Confirm the MySQL root password:

" $HEIGHT $WIDTH 2>$TEMP_FILE
            
            local confirm=$(cat $TEMP_FILE)
            
            if [ "$MYSQL_ROOT_PASS" != "$confirm" ]; then
                $DIALOG --title "Error" --msgbox "Passwords do not match! Please try again." 8 50
                continue
            fi
            
            break
        done
    fi
    
    # Database settings
    $DIALOG --title "Step 4b: AtoM Database Settings" \
            --form "
Configure the database for AtoM:

" $HEIGHT $WIDTH 0 \
            "Database Host:" 1 1 "$DB_HOST" 1 20 30 100 \
            "Database Name:" 2 1 "$DB_NAME" 2 20 30 100 \
            "Database User:" 3 1 "$DB_USER" 3 20 30 100 \
            2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    
    DB_HOST=$(sed -n '1p' $TEMP_FILE)
    DB_NAME=$(sed -n '2p' $TEMP_FILE)
    DB_USER=$(sed -n '3p' $TEMP_FILE)
    
    [ -z "$DB_HOST" ] && DB_HOST="localhost"
    [ -z "$DB_NAME" ] && DB_NAME="atom"
    [ -z "$DB_USER" ] && DB_USER="atom"
    
    # Database user password
    while true; do
        $DIALOG --title "Step 4c: Database User Password" \
                --passwordbox "
Set password for database user '$DB_USER':

(Minimum 8 characters)

" $HEIGHT $WIDTH 2>$TEMP_FILE
        
        [ $? -ne 0 ] && exit 1
        DB_PASS=$(cat $TEMP_FILE)
        
        if [ ${#DB_PASS} -lt 8 ]; then
            $DIALOG --title "Error" --msgbox "Password must be at least 8 characters!" 8 50
            continue
        fi
        
        $DIALOG --title "Step 4c: Confirm Database Password" \
                --passwordbox "
Confirm the database password:

" $HEIGHT $WIDTH 2>$TEMP_FILE
        
        local confirm=$(cat $TEMP_FILE)
        
        if [ "$DB_PASS" != "$confirm" ]; then
            $DIALOG --title "Error" --msgbox "Passwords do not match! Please try again." 8 50
            continue
        fi
        
        break
    done
}

#===============================================================================
# Elasticsearch Configuration
#===============================================================================
configure_elasticsearch() {
    if [ "$INSTALL_ES" = "yes" ] || curl -s http://localhost:9200 &>/dev/null; then
        $DIALOG --title "Step 5: Elasticsearch Configuration" \
                --form "
Configure Elasticsearch settings:

" $HEIGHT $WIDTH 0 \
                "Host:" 1 1 "$ES_HOST" 1 15 30 100 \
                "Port:" 2 1 "$ES_PORT" 2 15 10 10 \
                "Heap Size:" 3 1 "$ES_HEAP" 3 15 10 10 \
                2>$TEMP_FILE
        
        [ $? -ne 0 ] && exit 1
        
        ES_HOST=$(sed -n '1p' $TEMP_FILE)
        ES_PORT=$(sed -n '2p' $TEMP_FILE)
        ES_HEAP=$(sed -n '3p' $TEMP_FILE)
        
        [ -z "$ES_HOST" ] && ES_HOST="localhost"
        [ -z "$ES_PORT" ] && ES_PORT="9200"
        [ -z "$ES_HEAP" ] && ES_HEAP="512m"
    fi
}

#===============================================================================
# Site Configuration
#===============================================================================
configure_site() {
    # Get server IP for default URL
    local server_ip=$(hostname -I | awk '{print $1}')
    [ -z "$SITE_URL" ] && SITE_URL="http://${server_ip}"
    
    $DIALOG --title "Step 6: Site Configuration" \
            --form "
Configure your AtoM site:

" $HEIGHT $WIDTH 0 \
            "Site Title:" 1 1 "$SITE_TITLE" 1 20 40 100 \
            "Description:" 2 1 "$SITE_DESCRIPTION" 2 20 40 200 \
            "Site URL:" 3 1 "$SITE_URL" 3 20 40 200 \
            2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    
    SITE_TITLE=$(sed -n '1p' $TEMP_FILE)
    SITE_DESCRIPTION=$(sed -n '2p' $TEMP_FILE)
    SITE_URL=$(sed -n '3p' $TEMP_FILE)
    
    [ -z "$SITE_TITLE" ] && SITE_TITLE="My Archive"
    [ -z "$SITE_DESCRIPTION" ] && SITE_DESCRIPTION="Archival Management System"
    [ -z "$SITE_URL" ] && SITE_URL="http://${server_ip}"
}

#===============================================================================
# Admin Account
#===============================================================================
configure_admin() {
    $DIALOG --title "Step 7: Administrator Account" \
            --form "
Create the administrator account:

" $HEIGHT $WIDTH 0 \
            "Email:" 1 1 "$ADMIN_EMAIL" 1 15 40 100 \
            "Username:" 2 1 "$ADMIN_USER" 2 15 30 50 \
            2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    
    ADMIN_EMAIL=$(sed -n '1p' $TEMP_FILE)
    ADMIN_USER=$(sed -n '2p' $TEMP_FILE)
    
    [ -z "$ADMIN_USER" ] && ADMIN_USER="admin"
    
    # Admin password
    while true; do
        $DIALOG --title "Step 7: Administrator Password" \
                --passwordbox "
Set password for administrator '$ADMIN_USER':

(Minimum 8 characters)

" $HEIGHT $WIDTH 2>$TEMP_FILE
        
        [ $? -ne 0 ] && exit 1
        ADMIN_PASS=$(cat $TEMP_FILE)
        
        if [ ${#ADMIN_PASS} -lt 8 ]; then
            $DIALOG --title "Error" --msgbox "Password must be at least 8 characters!" 8 50
            continue
        fi
        
        $DIALOG --title "Step 7: Confirm Administrator Password" \
                --passwordbox "
Confirm the administrator password:

" $HEIGHT $WIDTH 2>$TEMP_FILE
        
        local confirm=$(cat $TEMP_FILE)
        
        if [ "$ADMIN_PASS" != "$confirm" ]; then
            $DIALOG --title "Error" --msgbox "Passwords do not match! Please try again." 8 50
            continue
        fi
        
        break
    done
}

#===============================================================================
# Optional Features
#===============================================================================
select_options() {
    $DIALOG --title "Step 8: Optional Features" \
            --checklist "
Select additional options:

" $HEIGHT $WIDTH $CHOICE_HEIGHT \
            "demo" "Load demo/sample data" OFF \
            "ssl" "Configure SSL with Let's Encrypt" OFF \
            "backups" "Enable automated daily backups" ON \
            "firewall" "Configure UFW firewall" ON \
            2>$TEMP_FILE
    
    [ $? -ne 0 ] && exit 1
    
    local selected=$(cat $TEMP_FILE)
    
    LOAD_DEMO_DATA="no"
    CONFIGURE_SSL="no"
    ENABLE_BACKUPS="no"
    CONFIGURE_FIREWALL="no"
    
    [[ "$selected" == *"demo"* ]] && LOAD_DEMO_DATA="yes"
    [[ "$selected" == *"ssl"* ]] && CONFIGURE_SSL="yes"
    [[ "$selected" == *"backups"* ]] && ENABLE_BACKUPS="yes"
    [[ "$selected" == *"firewall"* ]] && CONFIGURE_FIREWALL="yes"
}

#===============================================================================
# SSL Configuration
#===============================================================================
configure_ssl() {
    if [ "$CONFIGURE_SSL" = "yes" ]; then
        $DIALOG --title "Step 8a: SSL Configuration" \
                --inputbox "
Enter your domain name for SSL certificate:

(e.g., archive.example.com)

Note: Domain must point to this server's IP address.

" $HEIGHT $WIDTH "" 2>$TEMP_FILE
        
        [ $? -ne 0 ] && exit 1
        SSL_DOMAIN=$(cat $TEMP_FILE)
    fi
}

#===============================================================================
# Confirmation
#===============================================================================
confirm_installation() {
    local services_list=""
    [ "$INSTALL_NGINX" = "yes" ] && services_list="${services_list}Nginx, "
    [ "$INSTALL_PHP" = "yes" ] && services_list="${services_list}PHP 8.3, "
    [ "$INSTALL_MYSQL" = "yes" ] && services_list="${services_list}MySQL, "
    [ "$INSTALL_ES" = "yes" ] && services_list="${services_list}Elasticsearch, "
    [ "$INSTALL_GEARMAN" = "yes" ] && services_list="${services_list}Gearman, "
    [ "$INSTALL_MEMCACHED" = "yes" ] && services_list="${services_list}Memcached, "
    [ "$INSTALL_FFMPEG" = "yes" ] && services_list="${services_list}FFmpeg, "
    services_list="${services_list%, }"
    
    local options_list=""
    [ "$LOAD_DEMO_DATA" = "yes" ] && options_list="${options_list}Demo Data, "
    [ "$CONFIGURE_SSL" = "yes" ] && options_list="${options_list}SSL, "
    [ "$ENABLE_BACKUPS" = "yes" ] && options_list="${options_list}Backups, "
    [ "$CONFIGURE_FIREWALL" = "yes" ] && options_list="${options_list}Firewall, "
    options_list="${options_list%, }"
    [ -z "$options_list" ] && options_list="None"
    
    $DIALOG --title "Step 9: Confirm Installation" \
            --yesno "
╔══════════════════════════════════════════════════════════════════╗
║                    INSTALLATION SUMMARY                          ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  Installation Mode: $INSTALL_MODE
║  Install Path:      $ATOM_PATH
║                                                                  ║
║  Services to Install:                                            ║
║    $services_list
║                                                                  ║
║  Database:                                                       ║
║    Host:     $DB_HOST
║    Database: $DB_NAME
║    User:     $DB_USER
║                                                                  ║
║  Elasticsearch: $ES_HOST:$ES_PORT (Heap: $ES_HEAP)
║                                                                  ║
║  Site:                                                           ║
║    Title: $SITE_TITLE
║    URL:   $SITE_URL
║                                                                  ║
║  Admin:  $ADMIN_EMAIL ($ADMIN_USER)
║                                                                  ║
║  Options: $options_list
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝

                  Proceed with installation?
" 30 $WIDTH
    
    return $?
}

#===============================================================================
# Installation Progress
#===============================================================================
run_installation() {
    local total_steps=10
    local current_step=0
    
    # Create log file
    LOG_FILE="/var/log/atom-install-$(date +%Y%m%d-%H%M%S).log"
    exec 3>&1  # Save stdout
    exec 4>&2  # Save stderr
    
    (
    #---------------------------------------------------------------------------
    # Step 1: Update system
    #---------------------------------------------------------------------------
    current_step=$((current_step + 1))
    echo "XXX"
    echo $((current_step * 100 / total_steps))
    echo "Updating system packages..."
    echo "XXX"
    
    apt-get update -qq >> "$LOG_FILE" 2>&1
    
    #---------------------------------------------------------------------------
    # Step 2: Install Nginx
    #---------------------------------------------------------------------------
    if [ "$INSTALL_NGINX" = "yes" ]; then
        current_step=$((current_step + 1))
        echo "XXX"
        echo $((current_step * 100 / total_steps))
        echo "Installing Nginx web server..."
        echo "XXX"
        
        apt-get install -y nginx >> "$LOG_FILE" 2>&1
    fi
    
    #---------------------------------------------------------------------------
    # Step 3: Install PHP
    #---------------------------------------------------------------------------
    if [ "$INSTALL_PHP" = "yes" ]; then
        current_step=$((current_step + 1))
        echo "XXX"
        echo $((current_step * 100 / total_steps))
        echo "Installing PHP 8.3 and extensions..."
        echo "XXX"
        
        # Add PHP repository if needed
        if ! apt-cache show php8.3 &>/dev/null; then
            add-apt-repository -y ppa:ondrej/php >> "$LOG_FILE" 2>&1
            apt-get update -qq >> "$LOG_FILE" 2>&1
        fi
        
        apt-get install -y php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring \
            php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-xsl \
            php8.3-opcache php8.3-apcu php8.3-memcached php8.3-gearman \
            composer >> "$LOG_FILE" 2>&1
    fi
    
    #---------------------------------------------------------------------------
    # Step 4: Install MySQL
    #---------------------------------------------------------------------------
    if [ "$INSTALL_MYSQL" = "yes" ]; then
        current_step=$((current_step + 1))
        echo "XXX"
        echo $((current_step * 100 / total_steps))
        echo "Installing MySQL 8.0..."
        echo "XXX"
        
        # Set root password non-interactively
        debconf-set-selections <<< "mysql-server mysql-server/root_password password ${MYSQL_ROOT_PASS}"
        debconf-set-selections <<< "mysql-server mysql-server/root_password_again password ${MYSQL_ROOT_PASS}"
        
        apt-get install -y mysql-server >> "$LOG_FILE" 2>&1
        
        systemctl start mysql
        systemctl enable mysql
        
        # Secure installation
        mysql -u root -p"${MYSQL_ROOT_PASS}" << EOSQL >> "$LOG_FILE" 2>&1
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOSQL
    fi
    
    #---------------------------------------------------------------------------
    # Step 5: Create Database
    #---------------------------------------------------------------------------
    current_step=$((current_step + 1))
    echo "XXX"
    echo $((current_step * 100 / total_steps))
    echo "Creating AtoM database..."
    echo "XXX"
    
    if [ -n "$MYSQL_ROOT_PASS" ]; then
        MYSQL_CMD="mysql -u root -p${MYSQL_ROOT_PASS}"
    else
        MYSQL_CMD="mysql -u root"
    fi
    
    $MYSQL_CMD << EOSQL >> "$LOG_FILE" 2>&1
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;
EOSQL
    
    #---------------------------------------------------------------------------
    # Step 6: Install Elasticsearch
    #---------------------------------------------------------------------------
    if [ "$INSTALL_ES" = "yes" ]; then
        current_step=$((current_step + 1))
        echo "XXX"
        echo $((current_step * 100 / total_steps))
        echo "Installing Elasticsearch..."
        echo "XXX"
        
        # Add Elasticsearch repository
        wget -qO - https://artifacts.elastic.co/GPG-KEY-elasticsearch | gpg --dearmor -o /usr/share/keyrings/elasticsearch-keyring.gpg 2>> "$LOG_FILE"
        echo "deb [signed-by=/usr/share/keyrings/elasticsearch-keyring.gpg] https://artifacts.elastic.co/packages/8.x/apt stable main" > /etc/apt/sources.list.d/elastic-8.x.list
        
        apt-get update -qq >> "$LOG_FILE" 2>&1
        apt-get install -y elasticsearch >> "$LOG_FILE" 2>&1
        
        # Configure Elasticsearch
        cat > /etc/elasticsearch/elasticsearch.yml << ESCONFIG
cluster.name: atom
node.name: atom-node
path.data: /var/lib/elasticsearch
path.logs: /var/log/elasticsearch
network.host: ${ES_HOST}
http.port: ${ES_PORT}
xpack.security.enabled: false
ESCONFIG
        
        # Set heap size
        sed -i "s/-Xms.*/-Xms${ES_HEAP}/" /etc/elasticsearch/jvm.options.d/heap.options 2>/dev/null || \
        echo "-Xms${ES_HEAP}" > /etc/elasticsearch/jvm.options.d/heap.options
        sed -i "s/-Xmx.*/-Xmx${ES_HEAP}/" /etc/elasticsearch/jvm.options.d/heap.options 2>/dev/null || \
        echo "-Xmx${ES_HEAP}" >> /etc/elasticsearch/jvm.options.d/heap.options
        
        systemctl daemon-reload
        systemctl enable elasticsearch
        systemctl start elasticsearch
    fi
    
    #---------------------------------------------------------------------------
    # Step 7: Install other services
    #---------------------------------------------------------------------------
    current_step=$((current_step + 1))
    echo "XXX"
    echo $((current_step * 100 / total_steps))
    echo "Installing additional services..."
    echo "XXX"
    
    [ "$INSTALL_GEARMAN" = "yes" ] && apt-get install -y gearman-job-server >> "$LOG_FILE" 2>&1
    [ "$INSTALL_MEMCACHED" = "yes" ] && apt-get install -y memcached >> "$LOG_FILE" 2>&1
    [ "$INSTALL_FFMPEG" = "yes" ] && apt-get install -y ffmpeg imagemagick ghostscript poppler-utils >> "$LOG_FILE" 2>&1
    
    apt-get install -y git nodejs npm >> "$LOG_FILE" 2>&1
    
    #---------------------------------------------------------------------------
    # Step 8: Clone AtoM
    #---------------------------------------------------------------------------
    current_step=$((current_step + 1))
    echo "XXX"
    echo $((current_step * 100 / total_steps))
    echo "Downloading AtoM from GitHub..."
    echo "XXX"
    
    if [ "$INSTALL_MODE" != "extensions" ]; then
        rm -rf "${ATOM_PATH}"
        git clone -b "${ATOM_BRANCH}" --depth 1 https://github.com/artefactual/atom.git "${ATOM_PATH}" >> "$LOG_FILE" 2>&1
        
        cd "${ATOM_PATH}"
        composer install --no-dev --no-interaction >> "$LOG_FILE" 2>&1
    fi
    
    #---------------------------------------------------------------------------
    # Step 9: Install AHG Extensions
    #---------------------------------------------------------------------------
    if [ "$INSTALL_MODE" = "complete" ] || [ "$INSTALL_MODE" = "extensions" ]; then
        current_step=$((current_step + 1))
        echo "XXX"
        echo $((current_step * 100 / total_steps))
        echo "Installing AHG Extensions..."
        echo "XXX"
        
        git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-framework.git "${ATOM_PATH}/atom-framework" >> "$LOG_FILE" 2>&1
        git clone --depth 1 https://github.com/ArchiveHeritageGroup/atom-ahg-plugins.git "${ATOM_PATH}/atom-ahg-plugins" >> "$LOG_FILE" 2>&1
        
        cd "${ATOM_PATH}/atom-framework"
        composer install --no-dev --no-interaction >> "$LOG_FILE" 2>&1
        
        # Run framework install
        bash bin/install --auto >> "$LOG_FILE" 2>&1 || true
    fi
    
    #---------------------------------------------------------------------------
    # Step 10: Configure and finalize
    #---------------------------------------------------------------------------
    current_step=$((current_step + 1))
    echo "XXX"
    echo $((current_step * 100 / total_steps))
    echo "Finalizing configuration..."
    echo "XXX"
    
    # Create config.php
    cat > "${ATOM_PATH}/config/config.php" << CONFIGPHP
<?php
return [
    'all' => [
        'propel' => [
            'class' => 'sfPropelDatabase',
            'param' => [
                'encoding' => 'utf8mb4',
                'persistent' => true,
                'pooling' => true,
                'dsn' => 'mysql:host=${DB_HOST};dbname=${DB_NAME};charset=utf8mb4',
                'username' => '${DB_USER}',
                'password' => '${DB_PASS}',
            ],
        ],
    ],
];
CONFIGPHP
    
    # Create directories
    mkdir -p "${ATOM_PATH}/cache" "${ATOM_PATH}/log" "${ATOM_PATH}/uploads" "${ATOM_PATH}/downloads"
    
    # Set permissions
    chown -R www-data:www-data "${ATOM_PATH}"
    chmod -R 755 "${ATOM_PATH}"
    chmod -R 775 "${ATOM_PATH}/cache" "${ATOM_PATH}/log" "${ATOM_PATH}/uploads" "${ATOM_PATH}/downloads"
    
    # Configure Nginx
    cat > /etc/nginx/sites-available/atom << NGINXCONF
server {
    listen 80;
    server_name _;
    root ${ATOM_PATH};
    index index.php;
    
    client_max_body_size 64M;
    
    location / {
        try_files \$uri /index.php\$is_args\$args;
    }
    
    location ~ ^/(index|qubit_dev)\\.php(/|\$) {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_read_timeout 120;
    }
    
    location ~* \\.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    location ~ /\\. {
        deny all;
    }
}
NGINXCONF
    
    ln -sf /etc/nginx/sites-available/atom /etc/nginx/sites-enabled/atom
    rm -f /etc/nginx/sites-enabled/default
    
    # Start services
    systemctl restart nginx
    systemctl restart php8.3-fpm
    
    # Initialize AtoM database
    cd "${ATOM_PATH}"
    
    if [ "$LOAD_DEMO_DATA" = "yes" ]; then
        sudo -u www-data php symfony tools:install --demo --no-confirmation >> "$LOG_FILE" 2>&1 || true
    else
        sudo -u www-data php symfony tools:install --no-confirmation >> "$LOG_FILE" 2>&1 || true
    fi
    
    echo "XXX"
    echo 100
    echo "Installation complete!"
    echo "XXX"
    
    sleep 1
    
    ) | $DIALOG --title "Installing AtoM" --gauge "Preparing installation..." $HEIGHT $WIDTH 0
}

#===============================================================================
# Completion Screen
#===============================================================================
show_completion() {
    local server_ip=$(hostname -I | awk '{print $1}')
    
    $DIALOG --title "Installation Complete!" \
            --msgbox "
╔══════════════════════════════════════════════════════════════════╗
║                                                                  ║
║     ✓  AtoM has been successfully installed!                     ║
║                                                                  ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  Access your AtoM installation at:                               ║
║                                                                  ║
║     http://${server_ip}
║                                                                  ║
║  Login credentials:                                              ║
║     Email:    ${ADMIN_EMAIL}
║     Password: (the password you set)                             ║
║                                                                  ║
║  Installation path: ${ATOM_PATH}
║                                                                  ║
║  Log file: ${LOG_FILE}
║                                                                  ║
╠══════════════════════════════════════════════════════════════════╣
║                                                                  ║
║  Useful commands:                                                ║
║     Clear cache:    php symfony cc                               ║
║     Rebuild search: php symfony search:populate                  ║
║     View logs:      tail -f ${ATOM_PATH}/log/*.log               ║
║                                                                  ║
╚══════════════════════════════════════════════════════════════════╝

                    Press ENTER to finish
" 32 $WIDTH
}

#===============================================================================
# Main
#===============================================================================
main() {
    clear
    
    show_welcome
    select_install_mode
    get_install_path
    select_services
    configure_mysql
    configure_elasticsearch
    configure_site
    configure_admin
    select_options
    
    if [ "$CONFIGURE_SSL" = "yes" ]; then
        configure_ssl
    fi
    
    if confirm_installation; then
        run_installation
        show_completion
    else
        $DIALOG --title "Cancelled" --msgbox "\nInstallation cancelled by user." 8 40
        exit 1
    fi
    
    clear
    echo ""
    echo -e "${GREEN}AtoM installation complete!${NC}"
    echo ""
    echo -e "Access at: ${CYAN}http://$(hostname -I | awk '{print $1}')${NC}"
    echo ""
}

main "$@"
