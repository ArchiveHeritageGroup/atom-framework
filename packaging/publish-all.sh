#!/bin/bash
#===============================================================================
# AtoM Heratio - Full Release Pipeline
# Builds packages + publishes to all channels
#
# Usage:
#   bash publish-all.sh                  # Full pipeline
#   bash publish-all.sh --skip-build     # Publish existing packages only
#===============================================================================
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FRAMEWORK_PATH="$(dirname "$SCRIPT_DIR")"

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

SKIP_BUILD=false
[ "$1" = "--skip-build" ] && SKIP_BUILD=true

echo ""
echo "============================================================"
echo "  AtoM Heratio - Full Release Pipeline"
echo "============================================================"
echo ""
echo "  Heratio Version: ${HERATIO_VERSION}"
echo "  AtoM Version:    ${ATOM_VERSION}"
echo "  Skip Build:      ${SKIP_BUILD}"
echo ""

#===============================================================================
# Step 1: Build packages
#===============================================================================
if [ "$SKIP_BUILD" = false ]; then
    echo "------------------------------------------------------------"
    step "STEP 1/5: Building packages..."
    echo "------------------------------------------------------------"
    echo ""

    step "Building AtoM Heratio package..."
    cd "$SCRIPT_DIR"
    bash build.sh 2>&1 | tail -8
    echo ""

    step "Building vanilla AtoM package..."
    cd "${SCRIPT_DIR}/atom-vanilla"
    bash build.sh 2>&1 | tail -8
    echo ""

    log "Both packages built successfully"
else
    echo "------------------------------------------------------------"
    step "STEP 1/5: Skipping build (--skip-build)"
    echo "------------------------------------------------------------"
fi
echo ""

#===============================================================================
# Step 2: Verify packages
#===============================================================================
echo "------------------------------------------------------------"
step "STEP 2/5: Verifying packages..."
echo "------------------------------------------------------------"
echo ""

DIST_DIR="${FRAMEWORK_PATH}/dist"
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

log "Both packages verified"
echo "  atom-heratio: $(du -h "$HERATIO_DEB" | cut -f1)"
echo "  atom:         $(du -h "$ATOM_DEB" | cut -f1)"
echo ""

#===============================================================================
# Step 3: GitHub Release
#===============================================================================
echo "------------------------------------------------------------"
step "STEP 3/5: Publishing to GitHub Releases..."
echo "------------------------------------------------------------"
echo ""

if command -v gh &>/dev/null && gh auth status &>/dev/null 2>&1; then
    cd "$SCRIPT_DIR"
    bash publish-release.sh --upload-only 2>&1 | tail -15
    log "GitHub Release published"
else
    warn "GitHub CLI not available or not authenticated"
    warn "Skipping GitHub Release (run publish-release.sh manually)"
fi
echo ""

#===============================================================================
# Step 4: APT Repository
#===============================================================================
echo "------------------------------------------------------------"
step "STEP 4/5: Updating APT repository..."
echo "------------------------------------------------------------"
echo ""

if command -v reprepro &>/dev/null; then
    cd "${SCRIPT_DIR}/apt-repo"
    if [ -d "${FRAMEWORK_PATH}/apt-repository" ]; then
        bash setup-repo.sh --update 2>&1 | tail -10
    else
        bash setup-repo.sh 2>&1 | tail -15
    fi
    log "APT repository updated"
else
    warn "reprepro not installed. Skipping APT repository."
    warn "Install with: sudo apt install reprepro"
fi
echo ""

#===============================================================================
# Step 5: Download page
#===============================================================================
echo "------------------------------------------------------------"
step "STEP 5/5: Generating download page..."
echo "------------------------------------------------------------"
echo ""

cd "${SCRIPT_DIR}/download-page"
bash generate.sh 2>&1 | tail -8
log "Download page generated"
echo ""

#===============================================================================
# Summary
#===============================================================================
echo ""
echo "============================================================"
echo "  Release Pipeline Complete"
echo "============================================================"
echo ""
echo "  Version: ${HERATIO_VERSION}"
echo ""
echo "  Outputs:"
echo "    Packages:       ${DIST_DIR}/"
echo "    Download page:  ${DIST_DIR}/download-page/"
echo ""
echo "  Distribution channels:"

if command -v gh &>/dev/null && gh auth status &>/dev/null 2>&1; then
    echo "    GitHub Release: https://github.com/ArchiveHeritageGroup/atom-framework/releases/tag/v${HERATIO_VERSION}"
fi

if [ -d "${FRAMEWORK_PATH}/apt-repository" ]; then
    echo "    APT Repo:       ${FRAMEWORK_PATH}/apt-repository/"
    echo "                    (run: bash apt-repo/setup-repo.sh --publish)"
fi

echo "    Download page:  ${DIST_DIR}/download-page/index.html"
echo ""
echo "  Next steps:"
echo "    1. Push APT repo to GitHub Pages:  bash apt-repo/setup-repo.sh --publish"
echo "    2. Deploy download page to theahg.co.za"
echo ""
echo "============================================================"
echo ""
