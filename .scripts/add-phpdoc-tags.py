#!/usr/bin/env python3
"""
Script to add @since 1.0.0 tags to PHP class docblocks and public method docblocks.
"""

import re
import sys
from pathlib import Path

def add_since_to_class_docblock(content):
    """Add @since tag to class docblock if missing."""
    # Pattern for class docblock without @since
    pattern = r'(/\*\*[^*]*\*(?:[^*/][^*]*\*+)*/)(\s*(?:final\s+|abstract\s+)?class\s+)'

    def replacer(match):
        docblock = match.group(1)
        class_keyword = match.group(2)

        # Check if @since already exists
        if '@since' in docblock:
            return match.group(0)

        # Find the last line before closing */
        lines = docblock.split('\n')
        if len(lines) > 1:
            # Insert @since before the closing */
            lines.insert(-1, ' *')
            lines.insert(-1, ' * @since 1.0.0')
            return '\n'.join(lines) + class_keyword

        return match.group(0)

    return re.sub(pattern, replacer, content, flags=re.DOTALL)

def add_since_to_methods(content):
    """Add @since tag to public method docblocks if missing."""
    # Pattern for public method docblock without @since
    pattern = r'(/\*\*[^*]*\*(?:[^*/][^*]*\*+)*/)(\s*public\s+(?:static\s+)?function\s+)'

    def replacer(match):
        docblock = match.group(1)
        function_keyword = match.group(2)

        # Check if @since already exists
        if '@since' in docblock:
            return match.group(0)

        # Find position to insert @since (after first line, before @param/@return)
        lines = docblock.split('\n')
        if len(lines) > 2:
            # Find first @param or @return line
            insert_pos = len(lines) - 1
            for i, line in enumerate(lines):
                if '@param' in line or '@return' in line or '@throws' in line:
                    insert_pos = i
                    break

            # Insert @since with blank line after
            lines.insert(insert_pos, ' *')
            lines.insert(insert_pos, ' * @since 1.0.0')
            return '\n'.join(lines) + function_keyword

        return match.group(0)

    return re.sub(pattern, replacer, content, flags=re.DOTALL)

def process_file(filepath):
    """Process a single PHP file to add @since tags."""
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()

        original = content

        # Add @since to class docblock
        content = add_since_to_class_docblock(content)

        # Add @since to public methods
        content = add_since_to_methods(content)

        # Only write if content changed
        if content != original:
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(content)
            print(f"Updated: {filepath}")
            return True
        else:
            print(f"No changes: {filepath}")
            return False
    except Exception as e:
        print(f"Error processing {filepath}: {e}")
        return False

def main():
    if len(sys.argv) < 2:
        print("Usage: python add-phpdoc-tags.py <file_or_directory>")
        sys.exit(1)

    path = Path(sys.argv[1])

    if path.is_file():
        process_file(path)
    elif path.is_dir():
        php_files = list(path.rglob('*.php'))
        # Exclude index.php and autoload.php
        php_files = [f for f in php_files if f.name not in ['index.php', 'autoload.php']]

        updated = 0
        for php_file in php_files:
            if process_file(php_file):
                updated += 1

        print(f"\nProcessed {len(php_files)} files, updated {updated}")
    else:
        print(f"Path not found: {path}")
        sys.exit(1)

if __name__ == '__main__':
    main()
