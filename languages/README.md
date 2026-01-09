# Internationalization (i18n)

This directory contains the translation files for the WP Admin Health Suite plugin.

## Files

- `wp-admin-health-suite.pot` - Template file for translations (POT = Portable Object Template)
- Translation files (.po and .mo) for specific languages will be placed here

## Generating the POT File

To regenerate the .pot file with all translatable strings, use WP-CLI:

```bash
wp i18n make-pot . languages/wp-admin-health-suite.pot --domain=wp-admin-health-suite
```

### For JavaScript Files

To generate JSON translation files for JavaScript (required for wp.i18n), use:

```bash
wp i18n make-json languages --no-purge
```

## Creating Translations

1. Copy `wp-admin-health-suite.pot` to a new file named `wp-admin-health-suite-{locale}.po` (e.g., `wp-admin-health-suite-es_ES.po` for Spanish)
2. Translate the strings in the .po file using a translation tool like Poedit
3. Compile the .po file to .mo format (most tools do this automatically)
4. Place both the .po and .mo files in this directory

## Plugin Text Domain

The plugin uses the text domain: `wp-admin-health-suite`

All translatable strings in PHP use WordPress i18n functions:

- `__()` - Returns translated string
- `_e()` - Echoes translated string
- `_n()` - Handles plural forms
- `_x()` - Returns translated string with context

JavaScript translations use `wp.i18n`:

- `__()` - Returns translated string (requires wp-i18n dependency)

## Loading Translations

Translations are automatically loaded in the plugin using:

- `load_plugin_textdomain()` for PHP strings
- `wp_set_script_translations()` for JavaScript strings
