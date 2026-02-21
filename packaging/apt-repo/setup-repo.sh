#!/bin/bash
#===============================================================================
# AtoM Heratio - APT Repository Setup
# Creates a reprepro-based APT repository for GitHub Pages hosting
#
# Usage:
#   bash setup-repo.sh              # Initialize + add current packages
#   bash setup-repo.sh --update     # Update with latest packages from dist/
#   bash setup-repo.sh --publish    # Push to GitHub Pages
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGING_DIR="$(dirname "$SCRIPT_DIR")"
FRAMEWORK_PATH="$(dirname "$PACKAGING_DIR")"
DIST_DIR="${FRAMEWORK_PATH}/dist"
REPO_DIR="${FRAMEWORK_PATH}/apt-repository"
REPO_NAME="ArchiveHeritageGroup/atom-framework"

# Get version
HERATIO_VERSION=$(php -r "\$j=json_decode(file_get_contents('${FRAMEWORK_PATH}/version.json'),true); echo \$j['version'] ?? '2.10.0';")
ATOM_VERSION="2.10.1"

# Colors
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log()  { echo -e "${GREEN}[OK]${NC} $1"; }
step() { echo -e "${CYAN}[=>]${NC} $1"; }
warn() { echo -e "${YELLOW}[!!]${NC} $1"; }
err()  { echo -e "${RED}[ERR]${NC} $1"; }

MODE="${1:-init}"

echo ""
echo "============================================================"
echo "  AtoM Heratio - APT Repository Manager"
echo "============================================================"
echo ""
echo "  Heratio Version: ${HERATIO_VERSION}"
echo "  AtoM Version:    ${ATOM_VERSION}"
echo "  Mode:            ${MODE}"
echo "  Repo Dir:        ${REPO_DIR}"
echo ""

#===============================================================================
# Check dependencies
#===============================================================================
check_deps() {
    local missing=()
    for cmd in reprepro gpg; do
        if ! command -v "$cmd" &>/dev/null; then
            missing+=("$cmd")
        fi
    done

    if [ ${#missing[@]} -gt 0 ]; then
        err "Missing dependencies: ${missing[*]}"
        echo ""
        echo "  Install with:"
        echo "    sudo apt install reprepro gnupg"
        echo ""
        exit 1
    fi
}

#===============================================================================
# Generate GPG key (if not exists)
#===============================================================================
setup_gpg() {
    step "Checking GPG key..."

    local KEY_ID
    KEY_ID=$(gpg --list-keys --with-colons "atom-heratio@theahg.co.za" 2>/dev/null | grep '^pub' | head -1 | cut -d: -f5)

    if [ -z "$KEY_ID" ]; then
        step "Generating GPG key for APT repository..."

        gpg --batch --gen-key << GPGEOF
%no-protection
Key-Type: RSA
Key-Length: 4096
Subkey-Type: RSA
Subkey-Length: 4096
Name-Real: AtoM Heratio
Name-Email: atom-heratio@theahg.co.za
Expire-Date: 0
%commit
GPGEOF

        KEY_ID=$(gpg --list-keys --with-colons "atom-heratio@theahg.co.za" | grep '^pub' | head -1 | cut -d: -f5)
        log "GPG key generated: ${KEY_ID}"
    else
        log "GPG key found: ${KEY_ID}"
    fi

    echo "$KEY_ID"
}

#===============================================================================
# Initialize repository structure
#===============================================================================
init_repo() {
    step "Initializing APT repository..."

    # Get GPG key
    local KEY_ID
    KEY_ID=$(setup_gpg | tail -1)

    # Create directory structure
    mkdir -p "${REPO_DIR}/conf"
    mkdir -p "${REPO_DIR}/incoming"

    # reprepro distributions config
    cat > "${REPO_DIR}/conf/distributions" << EOF
Origin: AtoM Heratio
Label: AtoM Heratio Repository
Codename: stable
Architectures: all amd64 i386
Components: main
Description: AtoM Heratio - GLAM/DAM Platform packages
SignWith: ${KEY_ID}
EOF

    # reprepro options
    cat > "${REPO_DIR}/conf/options" << EOF
verbose
basedir ${REPO_DIR}
ask-passphrase
EOF

    # Export public key
    gpg --armor --export "atom-heratio@theahg.co.za" > "${REPO_DIR}/gpg.key"

    log "Repository initialized"
    log "GPG public key: ${REPO_DIR}/gpg.key"
}

#===============================================================================
# Add packages to repository
#===============================================================================
add_packages() {
    step "Adding packages to repository..."

    local HERATIO_DEB="${DIST_DIR}/atom-heratio_${HERATIO_VERSION}-1_all.deb"
    local ATOM_DEB="${DIST_DIR}/atom_${ATOM_VERSION}-1_all.deb"

    if [ -f "$HERATIO_DEB" ]; then
        step "Adding atom-heratio ${HERATIO_VERSION}..."
        reprepro -b "${REPO_DIR}" includedeb stable "$HERATIO_DEB" 2>&1 || {
            warn "Package may already exist. Removing old version..."
            reprepro -b "${REPO_DIR}" remove stable atom-heratio 2>/dev/null || true
            reprepro -b "${REPO_DIR}" includedeb stable "$HERATIO_DEB"
        }
        log "Added atom-heratio_${HERATIO_VERSION}-1_all.deb"
    else
        warn "Heratio package not found: ${HERATIO_DEB}"
        echo "  Run 'bash build.sh' first"
    fi

    if [ -f "$ATOM_DEB" ]; then
        step "Adding atom ${ATOM_VERSION}..."
        reprepro -b "${REPO_DIR}" includedeb stable "$ATOM_DEB" 2>&1 || {
            warn "Package may already exist. Removing old version..."
            reprepro -b "${REPO_DIR}" remove stable atom 2>/dev/null || true
            reprepro -b "${REPO_DIR}" includedeb stable "$ATOM_DEB"
        }
        log "Added atom_${ATOM_VERSION}-1_all.deb"
    else
        warn "AtoM package not found: ${ATOM_DEB}"
        echo "  Run 'bash atom-vanilla/build.sh' first"
    fi
}

#===============================================================================
# Generate GitHub Pages files
#===============================================================================
generate_pages() {
    step "Generating GitHub Pages files..."

    # Create install script (one-liner for users)
    cat > "${REPO_DIR}/install.sh" << 'INSTALLEOF'
#!/bin/bash
#===============================================================================
# AtoM Heratio - Quick Install Script
# Usage: curl -fsSL https://archiveheritagegroup.github.io/atom-framework/install.sh | sudo bash
#===============================================================================
set -e

echo ""
echo "============================================================"
echo "  AtoM Heratio - Quick Installer"
echo "============================================================"
echo ""

# Check root
if [ "$(id -u)" -ne 0 ]; then
    echo "Error: This script must be run as root (sudo)"
    exit 1
fi

# Check OS
if ! command -v apt &>/dev/null; then
    echo "Error: This installer requires apt (Debian/Ubuntu)"
    exit 1
fi

# Add GPG key
echo "[=>] Adding repository key..."
curl -fsSL https://archiveheritagegroup.github.io/atom-framework/gpg.key | gpg --dearmor -o /usr/share/keyrings/atom-heratio.gpg

# Add repository
echo "[=>] Adding APT repository..."
echo "deb [signed-by=/usr/share/keyrings/atom-heratio.gpg] https://archiveheritagegroup.github.io/atom-framework stable main" > /etc/apt/sources.list.d/atom-heratio.list

# Update
echo "[=>] Updating package list..."
apt update -qq

echo ""
echo "[OK] Repository added successfully!"
echo ""
echo "  Install AtoM Heratio (recommended):"
echo "    sudo apt install atom-heratio"
echo ""
echo "  Install vanilla AtoM 2.10.1:"
echo "    sudo apt install atom"
echo ""
echo "============================================================"
echo ""
INSTALLEOF
    chmod +x "${REPO_DIR}/install.sh"

    # Create index.html for GitHub Pages
    cat > "${REPO_DIR}/index.html" << HTMLEOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtoM Heratio - APT Repository</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1a1a2e; background: #f8f9fa; }
        .hero { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%); color: white; padding: 60px 20px; text-align: center; }
        .hero h1 { font-size: 2.5em; margin-bottom: 10px; }
        .hero p { font-size: 1.2em; opacity: 0.9; max-width: 600px; margin: 0 auto; }
        .container { max-width: 900px; margin: 0 auto; padding: 40px 20px; }
        .card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .card h2 { color: #1a1a2e; margin-bottom: 16px; font-size: 1.4em; }
        .card h3 { color: #16213e; margin: 20px 0 10px; font-size: 1.1em; }
        pre { background: #1a1a2e; color: #e2e8f0; padding: 16px 20px; border-radius: 8px; overflow-x: auto; font-size: 0.9em; line-height: 1.8; margin: 12px 0; }
        code { font-family: 'SF Mono', 'Fira Code', monospace; }
        .badge { display: inline-block; background: #0f3460; color: white; padding: 4px 12px; border-radius: 20px; font-size: 0.85em; margin-right: 8px; }
        .badge.green { background: #2d6a4f; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { padding: 10px 14px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; font-weight: 600; }
        .footer { text-align: center; padding: 30px; color: #6c757d; font-size: 0.9em; }
        a { color: #0f3460; }
    </style>
</head>
<body>
    <div class="hero">
        <h1>AtoM Heratio</h1>
        <p>GLAM/DAM Platform for Galleries, Libraries, Archives, Museums & Digital Asset Management</p>
    </div>
    <div class="container">
        <div class="card">
            <h2>Quick Install</h2>
            <p>One command to add the repository and install:</p>
            <pre><code>curl -fsSL https://archiveheritagegroup.github.io/atom-framework/install.sh | sudo bash
sudo apt install atom-heratio</code></pre>
        </div>

        <div class="card">
            <h2>Manual Setup</h2>
            <h3>1. Add Repository Key</h3>
            <pre><code>curl -fsSL https://archiveheritagegroup.github.io/atom-framework/gpg.key | sudo gpg --dearmor -o /usr/share/keyrings/atom-heratio.gpg</code></pre>

            <h3>2. Add Repository</h3>
            <pre><code>echo "deb [signed-by=/usr/share/keyrings/atom-heratio.gpg] https://archiveheritagegroup.github.io/atom-framework stable main" | sudo tee /etc/apt/sources.list.d/atom-heratio.list</code></pre>

            <h3>3. Install</h3>
            <pre><code>sudo apt update
sudo apt install atom-heratio   # Full platform (recommended)
sudo apt install atom           # Vanilla AtoM 2.10.1</code></pre>
        </div>

        <div class="card">
            <h2>Available Packages</h2>
            <table>
                <tr>
                    <th>Package</th>
                    <th>Description</th>
                    <th>Version</th>
                </tr>
                <tr>
                    <td><code>atom-heratio</code></td>
                    <td>AtoM 2.10 + Heratio framework + 79 GLAM/DAM plugins</td>
                    <td><span class="badge green">HERATIO_VERSION</span></td>
                </tr>
                <tr>
                    <td><code>atom</code></td>
                    <td>Vanilla AtoM 2.10.1 (no Heratio)</td>
                    <td><span class="badge">2.10.1</span></td>
                </tr>
            </table>
        </div>

        <div class="card">
            <h2>Direct Downloads</h2>
            <p>Download .deb packages directly from <a href="https://github.com/ArchiveHeritageGroup/atom-framework/releases">GitHub Releases</a>:</p>
            <pre><code># AtoM Heratio (recommended)
wget https://github.com/ArchiveHeritageGroup/atom-framework/releases/latest/download/atom-heratio_HERATIO_VERSION-1_all.deb
sudo apt install ./atom-heratio_HERATIO_VERSION-1_all.deb

# Vanilla AtoM
wget https://github.com/ArchiveHeritageGroup/atom-framework/releases/latest/download/atom_2.10.1-1_all.deb
sudo apt install ./atom_2.10.1-1_all.deb</code></pre>
        </div>

        <div class="card">
            <h2>System Requirements</h2>
            <table>
                <tr><th>Component</th><th>Minimum</th><th>Recommended</th></tr>
                <tr><td>OS</td><td>Ubuntu 22.04 / Debian 12</td><td>Ubuntu 24.04</td></tr>
                <tr><td>PHP</td><td>8.1</td><td>8.3</td></tr>
                <tr><td>MySQL</td><td>8.0 / MariaDB 10.6</td><td>MySQL 8.0</td></tr>
                <tr><td>RAM</td><td>2 GB</td><td>4 GB</td></tr>
                <tr><td>Disk</td><td>5 GB</td><td>20 GB</td></tr>
                <tr><td>Elasticsearch</td><td>7.x (optional)</td><td>7.17</td></tr>
            </table>
        </div>
    </div>
    <div class="footer">
        <p>&copy; The Archive and Heritage Group (Pty) Ltd | <a href="https://github.com/ArchiveHeritageGroup">GitHub</a> | <a href="https://theahg.co.za">theahg.co.za</a></p>
    </div>
</body>
</html>
HTMLEOF

    # Replace version placeholders
    sed -i "s/HERATIO_VERSION/${HERATIO_VERSION}/g" "${REPO_DIR}/index.html"

    log "GitHub Pages files generated"
}

#===============================================================================
# Publish to GitHub Pages
#===============================================================================
publish() {
    step "Publishing to GitHub Pages..."

    if ! command -v gh &>/dev/null; then
        err "GitHub CLI (gh) not found."
        echo ""
        echo "  Manual steps:"
        echo "  1. Copy ${REPO_DIR}/ contents to gh-pages branch"
        echo "  2. Push to GitHub"
        echo "  3. Enable GitHub Pages in repo settings (gh-pages branch)"
        echo ""
        exit 1
    fi

    if ! gh auth status &>/dev/null 2>&1; then
        err "Not authenticated with GitHub. Run: gh auth login"
        exit 1
    fi

    local TEMP_DIR="/tmp/atom-heratio-pages-$$"
    rm -rf "$TEMP_DIR"

    # Clone just the gh-pages branch (or create it)
    if gh api "repos/${REPO_NAME}/branches/gh-pages" &>/dev/null 2>&1; then
        step "Cloning existing gh-pages branch..."
        git clone --branch gh-pages --single-branch --depth 1 \
            "https://github.com/${REPO_NAME}.git" "$TEMP_DIR" 2>/dev/null
    else
        step "Creating gh-pages branch..."
        mkdir -p "$TEMP_DIR"
        cd "$TEMP_DIR"
        git init
        git checkout -b gh-pages
        git remote add origin "https://github.com/${REPO_NAME}.git"
    fi

    # Copy repository files
    step "Copying repository files..."
    rsync -a --delete \
        --exclude='.git' \
        --exclude='conf/' \
        --exclude='incoming/' \
        --exclude='db/' \
        "${REPO_DIR}/" "${TEMP_DIR}/"

    # Copy the pool and dists directories (actual packages)
    if [ -d "${REPO_DIR}/pool" ]; then
        rsync -a "${REPO_DIR}/pool/" "${TEMP_DIR}/pool/"
    fi
    if [ -d "${REPO_DIR}/dists" ]; then
        rsync -a "${REPO_DIR}/dists/" "${TEMP_DIR}/dists/"
    fi

    # Add .nojekyll for GitHub Pages
    touch "${TEMP_DIR}/.nojekyll"

    # Commit and push
    cd "$TEMP_DIR"
    git add -A
    git commit -m "Update APT repository - Heratio ${HERATIO_VERSION}" 2>/dev/null || {
        warn "No changes to publish"
        rm -rf "$TEMP_DIR"
        return
    }
    git push origin gh-pages --force

    rm -rf "$TEMP_DIR"

    log "Published to GitHub Pages"
    echo ""
    echo "  Repository URL: https://archiveheritagegroup.github.io/atom-framework/"
    echo "  GPG Key:        https://archiveheritagegroup.github.io/atom-framework/gpg.key"
    echo "  Quick Install:  https://archiveheritagegroup.github.io/atom-framework/install.sh"
    echo ""
}

#===============================================================================
# Main
#===============================================================================
case "$MODE" in
    init|"")
        check_deps
        init_repo
        add_packages
        generate_pages
        echo ""
        echo "============================================================"
        echo "  APT Repository Ready"
        echo "============================================================"
        echo ""
        echo "  Repository: ${REPO_DIR}/"
        echo ""
        echo "  Next steps:"
        echo "    bash setup-repo.sh --publish   # Push to GitHub Pages"
        echo ""
        echo "  Or manually:"
        echo "    1. Copy ${REPO_DIR}/ to gh-pages branch"
        echo "    2. Enable GitHub Pages in repo settings"
        echo ""
        echo "============================================================"
        echo ""
        ;;
    --update)
        check_deps
        add_packages
        generate_pages
        log "Repository updated with latest packages"
        ;;
    --publish)
        publish
        ;;
    *)
        echo "Usage: bash setup-repo.sh [--update|--publish]"
        echo ""
        echo "  (no args)    Initialize repository + add packages"
        echo "  --update     Add latest packages from dist/"
        echo "  --publish    Push to GitHub Pages"
        echo ""
        exit 1
        ;;
esac
