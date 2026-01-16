# Media Audit Guide

A comprehensive guide to understanding and safely managing your WordPress media library using WP Admin Health Suite.

## Table of Contents

- [How Media Scanning Works](#how-media-scanning-works)
- [Understanding 'Unused' Media](#understanding-unused-media)
- [Duplicate Detection](#duplicate-detection)
- [Large File Optimization](#large-file-optimization)
- [Missing Alt Text](#missing-alt-text)
- [Safe Deletion Process](#safe-deletion-process)
- [Integration with Page Builders](#integration-with-page-builders)

---

## How Media Scanning Works

### What is Media Scanning?

Media scanning is an automated process that analyzes your entire WordPress media library to identify:

- Unused files that aren't referenced anywhere
- Duplicate files wasting storage space
- Large files that could be optimized
- Images missing accessibility alt text

**The Scan Process:**

1. **Media Library Enumeration:**
    - Scans all attachments in the media library
    - Retrieves file metadata (size, type, upload date)
    - Builds a comprehensive inventory of files

2. **Reference Detection:**
    - Searches post content for media references
    - Checks custom fields and post meta
    - Scans theme files and page builder data
    - Identifies featured images and thumbnails
    - Examines widgets and navigation menus

3. **Analysis and Classification:**
    - Marks files as used or unused based on references
    - Groups duplicate files by content hash
    - Categorizes files by size and type
    - Flags missing alt text on images

4. **Results Storage:**
    - Caches scan results for fast access
    - Updates statistics dashboard
    - Generates actionable recommendations

### When to Run a Media Scan

**Initial Scan:**

- Run immediately after plugin installation
- Provides baseline understanding of media health
- Identifies immediate cleanup opportunities

**Regular Maintenance:**

- Weekly for active sites with frequent uploads
- Bi-weekly for moderate content creation
- Monthly for low-activity sites

**After Major Changes:**

- Content migration or import
- Theme changes
- Plugin deactivation or deletion
- Large content cleanup operations
- Website redesign or restructure

### How to Trigger a Scan

#### Method 1: Manual Scan

1. Navigate to **Admin Health > Media Audit**
2. Click the **Rescan Media** button in the status banner
3. Wait for the scan to complete
4. Review the updated statistics

**Scan Duration:**

- Small libraries (< 500 files): 10-30 seconds
- Medium libraries (500-2000 files): 30-90 seconds
- Large libraries (2000-5000 files): 1-3 minutes
- Very large libraries (> 5000 files): 3-10 minutes

> **Note:** Scans run in the background using Action Scheduler when available, preventing browser timeouts.

#### Method 2: Scheduled Automatic Scans

Configure automated scanning in settings:

1. Navigate to **Admin Health > Settings > Media**
2. Find **Automated Scanning** section
3. Enable **Schedule Regular Scans**
4. Set frequency (Weekly recommended)
5. Choose preferred execution time
6. Click **Save Settings**

### Understanding Scan Limitations

**Performance Considerations:**

**Large Libraries:**

- Sites with 10,000+ media files may experience longer scan times
- Consider running scans during off-peak hours
- Enable Action Scheduler for background processing

**Server Resources:**

- Scanning uses moderate CPU and memory
- Shared hosting may have execution time limits
- Contact hosting if scans consistently timeout

**Caching Behavior:**

- Scan results are cached for performance
- Cache invalidates after 24 hours
- Manual rescans override cached results
- Delete operations automatically update cache

---

## Understanding 'Unused' Media

### What Qualifies as Unused?

A media file is marked as "unused" when it meets these criteria:

**Not Referenced in Posts or Pages:**

- Not embedded in post/page content
- Not set as a featured image
- Not used in galleries
- Not linked directly in content

**Not Referenced in Custom Fields:**

- Not stored in post meta
- Not used by page builders
- Not referenced in plugin data
- Not used in theme options

**Not Used in Site Elements:**

- Not in navigation menus
- Not in widgets or sidebars
- Not in theme customizer
- Not in site identity (logo, icon)

### False Positives and Limitations

> **Critical:** The scanner cannot detect all possible media usage. Always review before deleting.

#### Common False Positive Scenarios

**1. Hardcoded URLs in Theme Files**

If your theme directly references media URLs:

```php
// In theme template files
<img src="<?php echo home_url('/wp-content/uploads/2024/logo.png'); ?>">
```

**Why it's missed:** Static file paths in PHP/HTML aren't in the database.

**Solution:** Review theme files before deleting "unused" files, or use the exclusions feature.

**2. JavaScript and CSS References**

Media loaded dynamically via scripts:

```javascript
// In custom JavaScript
const backgroundImage = '/wp-content/uploads/2024/hero-bg.jpg';
document.querySelector('.hero').style.backgroundImage =
	`url(${backgroundImage})`;
```

**Why it's missed:** Dynamic URL construction isn't detectable by scanner.

**Solution:** Maintain a list of critical media used in scripts and exclude them from cleanup.

**3. External References**

Files linked from external sources:

- Email newsletters linking to images
- Social media posts using media URLs
- Third-party integrations
- Mobile app content

**Why it's missed:** External references aren't in WordPress database.

**Solution:** Keep important shared media or use exclusions feature.

**4. Third-Party Plugin Storage**

Some plugins store media references in custom ways:

- Form builders with image fields
- Quiz plugins with question images
- Membership plugins with restricted content
- Custom plugin databases

**Why it's missed:** Non-standard storage mechanisms may not be scanned.

**Solution:** Check plugin documentation and test thoroughly before deleting media used by critical plugins.

**5. Shortcode Parameters**

Media passed as shortcode attributes:

```
[custom_slider image_id="123" bg_image="456"]
```

**Why it might be missed:** Depends on shortcode implementation and storage.

**Solution:** Test page builder and plugin shortcodes before bulk deletion.

**6. Multisite Network Usage**

In multisite installations:

- Files used by other sites in network
- Network-wide shared media
- Cross-site references

**Why it's missed:** Scanning typically focuses on current site only.

**Solution:** Extra caution in multisite environments; coordinate with network administrators.

### Scan Detection Methods

**What the Scanner DOES Detect:**

**Post Content Analysis:**

- `<img>` tags with attachment IDs or URLs
- `[gallery]` shortcode references
- WordPress blocks with media
- Embedded media in Classic Editor
- Gutenberg image and media blocks

**WordPress Features:**

- Featured images (post thumbnails)
- Custom header images
- Site logos and icons
- Background images (via Customizer)
- User avatars (custom uploads)

**Database Metadata:**

- Post meta containing attachment IDs
- Standard custom fields
- Most page builder data (Elementor, Beaver Builder, etc.)
- Plugin meta using WordPress standards

**Common Page Builders:**

- Elementor
- WPBakery
- Beaver Builder
- Divi Builder
- Oxygen Builder

**What the Scanner MIGHT MISS:**

- Hardcoded paths in theme files
- JavaScript/CSS file references
- External hotlinks
- Non-standard plugin storage
- Server-side includes
- Custom database tables

### Best Practices to Avoid Issues

**Before Deleting Unused Media:**

1. **Run a Full Site Backup**
    - Include both database and files
    - Test backup restoration capability
    - Store backup off-site

2. **Visual Inspection**
    - Browse important pages on your site
    - Check for broken images
    - Verify all media loads correctly

3. **Review the List**
    - Sort by upload date (keep recent files)
    - Check file names for recognizable assets
    - Look for files you know are in use

4. **Use the Preview Feature**
    - Click on individual files to see where they might be used
    - Review file details before deletion
    - Check edit history if available

5. **Start with Old Files**
    - Files uploaded 6+ months ago are safer to evaluate
    - Recent uploads may be in draft content
    - Older unused files less likely to be needed

6. **Use Exclusions**
    - Mark critical files as excluded
    - Document why files are excluded
    - Regularly review exclusion list

7. **Test in Staging First**
    - If you have a staging site, test deletions there
    - Verify no broken images appear
    - Check all functionality still works

**Safe Cleanup Strategy:**

```
Phase 1: Very Old Files (1+ year old)
- Lowest risk
- Likely truly unused
- Start here for confidence

Phase 2: Old Files (6-12 months)
- Review file names carefully
- Check for important assets
- Proceed with caution

Phase 3: Recent Files (3-6 months)
- Higher risk of active use
- May be in draft content
- Extra review recommended

Phase 4: Very Recent Files (< 3 months)
- Highest risk
- Skip unless certain
- Often in active editing
```

### Using the Exclusions Feature

**When to Exclude Files:**

- Known to be used in theme files
- Referenced in custom code
- Shared externally (newsletters, etc.)
- Critical branding assets
- Legal or compliance documents
- Backup/archive purposes

**How to Exclude Files:**

1. Navigate to **Media Audit > Unused Files** tab
2. Select files to exclude (checkbox)
3. Choose **Ignore Selected** from Bulk Actions
4. Optionally add a reason for exclusion
5. Click **Apply**

**Managing Exclusions:**

View and manage excluded files:

1. Navigate to **Media Audit > Settings**
2. View **Excluded Files** list
3. Remove exclusions if needed
4. Export exclusion list for documentation

---

## Duplicate Detection

### What Are Duplicate Files?

Duplicate files are media files with identical content but stored multiple times in your media library. This commonly happens when:

**Common Causes:**

**Repeated Uploads:**

- Same image uploaded multiple times
- Different editors uploading the same file
- Re-uploading after forgetting it exists
- Bulk imports with duplicates

**Content Migration:**

- Importing from another site
- Moving from staging to production
- Plugin migration tools
- Manual content transfers

**Plugin Behavior:**

- Some plugins create copies instead of reusing
- Image optimization creating new versions
- Gallery plugins duplicating files
- Theme demos importing duplicate assets

**Multiple Editors:**

- Team members uploading same files
- Lack of media library organization
- Poor communication about existing assets

### How Duplicate Detection Works

**File Comparison Method:**

**Content-Based Hashing:**
The plugin uses MD5 or SHA-256 hashing:

1. **Read File Content:**
    - Loads actual file bytes
    - Ignores file name or location
    - Processes entire file

2. **Generate Hash:**
    - Creates unique fingerprint from content
    - Same content = same hash
    - Different content = different hash

3. **Compare Hashes:**
    - Groups files by matching hashes
    - Identifies original vs. copies
    - Calculates potential savings

**What Counts as a Duplicate:**

**Exact Duplicates:**

- Identical file content
- Same dimensions for images
- Byte-for-byte match
- Different file names OK

**NOT Considered Duplicates:**

- Different sizes of same image (WordPress thumbnails)
- Cropped versions
- Edited or filtered versions
- Same subject but different file
- Similar but not identical content

### Understanding Duplicate Groups

**The Duplicate Groups Display:**

Each duplicate group shows:

```
Original File:
- logo.png (uploaded Jan 2024)
- 50 KB
- Used in 5 locations

Duplicates (2):
- company-logo.png (uploaded Mar 2024) - 50 KB - Used in 2 locations
- brand-logo.png (uploaded May 2024) - 50 KB - Unused
```

**How "Original" is Determined:**

1. **Upload Date** (primary): Earliest upload is marked original
2. **Usage Count** (secondary): Most-referenced file if dates similar
3. **File Name** (tertiary): Clearest name if other factors equal

> **Note:** The "original" designation is for reference only. You can choose to keep any version from the group.

### Duplicate Cleanup Strategies

#### Strategy 1: Keep Original, Delete Copies

**Best for:** Most situations, safest approach

**Process:**

1. Review each duplicate group
2. Verify the "original" is used
3. Check if copies are used anywhere
4. Delete unused copies first
5. Manually replace references to used copies
6. Delete remaining copies

**Pros:**

- Safest approach
- Preserves existing references
- Easy to understand

**Cons:**

- Time-consuming for many duplicates
- Manual reference updating needed

#### Strategy 2: Keep Most-Used Version

**Best for:** Files with different usage patterns

**Process:**

1. Identify which version is used most
2. Keep the most-referenced version
3. Update references from other versions
4. Delete less-used versions

**Pros:**

- Minimizes broken references
- Pragmatic approach

**Cons:**

- May not keep "true" original
- Requires usage analysis

#### Strategy 3: Consolidate to Best Quality

**Best for:** Images with quality differences

**Process:**

1. Compare file sizes (larger often = better quality)
2. Check image dimensions
3. Visually inspect if uncertain
4. Keep highest quality version
5. Update all references to point to best version
6. Delete lower quality copies

**Pros:**

- Maintains best quality
- Improves user experience

**Cons:**

- Requires manual review
- May need reference updates

### Safe Duplicate Removal Process

**Step-by-Step Procedure:**

1. **Review the Group**
    - Understand how many copies exist
    - Check where each is used
    - Identify the best file to keep

2. **Document Usage**
    - Note which posts use each version
    - Check for featured images
    - Review page builder usage

3. **Update References** (if needed)
    - Edit posts using copies
    - Replace with original
    - Update featured images
    - Fix page builder elements

4. **Verify Changes**
    - Preview pages after updating
    - Check for broken images
    - Test responsive views

5. **Delete Copies**
    - Start with unused copies
    - Move used copies to trash (not permanent)
    - Wait 7-14 days before permanent deletion

6. **Confirm Success**
    - Check that one version remains
    - Verify all pages still work
    - Review media library organization

### Potential Savings Calculation

**How Savings Are Calculated:**

```
Example Duplicate Group:
Original: logo.png - 50 KB - Keep
Copy 1: logo-2.png - 50 KB - Delete
Copy 2: logo-copy.png - 50 KB - Delete

Potential Savings: 100 KB (2 copies × 50 KB)
```

**Total Potential Savings:**
Sum of all duplicate file sizes that could be deleted.

**Realistic Expectations:**

| Library Size    | Typical Duplicates | Expected Savings |
| --------------- | ------------------ | ---------------- |
| < 500 files     | 5-15%              | 10-50 MB         |
| 500-2000 files  | 10-20%             | 50-200 MB        |
| 2000-5000 files | 15-25%             | 200-500 MB       |
| > 5000 files    | 20-30%             | 500+ MB          |

### Preventing Future Duplicates

**Best Practices:**

**1. Search Before Uploading**

- Use media library search
- Check for similar file names
- Review recent uploads
- Coordinate with team members

**2. Organize Media Library**

- Use descriptive file names
- Create folders/categories (with plugin)
- Tag media appropriately
- Maintain naming conventions

**3. Team Communication**

- Document shared assets
- Use a media style guide
- Central repository for brand assets
- Regular team reviews

**4. Regular Audits**

- Monthly duplicate scans
- Quarterly deep cleanup
- Annual library organization
- Document cleanup actions

---

## Large File Optimization

### Why Large Files Matter

Large media files impact your website in several ways:

**Performance Impact:**

**Page Load Speed:**

- Larger files take longer to download
- Slow connections affected most
- Mobile users particularly impacted
- Increases time to first contentful paint

**Server Resources:**

- More bandwidth consumption
- Higher data transfer costs
- Increased storage usage
- More server processing for delivery

**User Experience:**

- Slower page loads reduce engagement
- Higher bounce rates
- Poor mobile experience
- Frustrated visitors

**SEO Consequences:**

- Google penalizes slow sites
- Lower search rankings
- Reduced organic traffic
- Poor Core Web Vitals scores

### What Qualifies as "Large"?

**Size Thresholds:**

| File Size     | Category   | Impact                     |
| ------------- | ---------- | -------------------------- |
| 100-500 KB    | Moderate   | Acceptable for hero images |
| 500 KB - 1 MB | Large      | Optimize if possible       |
| 1-5 MB        | Very Large | Should optimize            |
| 5-10 MB       | Excessive  | Must optimize              |
| 10+ MB        | Critical   | Urgent action needed       |

**Context Matters:**

**Acceptable Large Files:**

- Hero images on homepage (< 500 KB optimized)
- Portfolio photography sites (< 1 MB)
- Product showcases (< 800 KB)
- High-quality featured images (< 600 KB)

**Unacceptable Large Files:**

- Thumbnails over 100 KB
- Icon images over 50 KB
- Background textures over 200 KB
- Decorative images over 300 KB

### Identifying Large Files

**How to Find Large Files:**

1. Navigate to **Admin Health > Media Audit**
2. Click the **Large Files** tab
3. Review files sorted by size (largest first)
4. Use size filters:
    - Files > 1 MB
    - Files > 5 MB
    - Files > 10 MB

**What You'll See:**

```
Example Large File Entry:
- hero-image.jpg
- Size: 3.2 MB
- Dimensions: 4000 × 3000 px
- Upload Date: Jan 15, 2024
- Usage: Used in 3 posts
```

### Optimization Strategies

#### Strategy 1: Resize Dimensions

**Most Effective Method:**

**Common Issues:**

- Uploading camera photos directly (4000+ pixels wide)
- Designer files at print resolution
- Screenshots from 4K displays
- Unscaled vector exports

**Recommended Maximum Dimensions:**

| Usage                 | Width   | Height  | Reasoning              |
| --------------------- | ------- | ------- | ---------------------- |
| **Hero Images**       | 2000 px | 1200 px | Covers large displays  |
| **Featured Images**   | 1200 px | 800 px  | Blog posts, archives   |
| **Content Images**    | 1000 px | 800 px  | In-post images         |
| **Thumbnails**        | 400 px  | 300 px  | Grids, listings        |
| **Background Images** | 1920 px | 1080 px | Full-width backgrounds |

**How to Resize:**

**Before Upload:**

- Use image editing software (Photoshop, GIMP, etc.)
- Online tools (TinyPNG, Squoosh)
- Batch processing for multiple files
- Save at web-appropriate dimensions

**After Upload:**

- WordPress image editor (limited)
- Media editing plugins
- Regenerate thumbnails plugin
- Image optimization plugins

#### Strategy 2: Compression

**Reduce File Size Without Changing Dimensions:**

**Compression Types:**

**Lossy Compression:**

- Reduces quality slightly
- Significant size reduction (60-80%)
- Imperceptible to most users
- Best for photos

**Lossless Compression:**

- No quality loss
- Moderate size reduction (10-30%)
- Larger files than lossy
- Best for graphics, logos

**Recommended Tools:**

**WordPress Plugins:**

- Smush Image Compression
- ShortPixel Image Optimizer
- Imagify
- EWWW Image Optimizer

**Online Tools:**

- TinyPNG (PNG and JPG)
- Squoosh (Google)
- Compressor.io
- ImageOptim (Mac)

**Target Compression Levels:**

| File Type            | Quality Setting | Result             |
| -------------------- | --------------- | ------------------ |
| **JPEG Photos**      | 80-85%          | Good balance       |
| **JPEG Hero Images** | 85-90%          | High quality       |
| **PNG Graphics**     | Lossless        | No quality loss    |
| **PNG Photos**       | Convert to JPEG | Better compression |

#### Strategy 3: Format Optimization

**Choose the Right Format:**

**JPEG:**

- Best for: Photographs, complex images
- Supports: Millions of colors
- Compression: Lossy, highly efficient
- Use when: Photos, natural images

**PNG:**

- Best for: Graphics, logos, transparency
- Supports: Transparency, sharp edges
- Compression: Lossless, larger files
- Use when: Need transparency, logos, text

**WebP:**

- Best for: Modern web delivery
- Supports: Transparency, animation
- Compression: Superior to JPEG/PNG
- Use when: Browser supports it (95%+ now)
- Benefit: 25-35% smaller than JPEG/PNG

**SVG:**

- Best for: Icons, logos, simple graphics
- Supports: Infinite scaling, tiny file size
- Compression: XML-based, very small
- Use when: Simple vector graphics

**Format Conversion Guidelines:**

```
PNG photo (2 MB) → JPEG (300 KB) = 85% reduction
JPEG photo (800 KB) → WebP (500 KB) = 37% reduction
PNG graphic (200 KB) → SVG (20 KB) = 90% reduction (if simple)
```

#### Strategy 4: Lazy Loading

**Defer Loading Until Needed:**

**What is Lazy Loading?**
Images load only when they're about to enter the viewport.

**Benefits:**

- Faster initial page load
- Reduced bandwidth for users who don't scroll
- Better Core Web Vitals scores
- Native browser support now available

**Implementation:**

**WordPress Native (5.5+):**
WordPress automatically adds `loading="lazy"` to images.

**Plugins for Enhanced Control:**

- Lazy Load by WP Rocket
- a3 Lazy Load
- Smush (includes lazy load)

**Manual Implementation:**

```html
<img src="image.jpg" loading="lazy" alt="Description" />
```

### Bulk Optimization Workflow

**Step-by-Step Process:**

**1. Identify Target Files** (10 minutes)

- Navigate to Large Files tab
- Filter by size (> 1 MB)
- Review list of candidates
- Prioritize by usage frequency

**2. Categorize Files** (15 minutes)

- Photos → Resize + Compress
- Graphics → Consider format change
- Screenshots → Optimize heavily
- Backgrounds → Resize to 1920px max

**3. Backup Originals** (5 minutes)

- Download originals to local storage
- Keep for 30 days minimum
- Store organized by date

**4. Optimize Files** (30-60 minutes)

- Use bulk optimization plugins
- Process in batches (50-100 files)
- Monitor quality during process
- Check a few samples manually

**5. Upload Replacements** (if manual)

- Replace files in media library
- Use same file names
- WordPress updates references automatically
- Clear cache after upload

**6. Test Results** (15 minutes)

- Visit important pages
- Check image quality
- Verify proper loading
- Test on mobile devices

**7. Measure Improvements** (5 minutes)

- Run speed test (GTmetrix, PageSpeed Insights)
- Compare before/after metrics
- Document improvements
- Note any issues

### Expected Results

**Typical Improvements:**

**Before Optimization:**

```
Homepage:
- Total Page Size: 8.5 MB
- Load Time: 6.2 seconds
- Images: 7.5 MB (88% of page)
- PageSpeed Score: 45/100
```

**After Optimization:**

```
Homepage:
- Total Page Size: 2.1 MB (-75%)
- Load Time: 2.4 seconds (-61%)
- Images: 1.2 MB (-84%)
- PageSpeed Score: 82/100 (+37 points)
```

**Real-World Example:**

```
Large File Optimization Results:
Files Optimized: 234 images
Original Total Size: 456 MB
Optimized Total Size: 98 MB
Space Saved: 358 MB (78% reduction)
Pages Load Faster: 3.5 seconds average improvement
```

### Preventing Future Large Uploads

**Proactive Strategies:**

**1. Pre-Upload Optimization**

- Resize images before upload
- Use optimization tools locally
- Batch process photos
- Educate content creators

**2. Automatic Optimization**

- Install image optimization plugin
- Configure automatic processing
- Set maximum upload dimensions
- Enable format conversion

**3. Upload Guidelines**

- Document maximum file sizes
- Create image size guide
- Train team members
- Regular compliance checks

**4. Monitoring**

- Weekly large file scan
- Alert on oversized uploads
- Monthly optimization review
- Track storage trends

---

## Missing Alt Text

### Why Alt Text Matters

Alt text (alternative text) serves multiple critical purposes:

**Accessibility:**

**Screen Reader Support:**

- Visually impaired users rely on screen readers
- Alt text describes images verbally
- Required for ADA and WCAG compliance
- Legal requirement for many organizations

**Context Understanding:**

- Provides image context when images don't load
- Helpful on slow connections
- Describes broken image links
- Assists all users in understanding content

**SEO Benefits:**

**Search Engine Understanding:**

- Google can't "see" images without alt text
- Alt text helps image ranking
- Improves page relevance signals
- Supports image search results

**Keyword Optimization:**

- Natural place for relevant keywords
- Supports content topic relevance
- Enhances semantic understanding
- Improves overall page SEO

**User Experience:**

**Failed Image Loads:**

- Shows description if image fails
- Maintains content meaning
- Reduces user confusion
- Provides fallback context

### What Qualifies as Missing Alt Text?

**Technically Missing:**

- No `alt` attribute at all
- Empty alt attribute (`alt=""`)
- Whitespace-only alt text (`alt="   "`)

**Functionally Missing:**

- Generic descriptions ("image", "photo")
- File names as alt text ("IMG_1234.jpg")
- Non-descriptive text ("click here")
- Placeholder text ("image description")

**Intentionally Empty:**

> **Note:** Decorative images SHOULD have empty alt text (`alt=""`).

**When Empty Alt Is Correct:**

- Purely decorative images
- Image is described in surrounding text
- Image repeats information already present
- Spacer or layout images

### How to Write Good Alt Text

**Best Practices:**

**1. Be Descriptive and Specific**

**Bad:**

```html
<img src="dog.jpg" alt="dog" />
```

**Good:**

```html
<img src="dog.jpg" alt="Golden retriever playing fetch in a park" />
```

**2. Keep It Concise**

**Too Long:**

```html
<img
	src="product.jpg"
	alt="This is a photograph of our premium quality,
handcrafted leather wallet which features multiple card slots and comes in
various colors including brown, black, and tan"
/>
```

**Just Right:**

```html
<img src="product.jpg" alt="Brown leather wallet with card slots" />
```

**Recommended Length:** 125 characters or fewer (screen reader limit)

**3. Don't Use "Image of" or "Picture of"**

**Redundant:**

```html
<img src="sunset.jpg" alt="Picture of a sunset over the ocean" />
```

**Better:**

```html
<img src="sunset.jpg" alt="Sunset over the ocean" />
```

**Exception:** When image type is relevant:

```html
<img src="chart.jpg" alt="Bar chart showing sales growth by quarter" />
```

**4. Include Relevant Keywords (Naturally)**

**Keyword Stuffing (Bad):**

```html
<img
	src="shoes.jpg"
	alt="Running shoes, athletic shoes, sports shoes,
Nike shoes, comfortable shoes"
/>
```

**Natural Keywords (Good):**

```html
<img src="shoes.jpg" alt="Nike Air Max running shoes in blue" />
```

**5. Context-Appropriate Descriptions**

**In a Product Page:**

```html
<img src="chair.jpg" alt="Modern ergonomic office chair in black fabric" />
```

**In a Blog Post:**

```html
<img src="workspace.jpg" alt="Minimalist home office with desk and chair" />
```

**6. Decorative Images: Use Empty Alt**

**Decorative Border:**

```html
<img src="divider.png" alt="" />
```

**Background Pattern:**

```html
<img src="texture.jpg" alt="" />
```

### Identifying Missing Alt Text

**How to Find Images Missing Alt Text:**

1. Navigate to **Admin Health > Media Audit**
2. Click the **Missing Alt Text** tab
3. Review the list of flagged images
4. Sort by:
    - Upload date (prioritize recent)
    - Usage frequency (prioritize used images)
    - File type (focus on images in content)

**What You'll See:**

```
Example Missing Alt Text Entry:
- hero-banner.jpg
- Size: 450 KB
- Dimensions: 1920 × 1080 px
- Used in: 3 posts
- Upload Date: Jan 15, 2024
- Alt Text: [EMPTY]
```

### Bulk Fixing Alt Text

#### Method 1: Individual Updates

**Best for:** Small numbers, important images

**Process:**

1. Click **Edit** next to image in Missing Alt Text list
2. WordPress media editor opens
3. Add alt text in "Alternative Text" field
4. Consider updating:
    - Title
    - Caption
    - Description
5. Click **Update**
6. Repeat for each image

**Time Required:** 1-2 minutes per image

#### Method 2: Bulk Edit in Media Library

**Best for:** Moderate numbers, organized approach

**Process:**

1. Navigate to **Media > Library**
2. Switch to **List View**
3. Filter by:
    - Upload date
    - Media type (images only)
4. Click **Edit** for each item
5. Add alt text in quick edit panel
6. Save changes
7. Move to next item

**Time Required:** 30-60 seconds per image

#### Method 3: Use Plugin Tools

**Best for:** Large numbers, automated approach

**Recommended Plugins:**

**Auto Alt Text Plugins:**

- SEO Optimized Images (auto-generates from file name)
- Image SEO (analyzes image and suggests alt text)
- WP Accessibility (bulk alt text tools)

**AI-Powered Options:**

- AltTextAI (uses computer vision)
- Accessibility Checker (suggests improvements)

**Process:**

1. Install and activate plugin
2. Configure alt text generation rules
3. Review and approve suggestions
4. Apply in bulk
5. Manually review critical images

**Caution:** AI-generated alt text should be reviewed for accuracy and relevance.

#### Method 4: Bulk Update via Database

**Best for:** Very large numbers, technical users

**Warning:** Direct database edits can cause issues. Backup first!

**Process:**

```sql
-- Example: Update alt text based on post title
UPDATE wp_postmeta
SET meta_value = (
  SELECT post_title
  FROM wp_posts
  WHERE ID = wp_postmeta.post_id
)
WHERE meta_key = '_wp_attachment_image_alt'
AND (meta_value IS NULL OR meta_value = '');
```

**Only use if:**

- Comfortable with SQL
- Have full database backup
- Tested on staging first
- Understand WordPress database structure

### Alt Text Workflow for New Uploads

**Prevent Future Missing Alt Text:**

**1. Add During Upload**

- Fill alt text field immediately
- Before clicking "Insert into post"
- Make it a standard practice
- Train all content creators

**2. Content Creation Guidelines**

Create a checklist for content creators:

```
Image Upload Checklist:
[ ] Image optimized (size and dimensions)
[ ] Descriptive file name
[ ] Alt text added (specific and concise)
[ ] Title filled (optional)
[ ] Caption added (if needed for context)
[ ] Image inserted in content
```

**3. Editorial Review Process**

Before publishing:

```
Pre-Publish Content Review:
[ ] All images have alt text
[ ] Alt text is descriptive, not generic
[ ] No file names as alt text
[ ] Decorative images have empty alt
[ ] Alt text under 125 characters
[ ] Keywords used naturally
```

**4. Automated Reminders**

Some plugins can:

- Alert when publishing with missing alt text
- Block publish until alt text added
- Show dashboard reminder
- Generate alt text reports

### Testing Accessibility

**How to Verify Alt Text Works:**

**1. Manual Testing**

**Right-Click Inspect:**

```html
<!-- Check the HTML -->
<img src="image.jpg" alt="Descriptive text here" />
```

**Turn Off Images:**

- Browser settings > Disable images
- See if alt text displays
- Verify descriptions make sense

**2. Screen Reader Testing**

**Free Screen Readers:**

- NVDA (Windows) - Free
- JAWS (Windows) - Paid, widely used
- VoiceOver (Mac/iOS) - Built-in
- TalkBack (Android) - Built-in

**Test Process:**

1. Enable screen reader
2. Navigate page with keyboard only
3. Listen to image descriptions
4. Verify clarity and context

**3. Automated Testing Tools**

**Browser Extensions:**

- WAVE (WebAIM)
- axe DevTools
- Lighthouse (Chrome DevTools)

**Online Tools:**

- WAVE Web Accessibility Evaluation Tool
- AChecker
- Accessibility Insights

**What to Check:**

- All images have alt attributes
- No images flagged as missing alt
- Decorative images properly marked
- Alt text quality scores

### Alt Text Compliance Requirements

**WCAG Guidelines:**

**Level A (Minimum):**

- All non-decorative images have alt text
- Alt text is meaningful
- Decorative images have empty alt

**Level AA (Standard):**

- Alt text describes image purpose
- Context-appropriate descriptions
- No redundant "image of" phrases

**Level AAA (Enhanced):**

- Extended descriptions for complex images
- Supplementary long descriptions
- Multiple description options

**Legal Compliance:**

**ADA (Americans with Disabilities Act):**

- Requires accessible websites
- Alt text is essential component
- Legal liability if missing
- Regular audits recommended

**WCAG 2.1 Standards:**

- Internationally recognized
- Required for many government sites
- Best practice for all websites
- Success criteria for alt text

### Measuring Alt Text Coverage

**Key Metrics:**

**Coverage Percentage:**

```
Alt Text Coverage = (Images with alt text / Total images) × 100

Example:
(850 images with alt / 1000 total images) × 100 = 85% coverage
```

**Target Goals:**

| Coverage  | Rating    | Action                  |
| --------- | --------- | ----------------------- |
| 95-100%   | Excellent | Maintain standards      |
| 85-94%    | Good      | Improve gradually       |
| 70-84%    | Fair      | Prioritize improvement  |
| Below 70% | Poor      | Immediate action needed |

**Quality Metrics:**

Beyond just having alt text:

```
Quality Checklist:
✓ Descriptive (not generic)
✓ Concise (< 125 characters)
✓ Relevant to context
✓ No file names
✓ Proper use of empty alt for decorative
```

---

## Safe Deletion Process

### Understanding Safe Deletion

Safe deletion is a multi-step process designed to minimize risk when removing media files:

**Why "Safe" Deletion Matters:**

**Permanent Consequences:**

- Deleted media files cannot be recovered
- Broken images damage user experience
- SEO impact from missing images
- Lost visual content history

**Complex Dependencies:**

- Files may be used in unexpected places
- Page builders store references
- Custom fields link to media
- Theme customizations reference files

**Safe Deletion Philosophy:**

1. **Trash First, Delete Later**
    - Move to trash, don't delete immediately
    - Review impact for 7-30 days
    - Restore if issues arise
    - Permanent delete only after verification

2. **Backup Before Deletion**
    - Full site backup recommended
    - Download specific files locally
    - Keep backups for 30-90 days
    - Test restore capability

3. **Verify Impact**
    - Check for broken images
    - Review important pages
    - Monitor error logs
    - Test functionality

### The Three-Stage Deletion Process

#### Stage 1: Move to Trash (Reversible)

**What Happens:**

- File remains in database
- Marked as "trash" status
- Hidden from media library
- Easily restorable
- No file deletion yet

**How to Move to Trash:**

1. Navigate to **Media Audit > Unused Files** tab
2. Select files to delete (checkboxes)
3. Choose **Delete Selected** from Bulk Actions
4. Click **Apply**
5. Confirm the action
6. Files move to trash

**Retention Period:**

- WordPress default: 30 days
- Configurable in plugin settings
- Recommended: 14-30 days
- Longer for critical sites

**What You Can Still Do:**

- View trashed items in Media > Library > Trash
- Restore individual files
- Preview before permanent deletion
- Bulk restore if needed

#### Stage 2: Verification Period (7-30 Days)

**What to Monitor:**

**1. Visual Inspection** (Daily for first week)

Visit key pages:

- Homepage
- Popular blog posts
- Product pages
- Landing pages
- About/Contact pages

Check for:

- Broken image icons
- Missing visual elements
- Layout problems
- 404 errors in browser console

**2. Error Log Monitoring**

Check WordPress debug log:

```
wp-content/debug.log
```

Look for:

- Missing file warnings
- 404 file not found errors
- Broken attachment references

**3. User Feedback**

Monitor for:

- User reports of broken images
- Contact form submissions about issues
- Support tickets
- Social media mentions

**4. Analytics Review**

Check Google Analytics or similar:

- Increased bounce rate on specific pages
- Drop in engagement
- User behavior changes
- Exit rate increases

**If Issues Arise:**

**Immediate Restoration:**

1. Navigate to **Media > Library**
2. Click **Trash** tab
3. Find the problematic file
4. Click **Restore**
5. File returns to media library
6. References automatically reconnect

**Document the Issue:**

- Note which file caused problems
- Where it was used
- Mark as "do not delete"
- Add to exclusions list

#### Stage 3: Permanent Deletion (Irreversible)

**Only Proceed If:**

- ✅ Verification period complete (14-30 days)
- ✅ No broken images reported
- ✅ No errors in logs
- ✅ Full backup completed
- ✅ Confident in deletion decision

**How to Permanently Delete:**

**Method 1: Via WordPress Admin**

1. Navigate to **Media > Library**
2. Click **Trash** tab
3. Review files in trash
4. Select items to permanently delete
5. Choose **Delete Permanently** from Bulk Actions
6. Click **Apply**
7. Confirm final deletion

**Method 2: Automatic After Retention Period**

Configure automatic permanent deletion:

1. **Admin Health > Settings > Media**
2. **Trash Retention** section
3. Set retention days (default: 30)
4. Enable **Auto-Delete After Retention**
5. Files automatically deleted after period

**What Actually Happens:**

**File System:**

- Physical files deleted from server
- All image sizes removed
- Thumbnail variants deleted
- Cannot be recovered from filesystem

**Database:**

- Attachment post permanently removed
- All metadata deleted
- References become invalid
- History erased

### Restoration Process

**When You Need to Restore:**

Common scenarios:

- Found the file is actually needed
- User reported broken image
- Feature stopped working
- New requirement for old file

**Restoration Window:**

```
Timeline:
Day 1-30: In Trash → Easy restore via WordPress
Day 31+: Permanently deleted → Restore from backup only
```

#### Restoring from Trash (Easy)

**Step-by-Step:**

1. **Locate the File**
    - Media > Library > Trash
    - Use search if needed
    - Check deletion date

2. **Restore the File**
    - Hover over file
    - Click **Restore**
    - File returns to library
    - Status: Published

3. **Verify Restoration**
    - File appears in media library
    - Check if images reappear on pages
    - Test functionality
    - Clear cache if needed

**Bulk Restoration:**

1. **Media > Library > Trash**
2. Select multiple files (checkboxes)
3. **Bulk Actions > Restore**
4. **Apply**
5. All selected files restored

#### Restoring from Backup (Complex)

**If Permanently Deleted:**

**Prerequisites:**

- Have full site backup
- Backup includes media files
- Know file location in backup
- Have FTP/server access

**Process:**

1. **Locate in Backup**
    - Extract backup archive
    - Navigate to wp-content/uploads/
    - Find file by name or date
    - Note original path

2. **Restore File to Server**
    - Via FTP: Upload to correct path
    - Via cPanel: Use file manager
    - Via SSH: Copy file to server
    - Maintain folder structure

3. **Restore Database Entry**
    - Import from database backup, or
    - Re-upload via media library
    - WordPress recreates thumbnails
    - Update references if needed

4. **Verify and Reconnect**
    - Check file displays correctly
    - Update posts if needed
    - Regenerate thumbnails
    - Clear all caches

### Deletion Safety Checklist

**Before Moving to Trash:**

- [ ] **Backup completed** (within 24 hours)
- [ ] **Reviewed unused list** for false positives
- [ ] **Excluded critical files** (logos, essential assets)
- [ ] **Documented deletion** (note what and why)
- [ ] **Team notified** (if applicable)
- [ ] **Staging tested** (if available)

**During Verification Period:**

- [ ] **Monitor daily** (first 7 days)
- [ ] **Check key pages** for broken images
- [ ] **Review error logs** for 404s
- [ ] **Test functionality** (forms, sliders, etc.)
- [ ] **Collect user feedback** (no reports of issues)
- [ ] **Verify analytics** (no unusual drops)

**Before Permanent Deletion:**

- [ ] **Full verification complete** (14-30 days)
- [ ] **No issues reported** or found
- [ ] **Fresh backup** (within 24 hours)
- [ ] **Document permanent deletion** (log action)
- [ ] **Team final approval** (if required)
- [ ] **Restoration plan** (know how to recover from backup)

### Recovery Scenarios

**Scenario 1: Accidentally Deleted Wrong File**

**Immediate Action:**

1. Don't panic
2. Go to Media > Library > Trash
3. Find and restore file immediately
4. Verify restoration
5. Document mistake

**Time Limit:** Restore before trash is emptied (usually 30 days)

**Scenario 2: Deleted File Still Needed**

**If in Trash:**

1. Restore from trash (see above)
2. Update usage documentation
3. Add to exclusions list
4. Mark as "do not delete"

**If Permanently Deleted:**

1. Restore from most recent backup
2. Upload via FTP to correct location
3. Verify file path matches original
4. Clear caches
5. Document for future reference

**Scenario 3: Bulk Deletion Went Wrong**

**Immediate Response:**

1. Stop any ongoing operations
2. List what was deleted
3. Restore all from trash
4. Review what actually should be deleted
5. Delete again (carefully) in smaller batches

**Prevention:**

- Start with small batches (10-20 files)
- Review each batch
- Pause between batches
- Monitor for issues

### Deletion Best Practices

**Safe Deletion Strategy:**

**Week 1: Preparation**

- Run full backup
- Review unused media list
- Identify critical files to exclude
- Create deletion plan
- Document process

**Week 2: Initial Deletion (Trash)**

- Delete first small batch (25-50 files)
- Monitor for issues
- Verify no breakage
- Continue in batches if successful

**Weeks 3-4: Verification**

- Daily monitoring (week 3)
- Weekly checks (week 4)
- Collect feedback
- Review logs
- Document results

**Week 5: Permanent Deletion**

- Final backup
- Permanent delete trash
- Final verification
- Document completion

**Ongoing Maintenance:**

- Monthly media audits
- Regular backup verification
- Continuous monitoring
- Updated exclusions

---

## Integration with Page Builders

### Common Page Builders

WP Admin Health Suite is designed to work with popular page builders, but each has unique considerations:

**Supported Page Builders:**

- Elementor
- WPBakery Page Builder
- Beaver Builder
- Divi Builder
- Oxygen Builder
- Gutenberg (Block Editor)
- Bricks Builder
- GeneratePress

### How Page Builders Store Media

**Understanding the Differences:**

**Standard WordPress:**

```
Storage: Post content (post_content field)
Format: HTML with <img> tags
Scanning: Straightforward
```

**Page Builders:**

```
Storage: Post meta, custom tables, JSON
Format: Serialized data, shortcodes, JSON
Scanning: More complex
```

**Example Storage Methods:**

**Elementor:**

```json
// Stored in post meta as JSON
{
	"id": "abc123",
	"elType": "widget",
	"widgetType": "image",
	"settings": {
		"image": {
			"id": 456, // Media library ID
			"url": "https://site.com/wp-content/uploads/image.jpg"
		}
	}
}
```

**WPBakery:**

```
// Stored as shortcodes
[vc_single_image image="456" img_size="large"]
```

**Divi:**

```
// Stored in post meta
background_image="https://site.com/wp-content/uploads/bg.jpg"
```

### Detection Capabilities

**What the Scanner Detects:**

**✅ Successfully Detected:**

**Elementor:**

- Image widgets
- Background images (sections, columns)
- Galleries
- Carousels and sliders
- Icon boxes with images
- Most dynamic content

**Beaver Builder:**

- Photo modules
- Galleries
- Background images
- Slideshow modules

**Divi:**

- Image modules
- Background images
- Gallery modules
- Sliders

**Gutenberg:**

- Image blocks
- Gallery blocks
- Cover blocks
- Media & Text blocks

**WPBakery:**

- Single image elements
- Image galleries
- Background images in rows/columns

**❓ May Be Missed:**

**Custom Shortcodes:**

```
[custom_slider id="123"]
```

If the shortcode doesn't store standard attachment IDs.

**Third-Party Add-ons:**

- Custom page builder widgets
- Third-party extensions
- Non-standard storage methods

**Dynamic Content:**

- ACF image fields (usually detected)
- Custom field implementations
- Dynamic widgets
- Conditional displays

**CSS References:**

```css
.custom-background {
	background-image: url('/uploads/image.jpg');
}
```

### Page Builder-Specific Considerations

#### Elementor

**What Works Well:**

- Standard widgets fully detected
- Template library media tracked
- Global widgets recognized
- Theme Builder media included

**Potential Issues:**

- Custom CSS backgrounds may be missed
- Third-party widgets vary
- Dynamic content depends on storage

**Best Practices:**

1. Use Elementor's native image widgets
2. Avoid hardcoded URLs in custom CSS
3. Store background images via widget settings
4. Test media detection before bulk deletion

**Safe Testing:**

1. Create a test page with various widgets
2. Add images via different methods
3. Run media scan
4. Verify all images marked as "used"
5. If missed, add to exclusions

#### Divi Builder

**What Works Well:**

- Standard modules detected
- Background images tracked
- Gallery modules supported
- Theme Builder elements included

**Potential Issues:**

- Some custom CSS backgrounds
- Divi Library items storage
- Pre-made layout imports

**Best Practices:**

1. Import images properly through modules
2. Use module settings for backgrounds
3. Avoid Custom CSS for image URLs
4. Test Divi Library separately

**Verification Steps:**

1. Check Divi Library layouts
2. Verify global modules
3. Test pre-made layouts
4. Confirm theme builder media

#### Beaver Builder

**What Works Well:**

- Photo modules detected
- Background images tracked
- Gallery modules supported
- Saved templates included

**Potential Issues:**

- Custom module add-ons
- Third-party extensions
- Some dynamic sources

**Best Practices:**

1. Use native photo modules
2. Set backgrounds via module settings
3. Test saved templates
4. Verify global rows/modules

#### WPBakery Page Builder

**What Works Well:**

- Image shortcodes detected
- Background images in rows/columns
- Standard gallery elements
- Template library media

**Potential Issues:**

- Custom shortcode parameters
- Third-party VC add-ons
- Some template imports

**Best Practices:**

1. Use standard VC image elements
2. Avoid custom shortcodes for critical media
3. Test template library separately
4. Document custom implementations

#### Gutenberg (Block Editor)

**What Works Well:**

- Native blocks fully detected
- Image blocks
- Gallery blocks
- Cover blocks
- Media & Text blocks

**Potential Issues:**

- Custom third-party blocks
- Dynamic block content
- Some block variations

**Best Practices:**

1. Use core blocks when possible
2. Test third-party blocks individually
3. Verify reusable blocks
4. Check block patterns

### Testing Page Builder Integration

**Comprehensive Testing Process:**

**1. Create Test Page**

Build a test page using your page builder with:

- Image widgets/modules
- Background images
- Galleries
- Sliders/carousels
- Icon boxes with images
- Custom sections

**2. Run Media Scan**

1. Navigate to **Admin Health > Media Audit**
2. Click **Rescan Media**
3. Wait for completion

**3. Check Detection**

1. Go to **Unused Files** tab
2. Search for test page images
3. Verify none marked as "unused"
4. If any missed, note which types

**4. Review Different Modules**

Test each module type:

```
Module Type: Image Widget
Expected: Detected ✓
Actual: [Your result]

Module Type: Background Image
Expected: Detected ✓
Actual: [Your result]

Module Type: Custom Slider
Expected: Detected ✓
Actual: [Your result]
```

**5. Document Findings**

Create a reference:

```
Page Builder: Elementor
Version: 3.18

Detected:
✓ Image widgets
✓ Background images (sections/columns)
✓ Gallery widgets
✓ Icon boxes

Not Detected:
✗ Custom CSS backgrounds
✗ Third-party carousel plugin
```

### Handling Undetected Media

**If Page Builder Media Is Missed:**

**Option 1: Use Exclusions**

1. Identify media used in page builder
2. Manually review pages
3. Add to exclusions list
4. Document which pages/templates use them

**Process:**

1. **Media Audit > Unused Files**
2. Search for specific files
3. Select them (checkboxes)
4. **Bulk Actions > Ignore Selected**
5. Add reason: "Used in [Page Builder] on [Page Name]"
6. **Apply**

**Option 2: Manual Verification**

Before deleting "unused" files:

1. **Search Site for File**
    - Use page builder's search
    - Check template library
    - Review global elements
    - Search saved rows/sections

2. **Visual Page Review**
    - Visit all pages built with page builder
    - Check for missing images
    - Verify backgrounds load
    - Test galleries and sliders

**Option 3: Conservative Deletion**

Safe approach:

1. Only delete files older than 1 year
2. Skip files uploaded in last 6 months
3. Exclude files with unclear usage
4. Delete in very small batches
5. Extensive verification period

### Page Builder Backup Recommendations

**Before Media Cleanup:**

**Export Page Builder Content:**

**Elementor:**

1. Tools > Export Templates
2. Export all templates
3. Export theme builder templates
4. Save locally

**Divi:**

1. Divi > Theme Options
2. Export Builder Layouts
3. Save Divi Library items
4. Export theme options

**Beaver Builder:**

1. Export saved templates
2. Export global rows
3. Export global modules
4. Save locally

**WPBakery:**

1. Export templates library
2. Save custom elements
3. Export theme options

**Why This Matters:**

- Page builder data contains media references
- Easier to identify used media
- Restoration safety net
- Documentation of usage

### Troubleshooting Page Builder Issues

**Issue: Page Builder Images Marked as Unused**

**Diagnosis:**

1. Verify page builder is active
2. Check plugin compatibility
3. Review storage method
4. Test with single page

**Solutions:**

1. Update media scanner (plugin update)
2. Report to plugin support
3. Use manual verification
4. Add affected files to exclusions

**Issue: Deleted Media Breaks Page Builder**

**Immediate Fix:**

1. Restore from trash (if available)
2. Or restore from backup
3. Re-upload file to same location
4. Clear page builder cache
5. Regenerate page builder CSS

**Prevention:**

1. More thorough testing
2. Longer verification period
3. Conservative deletion approach
4. Better exclusion documentation

**Issue: Dynamic Content Not Detected**

**Understanding:**

- Dynamic content loads differently
- May use non-standard storage
- Template rendering varies

**Solutions:**

1. Manually verify dynamic sections
2. Exclude dynamic content media
3. Test dynamic templates separately
4. Document dynamic media usage

---

## Best Practices Summary

### Media Audit Workflow

**Weekly Maintenance (15 minutes):**

1. **Quick Review**
    - Check Media Audit dashboard
    - Review recent uploads
    - Note any issues

2. **Scan Updates**
    - Run media scan if last one > 7 days
    - Review statistics
    - Check for new duplicates

**Monthly Cleanup (60 minutes):**

1. **Comprehensive Scan** (10 min)
    - Run full media scan
    - Review all categories
    - Document findings

2. **Unused Media Review** (20 min)
    - Review unused files list
    - Verify false positives
    - Add exclusions as needed
    - Move truly unused to trash

3. **Duplicate Cleanup** (15 min)
    - Review duplicate groups
    - Keep best quality versions
    - Delete obvious copies
    - Update references if needed

4. **Large File Optimization** (15 min)
    - Identify files > 1 MB
    - Optimize largest files first
    - Test optimized versions
    - Measure improvements

**Quarterly Deep Clean (2-3 hours):**

1. **Alt Text Audit** (60 min)
    - Review all missing alt text
    - Bulk update descriptions
    - Document patterns
    - Train content creators

2. **Verification and Cleanup** (60 min)
    - Final review before permanent deletion
    - Delete trash (after 30 days)
    - Verify no issues
    - Update documentation

3. **Optimization Review** (30 min)
    - Measure overall improvements
    - Adjust thresholds
    - Update guidelines
    - Plan next quarter

### Safety Checklist

**Before Any Deletion:**

- [ ] Full site backup completed (< 24 hours old)
- [ ] Backup tested and verified
- [ ] Reviewed unused files list carefully
- [ ] Excluded critical files (logos, essential assets)
- [ ] Tested on staging site (if available)
- [ ] Team notified (if applicable)
- [ ] Deletion documented

**During Verification Period:**

- [ ] Daily monitoring (first week)
- [ ] Check key pages for broken images
- [ ] Review error logs
- [ ] Monitor user feedback
- [ ] Test all functionality
- [ ] Verify analytics metrics

**Before Permanent Deletion:**

- [ ] Verification complete (14-30 days)
- [ ] No issues reported
- [ ] Fresh backup completed
- [ ] Final review of trash
- [ ] Team approval (if required)

### Getting Help

**When to Seek Support:**

Contact support if:

- Scanner not detecting page builder media
- Large number of false positives
- Unsure about specific files
- Planning very large cleanup (1000+ files)
- Multisite installation
- Critical production site
- Experiencing technical issues

**Resources:**

**Documentation:**

- This guide (comprehensive reference)
- [Getting Started Guide](./getting-started.md)
- [Database Cleanup Guide](./database-cleanup.md)
- [FAQ Section](./README.md#faq)

**Support Channels:**

- Plugin support forum
- WordPress.org forums
- Plugin documentation site
- Email support (if applicable)

---

## Final Thoughts

Media management is an essential part of WordPress maintenance. Regular attention to your media library:

- Improves site performance
- Reduces storage costs
- Enhances accessibility
- Supports better SEO
- Improves user experience

**Remember:**

**The Golden Rules:**

1. Always backup before cleanup
2. Move to trash first, delete later
3. Review before permanent deletion
4. Monitor after cleanup
5. Document exclusions
6. Test with page builders
7. When in doubt, don't delete

**Key Takeaway:**
The best media cleanup strategy is methodical and cautious. Start small, verify thoroughly, and gradually optimize based on your specific needs and comfort level.

**Questions or Issues?**

- Check the [Getting Started Guide](./getting-started.md)
- Review the [Database Cleanup Guide](./database-cleanup.md)
- Contact support through the plugin
- Visit WordPress.org support forums

Happy optimizing, and here's to a healthier, faster WordPress site!
