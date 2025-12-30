#!/bin/bash

# Fix script for AtomExtensions\Database\DatabaseBootstrap not found error
# Run this on the server: bash fix-autoloader.sh

FRAMEWORK_DIR="/usr/share/nginx/archive/atom-framework"
BOOTSTRAP_FILE="$FRAMEWORK_DIR/bootstrap.php"

echo "=== AtoM Framework v2 Autoloader Fix ==="
echo ""

# Check if framework exists
if [ ! -d "$FRAMEWORK_DIR" ]; then
    echo "ERROR: Framework directory not found at $FRAMEWORK_DIR"
    exit 1
fi

# Check if bootstrap exists
if [ ! -f "$BOOTSTRAP_FILE" ]; then
    echo "ERROR: Bootstrap file not found at $BOOTSTRAP_FILE"
    exit 1
fi

# Check if Database namespace is already registered
if grep -q "AtomExtensions.*Database" "$BOOTSTRAP_FILE"; then
    echo "Database namespace already registered in bootstrap.php"
else
    echo "Adding Database namespace to autoloader..."
    
    # Add the Database namespace line before Extensions
    sed -i "/addPsr4('AtomExtensions.*Extensions/i \$loader->addPsr4('AtomExtensions\\\\\\\\Database\\\\\\\\', __DIR__ . '/src/Database/');" "$BOOTSTRAP_FILE"
    
    echo "Added Database namespace registration"
fi

# Verify the Database directory and class exist
if [ ! -d "$FRAMEWORK_DIR/src/Database" ]; then
    echo "Creating Database directory..."
    mkdir -p "$FRAMEWORK_DIR/src/Database"
fi

if [ ! -f "$FRAMEWORK_DIR/src/Database/DatabaseBootstrap.php" ]; then
    echo "ERROR: DatabaseBootstrap.php not found in $FRAMEWORK_DIR/src/Database/"
    echo "You may need to copy this file from your development environment"
fi

# Clear AtoM cache
echo ""
echo "Clearing AtoM cache..."
cd /usr/share/nginx/archive
php symfony cc 2>/dev/null || echo "Cache clear command not found"

echo ""
echo "=== Fix Complete ==="
echo ""
echo "If you still get errors, verify that:"
echo "1. $FRAMEWORK_DIR/src/Database/DatabaseBootstrap.php exists"
echo "2. The bootstrap.php file is being included in AtoM"
echo ""
echo "To test, run:"
echo "  php -r \"require '$BOOTSTRAP_FILE'; echo 'Bootstrap OK';\""
