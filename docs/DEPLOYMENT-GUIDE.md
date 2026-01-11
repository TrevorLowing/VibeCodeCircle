# Vibe Code Deploy - Deployment Guide

> **📋 Structural Rules:** Before deploying, review `biogaspros/docs/STRUCTURAL_RULES.md` for required structural patterns to prevent common deployment issues.

This guide provides step-by-step instructions for deploying static HTML sites to WordPress using Vibe Code Deploy.

## Prerequisites

1. **WordPress Site**: Running WordPress 6.0+ with PHP 8.0+
2. **Plugin Installed**: Vibe Code Deploy plugin activated
3. **Theme**: Etch theme or child theme (recommended)
4. **HTML Files**: Prepared with correct structure
5. **Plugin Settings Configured**: Project Slug and Class Prefix must be set before deployment

### Plugin Settings Configuration

**Project Slug Auto-Detection (v0.1.4+):**
- The plugin now **automatically detects** `project_slug` from `vibecode-deploy-shortcodes.json` in the zip file
- If `project_slug` is not set in WordPress settings, the plugin will:
  1. Read the JSON file from the zip (without extracting)
  2. Extract the `project_slug` value
  3. Automatically set it in WordPress settings
  4. Continue with upload
- **You can still set it manually** in Configuration for consistency or if auto-detection fails

**Class Prefix Auto-Detection:**
- The plugin automatically detects class prefix from CSS files after extraction
- If not set, it will be auto-detected and saved to settings

**Recommended (but not required):**
1. Go to **Vibe Code Deploy → Configuration**
2. Set **Project Slug** (e.g., `bgp`, `cfa`, `my-site`)
   - Should match the `project_slug` in `vibecode-deploy-shortcodes.json`
   - Used for shortcode prefix validation and build organization
   - **Optional** - will be auto-detected from JSON if not set
3. Set **Class Prefix** (e.g., `bgp-`, `cfa-`, `my-site-`)
   - Must end with a dash (e.g., `bgp-` not `bgp`)
   - Used for CSS class prefix validation
   - **Optional** - will be auto-detected from CSS files if not set
4. Click **Save Changes** (optional - auto-detection will handle it)

**If Auto-Detection Fails:**
- If the JSON file is missing or invalid, you'll see: "Project Slug is required. Could not auto-detect from staging zip."
- In this case, manually set it in **Vibe Code Deploy → Configuration**
- Make sure `vibecode-deploy-shortcodes.json` exists in your staging zip with `project_slug` field

**Why Manual Configuration is Still Recommended:**
- Ensures consistency across multiple deployments
- Allows you to verify the slug before upload
- Faster (no need to read zip file)
- Required if JSON file is missing or malformed

## Step 1: Prepare Your HTML Files

### Required Structure
Each HTML page must follow this structure:

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/icons.css">
</head>
<body>
    <!-- Skip Link (Required) -->
    <a class="{project-prefix}-skip-link" href="#main">Skip to main content</a>
    
    <!-- Header (repeated elements) -->
    <header class="{project-prefix}-header" role="banner">
        <!-- Navigation, logo, top bar -->
    </header>
    
    <!-- Main Content (only this is imported) -->
    <main id="main" class="{project-prefix}-main" role="main">
        <!-- Page content -->
    </main>
    
    <!-- Footer -->
    <footer class="{project-prefix}-footer" role="contentinfo">
        <!-- Footer content -->
    </footer>
</body>
</html>
```

### Important Rules
1. **Skip Link**: Must be the first element in `<body>`
2. **Header**: Elements repeated on every page must be INSIDE `<header>`
3. **Main**: Only content inside `<main>` becomes page content
4. **CSS Classes**: Use BEM naming with project prefix (e.g., `my-site-*` or configure in plugin settings)

### CPT Consideration Before Deployment

**Before deploying, review your static HTML content to identify items that should be Custom Post Types (CPTs) instead of hardcoded content.**

**When to Use CPTs:**
- **Product listings** - Products, services, or items with similar structure
- **FAQs** - Frequently asked questions organized by category
- **Testimonials/Case Studies** - Customer success stories or reviews
- **Team Members** - Staff or team member profiles
- **Portfolio Items** - Projects, work samples, or portfolio entries
- **Blog Posts** - If you have a blog or news section
- **Events** - Upcoming events, workshops, or announcements
- **Resources** - Downloads, guides, or resource library items

**Benefits of Using CPTs:**
- ✅ Easy content management via WordPress admin
- ✅ No need to edit HTML files for content updates
- ✅ Consistent structure and formatting
- ✅ Better SEO with proper post types
- ✅ Filtering and querying capabilities
- ✅ Future-proof for content growth

**Implementation Steps:**
1. **Identify repetitive content** - Look for repeated HTML structures (cards, lists, grids)
2. **Design CPT structure** - Plan fields, taxonomies, and relationships
3. **Create CPT in theme** - Register CPT and taxonomies in `functions.php`
4. **Create ACF field groups** - Define custom fields for content
5. **Create shortcodes** - Build shortcode handlers to display CPT content
6. **Replace HTML with placeholders** - Use `<!-- VIBECODE_SHORTCODE shortcode_name -->` comments
7. **Update shortcode config** - Add to `vibecode-deploy-shortcodes.json`

**Example:**
Instead of hardcoding 10 product cards in HTML:
```html
<!-- Hardcoded (not recommended) -->
<div class="product-card">
    <h3>Product Name</h3>
    <p>Description...</p>
    <span>Price: $99</span>
</div>
```

Use a CPT with shortcode:
```html
<!-- VIBECODE_SHORTCODE bgp_products type="system" -->
```

**After Deployment:**
- Content will need to be manually entered into WordPress admin
- Shortcodes will render the content dynamically
- Future updates can be made without editing HTML files

## Step 2: Create Staging ZIP

### Option A: Use Build Script
```bash
cd testing/plugins/vibecode-deploy
./scripts/build-staging-zip.sh
```

### Option B: Manual Creation
1. Create folder structure:
```
vibecode-deploy-staging/
├── pages/
│   ├── home.html
│   ├── about.html
│   └── ...
├── css/
│   ├── styles.css
│   └── icons.css
├── js/
│   ├── route-adapter.js  # Use standard version from plugin starter-pack
│   └── main.js
└── resources/
    └── images/
```

2. ZIP the folder:
```bash
zip -r vibecode-deploy-staging.zip vibecode-deploy-staging/ \
  -x "*.DS_Store" "__MACOSX/*" "*/__MACOSX/*" "._*"
```

**Standard Location:** For consistency, copy the staging zip to `VibeCodeCircle/dist/`:
```bash
cp vibecode-deploy-staging.zip /path/to/VibeCodeCircle/dist/
```

**Note:** All distribution files (plugin and staging zips) should be placed in `VibeCodeCircle/dist/` directory. See [Build Guide](docs/BUILD.md) for complete build instructions.

### Standard Route Adapter

**Recommended:** Use the standard route adapter from the plugin's starter pack:

1. Copy `VibeCodeCircle/plugins/vibecode-deploy/assets/starter-pack/js/route-adapter.js` to your project's `js/` directory
2. Update the `productionHosts` array with your production domain(s)
3. Include in all HTML pages: `<script src="js/route-adapter.js" defer></script>`

**Why use the standard version:**
- ✅ Consistent behavior across all projects
- ✅ Tested and proven to work with WordPress permalink format
- ✅ Handles dynamically added links (Gutenberg blocks)
- ✅ Updated in one place, benefits all projects

**Location in plugin:** `plugins/vibecode-deploy/assets/starter-pack/js/route-adapter.js`

## Step 3: Configure Plugin Settings (REQUIRED)

**Before uploading, configure plugin settings:**

1. Go to **Vibe Code Deploy → Configuration**
2. Set **Project Slug**:
   - Must match `project_slug` in your `vibecode-deploy-shortcodes.json` file
   - Example: If JSON has `"project_slug": "bgp"`, set it to `bgp` in settings
   - **Required** - deployment will fail without this
3. Set **Class Prefix**:
   - Must end with a dash (e.g., `bgp-`, `cfa-`)
   - Example: If your CSS uses `bgp-header`, set prefix to `bgp-`
   - **Required** - deployment will fail without this
4. Click **Save Changes**

**Validation:**
- Plugin validates that shortcodes match project prefix (e.g., `bgp_products` for project slug `bgp`)
- Plugin validates that CSS classes match class prefix (e.g., `bgp-header` for prefix `bgp-`)

## Step 4: Deploy to WordPress

### ⚠️ CRITICAL: Configure Settings FIRST

**The plugin validates WordPress settings BEFORE allowing upload. The JSON file's `project_slug` is metadata only - WordPress settings are what matter.**

**If you see "Project Slug is required" error:**
1. Go to **Vibe Code Deploy → Configuration** (NOT Import Build)
2. Set Project Slug and Class Prefix
3. Click **Save Changes**
4. THEN go to Import Build and upload

### 1. Upload Staging ZIP
1. Go to **Vibe Code Deploy → Import Build**
2. Click **Choose File** and select your staging ZIP
3. Click **Upload Staging Zip**
4. **If upload fails with "Project Slug is required"**: Go back to Configuration, set the fields, save, then try again

### 2. Run Preflight
1. Select the build fingerprint from dropdown
2. Click **Run Preflight**
3. Review:
   - Pages to be created/updated
   - Warnings (if any)
   - Template parts to be extracted

### 3. Deploy
1. Configure options:
   - Set as front page (for home.html)
   - Extract header/footer from home.html
   - Generate 404 template
   - Force claim unowned pages
   - Validate CPT shortcodes
2. Click **Deploy**

## Step 5: Post-Deployment Tasks

### 1. Check Pages
- Visit each page to verify content
- Check for styling issues
- Verify navigation links work

### 2. Configure Theme
If not using Etch theme, manually configure:

#### Create Theme Files
```php
// index.php
<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <?php the_content(); ?>
<?php endwhile; endif; ?>
<?php get_footer(); ?>
```

```php
// page.php
<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <?php the_content(); ?>
<?php endwhile; ?>
<?php get_footer(); ?>
```

#### Enqueue Assets (functions.php)
```php
add_action('wp_enqueue_scripts', function() {
    if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/styles.css')) {
        wp_enqueue_style('vibecode-deploy-styles', plugins_url('assets/css/styles.css', 'vibecode-deploy'));
    }
    if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/icons.css')) {
        wp_enqueue_style('vibecode-deploy-icons', plugins_url('assets/css/icons.css', 'vibecode-deploy'));
    }
}, 20);
```

### 3. Set Front Page
1. Go to **Settings → Reading**
2. Select "A static page"
3. Choose "Home" as front page
4. Save changes

### 4. Check Templates
1. Go to **Appearance → Editor**
2. Verify Header and Footer template parts exist
3. Check they contain the correct content
4. **Automatic Template Assignment**: Custom page templates (`page-{slug}.php`) are automatically assigned during deployment
5. **Automatic CPT Templates**: Default `single-{post_type}.html` block templates are automatically created for all public CPTs

## Troubleshooting

### Preflight Shows Nothing
- **Cause**: Wrong ZIP or incorrect structure
- **Solution**: Verify you're uploading staging ZIP, not plugin ZIP
- **Check**: ZIP contains `vibecode-deploy-staging/` folder

### Assets 404 Error
- **Cause**: Assets not copied to plugin folder
- **Solution**: Re-run import
- **Manual**: Add enqueue code to theme's functions.php

### Header/Footer Missing
- **Cause**: Template parts not created
- **Solution**: Ensure "Extract header/footer" is checked
- **Check**: Appearance → Editor for template parts

### White Screen After Install
- **Cause**: PHP error or plugin conflict
- **Solution**: Delete plugin via FTP
- **Check**: PHP error logs

### Pages Not Styled
- **Cause**: CSS not enqueued
- **Solution**: Add enqueue code to theme
- **Check**: Browser dev tools for CSS loading

## Common Mistakes

1. **Wrong ZIP**: Uploading plugin ZIP instead of staging ZIP
2. **Incorrect Structure**: Missing `vibecode-deploy-staging/` folder
3. **Missing home.html**: Required for template extraction
4. **Wrong HTML Structure**: Skip link or main element missing
5. **Theme Issues**: Not using compatible theme

## Best Practices

1. **Test Locally**: Deploy to staging site first
2. **Backup**: Always backup before deployment
3. **Check Logs**: Review Vibe Code Deploy → Logs for errors
4. **Use Preflight**: Always run preflight before deployment
5. **Verify Links**: Check all internal links work after deployment
6. **Monitor Assets**: Ensure CSS/JS files load correctly

## CLI Deployment

For automated deployments:

```bash
# Upload and extract staging zip
wp vibecode-deploy upload-staging /path/to/staging.zip --project=mysite

# Run preflight
wp vibecode-deploy preflight --project=mysite --fingerprint=abc123

# Deploy
wp vibecode-deploy deploy --project=mysite --fingerprint=abc123 --set-front-page

# Rollback if needed
wp vibecode-deploy rollback --project=mysite --to=previous
```

## Support

- **Documentation**: See Help page in plugin admin
- **Issues**: GitHub repository
- **Logs**: Vibe Code Deploy → Logs page
