#!/bin/bash
set -e

ATOM_ROOT="/usr/share/nginx/atom"

# Wait for MySQL
echo "Waiting for MySQL..."
until mysqladmin ping -h "${ATOM_MYSQL_HOST:-mysql}" --silent 2>/dev/null; do
    sleep 2
done
echo "MySQL is ready!"

# Check if AtoM is installed
if [ ! -f "${ATOM_ROOT}/config/config.php" ]; then
    echo "Creating config.php..."
    cat > "${ATOM_ROOT}/config/config.php" << EOF
<?php
return array (
  'all' => array (
    'propel' => array (
      'class' => 'sfPropelDatabase',
      'param' => array (
        'encoding' => 'utf8mb4',
        'persistent' => true,
        'pooling' => true,
        'dsn' => '${ATOM_MYSQL_DSN:-mysql:host=mysql;dbname=atom;charset=utf8mb4}',
        'username' => '${ATOM_MYSQL_USERNAME:-atom}',
        'password' => '${ATOM_MYSQL_PASSWORD:-AtoM@123}',
      ),
    ),
  ),
);
EOF
fi

# Run AHG Framework install if needed
if [ -f "${ATOM_ROOT}/atom-framework/bin/install" ]; then
    if [ ! -f "${ATOM_ROOT}/.ahg-installed" ]; then
        echo "Running AHG Framework installation..."
        cd "${ATOM_ROOT}/atom-framework"
        bash bin/install --auto || true
        touch "${ATOM_ROOT}/.ahg-installed"
    fi
fi

# Clear cache
cd "${ATOM_ROOT}"
php symfony cc 2>/dev/null || true

# Set permissions
chown -R www-data:www-data "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads"

echo "Starting services..."
exec "$@"
