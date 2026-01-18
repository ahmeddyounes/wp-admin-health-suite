#!/usr/bin/env bash
#
# Forbidden Patterns Check Script
#
# Validates that Plugin::get_instance() is not used inside domain/application
# code. This pattern should only be used in explicit adapter contexts:
#
# ALLOWED locations:
#   - wp-admin-health-suite.php (main plugin entry point)
#   - templates/ directory (template edge adapters)
#   - tests/ directory (test harnesses)
#   - Files containing "EDGE ADAPTER" comment (documented exceptions)
#
# DISALLOWED locations:
#   - includes/Contracts/ (interfaces - should never need service location)
#   - includes/Settings/Contracts/ (interface contracts)
#   - includes/Settings/Domain/ (pure domain logic)
#   - includes/Scheduler/Contracts/ (interface contracts)
#   - includes/Exceptions/ (exception classes)
#   - includes/Services/ (service implementations should use DI)
#   - Pure domain classes without EDGE ADAPTER documentation
#
# Usage:
#   ./scripts/check-forbidden-patterns.sh
#
# Exit codes:
#   0 - All checks passed
#   1 - Forbidden pattern detected
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Project root (script is in scripts/ directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Track errors
ERRORS=0

echo "=============================================="
echo "Forbidden Patterns Check"
echo "=============================================="
echo ""

#
# Check 1: Plugin::get_instance() in forbidden locations
#
echo "[Check 1] Scanning for Plugin::get_instance() in forbidden locations..."
echo ""

# Directories where Plugin::get_instance() is NEVER allowed
FORBIDDEN_DIRS=(
    "includes/Contracts"
    "includes/Settings/Contracts"
    "includes/Settings/Domain"
    "includes/Scheduler/Contracts"
    "includes/Exceptions"
    "includes/Services"
    "includes/Container"
    "includes/AI"
)

forbidden_errors=0

for dir in "${FORBIDDEN_DIRS[@]}"; do
    dir_path="$PROJECT_ROOT/$dir"
    if [[ -d "$dir_path" ]]; then
        while IFS= read -r -d '' php_file; do
            # Search for Plugin::get_instance() pattern
            if grep -n "Plugin::get_instance()" "$php_file" >/dev/null 2>&1; then
                matches=$(grep -n "Plugin::get_instance()" "$php_file" 2>/dev/null || true)
                echo -e "${RED}ERROR:${NC} Forbidden pattern in ${php_file#$PROJECT_ROOT/}"
                echo "       Plugin::get_instance() is not allowed in $dir/"
                echo "       Use dependency injection instead."
                echo ""
                echo "       Occurrences:"
                echo "$matches" | while read -r line; do
                    echo "         $line"
                done
                echo ""
                ((forbidden_errors++)) || true
            fi
        done < <(find "$dir_path" -name "*.php" -print0 2>/dev/null)
    fi
done

if [[ $forbidden_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} No forbidden patterns in pure domain/contract directories"
else
    ERRORS=$((ERRORS + forbidden_errors))
fi
echo ""

#
# Check 2: Plugin::get_instance() without EDGE ADAPTER documentation
#
echo "[Check 2] Checking for undocumented edge adapter usages..."
echo ""

# Directories where Plugin::get_instance() is allowed ONLY with documentation
REQUIRES_DOCS_DIRS=(
    "includes"
)

# Explicitly allowed directories (no documentation needed)
ALLOWED_DIRS=(
    "templates"
    "tests"
)

# Explicitly allowed files (main entry points)
ALLOWED_FILES=(
    "wp-admin-health-suite.php"
    "uninstall.php"
)

undoc_errors=0

for dir in "${REQUIRES_DOCS_DIRS[@]}"; do
    dir_path="$PROJECT_ROOT/$dir"
    if [[ -d "$dir_path" ]]; then
        while IFS= read -r -d '' php_file; do
            rel_path="${php_file#$PROJECT_ROOT/}"

            # Skip if file is in an allowed directory
            skip=false
            for allowed_dir in "${ALLOWED_DIRS[@]}"; do
                if [[ "$rel_path" == "$allowed_dir/"* ]]; then
                    skip=true
                    break
                fi
            done

            # Skip if file is an allowed file
            for allowed_file in "${ALLOWED_FILES[@]}"; do
                if [[ "$rel_path" == "$allowed_file" ]]; then
                    skip=true
                    break
                fi
            done

            # Skip forbidden dirs (already checked above)
            for forbidden_dir in "${FORBIDDEN_DIRS[@]}"; do
                if [[ "$rel_path" == "$forbidden_dir/"* ]]; then
                    skip=true
                    break
                fi
            done

            if [[ "$skip" == true ]]; then
                continue
            fi

            # Check if file contains Plugin::get_instance()
            if grep -q "Plugin::get_instance()" "$php_file" 2>/dev/null; then
                # Check if file contains EDGE ADAPTER documentation
                if ! grep -q "EDGE ADAPTER" "$php_file" 2>/dev/null; then
                    echo -e "${YELLOW}WARNING:${NC} Undocumented edge adapter usage in ${rel_path}"
                    echo "       Plugin::get_instance() requires EDGE ADAPTER comment explaining why DI cannot be used."
                    echo ""
                    echo "       Add a comment like:"
                    echo "       /**"
                    echo "        * EDGE ADAPTER: This class uses Plugin::get_instance() because..."
                    echo "        */"
                    echo ""
                    ((undoc_errors++)) || true
                else
                    echo -e "${CYAN}INFO:${NC} Documented edge adapter in ${rel_path}"
                fi
            fi
        done < <(find "$dir_path" -name "*.php" -print0 2>/dev/null)
    fi
done

if [[ $undoc_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} All edge adapter usages are documented"
else
    echo ""
    echo -e "${YELLOW}NOTE:${NC} Undocumented usages are warnings only (not blocking CI)."
    echo "      Please add EDGE ADAPTER documentation to explain why service location is needed."
fi
echo ""

#
# Check 3: get_container() direct access in domain code
#
echo "[Check 3] Scanning for get_container() in domain code..."
echo ""

container_errors=0

for dir in "${FORBIDDEN_DIRS[@]}"; do
    dir_path="$PROJECT_ROOT/$dir"
    if [[ -d "$dir_path" ]]; then
        while IFS= read -r -d '' php_file; do
            # Search for ->get_container() or get_container() patterns
            if grep -n "get_container()" "$php_file" >/dev/null 2>&1; then
                matches=$(grep -n "get_container()" "$php_file" 2>/dev/null || true)
                echo -e "${RED}ERROR:${NC} Container access in domain code: ${php_file#$PROJECT_ROOT/}"
                echo "       Direct container access violates dependency inversion."
                echo "       Inject dependencies through constructor instead."
                echo ""
                echo "       Occurrences:"
                echo "$matches" | while read -r line; do
                    echo "         $line"
                done
                echo ""
                ((container_errors++)) || true
            fi
        done < <(find "$dir_path" -name "*.php" -print0 2>/dev/null)
    fi
done

if [[ $container_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} No direct container access in domain code"
else
    ERRORS=$((ERRORS + container_errors))
fi
echo ""

#
# Summary
#
echo "=============================================="
if [[ $ERRORS -eq 0 ]]; then
    echo -e "${GREEN}All forbidden pattern checks passed!${NC}"
    if [[ $undoc_errors -gt 0 ]]; then
        echo ""
        echo -e "${YELLOW}$undoc_errors warning(s) about undocumented edge adapters.${NC}"
        echo "Consider adding EDGE ADAPTER comments for maintainability."
    fi
    exit 0
else
    echo -e "${RED}Found $ERRORS forbidden pattern error(s)${NC}"
    echo ""
    echo "These patterns violate the architecture guidelines."
    echo "See docs/developers/edge-adapters.md for allowed exceptions."
    exit 1
fi
