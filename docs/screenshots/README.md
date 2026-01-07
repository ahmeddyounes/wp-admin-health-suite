# Screenshots for Getting Started Guide

This directory contains screenshots referenced in the Getting Started documentation.

## Required Screenshots

The following screenshots need to be captured from the actual plugin UI and placed in this directory:

### Installation & Activation

1. **activation-success.png**
   - Screenshot of the WordPress plugin activation success notice
   - Shows: Green success banner after plugin activation
   - When: Immediately after clicking "Activate" on the plugin

2. **welcome-notice.png**
   - Screenshot of the welcome notice that appears after first activation
   - Shows: Welcome banner with quick start options
   - When: First page load after plugin activation

3. **admin-menu.png**
   - Screenshot showing the "Admin Health" menu in WordPress sidebar
   - Shows: WordPress admin sidebar with heart icon menu item
   - Highlight: The "Admin Health" menu item

### Dashboard

4. **dashboard-loading.png**
   - Screenshot of dashboard while health score is calculating
   - Shows: Loading spinner or skeleton loader for health score
   - When: Initial page load before score calculation completes

5. **dashboard-complete.png**
   - Screenshot of complete dashboard with all data loaded
   - Shows: Health score circle, metrics cards, recent activity, recommendations
   - Annotation: Label key areas (health score, metrics, activity, recommendations)

### Database Health

6. **database-overview.png**
   - Screenshot of Database Health page overview section
   - Shows: Overview cards with database size, tables count, potential savings
   - When: After page loads with scan complete

7. **cleanup-modules.png**
   - Screenshot showing the accordion-style cleanup modules
   - Shows: Multiple cleanup modules (Post Revisions, Auto-Drafts, etc.)
   - Display: At least 2-3 modules expanded to show details

### Media Audit

8. **media-audit-overview.png** (Optional enhancement)
   - Screenshot of Media Audit page
   - Shows: Scan status, stats overview cards
   - When: After completing a media scan

### Performance

9. **performance-overview.png** (Optional enhancement)
   - Screenshot of Performance page
   - Shows: Performance score, plugin impact table
   - When: After performance data has been collected

### Settings

10. **settings-general.png** (Optional enhancement)
    - Screenshot of Settings page, General tab
    - Shows: Configuration fields for general settings
    - When: Settings page loaded on General tab

## Screenshot Guidelines

### Capture Requirements

- **Resolution:** Minimum 1920x1080 (Full HD) for clarity
- **Browser:** Use latest Chrome or Firefox for consistency
- **WordPress Version:** Latest stable version
- **Theme:** Default WordPress admin theme (no custom admin themes)
- **Zoom Level:** 100% browser zoom
- **Format:** PNG for better quality and transparency support

### Preparation Checklist

Before taking screenshots:

- [ ] Install plugin on a test WordPress site
- [ ] Use sample data to populate dashboard (posts, media, comments)
- [ ] Add some "cleanup candidates" (trashed posts, spam, etc.) for realism
- [ ] Clear browser cache and cookies
- [ ] Disable other admin notices/banners for clean screenshots
- [ ] Use consistent demo site name (e.g., "Demo Site" or "My WordPress Site")

### Content Guidelines

- **Blur sensitive data:** Hide real site URLs, emails, or identifying information
- **Use realistic data:** Show actual numbers and data, not zeros or placeholders
- **Show good scores:** Display health scores in the 60-85 range (shows room for improvement)
- **Include context:** Capture enough surrounding UI for users to recognize location

### Post-Processing

1. **Crop appropriately:** Include relevant UI, remove excessive whitespace
2. **Add annotations:** Use tools like Skitch or Snagit to add:
   - Arrows pointing to key features
   - Text labels for important elements
   - Numbered callouts for multi-step processes
3. **Optimize file size:** Compress PNGs using TinyPNG or similar (target <500KB per image)
4. **Naming convention:** Use exact filenames listed above (lowercase, hyphen-separated)

## Screenshot Alternatives

If screenshots are not yet available, the documentation will gracefully handle this with:

- Descriptive alt text explaining what should be visible
- Detailed text descriptions accompanying each screenshot reference
- Users can still follow the guide without images

## Contributing Screenshots

To contribute screenshots:

1. Follow the guidelines above
2. Name files exactly as specified
3. Place files in this directory (`docs/screenshots/`)
4. Submit via pull request or contact the documentation maintainer
5. Include a brief description of your test environment (WP version, PHP version)

## Current Status

- [ ] activation-success.png
- [ ] welcome-notice.png
- [ ] admin-menu.png
- [ ] dashboard-loading.png
- [ ] dashboard-complete.png
- [ ] database-overview.png
- [ ] cleanup-modules.png
- [ ] media-audit-overview.png (optional)
- [ ] performance-overview.png (optional)
- [ ] settings-general.png (optional)

**Last Updated:** 2026-01-07
