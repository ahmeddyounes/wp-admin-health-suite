# Getting Started with WP Admin Health Suite

Welcome to WP Admin Health Suite! This guide will help you get your WordPress site cleaned up, optimized, and running at peak performance in just a few minutes.

## Table of Contents

- [Installation](#installation)
- [Activation](#activation)
- [First Scan](#first-scan)
- [Understanding Your Health Score](#understanding-your-health-score)
- [Quick Wins](#quick-wins)
- [Recommended Settings](#recommended-settings)
- [Troubleshooting](#troubleshooting)
- [Video Tutorial](#video-tutorial)

---

## Installation

### System Requirements

Before installing WP Admin Health Suite, ensure your WordPress site meets these minimum requirements:

- **WordPress Version:** 6.0 or higher
- **PHP Version:** 7.4 or higher
- **Database:** MySQL 5.6+ or MariaDB 10.0+
- **Required PHP Extensions:** MySQLi or PDO
- **Recommended:** Action Scheduler plugin (optional, but improves scheduled task reliability)

### Installation Steps

#### Method 1: WordPress Plugin Directory (Recommended)

1. Log in to your WordPress admin panel
2. Navigate to **Plugins > Add New**
3. Search for "WP Admin Health Suite"
4. Click **Install Now** on the WP Admin Health Suite plugin
5. Wait for the installation to complete
6. Click **Activate** to enable the plugin

#### Method 2: Manual Upload

1. Download the plugin ZIP file from the WordPress Plugin Directory or your purchase location
2. Log in to your WordPress admin panel
3. Navigate to **Plugins > Add New > Upload Plugin**
4. Click **Choose File** and select the downloaded ZIP file
5. Click **Install Now**
6. After installation completes, click **Activate Plugin**

#### Method 3: FTP Upload

1. Extract the plugin ZIP file on your computer
2. Connect to your server via FTP
3. Upload the `wp-admin-health-suite` folder to `/wp-content/plugins/`
4. Log in to your WordPress admin panel
5. Navigate to **Plugins > Installed Plugins**
6. Find "WP Admin Health Suite" and click **Activate**

### What Happens During Installation

When you activate WP Admin Health Suite, the plugin automatically:

- Creates two database tables for storing scan history and scheduled tasks
- Sets up default configuration with safe, recommended settings
- Initializes the health score calculation system
- Registers scheduled maintenance tasks (if enabled)
- Adds the "Admin Health" menu to your WordPress admin sidebar

![Screenshot: Successful activation notice](./screenshots/activation-success.png)
*Screenshot: You'll see this success message after activation*

---

## Activation

### First-Time Setup

After activating the plugin, you'll see a welcome notice at the top of your WordPress admin:

![Screenshot: Welcome notice](./screenshots/welcome-notice.png)
*Screenshot: Welcome notice with quick start options*

The welcome notice provides quick links to:
- View your initial health score
- Run your first database scan
- Configure settings
- Access documentation

### Accessing the Plugin

Once activated, you can access WP Admin Health Suite from:

1. **Main Menu:** Look for "Admin Health" in the WordPress admin sidebar (with a heart icon)
2. **Admin Bar:** (Optional) Quick access menu at the top of admin pages
3. **Dashboard Widget:** (Optional) Health overview on your WordPress Dashboard

![Screenshot: Admin menu location](./screenshots/admin-menu.png)
*Screenshot: Admin Health menu in the WordPress sidebar*

### Initial Configuration (Optional)

While WP Admin Health Suite works great with default settings, you may want to configure a few things right away:

1. Navigate to **Admin Health > Settings**
2. Click on the **General** tab
3. Configure these optional settings:
   - **Notification Email:** Enter your email to receive health alerts
   - **Enable Dashboard Widget:** Show health overview on WordPress Dashboard
   - **Show Admin Bar Menu:** Quick access from the admin bar
4. Click **Save Settings** at the bottom

The plugin is now ready to use!

---

## First Scan

### Running Your First Health Check

Let's assess your site's current health status:

1. Navigate to **Admin Health > Dashboard**
2. The page will automatically calculate your initial health score
3. Wait a few seconds while the plugin analyzes your site

![Screenshot: Dashboard loading](./screenshots/dashboard-loading.png)
*Screenshot: Health score calculation in progress*

### Understanding the Dashboard

Once the scan completes, you'll see:

![Screenshot: Complete dashboard](./screenshots/dashboard-complete.png)
*Screenshot: Main dashboard with health score and metrics*

**Health Score Circle:**
- Large circular indicator showing your overall score (0-100)
- Color-coded: Green (80+), Yellow (60-79), Red (0-59)
- Letter grade: A, B, C, D, or F

**Key Metrics Cards:**
1. **Database Size:** Total size of your WordPress database
2. **Media Files:** Number of files in your media library
3. **Active Plugins:** Count of currently active plugins
4. **Last Cleanup:** Timestamp of most recent maintenance

**Recent Activity:**
- Shows recent cleanup operations
- Displays items cleaned and space freed
- Provides audit trail of changes

**AI Recommendations:**
- Automatically generated suggestions based on your site's health
- Prioritized by impact
- Actionable steps to improve your score

### Running Your First Database Scan

Now let's see what can be cleaned up:

1. Navigate to **Admin Health > Database Health**
2. The page automatically scans your database
3. Review the "Overview Cards" showing:
   - Total database size
   - Number of tables
   - Potential space savings

![Screenshot: Database Health overview](./screenshots/database-overview.png)
*Screenshot: Database Health page with overview metrics*

### Reviewing Cleanup Opportunities

Scroll down to see the cleanup modules:

![Screenshot: Cleanup modules](./screenshots/cleanup-modules.png)
*Screenshot: Accordion-style cleanup modules*

Each module shows:
- **Items Found:** Number of items that can be cleaned
- **Potential Savings:** Estimated space to be freed
- **Last Run:** When this cleanup was last performed
- **Actions:** Preview and Clean buttons

**Available Cleanup Modules:**
1. **Post Revisions:** Old versions of posts and pages
2. **Auto-Drafts:** Automatically saved drafts
3. **Trashed Posts:** Posts in trash older than configured retention
4. **Spam Comments:** Comments marked as spam
5. **Trashed Comments:** Comments in trash
6. **Expired Transients:** Outdated temporary cache data
7. **Orphaned Metadata:** Database records for deleted content

---

## Understanding Your Health Score

### How the Score is Calculated

Your health score (0-100) is calculated using five weighted factors:

```
Overall Score = (Database Health × 25%) + (Media Health × 20%) +
                (Plugin Performance × 25%) + (Revisions × 15%) +
                (Transients × 15%)
```

### The Five Health Factors

#### 1. Database Bloat (25% weight)

Examines database cleanliness:
- Trashed posts and pages
- Spam comments
- Orphaned metadata (data from deleted plugins/posts)
- Auto-draft posts

**What affects this score:**
- More than 100 trashed posts: Score decreases
- More than 50 spam comments: Score decreases
- Orphaned metadata present: Score decreases
- More than 20 auto-drafts: Score decreases

#### 2. Unused Media (20% weight)

Analyzes media library efficiency:
- Unattached media files (not used in posts/pages)
- Total media count vs. usage

**What affects this score:**
- More than 20% unattached media: Score decreases
- Large number of unused files: Score decreases

#### 3. Plugin Performance (25% weight)

Evaluates plugin health:
- Number of active plugins
- Number of inactive plugins
- Total plugin count

**What affects this score:**
- More than 20 active plugins: Score decreases
- More than 5 inactive plugins: Score decreases
- Total plugins exceeding 30: Score decreases

#### 4. Revision Count (15% weight)

Checks post revision efficiency:
- Average revisions per post
- Total revision count

**What affects this score:**
- More than 5 revisions per post on average: Score decreases
- Total revisions exceeding 500: Score decreases

#### 5. Transient Bloat (15% weight)

Monitors WordPress cache:
- Total transients (temporary cache entries)
- Expired transients still in database

**What affects this score:**
- More than 200 total transients: Score decreases
- More than 50 expired transients: Score decreases

### Score Grades and What They Mean

| Grade | Score Range | Status | What to Do |
|-------|-------------|--------|------------|
| **A** | 90-100 | Excellent | Your site is in great shape! Maintain current practices. |
| **B** | 80-89 | Good | Solid health. Consider minor optimizations. |
| **C** | 70-79 | Fair | Some issues present. Follow recommendations. |
| **D** | 60-69 | Poor | Attention needed. Perform suggested cleanups. |
| **F** | 0-59 | Critical | Immediate action required. Start with Quick Wins. |

### Score Caching

For performance, health scores are cached for 1 hour. To force a refresh:

1. Go to **Admin Health > Dashboard**
2. Scroll to the health score section
3. Click the **Refresh Score** button (if available)

Or wait for the automatic refresh after 1 hour.

---

## Quick Wins

These are the fastest ways to improve your health score in minutes:

### 1. Clean Expired Transients (2 minutes)

**Impact:** Medium to High | **Risk:** Very Low

Expired transients are safe to remove and often provide immediate benefits:

1. Go to **Admin Health > Database Health**
2. Expand the **Expired Transients** module
3. Click **Preview** to see what will be removed
4. Click **Clean** to remove expired cache entries
5. Confirm the action

**Expected Improvement:** 5-15 points if you had many expired transients

### 2. Remove Spam Comments (3 minutes)

**Impact:** Medium | **Risk:** Very Low

Spam comments bloat your database with no benefit:

1. Go to **Admin Health > Database Health**
2. Expand the **Spam Comments** module
3. Click **Preview** to review spam comments
4. Click **Clean** to permanently delete them
5. Confirm the action

**Expected Improvement:** 5-10 points if you had spam accumulation

### 3. Clean Trashed Posts and Comments (5 minutes)

**Impact:** Medium | **Risk:** Low

Items in trash are meant to be temporary:

1. Go to **Admin Health > Database Health**
2. Expand the **Trashed Posts** module
3. Click **Preview** to see posts that have been in trash for 30+ days
4. Click **Clean** to permanently delete old trash
5. Repeat for **Trashed Comments** module

**Expected Improvement:** 5-15 points depending on trash volume

### 4. Deactivate Unused Plugins (10 minutes)

**Impact:** High | **Risk:** Low (if plugins are truly unused)

Inactive plugins still count against your score:

1. Go to **Plugins > Installed Plugins** in WordPress
2. Review inactive plugins
3. Delete plugins you don't plan to use
4. Return to **Admin Health > Dashboard** to see score improvement

**Expected Improvement:** 10-20 points if you had many inactive plugins

### 5. Scan and Remove Unused Media (15 minutes)

**Impact:** High | **Risk:** Low with preview

Remove media files not used anywhere on your site:

1. Go to **Admin Health > Media Audit**
2. Click **Rescan Media** button
3. Wait for scan to complete
4. Review the **Unused Media** section
5. Select files that are confirmed unused
6. Click **Safe Delete** (moves to trash, not permanent)
7. Verify your site looks correct
8. Empty media trash if satisfied

**Expected Improvement:** 10-25 points if you had significant unused media

### 6. Limit Post Revisions (2 minutes)

**Impact:** Long-term benefit | **Risk:** None

Prevent future revision bloat:

1. Go to **Admin Health > Settings**
2. Click on **Database Cleanup** tab
3. Find **Revisions to Keep** setting
4. Set to a reasonable number (recommended: 5-10)
5. Enable **Clean Post Revisions** checkbox
6. Click **Save Settings**

**Expected Improvement:** Prevents future score degradation

### Quick Win Checklist

Follow this order for maximum impact in minimum time:

- [ ] Clean expired transients (2 min)
- [ ] Remove spam comments (3 min)
- [ ] Clean trashed posts/comments (5 min)
- [ ] Deactivate unused plugins (10 min)
- [ ] Set revision limits (2 min)
- [ ] Scan and remove unused media (15 min)

**Total Time:** ~37 minutes for a potentially 40-60 point score improvement!

---

## Recommended Settings

These are the best settings for most WordPress sites. Adjust based on your specific needs.

### General Settings

Navigate to **Admin Health > Settings > General**:

| Setting | Recommended Value | Why |
|---------|-------------------|-----|
| Health Score Cache Duration | 1 hour | Balances performance and freshness |
| Enable Dashboard Widget | Enabled | Quick visibility into site health |
| Show Admin Bar Menu | Enabled | Easy access to health tools |
| Notification Email | Your email | Get alerts about issues |
| Enable Logging | Enabled | Maintain audit trail |
| Log Retention Days | 30 days | Keep sufficient history without bloat |
| Delete Data on Uninstall | Disabled | Preserve data if you reinstall |
| Health Score Threshold | 70 | Get notified when score drops below "Fair" |

### Database Cleanup Settings

Navigate to **Admin Health > Settings > Database Cleanup**:

| Setting | Recommended Value | Why |
|---------|-------------------|-----|
| Clean Post Revisions | Enabled | Prevent revision bloat |
| Revisions to Keep | 5-10 | Balance history with database size |
| Clean Auto-Drafts | Enabled | Remove unnecessary drafts |
| Clean Trashed Posts | Enabled | Automatically clean old trash |
| Auto Clean Trash | 30 days | Standard WordPress retention |
| Clean Spam Comments | Enabled | Remove spam automatically |
| Auto Clean Spam Comments | 7 days | Quick removal of spam |
| Clean Expired Transients | Enabled | Essential for cache health |
| Clean Orphaned Metadata | Enabled | Remove leftover database entries |
| Optimize Tables Weekly | Enabled | Maintain database performance |

### Media Audit Settings

Navigate to **Admin Health > Settings > Media Audit**:

| Setting | Recommended Value | Why |
|---------|-------------------|-----|
| Scan for Unused Media | Enabled | Identify optimization opportunities |
| Media Retention Days | 90 days | Safety buffer before marking unused |
| Unused Media Scan Depth | All Content | Thorough detection |
| Large File Threshold | 1000 KB (1 MB) | Identify files that should be optimized |
| Duplicate Detection Method | Hash | Most accurate detection |
| Media Trash Retention | 30 days | Time to recover if needed |
| Scan ACF Fields | Enabled (if using ACF) | Check Advanced Custom Fields |
| Scan Elementor | Enabled (if using Elementor) | Check page builder content |
| Scan WooCommerce | Enabled (if using WooCommerce) | Check product images |

### Performance Settings

Navigate to **Admin Health > Settings > Performance**:

| Setting | Recommended Value | Why |
|---------|-------------------|-----|
| Enable Query Monitoring | Enabled | Identify slow queries |
| Slow Query Threshold | 500 ms | Catch performance issues |
| Enable AJAX Monitoring | Enabled | Track async requests |
| Heartbeat Admin Frequency | 60 seconds | Standard interval |
| Heartbeat Editor Frequency | 15 seconds | Good for collaborative editing |
| Enable Heartbeat on Frontend | Disabled | Reduce server load for visitors |
| Query Logging Enabled | Enabled | Debugging capability |
| Plugin Profiling Enabled | Enabled | Identify slow plugins |

### Scheduling Settings

Navigate to **Admin Health > Settings > Scheduling**:

| Setting | Recommended Value | Why |
|---------|-------------------|-----|
| Enable Scheduler | Enabled | Automated maintenance |
| Database Cleanup Frequency | Weekly | Regular maintenance without overhead |
| Media Scan Frequency | Monthly | Balance freshness and performance |
| Performance Check Frequency | Weekly | Regular monitoring |
| Preferred Time | 2:00 AM | Low-traffic hours (adjust for your timezone) |
| Notification on Completion | Enabled | Stay informed |

### Advanced Settings

Navigate to **Admin Health > Settings > Advanced**:

| Setting | Recommended Value | Why |
|---------|-------------------|-----|
| Enable REST API | Enabled | Allow API access if needed |
| REST API Rate Limit | 60 requests/minute | Prevent abuse |
| Debug Mode | Disabled | Enable only for troubleshooting |
| Safe Mode | Disabled | Enable for testing before production |
| Batch Processing Size | 100 | Balance speed and server resources |

### Applying Recommended Settings

**Quick Apply (Manual):**
1. Go through each tab and apply settings from tables above
2. Click **Save Settings** after each tab
3. Verify settings are saved successfully

**Import Preset (If Available):**
1. Go to **Admin Health > Settings > Advanced**
2. Download the recommended settings JSON from this documentation
3. Click **Import Settings**
4. Upload the JSON file
5. Click **Save Settings**

---

## Troubleshooting

### Common Issues and Solutions

#### Issue: Health Score Shows "Calculating..." Forever

**Symptoms:** Dashboard stuck loading health score

**Solutions:**
1. **Refresh the page:** Sometimes a simple page refresh resolves the issue
2. **Check for JavaScript errors:** Open browser console (F12) and look for errors
3. **Disable conflicting plugins:** Temporarily deactivate other plugins to find conflicts
4. **Clear cache:** If using a caching plugin, clear all caches
5. **Check PHP version:** Ensure you're running PHP 7.4+

**Still not working?** Enable Debug Mode in Settings > Advanced and check error logs.

#### Issue: "Error: Could not connect to database"

**Symptoms:** Error message when trying to run cleanup

**Solutions:**
1. **Check database credentials:** Verify wp-config.php has correct database info
2. **Database server status:** Ensure MySQL/MariaDB is running
3. **Database user permissions:** Ensure WordPress database user has sufficient privileges
4. **Contact hosting:** May be a temporary server issue

#### Issue: Cleanup Operations Timeout

**Symptoms:** Page goes blank or shows timeout error during cleanup

**Solutions:**
1. **Reduce batch size:** Go to Settings > Advanced, lower "Batch Processing Size" to 50
2. **Increase PHP timeout:** Add to wp-config.php: `set_time_limit(300);`
3. **Clean in smaller chunks:** Use Preview to see count, then clean categories separately
4. **Contact hosting:** May need to increase server limits

#### Issue: Media Scan Finds No Unused Media (But You Know There Is Some)

**Symptoms:** Media audit shows 0 unused files when you expect some

**Solutions:**
1. **Increase scan depth:** Settings > Media Audit > Set "Unused Media Scan Depth" to "Deep Scan"
2. **Check retention days:** Files used within retention period aren't marked unused
3. **Enable framework scanning:** Turn on ACF, Elementor, WooCommerce scanning if you use those
4. **Rescan:** Click "Rescan Media" button to force a fresh scan
5. **Check exclude list:** Ensure files aren't in the excluded media IDs list

#### Issue: Health Score Doesn't Improve After Cleanup

**Symptoms:** Performed cleanup but score stays the same

**Solutions:**
1. **Wait for cache expiry:** Score is cached for 1 hour by default
2. **Force refresh:** Look for "Refresh Score" button on dashboard
3. **Clear transient cache:** Use a plugin like "Transients Manager" to clear `wpha_health_score`
4. **Check other factors:** Your cleanup may have improved one factor, but others may need attention

#### Issue: Scheduled Tasks Don't Run

**Symptoms:** No automatic cleanups happening

**Solutions:**
1. **Check scheduler status:** Settings > Scheduling > Ensure "Enable Scheduler" is checked
2. **Verify WP-Cron:** Test if WP-Cron is working: `wp cron test` (if using WP-CLI)
3. **Install Action Scheduler:** This plugin improves scheduled task reliability
4. **Check server cron:** Some hosts disable WP-Cron; may need to set up server cron
5. **Review logs:** Check activity logs for scheduled task errors

#### Issue: "Permission Denied" Errors

**Symptoms:** Cannot delete media files or perform cleanups

**Solutions:**
1. **Check file permissions:** Media files should be owned by web server user
2. **Safe Mode enabled?** If Safe Mode is on, no deletions will occur (by design)
3. **User capabilities:** Ensure you're logged in as Administrator
4. **Server restrictions:** Contact hosting if server has file deletion restrictions

#### Issue: Plugin Conflicts

**Symptoms:** Other plugins stop working after activation

**Solutions:**
1. **Test for conflicts:** Deactivate WP Admin Health Suite, reactivate, see if issue returns
2. **Check known conflicts:** Security and caching plugins sometimes conflict
3. **Update all plugins:** Ensure all plugins are running latest versions
4. **Disable features:** Try disabling REST API or specific features in settings
5. **Report conflict:** Contact support with details about conflicting plugin

#### Issue: Large Database Not Showing Savings

**Symptoms:** Database is large but cleanup shows minimal savings

**Solutions:**
1. **Check what's using space:** Use a plugin like "WP-Optimize" to see table sizes
2. **Consider post content:** Large posts with images in content affect size
3. **Check custom tables:** Third-party plugins may create large tables not scanned by this plugin
4. **Optimize tables:** Database cleanup > Run table optimization
5. **May be normal:** Some sites legitimately need large databases

### Getting Help

If you've tried the solutions above and still have issues:

1. **Enable Debug Mode:**
   - Go to Settings > Advanced
   - Enable "Debug Mode"
   - Enable "Enable Logging"
   - Reproduce the issue
   - Check logs for detailed error messages

2. **Check System Status:**
   - Note your WordPress version
   - Note your PHP version
   - Note active plugins
   - Note theme in use

3. **Contact Support:**
   - Provide your health score and grades
   - Include any error messages
   - Describe what you've already tried
   - Include debug logs if available

4. **Community Resources:**
   - WordPress.org support forums
   - Plugin documentation
   - Video tutorials (see below)

---

## Video Tutorial

Watch our comprehensive video walkthrough to see WP Admin Health Suite in action:

### Getting Started Video

<!-- Video embed placeholder - Replace with actual video when available -->
```html
<iframe width="560" height="315"
  src="https://www.youtube.com/embed/VIDEO_ID_HERE"
  title="WP Admin Health Suite - Getting Started Guide"
  frameborder="0"
  allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
  allowfullscreen>
</iframe>
```

**Video Coming Soon!** Subscribe to be notified when it's available.

**What the video covers:**
- Complete installation walkthrough (0:00-2:30)
- Understanding the dashboard interface (2:30-5:00)
- Running your first database cleanup (5:00-8:00)
- Media audit and optimization (8:00-12:00)
- Performance monitoring setup (12:00-15:00)
- Configuring automated maintenance (15:00-18:00)
- Tips and best practices (18:00-20:00)

### Additional Resources

- **Documentation:** [Full documentation](./README.md)
- **API Reference:** [REST API documentation](./api-reference.md)
- **Advanced Guide:** [Advanced features and customization](./advanced-usage.md)
- **Changelog:** [Version history and updates](../CHANGELOG.md)

---

## Next Steps

Now that you're up and running:

1. **Set up automation:** Configure scheduled cleanups in Settings > Scheduling
2. **Monitor regularly:** Check your dashboard weekly to catch issues early
3. **Follow recommendations:** The AI suggestions are tailored to your site
4. **Explore features:** Try the Performance and Media Audit tools
5. **Stay updated:** Keep the plugin updated for new features and fixes

**Congratulations!** You've successfully set up WP Admin Health Suite. Your WordPress site is now being actively monitored and maintained for optimal health and performance.

---

## Feedback and Support

We'd love to hear from you:

- **Found a bug?** Report it on our GitHub issues page
- **Have a suggestion?** Share feature requests with us
- **Need help?** Contact support or visit our forums
- **Love the plugin?** Leave a review on WordPress.org!

Thank you for choosing WP Admin Health Suite!
