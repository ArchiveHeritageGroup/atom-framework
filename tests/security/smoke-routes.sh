#!/usr/bin/env bash
# =============================================================================
# DAST Smoke Test - Route & Security Header Checks
# =============================================================================
# Usage: ./smoke-routes.sh [BASE_URL]
# Default BASE_URL: https://psis.theahg.co.za
# Exit code: 0 if all checks pass, 1 if any check fails
# =============================================================================

set -euo pipefail

BASE_URL="${1:-https://psis.theahg.co.za}"
# Strip trailing slash if present
BASE_URL="${BASE_URL%/}"

FAIL=0
PASS_COUNT=0
FAIL_COUNT=0

pass() {
    echo "  PASS: $1"
    PASS_COUNT=$((PASS_COUNT + 1))
}

fail() {
    echo "  FAIL: $1"
    FAIL_COUNT=$((FAIL_COUNT + 1))
    FAIL=1
}

echo "============================================="
echo "DAST Smoke Test"
echo "Target: ${BASE_URL}"
echo "Date:   $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "============================================="
echo ""

# -----------------------------------------------------------------
# 1. Route status code checks
# -----------------------------------------------------------------
echo "[1] Route Status Code Checks"
echo "---------------------------------------------"

# Helper: check HTTP status code against one or two expected values
# Usage: check_status "LABEL" "PATH" EXPECTED1 [EXPECTED2]
check_status() {
    local label="$1"
    local path="$2"
    local expected1="$3"
    local expected2="${4:-}"

    local status
    status=$(curl -s -o /dev/null -w '%{http_code}' -k --max-time 15 "${BASE_URL}${path}")

    if [ "$status" = "$expected1" ]; then
        pass "${label} -> ${status} (expected ${expected1}${expected2:+ or ${expected2}})"
    elif [ -n "$expected2" ] && [ "$status" = "$expected2" ]; then
        pass "${label} -> ${status} (expected ${expected1} or ${expected2})"
    else
        fail "${label} -> ${status} (expected ${expected1}${expected2:+ or ${expected2}})"
    fi
}

check_status "GET /" "/" "200"
check_status "GET /index.php/informationobject/browse" "/index.php/informationobject/browse" "200"
check_status "GET /admin/integrity (unauth)" "/admin/integrity" "302" "401"
check_status "GET /api/integrity/stats (unauth)" "/api/integrity/stats" "401" "403"

echo ""

# -----------------------------------------------------------------
# 2. Security header checks on /
# -----------------------------------------------------------------
echo "[2] Security Header Checks (GET /)"
echo "---------------------------------------------"

HEADERS=$(curl -s -D - -o /dev/null -k --max-time 15 "${BASE_URL}/")

# X-Content-Type-Options: nosniff
if echo "$HEADERS" | grep -qi '^X-Content-Type-Options:.*nosniff'; then
    pass "X-Content-Type-Options: nosniff"
else
    fail "X-Content-Type-Options: nosniff not found"
fi

# X-Frame-Options present
if echo "$HEADERS" | grep -qi '^X-Frame-Options:'; then
    pass "X-Frame-Options header present"
else
    fail "X-Frame-Options header not present"
fi

# Content-Security-Policy present
if echo "$HEADERS" | grep -qi '^Content-Security-Policy:'; then
    pass "Content-Security-Policy header present"
else
    fail "Content-Security-Policy header not present"
fi

echo ""

# -----------------------------------------------------------------
# 3. Version disclosure check
# -----------------------------------------------------------------
echo "[3] Version Disclosure Check (Server header)"
echo "---------------------------------------------"

SERVER_HEADER=$(echo "$HEADERS" | grep -i '^Server:' | head -1 || true)

if [ -z "$SERVER_HEADER" ]; then
    pass "Server header not present (no disclosure)"
elif echo "$SERVER_HEADER" | grep -qP '/[0-9]+\.[0-9]+'; then
    fail "Server header discloses version: $(echo "$SERVER_HEADER" | tr -d '\r')"
else
    pass "Server header present but no version number: $(echo "$SERVER_HEADER" | tr -d '\r')"
fi

echo ""

# -----------------------------------------------------------------
# Summary
# -----------------------------------------------------------------
echo "============================================="
echo "Results: ${PASS_COUNT} passed, ${FAIL_COUNT} failed"
echo "============================================="

exit $FAIL
