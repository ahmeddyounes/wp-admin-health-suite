# Case-Sensitivity Validation

This document describes the case-sensitivity validation system that prevents regressions on case-sensitive filesystems (Linux CI) that might not be caught during development on case-insensitive filesystems (macOS).

## Table of Contents

- [Background](#background)
- [What the Check Does](#what-the-check-does)
- [Running Locally](#running-locally)
- [CI Integration](#ci-integration)
- [Common Issues and Fixes](#common-issues-and-fixes)
- [Adding New Checks](#adding-new-checks)

---

## Background

### The Problem

macOS uses a case-insensitive filesystem by default (HFS+ or APFS). This means:

- `includes/Integrations/Acf.php` and `includes/Integrations/ACF.php` are treated as the same file
- `use WPAdminHealth\Integrations\acf;` will find `ACF.php` just fine

However, Linux filesystems (ext4, etc.) are case-sensitive:

- `includes/Integrations/Acf.php` and `includes/Integrations/ACF.php` are **different** files
- `use WPAdminHealth\Integrations\acf;` will **fail** if the file is named `ACF.php`

This leads to bugs that work locally on macOS but fail in CI (Ubuntu) or production (Linux servers).

### The Solution

We validate that all PHP file references (namespaces, `use` statements, `require`/`include` paths) exactly match the actual filesystem casing. This check runs on every push and PR in CI.

---

## What the Check Does

The validation script (`scripts/check-case-sensitivity.sh`) performs four main checks:

### Check 1: PSR-4 Namespace Mappings

Validates that each PHP class file's namespace and class name map correctly to its filesystem path.

**Example:**
```
Namespace: WPAdminHealth\Integrations\ACF
Expected file: includes/Integrations/ACF.php
```

If the file exists at `includes/Integrations/Acf.php` instead, this is flagged as an error.

### Check 2: Require/Include Statements

Validates that `require`, `require_once`, `include`, and `include_once` statements reference files with correct casing.

**Example:**
```php
// This in some-file.php:
require_once __DIR__ . '/ACF.php';

// Must match actual file: ACF.php (not acf.php or Acf.php)
```

### Check 3: Use Statement References

Validates that `use` statements for `WPAdminHealth\*` classes reference classes that actually exist with that exact casing.

**Example:**
```php
use WPAdminHealth\Integrations\ACF;  // ACF class must exist with this casing
```

### Check 4: Integration Class References

Specifically validates references to integration classes (ACF, WooCommerce, Elementor, Multilingual) which are common sources of case issues.

---

## Running Locally

### Prerequisites

- Bash 4.0+ (for full validation)
- GNU find and grep (available by default on most systems)

**Note on macOS:** macOS ships with Bash 3.x by default, which doesn't support all features used in the script. On macOS with Bash 3.x:
- The script will display a warning and exit gracefully with code 0
- Full validation runs in CI (Ubuntu with Bash 4+)
- To run full checks locally, install Bash 4+: `brew install bash`

### Running the Script

From the project root:

```bash
./scripts/check-case-sensitivity.sh
```

On macOS with Bash 3.x, you'll see:

```
==============================================
Case-Sensitivity Validation
==============================================

WARNING: Bash 3.2.57(1)-release detected. This script requires Bash 4.0+.
         On macOS, install with: brew install bash
         Then run: /usr/local/bin/bash ./scripts/check-case-sensitivity.sh

         Skipping detailed checks. CI (Ubuntu) will run full validation.
==============================================
Checks skipped due to Bash version
```

To run full checks on macOS:
```bash
/usr/local/bin/bash ./scripts/check-case-sensitivity.sh
```

### Expected Output (All Passes)

```
==============================================
Case-Sensitivity Validation
==============================================

[Check 1] Validating PSR-4 namespace mappings...
PASS: All PSR-4 namespace mappings are correct

[Check 2] Validating require/include statements...
PASS: All require/include paths are correct

[Check 3] Validating use statement class references...
PASS: All use statements reference correctly-cased classes

[Check 4] Validating integration class references...
PASS: All integration references are correctly cased

==============================================
All case-sensitivity checks passed!
```

### Example Failure Output

```
==============================================
Case-Sensitivity Validation
==============================================

[Check 1] Validating PSR-4 namespace mappings...
ERROR: PSR-4 mismatch in includes/Integrations/Acf.php
       Namespace: WPAdminHealth\Integrations\ACF
       Expected:  includes/Integrations/ACF.php
       Actual:    includes/Integrations/Acf.php

==============================================
Found 1 case-sensitivity error(s)

These issues will cause failures on case-sensitive
filesystems (Linux). Please fix the casing to match
the actual file/class names.
```

### Adding to npm Scripts (Optional)

You can add this check to your `package.json` for convenience:

```json
{
  "scripts": {
    "check:case": "./scripts/check-case-sensitivity.sh"
  }
}
```

Then run: `npm run check:case`

### Adding to Composer Scripts (Optional)

You can add this check to your `composer.json`:

```json
{
  "scripts": {
    "check:case": "./scripts/check-case-sensitivity.sh"
  }
}
```

Then run: `composer check:case`

---

## CI Integration

The case-sensitivity check runs automatically in GitHub Actions CI on every push to `main`/`develop` and on pull requests.

### CI Workflow Configuration

The check is configured in `.github/workflows/ci.yml`:

```yaml
jobs:
  case-sensitivity:
    name: Case-Sensitivity Check
    runs-on: ubuntu-latest

    steps:
      - name: Checkout code
        uses: actions/checkout@v4

      - name: Run case-sensitivity validation
        run: ./scripts/check-case-sensitivity.sh
```

### Why Ubuntu?

The check runs on `ubuntu-latest` because:

1. Ubuntu uses a case-sensitive filesystem (ext4)
2. It matches our production deployment environment
3. Issues that pass on macOS will be caught here

---

## Common Issues and Fixes

### Issue: File Renamed But Class Casing Changed

**Symptom:**
```
ERROR: PSR-4 mismatch in includes/Integrations/acf.php
       Namespace: WPAdminHealth\Integrations\ACF
       Expected:  includes/Integrations/ACF.php
       Actual:    includes/Integrations/acf.php
```

**Fix:**
1. Rename the file to match the class name:
   ```bash
   git mv includes/Integrations/acf.php includes/Integrations/ACF.php
   ```

2. Note: On macOS, git may not detect the rename because the filesystem is case-insensitive. Use this workaround:
   ```bash
   git mv includes/Integrations/acf.php includes/Integrations/ACF_temp.php
   git mv includes/Integrations/ACF_temp.php includes/Integrations/ACF.php
   ```

### Issue: Use Statement Has Wrong Casing

**Symptom:**
```
ERROR: Case mismatch in use statement in includes/Providers/IntegrationServiceProvider.php
       Referenced: WPAdminHealth\Integrations\Acf
       Actual:     WPAdminHealth\Integrations\ACF
```

**Fix:**
Update the `use` statement to match the actual class name:
```php
// Before (wrong):
use WPAdminHealth\Integrations\Acf;

// After (correct):
use WPAdminHealth\Integrations\ACF;
```

### Issue: Require Path Has Wrong Casing

**Symptom:**
```
ERROR: Case mismatch in includes/SomeFile.php
       Referenced: /Some/Path/file.php
       Actual file: File.php
```

**Fix:**
Update the require/include statement to match the actual filename:
```php
// Before (wrong):
require_once __DIR__ . '/file.php';

// After (correct):
require_once __DIR__ . '/File.php';
```

### Issue: Git Not Detecting Rename on macOS

Git on macOS may not detect case-only renames. To force it:

```bash
# Method 1: Two-step rename
git mv OldName.php TempName.php
git mv TempName.php NewName.php

# Method 2: Set git config (affects all repos)
git config core.ignorecase false
git mv OldName.php NewName.php
```

---

## Adding New Checks

The validation script is designed to be extensible. To add new checks:

1. Open `scripts/check-case-sensitivity.sh`

2. Add a new check section following the pattern:
   ```bash
   #
   # Check N: Description
   #
   echo "[Check N] Validating something..."

   check_errors=0
   # Your validation logic here

   if [[ $check_errors -eq 0 ]]; then
       echo -e "${GREEN}PASS:${NC} Description of what passed"
   else
       ERRORS=$((ERRORS + check_errors))
   fi
   echo ""
   ```

3. The script automatically includes the new check in the total error count.

### Example: Adding WordPress Hook Casing Check

```bash
#
# Check 5: Validate WordPress hook names
#
echo "[Check 5] Validating WordPress hook naming conventions..."

hook_errors=0
while IFS= read -r -d '' php_file; do
    # Check for hooks that should use snake_case
    while IFS= read -r line; do
        [[ -z "$line" ]] && continue
        # Your hook validation logic
    done < <(grep -E "(add_action|add_filter)\s*\(" "$php_file" 2>/dev/null || true)
done < <(find "$PROJECT_ROOT/includes" -name "*.php" -print0 2>/dev/null)

if [[ $hook_errors -eq 0 ]]; then
    echo -e "${GREEN}PASS:${NC} All WordPress hooks follow naming conventions"
else
    ERRORS=$((ERRORS + hook_errors))
fi
echo ""
```

---

## Best Practices

1. **Run the check before committing** - Add it to your pre-commit workflow
2. **Use consistent naming** - Follow PSR-4 naming: `ClassName.php` matches `class ClassName`
3. **Avoid acronyms in mixed case** - Use `ACF` not `Acf` when the class is `ACF`
4. **Test on Linux** - If possible, test on a Linux VM or container before pushing

---

## Support

If you encounter issues with the case-sensitivity check:

1. Check the script output for specific error messages
2. Review this documentation for common fixes
3. Open an issue in the repository if you believe it's a bug in the check itself

---

**Last Updated:** 2026-01-18
**Script Location:** `scripts/check-case-sensitivity.sh`
