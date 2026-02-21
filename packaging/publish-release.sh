#!/bin/bash
#===============================================================================
# AtoM Heratio - Build & Publish to GitHub Releases
# Builds both .deb packages and uploads them as release assets
#
# Usage:
#   bash publish-release.sh                    # Build + upload
#   bash publish-release.sh --build-only       # Build only, no upload
#   bash publish-release.sh --upload-only       # Upload existing dist/ files
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"
DIST_DIR="${FRAMEWORK_PATH}/dist"

# Get versions
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

MODE="${1:-all}"

echo ""
echo "============================================================"
echo "  AtoM Heratio - Release Publisher"
echo "============================================================"
echo ""
echo "  Heratio Version:  ${HERATIO_VERSION}"
echo "  AtoM Version:     ${ATOM_VERSION}"
echo "  Mode:             ${MODE}"
echo ""

#===============================================================================
# Build packages
#===============================================================================
if [ "$MODE" = "all" ] || [ "$MODE" = "--build-only" ]; then
    step "Building AtoM Heratio package..."
    cd "${SCRIPT_DIR}"
    bash build.sh 2>&1 | tail -5
    echo ""

    step "Building vanilla AtoM package..."
    cd "${SCRIPT_DIR}/atom-vanilla"
    bash build.sh 2>&1 | tail -5
    echo ""
fi

#===============================================================================
# Verify built files
#===============================================================================
HERATIO_DEB="${DIST_DIR}/atom-heratio_${HERATIO_VERSION}-1_all.deb"
ATOM_DEB="${DIST_DIR}/atom_${ATOM_VERSION}-1_all.deb"

if [ ! -f "$HERATIO_DEB" ]; then
    err "Heratio package not found: $HERATIO_DEB"
    exit 1
fi

if [ ! -f "$ATOM_DEB" ]; then
    err "AtoM package not found: $ATOM_DEB"
    exit 1
fi

HERATIO_SIZE=$(du -h "$HERATIO_DEB" | cut -f1)
ATOM_SIZE=$(du -h "$ATOM_DEB" | cut -f1)
HERATIO_SHA=$(sha256sum "$HERATIO_DEB" | cut -d' ' -f1)
ATOM_SHA=$(sha256sum "$ATOM_DEB" | cut -d' ' -f1)

log "Packages ready:"
echo "  atom-heratio_${HERATIO_VERSION}-1_all.deb  (${HERATIO_SIZE})  sha256:${HERATIO_SHA:0:16}..."
echo "  atom_${ATOM_VERSION}-1_all.deb             (${ATOM_SIZE})  sha256:${ATOM_SHA:0:16}..."
echo ""

if [ "$MODE" = "--build-only" ]; then
    echo "Build complete. Packages in: ${DIST_DIR}/"
    exit 0
fi

#===============================================================================
# Upload to GitHub Releases
#===============================================================================
step "Uploading to GitHub Releases..."

# Check gh CLI
if ! command -v gh &>/dev/null; then
    err "GitHub CLI (gh) not found. Install: https://cli.github.com/"
    echo ""
    echo "Manual upload:"
    echo "  1. Go to https://github.com/ArchiveHeritageGroup/atom-framework/releases/new"
    echo "  2. Tag: v${HERATIO_VERSION}"
    echo "  3. Upload: ${HERATIO_DEB}"
    echo "  4. Upload: ${ATOM_DEB}"
    exit 1
fi

# Check authentication
if ! gh auth status &>/dev/null 2>&1; then
    err "Not authenticated with GitHub. Run: gh auth login"
    exit 1
fi

TAG="v${HERATIO_VERSION}"
REPO="ArchiveHeritageGroup/atom-framework"

# Generate release notes
RELEASE_NOTES=$(cat << EOF
## AtoM Heratio v${HERATIO_VERSION}

### Downloads

| Package | Description | Size |
|---------|-------------|------|
| \`atom-heratio_${HERATIO_VERSION}-1_all.deb\` | AtoM 2.10 + Heratio framework + 79 plugins | ${HERATIO_SIZE} |
| \`atom_${ATOM_VERSION}-1_all.deb\` | Vanilla AtoM ${ATOM_VERSION} (no Heratio) | ${ATOM_SIZE} |

### Install

\`\`\`bash
# Option A: AtoM Heratio (recommended)
wget https://github.com/${REPO}/releases/download/${TAG}/atom-heratio_${HERATIO_VERSION}-1_all.deb
sudo apt install ./atom-heratio_${HERATIO_VERSION}-1_all.deb

# Option B: Vanilla AtoM 2.10.1
wget https://github.com/${REPO}/releases/download/${TAG}/atom_${ATOM_VERSION}-1_all.deb
sudo apt install ./atom_${ATOM_VERSION}-1_all.deb
\`\`\`

### APT Repository

\`\`\`bash
# Add repository (one-time setup)
curl -fsSL https://archiveheritagegroup.github.io/atom-framework/gpg.key | sudo gpg --dearmor -o /usr/share/keyrings/atom-heratio.gpg
echo "deb [signed-by=/usr/share/keyrings/atom-heratio.gpg] https://archiveheritagegroup.github.io/atom-framework stable main" | sudo tee /etc/apt/sources.list.d/atom-heratio.list
sudo apt update

# Then install
sudo apt install atom-heratio   # Full platform
sudo apt install atom           # Vanilla AtoM
\`\`\`

### Checksums (SHA-256)

\`\`\`
${HERATIO_SHA}  atom-heratio_${HERATIO_VERSION}-1_all.deb
${ATOM_SHA}  atom_${ATOM_VERSION}-1_all.deb
\`\`\`

### What's Included

**AtoM Heratio** (complete platform):
- AtoM 2.10 base (bundled tarball, no internet required)
- Heratio framework v${HERATIO_VERSION}
- 79 GLAM/DAM plugins
- Bootstrap 5 theme
- Debconf TUI wizard + web configuration wizard
- \`atom-heratio\` CLI management tool

**AtoM ${ATOM_VERSION}** (vanilla):
- Standard AtoM ${ATOM_VERSION} (bundled tarball)
- Debconf TUI wizard for guided setup
- Nginx, PHP-FPM, Elasticsearch auto-configuration

### System Requirements

- Ubuntu 22.04+ / Debian 12+
- PHP 8.1+ (8.3 recommended)
- MySQL 8.0+ / MariaDB 10.6+
- 2 GB RAM minimum, 4 GB recommended
- 5 GB disk space
EOF
)

# Create release
step "Creating GitHub release ${TAG}..."

gh release create "$TAG" \
    --repo "$REPO" \
    --title "AtoM Heratio v${HERATIO_VERSION}" \
    --notes "$RELEASE_NOTES" \
    "$HERATIO_DEB" \
    "$ATOM_DEB" \
    2>&1 || {
    # If tag already exists, upload assets to existing release
    warn "Release ${TAG} may already exist. Uploading assets..."
    gh release upload "$TAG" \
        --repo "$REPO" \
        --clobber \
        "$HERATIO_DEB" \
        "$ATOM_DEB" \
        2>&1 || {
        err "Failed to upload. Check: gh release list --repo ${REPO}"
        exit 1
    }
}

log "Published to GitHub Releases"
echo ""
echo "  Release:  https://github.com/${REPO}/releases/tag/${TAG}"
echo ""

#===============================================================================
# Generate SHA256SUMS file
#===============================================================================
step "Generating checksums file..."

cat > "${DIST_DIR}/SHA256SUMS" << EOF
${HERATIO_SHA}  atom-heratio_${HERATIO_VERSION}-1_all.deb
${ATOM_SHA}  atom_${ATOM_VERSION}-1_all.deb
EOF

log "SHA256SUMS written to ${DIST_DIR}/SHA256SUMS"

echo ""
echo "============================================================"
echo "  Release Published"
echo "============================================================"
echo ""
echo "  GitHub:   https://github.com/${REPO}/releases/tag/${TAG}"
echo ""
echo "  Direct download URLs:"
echo "    https://github.com/${REPO}/releases/download/${TAG}/atom-heratio_${HERATIO_VERSION}-1_all.deb"
echo "    https://github.com/${REPO}/releases/download/${TAG}/atom_${ATOM_VERSION}-1_all.deb"
echo ""
echo "============================================================"
echo ""
