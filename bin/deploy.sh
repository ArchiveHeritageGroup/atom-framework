#!/bin/bash
# Extension Deployment Script for AtoM 2.10
# Run as root on server 192.168.0.112

ATOM_DIR="/usr/share/nginx/archive"

echo "=== Deploying Extension Suites ==="

# 1. Copy Extensions to atom-framework
echo "Copying Extensions..."
mkdir -p "$ATOM_DIR/atom-framework/src/Extensions"
cp -r Extensions/* "$ATOM_DIR/atom-framework/src/Extensions/"

# 2. Copy Plugin
echo "Copying arExtensionsPlugin..."
cp -r arExtensionsPlugin "$ATOM_DIR/plugins/"

# 3. Set permissions
echo "Setting permissions..."
chown -R www-data:www-data "$ATOM_DIR/atom-framework/src/Extensions"
chown -R www-data:www-data "$ATOM_DIR/plugins/arExtensionsPlugin"

# 4. Clear cache
echo "Clearing cache..."
rm -rf "$ATOM_DIR/cache/"*
php "$ATOM_DIR/symfony" cc 2>/dev/null || true

echo ""
echo "=== Deployment Complete ==="
echo ""
echo "Now run migrations at:"
echo "https://wdb.theahg.co.za/index.php/extensions/migrate?key=ahg2025"
echo ""
echo "Or run manually via CLI:"
echo "cd $ATOM_DIR && php symfony extensions:migrate"
