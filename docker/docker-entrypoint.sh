#!/bin/bash
set -e

ATOM_ROOT="/usr/share/nginx/atom"

# Wait for MySQL
echo "Waiting for MySQL..."
until mysql -h "$ATOM_DB_HOST" -u "$ATOM_DB_USER" -p"$ATOM_DB_PASS" -e "SELECT 1" &> /dev/null; do
    sleep 2
done
echo "MySQL is ready!"

# Run framework install if not done
if [ ! -f "${ATOM_ROOT}/.ahg-installed" ]; then
    echo "Running first-time setup..."
    cd "${ATOM_ROOT}/atom-framework"
    bash bin/install
    touch "${ATOM_ROOT}/.ahg-installed"
fi

# Clear cache
rm -rf "${ATOM_ROOT}/cache/"*

# Set permissions
chown -R www-data:www-data "${ATOM_ROOT}/cache" "${ATOM_ROOT}/log" "${ATOM_ROOT}/uploads"

exec "$@"
