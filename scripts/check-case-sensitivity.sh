#!/usr/bin/env bash
#
# Case-Sensitivity Validation Script
#
# Validates that PHP file references (require, include, class instantiation)
# match actual file paths with correct casing. This prevents regressions that
# work on case-insensitive filesystems (macOS) but fail on case-sensitive
# ones (Linux CI).
#
# Usage:
#   ./scripts/check-case-sensitivity.sh
#
# Exit codes:
#   0 - All checks passed
#   1 - Case mismatch detected
#
# Note: This script requires Bash 4.0+ for associative arrays.
# Ubuntu (CI) has Bash 4+. macOS users may need: brew install bash
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
NC='\033[0m' # No Color

# Project root (script is in scripts/ directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Track errors
ERRORS=0

echo "=============================================="
echo "Case-Sensitivity Validation"
echo "=============================================="
echo ""

# Check Bash version
BASH_MAJOR_VERSION="${BASH_VERSION%%.*}"
if [[ "$BASH_MAJOR_VERSION" -lt 4 ]]; then
    echo -e "${YELLOW}WARNING:${NC} Bash $BASH_VERSION detected. This script requires Bash 4.0+."
    echo "         On macOS, install with: brew install bash"
    echo "         Then run: /usr/local/bin/bash $0"
    echo ""
    echo "         Skipping detailed checks. CI (Ubuntu) will run full validation."
    echo "=============================================="
    echo -e "${YELLOW}Checks skipped due to Bash version${NC}"
    exit 0
fi

#
# Check 1: Validate PSR-4 namespace to file path mappings
#
echo "[Check 1] Validating PSR-4 namespace mappings..."

psr4_errors=0
while IFS= read -r -d '' php_file; do
    # Extract namespace declaration
    namespace=$(grep -E "^namespace\s+WPAdminHealth" "$php_file" 2>/dev/null | head -1 | sed -E 's/namespace\s+//; s/\s*;.*//' || true)

    if [[ -z "$namespace" ]]; then
        continue
    fi

    # Extract class/interface/trait name
    classname=$(grep -E "^(class|interface|trait|abstract class|final class)\s+\w+" "$php_file" 2>/dev/null | head -1 | sed -E 's/.*(class|interface|trait)\s+(\w+).*/\2/' || true)

    if [[ -z "$classname" ]]; then
        continue
    fi

    # Build expected file path from namespace + class
    # WPAdminHealth\Integrations\ACF -> includes/Integrations/ACF.php
    expected_path="${namespace//WPAdminHealth/includes}"
    expected_path="${expected_path//\\//}/${classname}.php"

    # Get actual relative path
    actual_path="${php_file#$PROJECT_ROOT/}"

    # Compare (case-sensitive)
    if [[ "$expected_path" != "$actual_path" ]]; then
        echo -e "${RED}ERROR:${NC} PSR-4 mismatch in $actual_path"
        echo "       Namespace: $namespace\\$classname"
        echo "       Expected:  $expected_path"
        echo "       Actual:    $actual_path"
        ((psr4_errors++)) || true
    fi
done < <(find "$PROJECT_ROOT/includes" -name "*.php" -print0 2>/dev/null)

if [[ $psr4_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} All PSR-4 namespace mappings are correct"
else
    ERRORS=$((ERRORS + psr4_errors))
fi
echo ""

#
# Check 2: Validate require/require_once/include/include_once statements
#
echo "[Check 2] Validating require/include statements..."

require_errors=0
while IFS= read -r -d '' php_file; do
    file_dir=$(dirname "$php_file")

    # Extract require/include statements with file paths
    # Match patterns like: require_once __DIR__ . '/File.php'
    # or: require_once WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/File.php'
    while IFS= read -r line; do
        # Skip empty lines
        [[ -z "$line" ]] && continue

        # Extract the path string (handle various patterns)
        # Pattern 1: __DIR__ . '/path/to/file.php'
        if [[ "$line" =~ __DIR__[[:space:]]*\.[[:space:]]*[\'\"]([^\'\"]+)[\'\"] ]]; then
            rel_path="${BASH_REMATCH[1]}"
            # Resolve relative to the file's directory
            if [[ "$rel_path" == /* ]]; then
                rel_path="${rel_path:1}"  # Remove leading /
            fi
            target_path="$file_dir/$rel_path"
            target_path=$(cd "$(dirname "$target_path")" 2>/dev/null && pwd)/$(basename "$target_path") 2>/dev/null || continue

            if [[ ! -f "$target_path" ]]; then
                # Check if it's a case mismatch by doing case-insensitive search
                expected_dir=$(dirname "$target_path")
                expected_file=$(basename "$target_path")

                if [[ -d "$expected_dir" ]]; then
                    actual_file=$(find "$expected_dir" -maxdepth 1 -iname "$expected_file" 2>/dev/null | head -1)
                    if [[ -n "$actual_file" && "$actual_file" != "$target_path" ]]; then
                        echo -e "${RED}ERROR:${NC} Case mismatch in ${php_file#$PROJECT_ROOT/}"
                        echo "       Referenced: $rel_path"
                        echo "       Actual file: $(basename "$actual_file")"
                        ((require_errors++)) || true
                    fi
                fi
            fi
        fi

        # Pattern 2: WP_ADMIN_HEALTH_PLUGIN_DIR . 'includes/path/to/file.php'
        if [[ "$line" =~ WP_ADMIN_HEALTH_PLUGIN_DIR[[:space:]]*\.[[:space:]]*[\'\"]([^\'\"]+)[\'\"] ]]; then
            rel_path="${BASH_REMATCH[1]}"
            target_path="$PROJECT_ROOT/$rel_path"

            if [[ ! -f "$target_path" ]]; then
                expected_dir=$(dirname "$target_path")
                expected_file=$(basename "$target_path")

                if [[ -d "$expected_dir" ]]; then
                    actual_file=$(find "$expected_dir" -maxdepth 1 -iname "$expected_file" 2>/dev/null | head -1)
                    if [[ -n "$actual_file" && "$actual_file" != "$target_path" ]]; then
                        echo -e "${RED}ERROR:${NC} Case mismatch in ${php_file#$PROJECT_ROOT/}"
                        echo "       Referenced: $rel_path"
                        echo "       Actual file: $(basename "$actual_file")"
                        ((require_errors++)) || true
                    fi
                fi
            fi
        fi
    done < <(grep -E "(require|include)(_once)?\s*\(" "$php_file" 2>/dev/null || true)
done < <(find "$PROJECT_ROOT" -name "*.php" -not -path "*/vendor/*" -not -path "*/node_modules/*" -print0 2>/dev/null)

if [[ $require_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} All require/include paths are correct"
else
    ERRORS=$((ERRORS + require_errors))
fi
echo ""

#
# Check 3: Validate use statements match existing classes
#
echo "[Check 3] Validating use statement class references..."

use_errors=0

# Create temp file for class map (portable alternative to associative arrays)
CLASS_MAP_FILE=$(mktemp)
trap "rm -f $CLASS_MAP_FILE" EXIT

# Build class map from discovered PHP files
while IFS= read -r -d '' php_file; do
    # Get the fully qualified class name from namespace + class
    namespace=$(grep -E "^namespace\s+" "$php_file" 2>/dev/null | head -1 | sed -E 's/namespace\s+//; s/\s*;.*//' || true)
    classname=$(grep -E "^(class|interface|trait|abstract class|final class)\s+\w+" "$php_file" 2>/dev/null | head -1 | sed -E 's/.*(class|interface|trait)\s+(\w+).*/\2/' || true)

    if [[ -n "$namespace" && -n "$classname" ]]; then
        fqcn="${namespace}\\${classname}"
        echo "$fqcn" >> "$CLASS_MAP_FILE"
    fi
done < <(find "$PROJECT_ROOT/includes" -name "*.php" -print0 2>/dev/null)

# Now check use statements in PHP files
while IFS= read -r -d '' php_file; do
    while IFS= read -r use_class; do
        # Skip empty
        [[ -z "$use_class" ]] && continue

        # Only check WPAdminHealth namespace
        if [[ "$use_class" != WPAdminHealth\\* ]]; then
            continue
        fi

        # Check if class exists in our map (exact match)
        if ! grep -qxF "$use_class" "$CLASS_MAP_FILE" 2>/dev/null; then
            # Try case-insensitive match
            found_match=$(grep -i "^${use_class}$" "$CLASS_MAP_FILE" 2>/dev/null | head -1 || true)

            if [[ -n "$found_match" && "$found_match" != "$use_class" ]]; then
                echo -e "${RED}ERROR:${NC} Case mismatch in use statement in ${php_file#$PROJECT_ROOT/}"
                echo "       Referenced: $use_class"
                echo "       Actual:     $found_match"
                ((use_errors++)) || true
            fi
        fi
    done < <(grep -E "^use\s+WPAdminHealth\\\\" "$php_file" 2>/dev/null | sed -E 's/^use\s+//; s/\s*(;|as\s).*$//' || true)
done < <(find "$PROJECT_ROOT/includes" -name "*.php" -print0 2>/dev/null)

if [[ $use_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} All use statements reference correctly-cased classes"
else
    ERRORS=$((ERRORS + use_errors))
fi
echo ""

#
# Check 4: Validate known integration class references
#
echo "[Check 4] Validating integration class references..."

integration_errors=0
INTEGRATIONS_DIR="$PROJECT_ROOT/includes/Integrations"

if [[ -d "$INTEGRATIONS_DIR" ]]; then
    # Create temp file for integration files
    INTEGRATION_FILE=$(mktemp)
    trap "rm -f $CLASS_MAP_FILE $INTEGRATION_FILE" EXIT

    # Get list of actual integration files (excluding index.php and abstract)
    find "$INTEGRATIONS_DIR" -maxdepth 1 -name "*.php" ! -name "index.php" ! -name "Abstract*.php" ! -name "*Interface.php" 2>/dev/null | while read -r file; do
        basename "$file" .php
    done > "$INTEGRATION_FILE"

    # Search for integration class instantiations or references
    while IFS= read -r -d '' php_file; do
        # Look for patterns like: Integrations\ACF, Integrations\Acf, etc.
        while IFS= read -r ref; do
            [[ -z "$ref" ]] && continue

            # Check if this is a known integration that might have wrong case
            while IFS= read -r int_name; do
                [[ -z "$int_name" ]] && continue
                # Case-insensitive comparison using tr for portability
                ref_lower=$(echo "$ref" | tr '[:upper:]' '[:lower:]')
                int_lower=$(echo "$int_name" | tr '[:upper:]' '[:lower:]')

                if [[ "$ref_lower" == "$int_lower" && "$ref" != "$int_name" ]]; then
                    echo -e "${RED}ERROR:${NC} Integration class case mismatch in ${php_file#$PROJECT_ROOT/}"
                    echo "       Referenced: $ref"
                    echo "       Actual:     $int_name"
                    ((integration_errors++)) || true
                fi
            done < "$INTEGRATION_FILE"
        done < <(grep -oE "Integrations\\\\[A-Za-z]+" "$php_file" 2>/dev/null | sed 's/Integrations\\\\//' | sort -u || true)

        # Also check new ClassName( patterns for integration classes
        while IFS= read -r ref; do
            [[ -z "$ref" ]] && continue

            while IFS= read -r int_name; do
                [[ -z "$int_name" ]] && continue
                ref_lower=$(echo "$ref" | tr '[:upper:]' '[:lower:]')
                int_lower=$(echo "$int_name" | tr '[:upper:]' '[:lower:]')

                if [[ "$ref_lower" == "$int_lower" && "$ref" != "$int_name" ]]; then
                    echo -e "${RED}ERROR:${NC} Integration instantiation case mismatch in ${php_file#$PROJECT_ROOT/}"
                    echo "       Referenced: new $ref("
                    echo "       Actual class: $int_name"
                    ((integration_errors++)) || true
                fi
            done < "$INTEGRATION_FILE"
        done < <(grep -oE "new\s+(ACF|Acf|WooCommerce|Woocommerce|Elementor|Multilingual)\s*\(" "$php_file" 2>/dev/null | sed -E 's/new\s+//; s/\s*\(//' | sort -u || true)
    done < <(find "$PROJECT_ROOT/includes" -name "*.php" -print0 2>/dev/null)
fi

if [[ $integration_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} All integration references are correctly cased"
else
    ERRORS=$((ERRORS + integration_errors))
fi
echo ""

#
# Summary
#
echo "=============================================="
if [[ $ERRORS -eq 0 ]]; then
    echo -e "${GREEN}All case-sensitivity checks passed!${NC}"
    exit 0
else
    echo -e "${RED}Found $ERRORS case-sensitivity error(s)${NC}"
    echo ""
    echo "These issues will cause failures on case-sensitive"
    echo "filesystems (Linux). Please fix the casing to match"
    echo "the actual file/class names."
    exit 1
fi
