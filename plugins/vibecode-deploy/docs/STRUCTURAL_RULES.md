# Structural Rules for Vibe Code Deploy Projects

**Purpose:** This document serves as the **source of truth** for all structural rules and standards that projects must follow when using the Vibe Code Deploy plugin.

**Status:** Active Standard  
**Last Updated:** 2026-01-26  
**Plugin Version:** 0.1.63+

**Note:** All projects (BGP, CFA, and future projects) should reference this document and align their structural rules accordingly.

---

## Source Files vs Staging Files

### ✅ REQUIRED: Source Files Are Primary

**Critical Principle:** All structural rules apply to **source files** (root HTML files in project directory) as the primary location for compliance. Staging files are built from source and should mirror source compliance.

**Why Source Files Must Be Compliant:**
- Source files are what developers work with during development
- Staging is built from source (via build scripts or manual copying)
- Fixing only staging creates technical debt
- Source files should reflect the deployed state
- Compliance during development prevents issues from the start
- Rebuilding staging from non-compliant source reintroduces violations

**Workflow:**
1. ✅ Develop source files with compliance from the start
2. ✅ Build staging from compliant source files
3. ✅ Deploy staging to WordPress
4. ✅ Source files remain the source of truth

**Rules Apply To:**
- ✅ Source files (primary) - HTML files in project root
- ✅ Staging files (derived) - Built from source, should match source
- ✅ Deployed files (derived) - Deployed from staging

**Common Mistake:**
- ❌ Fixing only staging files, leaving source non-compliant
- ❌ Rebuilding staging from non-compliant source reintroduces violations
- ❌ Source and staging become out of sync

**Best Practice:**
- ✅ Always fix source files first
- ✅ Ensure source files are compliant before building staging
- ✅ Verify staging matches source after build

---

## Table of Contents

1. [Staging Directory Structure](#staging-directory-structure)
2. [Asset Path Conventions](#asset-path-conventions)
3. [HTML Structure Requirements](#html-structure-requirements)
4. [Semantic Block Conversion](#semantic-block-conversion)
5. [JavaScript Asset Placement](#javascript-asset-placement)
6. [Image Path Conventions](#image-path-conventions)
7. [URL Rewriting Rules](#url-rewriting-rules)
8. [Config File Structure](#config-file-structure)
9. [CPT and Shortcode Standards](#cpt-and-shortcode-standards)
10. [ACF Integration Standards](#acf-integration-standards)
11. [External CDN Dependencies](#external-cdn-dependencies)
12. [Code Documentation Standards](#code-documentation-standards)
13. [Theme File Structure](#theme-file-structure)
14. [CPT Single Templates and 404 Template](#cpt-single-templates-and-404-template)

---

## Staging Directory Structure

### ✅ REQUIRED: Standard Staging Bundle Layout

**Problem:** Plugin validates staging zip structure strictly. Incorrect structure causes upload failures.

**Solution:** Follow the exact directory structure expected by the plugin.

### Required Structure

```
vibecode-deploy-staging/          # OR {project-slug}-deployment/
├── pages/                        # REQUIRED: HTML page files
│   ├── home.html
│   ├── about.html
│   └── ...
├── css/                          # REQUIRED: CSS files
│   ├── styles.css
│   ├── icons.css
│   └── ...
├── js/                           # REQUIRED: JavaScript files
│   ├── main.js
│   ├── icons.js
│   └── ...
├── resources/                    # REQUIRED: Images and assets
│   ├── images/
│   │   └── logo.png
│   └── ...
├── templates/                    # Optional: Manual block templates
│   └── ...
├── template-parts/               # Optional: Template parts (header/footer)
│   └── ...
├── theme/                        # Optional: Theme files
│   ├── functions.php
│   └── acf-json/
│       └── group_*.json
└── vibecode-deploy-shortcodes.json  # Optional: Shortcode rules
```

### Allowed Root Directories

**Old Format** (`vibecode-deploy-staging/`):
- `pages/` (required)
- `css/` (required)
- `js/` (required)
- `resources/` (required)
- `templates/` (optional)
- `template-parts/` (optional)
- `theme/` (optional)

**New Format** (`{project-slug}-deployment/`):
- `pages/` (required)
- `assets/` (required - contains `css/`, `js/`, `images/` subdirectories)
- `theme/` (optional)

### Allowed Root Files

- `vibecode-deploy-shortcodes.json`
- `manifest.json`
- `config.json`

### Zip Creation Requirements

**macOS Users:** Finder-created zips often include `__MACOSX/`, `.DS_Store`, and `._*` entries that cause validation failures.

**Recommended Command:**
```bash
zip -r vibecode-deploy-staging.zip vibecode-deploy-staging -x "*.DS_Store" "__MACOSX/*" "*/__MACOSX/*" "._*"
```

**Why This Matters:**
- Plugin validates zip structure during extraction
- Invalid structure causes upload to fail
- Consistent structure enables reliable deployment

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Staging.php`
- `ALLOWED_TOP_LEVEL_DIRS_OLD` and `ALLOWED_TOP_LEVEL_DIRS_NEW` constants

---

## Asset Path Conventions

### ✅ REQUIRED: Standard Asset Directory Structure

**Problem:** Plugin expects assets in specific directories. Incorrect paths cause assets not to load.

**Solution:** Use the exact directory structure and path conventions.

### CSS Files

**Location:** `css/` directory at staging root

**Reference in HTML:**
```html
<link rel="stylesheet" href="css/styles.css">
<link rel="stylesheet" href="css/icons.css">
```

**Plugin Behavior:**
- Extracts CSS links from `<head>` section only
- Only extracts paths starting with `css/`
- Rewrites to: `/wp-content/plugins/vibecode-deploy/assets/css/styles.css`

**Rules:**
- ✅ CSS files MUST be in `css/` directory
- ✅ CSS links MUST be in `<head>` section
- ✅ Paths MUST start with `css/` (not `/css/` or `./css/`)
- ❌ Do NOT use inline `<style>` tags
- ❌ Do NOT use `style=""` attributes

### JavaScript Files

**Location:** `js/` directory at staging root

**Reference in HTML:**
```html
<script src="js/main.js" defer></script>
<script src="js/icons.js" defer></script>
```

**Plugin Behavior:**
- Extracts scripts from **entire document** (not just `<head>`)
- Only extracts paths starting with `js/`
- Preserves `defer` and `async` attributes
- Rewrites to: `/wp-content/plugins/vibecode-deploy/assets/js/main.js`

**Rules:**
- ✅ JS files MUST be in `js/` directory
- ✅ Paths MUST start with `js/` (not `/js/` or `./js/`)
- ✅ Use `defer` attribute for scripts that don't need immediate execution
- ❌ Do NOT use inline `<script>` tags
- ❌ Do NOT use `onclick=""` or other inline handlers

**Note:** While plugin extracts from entire document, best practice is to place scripts in `<head>` with `defer` attribute for better performance and reliability.

### Image and Resource Files

**Location:** `resources/` directory at staging root

**Reference in HTML:**
```html
<img src="resources/images/logo.png" alt="Logo">
<a href="resources/documents/guide.pdf">Download Guide</a>
```

**Plugin Behavior:**
- Only rewrites paths starting with `resources/`
- Does NOT rewrite `images/` paths (causes 404 errors)
- Rewrites to: `/wp-content/plugins/vibecode-deploy/assets/resources/images/logo.png`

**Rules:**
- ✅ Images MUST be in `resources/images/` directory
- ✅ Reference as `resources/images/filename.png` in HTML
- ❌ Do NOT use `images/` paths (not rewritten by plugin)
- ❌ Do NOT use absolute paths or external URLs for local assets

**Why This Matters:**
- Plugin's `rewrite_asset_urls()` only matches `resources/` pattern
- Using `images/` causes 404 errors after deployment
- Consistent path structure ensures all assets load correctly

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php::rewrite_asset_urls()`
- Pattern: `/(href|src)="(css|js|resources)\/([^"]+)"/`

---

## HTML Structure Requirements

### ✅ REQUIRED: Semantic HTML Structure

**Problem:** Plugin extracts content from specific HTML elements. Missing or incorrect structure causes deployment failures.

**Solution:** Follow the required HTML structure.

### Required Page Structure

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- CSS Links (MUST be in <head>) -->
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/icons.css">
    
    <title>Page Title</title>
</head>
<body>
    <!-- Skip Link (first element, before header) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <div class="page-content">
        <!-- Header -->
        <header class="header" role="banner">
            <!-- Header content -->
        </header>
        
        <!-- Main Content (REQUIRED for extraction) -->
        <main class="main" id="main-content">
            <!-- Page content extracted by plugin -->
        </main>
        
        <!-- Footer -->
        <footer class="footer" role="contentinfo">
            <!-- Footer content -->
        </footer>
    </div>
    
    <!-- Scripts (can be in <head> or <body>, but <head> recommended) -->
    <script src="js/main.js" defer></script>
</body>
</html>
```

### Critical Requirements

1. **`<main>` Tag Required:**
   - Plugin extracts content from `<main>` tag
   - Content outside `<main>` is not deployed to WordPress page
   - Must have `id="main-content"` or similar for skip link

2. **CSS Links in `<head>`:**
   - Plugin only extracts CSS from `<head>` section
   - CSS links outside `<head>` are not enqueued

3. **Semantic Structure:**
   - Use `<header>`, `<main>`, `<footer>` for proper structure
   - Include `role` attributes for accessibility
   - Wrap page content in container div (e.g., `page-content`)

**Why This Matters:**
- Plugin's `DeployService` extracts `<main>` content specifically
- Missing `<main>` tag results in empty page content
- CSS not in `<head>` is not automatically enqueued

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php`
- Extracts `<main>` content via DOMDocument parsing

---

## Semantic Block Conversion

### ✅ REQUIRED: Use Semantic HTML Elements for Editable Content

**Problem:** Content wrapped in `wp:html` (CORE/HTML) blocks is not editable in EtchWP IDE or Gutenberg editor.

**Solution:** Use semantic HTML elements that the plugin automatically converts to editable Gutenberg blocks.

### Automatic Block Conversion (v0.1.57+)

**The plugin automatically converts semantic HTML elements to their corresponding Gutenberg blocks:**

| HTML Element | Gutenberg Block | Editable in EtchWP | Example |
|--------------|----------------|-------------------|---------|
| `<p>` | `wp:paragraph` | ✅ Yes | `<p class="intro">Text</p>` |
| `<ul>`, `<ol>` | `wp:list` | ✅ Yes | `<ul class="features"><li>Item</li></ul>` |
| `<img>` | `wp:image` | ✅ Yes | `<img src="logo.png" alt="Logo" />` |
| `<blockquote>` | `wp:quote` | ✅ Yes | `<blockquote>Quote text</blockquote>` |
| `<pre>` | `wp:preformatted` | ✅ Yes | `<pre class="code">Code</pre>` |
| `<code>` (block-level) | `wp:code` | ✅ Yes | `<code style="display:block">Code</code>` |
| `<table>` | `wp:table` | ✅ Yes | `<table><tr><td>Cell</td></tr></table>` |
| `<h1>`-`<h6>` | `wp:heading` | ✅ Yes | `<h2 class="title">Heading</h2>` |

### Best Practices

**✅ DO: Use Semantic Elements**
```html
<!-- Good: Paragraphs are converted to editable wp:paragraph blocks -->
<div class="content">
  <p class="intro">Introduction text</p>
  <p>Body text</p>
</div>

<!-- Good: Lists are converted to editable wp:list blocks -->
<ul class="feature-list">
  <li>Feature 1</li>
  <li>Feature 2</li>
</ul>

<!-- Good: Images are converted to editable wp:image blocks -->
<img src="resources/logo.png" alt="Logo" class="site-logo" width="200" height="100" />
```

**❌ DON'T: Wrap Semantic Content in Custom Divs**
```html
<!-- Bad: Paragraphs wrapped in custom divs become wp:html blocks -->
<div class="content">
  <div class="paragraph-wrapper">
    <p>Text</p>
  </div>
</div>
```

### Class and Attribute Preservation

**Classes:**
- Semantic blocks: Preserved via `className` attribute
- Example: `<p class="intro-text">` → `wp:paragraph` block with `className: "intro-text"`
- CSS classes remain functional after conversion

**Other Attributes:**
- IDs, data-* attributes, and other HTML attributes are preserved
- Example: `<p id="intro" data-section="hero">` → Both `id` and `data-section` preserved

### Structural Containers

**Structural elements (div, section, etc.) are converted to `wp:group` blocks:**
- These preserve HTML structure and classes
- Semantic content inside is extracted as separate blocks
- Example: `<div class="content"><p>Text</p></div>` → `wp:group` containing `wp:paragraph`

### Custom HTML Blocks

**`wp:html` blocks are only used when:**
- Truly custom HTML that doesn't map to semantic blocks
- Complex widgets or iframes
- Custom scripts or embedded content
- Elements that must preserve exact HTML structure

**Example:**
```html
<!-- Custom widget that needs exact HTML preservation -->
<div class="custom-widget" data-widget-id="123">
  <iframe src="..."></iframe>
  <script>...</script>
</div>
```

### Conversion Examples

**Paragraphs Inside Divs:**
```html
<!-- Source HTML -->
<div class="content">
  <p class="intro">First paragraph</p>
  <p>Second paragraph</p>
</div>

<!-- Converted to Gutenberg Blocks -->
<!-- wp:group {"className":"content"} -->
<div class="content wp-block-group">
  <!-- wp:paragraph {"className":"intro"} -->
  <p class="intro">First paragraph</p>
  <!-- /wp:paragraph -->
  <!-- wp:paragraph -->
  <p>Second paragraph</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
```

**Mixed Content:**
```html
<!-- Source HTML -->
<section class="features">
  <h2>Features</h2>
  <ul class="feature-list">
    <li>Feature 1</li>
    <li>Feature 2</li>
  </ul>
  <p class="note">Additional information</p>
</section>

<!-- Converted to Gutenberg Blocks -->
<!-- wp:group {"className":"features"} -->
<section class="features wp-block-group">
  <!-- wp:heading {"level":2} -->
  <h2>Features</h2>
  <!-- /wp:heading -->
  <!-- wp:list {"className":"feature-list"} -->
  <ul class="feature-list">
    <li>Feature 1</li>
    <li>Feature 2</li>
  </ul>
  <!-- /wp:list -->
  <!-- wp:paragraph {"className":"note"} -->
  <p class="note">Additional information</p>
  <!-- /wp:paragraph -->
</section>
<!-- /wp:group -->
```

### Benefits

1. **Fully Editable Content:** All semantic elements are editable in EtchWP IDE
2. **Reduced CORE/HTML Blocks:** Minimal use of `wp:html` blocks
3. **Preserved Structure:** CSS classes, IDs, and attributes are preserved
4. **Better UX:** Content creators can edit directly in visual editor
5. **Semantic HTML:** Maintains semantic structure while enabling Gutenberg editing

### Plugin Code Reference

- **Conversion Logic:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`
- **Method:** `convert_element()` - Handles semantic block conversion
- **Method:** `html_to_etch_blocks()` - Main conversion entry point
- **Version:** Requires plugin version 0.1.57 or later

---

## JavaScript Asset Placement

### ✅ REQUIRED: Script Placement and Extraction Behavior

**Problem:** Confusion about where scripts should be placed and how plugin extracts them.

**Solution:** Understand plugin's actual behavior and follow best practices.

### Plugin Behavior

**Current Implementation:**
- Plugin queries `//script[@src]` which searches **entire document** (both `<head>` and `<body>`)
- Function name `extract_head_assets()` is misleading - it actually extracts from entire document
- Only extracts scripts with `src` attribute starting with `js/`

**Code Reference:**
```php
// In AssetService::extract_head_assets()
$scripts = $xpath->query( '//script[@src]' );  // Queries entire document
```

### Best Practice

**Recommended:** Place all scripts in `<head>` with `defer` attribute:

```html
<head>
    <!-- CSS -->
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- JavaScript (in <head> with defer) -->
    <script src="js/main.js" defer></script>
    <script src="js/map.js" defer></script>
</head>
```

**Why `<head>` with `defer`:**
- Better performance (scripts load in parallel)
- More reliable extraction (consistent behavior)
- Matches function name expectation (`extract_head_assets`)
- `defer` ensures scripts execute after DOM is ready

### Alternative (Not Recommended)

Scripts in `<body>` will be extracted, but:
- Less performant (blocks rendering)
- Inconsistent with function name
- May cause timing issues

**Rules:**
- ✅ Place scripts in `<head>` with `defer` attribute
- ✅ Use `defer` for scripts that don't need immediate execution
- ✅ Use `async` only for independent scripts (analytics, etc.)
- ⚠️ Scripts in `<body>` will work but are not recommended

**Why This Matters:**
- Understanding plugin behavior prevents confusion
- Consistent placement ensures reliable extraction
- Best practices improve page performance

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php::extract_head_assets()`
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php::extract_head_assets()`

---

## External CDN Dependencies

### ✅ REQUIRED: Enqueue External CDN Scripts in Theme Functions

**Problem:** The plugin's `AssetService::extract_head_assets()` skips external URLs (CDN scripts like Leaflet.js, Google Maps, etc.). These scripts are not automatically enqueued, causing JavaScript errors when dependent code tries to use them.

**Solution:** Manually enqueue external CDN scripts in the theme's `functions.php` using WordPress `wp_enqueue_script()` and `wp_enqueue_style()`.

### Plugin Behavior

**What Gets Extracted:**
- ✅ Scripts starting with `js/` (local files)
- ✅ CSS files starting with `css/` (local files)

**What Gets Skipped:**
- ❌ External URLs (`http://`, `https://`)
- ❌ CDN scripts (e.g., `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js`)
- ❌ External CSS (e.g., `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css`)

**Code Reference:**
```php
// In AssetService::extract_head_assets()
if ( strpos( $src, 'http://' ) === 0 || strpos( $src, 'https://' ) === 0 ) {
    continue; // Skips external URLs
}
```

### Solution: Enqueue in Theme Functions

**Add to `theme/functions.php`:**
```php
/**
 * Enqueue external CDN dependencies.
 * 
 * External CDN scripts (Leaflet.js, Google Maps, etc.) must be enqueued
 * manually because the plugin doesn't extract external URLs.
 */
function project_enqueue_external_dependencies() {
    // Only enqueue on pages that need them
    if ( ! is_page( 'page-slug' ) ) {
        return;
    }
    
    // Enqueue external CSS
    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        array(),
        '1.9.4'
    );
    
    // Enqueue external JS (must load before dependent scripts)
    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        array(),
        '1.9.4',
        true // Load in footer
    );
}
add_action( 'wp_enqueue_scripts', 'project_enqueue_external_dependencies' );
```

### Common External Dependencies

**Leaflet.js (Interactive Maps):**
- CSS: `https://unpkg.com/leaflet@1.9.4/dist/leaflet.css`
- JS: `https://unpkg.com/leaflet@1.9.4/dist/leaflet.js`
- MarkerCluster: `https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js`

**Google Maps:**
- JS: `https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY`

**Other Libraries:**
- Chart.js, D3.js, etc. - follow the same pattern

### Load Order Considerations

**Critical:** External dependencies must load **before** dependent scripts.

**Example:**
- `map.js` depends on Leaflet.js
- Leaflet.js must load first (no `defer`)
- `map.js` can use `defer` (enqueued by plugin)

**Solution:**
```php
// Enqueue Leaflet without defer (loads synchronously)
wp_enqueue_script( 'leaflet-js', 'https://...', array(), '1.9.4', true );

// map.js is enqueued by plugin with defer
// WordPress dependency system ensures correct order
```

### Rules

- ✅ **Always enqueue external CDN scripts in `theme/functions.php`**
- ✅ **Use `wp_enqueue_script()` and `wp_enqueue_style()` for proper WordPress integration**
- ✅ **Conditionally enqueue only on pages that need them** (`is_page()`, `is_singular()`, etc.)
- ✅ **Set dependencies correctly** (e.g., MarkerCluster depends on Leaflet)
- ✅ **Load external scripts in footer** (`$in_footer = true`) for better performance
- ❌ **Do NOT rely on plugin to extract external URLs** (they are skipped)
- ❌ **Do NOT use inline `<script>` tags for external dependencies** (use WordPress enqueue system)

### Why This Matters

- Plugin intentionally skips external URLs to avoid security and performance issues
- WordPress enqueue system provides proper dependency management
- Conditional loading improves page performance
- Consistent pattern across all projects

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php::extract_head_assets()`
- Lines 97-98: External URL skip logic

---

## Image Path Conventions

### ✅ REQUIRED: Use `resources/images/` for All Images

**Problem:** Plugin only rewrites `resources/` paths, not `images/` paths. Using `images/` causes 404 errors.

**Solution:** Always use `resources/images/` for image paths.

### Correct Image Paths

**Source HTML:**
```html
<img src="resources/images/logo.png" alt="Logo">
<img src="resources/images/biogas-guide/image1.png" alt="Guide Image">
```

**After Plugin Rewriting:**
```html
<img src="/wp-content/plugins/vibecode-deploy/assets/resources/images/logo.png" alt="Logo">
```

### Plugin URL Rewriting

**Pattern Matched:**
- `resources/` → Plugin URL
- `css/` → Plugin URL
- `js/` → Plugin URL

**Pattern NOT Matched:**
- `images/` → **NOT rewritten** (causes 404)

**Code Reference:**
```php
// In AssetService::rewrite_asset_urls()
$pattern = '/(href|src)="(css|js|resources)\/([^"]+)"/';
// Only matches css/, js/, resources/ - NOT images/
```

### Common Mistakes

**❌ Wrong:**
```html
<img src="images/logo.png" alt="Logo">
<img src="images/biogas-guide/image1.png" alt="Guide">
```

**✅ Correct:**
```html
<img src="resources/images/logo.png" alt="Logo">
<img src="resources/images/biogas-guide/image1.png" alt="Guide">
```

### Directory Structure

**Staging Structure:**
```
vibecode-deploy-staging/
├── resources/
│   └── images/
│       ├── logo.png
│       └── biogas-guide/
│           ├── image1.png
│           └── image2.png
```

**Rules:**
- ✅ All images MUST be in `resources/images/` directory
- ✅ Reference as `resources/images/filename.png` in HTML
- ✅ Use subdirectories for organization: `resources/images/category/file.png`
- ❌ Do NOT use `images/` paths (not rewritten by plugin)
- ❌ Do NOT use absolute paths for local images

**Why This Matters:**
- Plugin's URL rewriting only handles `resources/` pattern
- Using `images/` causes 404 errors after deployment
- Consistent path structure ensures all images load correctly

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php::rewrite_asset_urls()`
- Pattern: `/(href|src)="(css|js|resources)\/([^"]+)"/`

### Image Block URL Conversion and Media Library Integration (v0.1.64+)

**Enhanced Image Handling:** The plugin now supports WordPress Media Library as the default image storage method, with plugin assets as an optional fallback.

**How It Works (Media Library Mode - Default):**
1. **Pre-Processing:** During deployment, images are collected from HTML
2. **Upload to Media Library:** Images are uploaded to WordPress Media Library (or existing attachments reused)
3. **Block Conversion:** Image handler uses Media Library attachment URLs in image blocks
4. **Result:** Image blocks have Media Library URLs with attachment IDs stored in block attributes

**How It Works (Plugin Assets Mode - Fallback):**
1. **URL Rewriting (First):** `rewrite_asset_urls()` converts `resources/` paths to plugin URLs in HTML
2. **Block Conversion (Second):** Image handler converts relative paths to full plugin URLs during block creation
3. **Result:** Image blocks always have absolute plugin asset URLs in both the `url` attribute and HTML `<img>` tag

**Redeployment Behavior:**
- **Smart Duplicate Detection:** Checks existing attachments by source path hash
- **File Change Detection:** Compares file hash to detect changes
- **Efficient Updates:** Only uploads new images or updates changed files
- **Stable URLs:** Attachment IDs preserved (URLs don't break on redeployment)

**Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/MediaLibraryService.php` - Media Library upload and lookup
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php::convert_asset_path_to_url()` - Plugin asset URL conversion
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` - Image block conversion (line ~697-744)

**Benefits:**
- ✅ WordPress best practices (Media Library default)
- ✅ Automatic image optimization (srcset, lazy loading)
- ✅ No duplicate uploads on redeployment
- ✅ Efficient file updates (only changed files)
- ✅ Stable URLs (attachment IDs preserved)
- ✅ EtchWP IDE compatibility (both methods work)
- ✅ Fallback option (plugin assets if Media Library fails)

### WordPress Best Practices and Media Library Integration

**Default Approach: WordPress Media Library (v0.1.64+)**
- Images uploaded to WordPress Media Library during deployment (default)
- URLs: `/wp-content/uploads/2026/01/image.jpg`
- **Default method** - follows WordPress best practices
- Provides automatic srcset generation, lazy loading, optimization
- Better integration with WordPress ecosystem
- Smart duplicate detection on redeployment (reuses existing attachments)
- File change detection (updates attachments when files change)

**Fallback Approach: Plugin Asset URLs**
- Images stored in plugin assets directory (optional fallback)
- URLs: `/wp-content/plugins/vibecode-deploy/assets/resources/images/...`
- **Optional fallback** - selectable in plugin settings
- **Use when:** Media Library upload fails or user preference
- **Limitations:** Missing srcset, lazy loading, Media Library management

**Configuration:**
- Plugin setting: `image_storage_method`
- Default: `media_library` (recommended)
- Fallback: `plugin_assets` (optional)
- Configure in: **Vibe Code Deploy → Configuration → Image Storage Method**

**Redeployment Behavior:**
- **Media Library Mode:**
  - Checks existing attachments by source path hash
  - Reuses existing attachments if file unchanged (no duplicate uploads)
  - Updates attachments if file changed (same attachment ID, new file)
  - Uploads new images if not found
- **Plugin Assets Mode:**
  - Copies images to plugin folder (current behavior)
  - No duplicate detection (files overwritten)

**EtchWP Compatibility:**
- ✅ EtchWP works with both Media Library URLs and plugin asset URLs
- ✅ No EtchWP-specific requirement for Media Library
- ✅ `etchData` metadata enables editability (not URL source)
- ✅ Both methods provide absolute URLs required for EtchWP

**Reference:**
- `reports/development/etchwp-image-handling-analysis-2026-01-26.md` - EtchWP compatibility analysis
- `reports/development/wordpress-media-library-evaluation-2026-01-26.md` - Media Library evaluation
- `docs/COMPLIANCE_REVIEW_PROCESS.md` - Monthly review process
- Plugin code: `includes/Services/MediaLibraryService.php` - Media Library upload service

### Media Library Attachment Cleanup (v0.1.64+)

**Attachment Tracking:**
- Media Library attachments created during deployment are tracked in the deployment manifest
- Manifest includes `created_attachments` array with attachment IDs and metadata
- Attachments are identified by project slug meta (`_vibecode_deploy_project_slug`)

**Rollback Behavior:**
- When rolling back a deployment, orphaned attachments are automatically deleted
- **Orphaned Detection:** Attachments not referenced in any post content are considered orphaned
- **Safety First:** Only truly orphaned attachments are deleted (conservative approach)
- Attachments still referenced in other posts are preserved
- Rollback results include `deleted_attachments` count

**Nuclear Operation Behavior:**
- Media Library attachments can be optionally deleted during nuclear operations
- **Delete Mode Options:**
  - **Orphaned Only (Default):** Deletes only attachments not referenced in any post content (safer)
  - **All Project Attachments:** Deletes all attachments with project slug meta (complete cleanup)
- **UI:** Checkbox option in nuclear operation UI with mode selection
- **Safety:** Default mode is "orphaned only" to preserve user content

**Orphaned Detection:**
- Searches all post content for attachment URL or ID in block attributes
- Checks for patterns: attachment URL, `"id":123`, `"id": 123`
- Only considers posts with status != 'trash'
- Returns true if attachment is not found in any post content

**Manifest Structure:**
```json
{
  "created_attachments": [
    {
      "attachment_id": 123,
      "source_path": "resources/images/logo.png",
      "filename": "logo.png"
    }
  ],
  "updated_attachments": [
    {
      "attachment_id": 456,
      "source_path": "resources/images/hero.jpg",
      "filename": "hero.jpg",
      "was_updated": true
    }
  ]
}
```

**Best Practices:**
- ✅ Use rollback for safe cleanup of orphaned attachments
- ✅ Use nuclear operation "orphaned only" mode for safer cleanup
- ✅ Use nuclear operation "all" mode only when you want complete project cleanup
- ✅ Check deployment manifest to see which attachments were created/updated
- ✅ Verify attachment references before using "all" mode in nuclear operation

**Reference:**
- Plugin code: `includes/Services/MediaLibraryService.php` - Attachment cleanup methods
- Plugin code: `includes/Services/RollbackService.php` - Rollback attachment cleanup
- Plugin code: `includes/Services/CleanupService.php` - Nuclear operation attachment cleanup
- Plugin code: `includes/Services/DeployService.php` - Attachment tracking during deployment

---

## URL Rewriting Rules

### ✅ REQUIRED: Understand URL Rewriting Order and Patterns

**Problem:** URL rewriting happens in a specific order. Incorrect understanding causes assets to point to wrong locations.

**Solution:** Understand the two-stage rewriting process.

### Two-Stage URL Rewriting

**Stage 1: Asset URL Rewriting (FIRST)**
```php
// In DeployService::create_page_templates_from_pages()
$content = AssetService::rewrite_asset_urls($content, $project_slug);
```

**What Gets Rewritten:**
- `css/styles.css` → `/wp-content/plugins/vibecode-deploy/assets/css/styles.css`
- `js/main.js` → `/wp-content/plugins/vibecode-deploy/assets/js/main.js`
- `resources/images/logo.png` → `/wp-content/plugins/vibecode-deploy/assets/resources/images/logo.png`

**Pattern:** `/(href|src)="(css|js|resources)\/([^"]+)"/`

**Stage 2: Page URL Rewriting (SECOND)**
```php
// After asset rewriting
$content = self::rewrite_urls($content, $slug_set, $resources_base_url);
```

**What Gets Rewritten:**
- `contact-us` → `/contact-us/`
- `about.html` → `/about/`
- Extensionless page links → WordPress permalinks

**What Gets Skipped:**
- Already-rewritten plugin asset URLs (checks for plugin URL base)
- External URLs (`http://`, `https://`)
- Anchor links (`#section`)
- Data URIs, mailto, tel links

### Critical Order

**Why Order Matters:**
1. Asset rewriting MUST happen first
2. Page rewriting skips already-converted plugin URLs
3. If page rewriting happens first, `resources/` gets converted to wrong location
4. Result: Images point to uploads directory instead of plugin assets

**Code Reference:**
```php
// Correct order in DeployService
$content = AssetService::rewrite_asset_urls($content, $project_slug);  // FIRST
$content = self::rewrite_urls($content, $slug_set, $resources_base_url); // SECOND
```

### URL Patterns

**Asset URLs (Rewritten in Stage 1):**
- `css/styles.css` → Plugin asset URL
- `js/main.js` → Plugin asset URL
- `resources/images/logo.png` → Plugin asset URL

**Page URLs (Rewritten in Stage 2):**
- `contact-us` → `/contact-us/`
- `about.html` → `/about/`
- `home` → `/home/` (or `/` if set as front page)

The plugin does not set the front page; set it in **Settings → Reading** if you want `/` to show the home page.

**External URLs (Not Rewritten):**
- `https://example.com` → Unchanged
- `mailto:info@example.com` → Unchanged
- `tel:+1234567890` → Unchanged

**Rules:**
- ✅ Use extensionless page links: `href="contact-us"`
- ✅ Use `resources/images/` for images
- ✅ Use `css/` and `js/` for assets
- ❌ Do NOT use absolute URLs for local assets
- ❌ Do NOT use `images/` paths (not rewritten)

**Why This Matters:**
- Understanding rewriting order prevents asset loading issues
- Consistent URL patterns ensure reliable deployment
- Correct patterns enable proper WordPress permalink structure

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php::rewrite_asset_urls()`
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php::rewrite_urls()`

---

## Config File Structure

### ✅ REQUIRED: `vibecode-deploy-shortcodes.json` Structure

**Problem:** Plugin validates shortcode rules against config file. Incorrect structure causes validation to be skipped or fail.

**Solution:** Follow the exact structure required by the plugin.

### Required Structure

```json
{
  "version": 1,
  "project_slug": "project-name",
  "defaults": {
    "on_missing_required": "warn",
    "on_missing_recommended": "warn",
    "on_unknown_placeholder": "warn",
    "validation": {
      "attrs": "ignore"
    }
  },
  "pages": {
    "page-slug": {
      "required_shortcodes": [
        {
          "name": "shortcode_name",
          "attrs": {
            "attr": "value"
          }
        }
      ],
      "recommended_shortcodes": [...]
    }
  },
  "post_types": {
    "post_type_name": {}
  }
}
```

### Required Sections

1. **`version`** (required): Must be `1`
2. **`project_slug`** (required): Project identifier for auto-detection (e.g., `"bgp"`, `"cfa"`)
   - Used by plugin to auto-detect project slug during upload
   - Must match the project's shortcode prefix (e.g., `bgp_products` → `project_slug: "bgp"`)
   - If missing, upload will fail with "Project Slug is required. Could not auto-detect from staging zip."
   - Plugin code: `ImportPage::detect_project_slug_from_zip()` reads this field from JSON
3. **`defaults`** (required): Validation mode settings
4. **`pages`** (required): Page-specific shortcode requirements - **CRITICAL: Plugin expects this section**
5. **`post_types`** (optional): CPT-specific shortcode requirements

### Why `pages` Section is Critical

**Plugin Code:**
```php
// In ShortcodePlaceholderService::validate_page_slug()
if ( ! isset( $config['pages'] ) ) {
    // Validation is skipped if pages section is missing
    return;
}
```

**Rules:**
- ✅ `project_slug` field MUST exist for auto-detection during upload
- ✅ `pages` section MUST exist (even if empty: `"pages": {}`)
- ✅ Each page slug must match actual HTML filename (minus `.html`)
- ✅ Shortcode names must match registered shortcode tags
- ✅ Attributes must match shortcode handler expectations
- ❌ Do NOT use `shortcodes` section (old format, not recognized)
- ❌ Do NOT omit `project_slug` field (causes upload failure with auto-detection error)

**Why This Matters:**
- Plugin's validation logic expects `pages` section
- Missing `pages` section causes validation to be skipped
- Validation helps catch shortcode issues before deployment

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/ShortcodePlaceholderService.php:351`

---

## CPT and Shortcode Standards

### ✅ REQUIRED: CPT Registration for Shortcode Queries

**Problem:** If a CPT is not publicly queryable, shortcodes cannot query it using `WP_Query`.

**Solution:** Set `'publicly_queryable' => true` for CPTs that shortcodes need to query.

### Correct CPT Registration

```php
register_post_type( 'bgp_product', array(
    'labels' => array(
        'name' => 'Products',
        'singular_name' => 'Product',
    ),
    'public' => true,
    'publicly_queryable' => true,  // ✅ REQUIRED for shortcode queries
    'show_ui' => true,
    'show_in_menu' => true,
    'has_archive' => true,
    'rewrite' => array( 'slug' => 'products' ),
    'supports' => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
) );
```

### Taxonomy Query Best Practices

**Problem:** Products appearing multiple times due to multiple taxonomy term assignments or incorrect query logic.

**Solution:** Each post should have ONE primary taxonomy term. Use `'field' => 'slug'` in tax_query.

**Correct Query Pattern:**
```php
$args = array(
    'post_type' => 'bgp_product',
    'posts_per_page' => -1,
    'post_status' => 'publish',
    'orderby' => 'menu_order',
    'order' => 'ASC',
);

// Filter by single taxonomy term
if ( ! empty( $atts['type'] ) ) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'bgp_product_type',
            'field' => 'slug',  // ✅ Use 'slug', not 'name'
            'terms' => sanitize_text_field( $atts['type'] ),  // ✅ Single term
        ),
    );
}
```

**Common Mistakes:**
- ❌ Setting `'public' => false` (makes CPT not queryable)
- ❌ Omitting `'publicly_queryable' => true` (defaults to `false` if `public` is `false`)
- ❌ Using `'field' => 'name'` instead of `'field' => 'slug'`
- ❌ Assigning multiple taxonomy terms to same post (causes duplicates)
- ❌ Using array of terms when single term is intended

**Rules:**
- ✅ CPTs queried by shortcodes MUST have `'publicly_queryable' => true`
- ✅ Use `'field' => 'slug'` in tax_query (more reliable than 'name')
- ✅ Assign ONE primary taxonomy term per post
- ✅ Use `wp_reset_postdata()` after `WP_Query` loops
- ❌ Do NOT assign multiple taxonomy terms if filtering by single term

**Why This Matters:**
- Shortcodes use `WP_Query` to fetch CPT posts
- `WP_Query` cannot query non-publicly-queryable CPTs
- Multiple term assignments cause products to appear in multiple sections
- Incorrect field type causes query failures

---

## ACF Integration Standards

### ✅ REQUIRED: ACF JSON Save/Load Filters

**Problem:** ACF field groups must be saved to and loaded from theme's `acf-json` directory for version control.

**Solution:** Add ACF JSON save/load filters in `functions.php`.

### ACF JSON Filters

```php
/**
 * ACF JSON Save/Load Filters
 * 
 * Configure ACF to save and load field groups from the theme's acf-json directory.
 * This ensures ACF field groups are version-controlled and automatically synced.
 */
add_filter( 'acf/settings/save_json', function( $path ) {
    return get_stylesheet_directory() . '/acf-json';
} );

add_filter( 'acf/settings/load_json', function( $paths ) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
} );
```

### ACF Availability Check

```php
/**
 * Check if ACF is available
 * 
 * @return bool True if ACF is active, false otherwise.
 */
function bgp_is_acf_available() {
    return function_exists( 'get_field' ) && class_exists( 'ACF' );
}
```

### ACF Field Helper Function

```php
/**
 * Get ACF field with fallback
 * 
 * Wrapper for get_field() that provides fallback if ACF is not available.
 * 
 * @param string $field_name Field name.
 * @param int|string $post_id Post ID.
 * @param mixed $fallback Fallback value if ACF not available or field not found.
 * @return mixed Field value or fallback.
 */
function bgp_get_field( $field_name, $post_id = false, $fallback = null ) {
    if ( ! bgp_is_acf_available() ) {
        return $fallback;
    }
    $value = get_field( $field_name, $post_id );
    return $value !== false && $value !== null ? $value : $fallback;
}
```

### ACF WYSIWYG Field Handling

**Problem:** WYSIWYG fields contain HTML that must be preserved. Using `esc_html()` strips HTML tags.

**Solution:** Always use `wp_kses_post()` for WYSIWYG field content.

**Correct Pattern:**
```php
function bgp_faqs_shortcode( $atts ) {
    // Check ACF availability first
    if ( ! bgp_is_acf_available() ) {
        return '<div class="bgp-notice bgp-notice--warning"><p><strong>ACF Required:</strong> Advanced Custom Fields plugin must be installed and activated.</p></div>';
    }
    
    // ... query logic ...
    
    while ( $faqs->have_posts() ) {
        $faqs->the_post();
        $post_id = get_the_ID();
        
        // Get WYSIWYG field with fallback
        $answer = bgp_get_field( 'bgp_faq_answer', $post_id, get_the_content() );
        
        // ✅ Use wp_kses_post() to preserve HTML formatting
        $output .= '<div class="faq-answer">' . wp_kses_post( $answer ) . '</div>';
    }
    
    wp_reset_postdata();
    return $output;
}
```

**Common Mistakes:**
- ❌ Using `esc_html()` for WYSIWYG fields (strips HTML)
- ❌ Using `get_field()` directly without ACF check (fatal error if ACF not active)
- ❌ Not providing fallback value (empty output if field not set)

**Rules:**
- ✅ Always check ACF availability before using ACF functions
- ✅ Use `bgp_get_field()` helper with fallback
- ✅ Use `wp_kses_post()` for WYSIWYG fields (preserves HTML)
- ✅ Use `esc_html()` only for plain text fields
- ❌ Do NOT use `get_field()` directly without availability check

**Why This Matters:**
- ACF may not be installed/active
- WYSIWYG fields contain HTML that must be preserved
- Fallback values prevent empty output
- Proper escaping prevents XSS while preserving formatting

---

## Code Documentation Standards

### ✅ REQUIRED: Comprehensive Code Comments Explaining Reasoning

**Problem:** Code without clear documentation makes it difficult to understand why decisions were made, what problems are being solved, and how to maintain or modify the code.

**Solution:** All functions, filters, and critical code sections must include comprehensive comments that explain:
1. **What** the code does
2. **Why** it exists (the problem it solves)
3. **How** it solves the problem
4. **Critical settings** and their rationale
5. **References** to structural rules or documentation

### Required Documentation Elements

**Function-Level Documentation:**
```php
/**
 * Function Name
 * 
 * **Why This Function Exists:**
 * Clear explanation of the problem this function solves and why it's needed.
 * 
 * **The Problem:**
 * Specific issue or error that occurs without this function (if applicable).
 * 
 * **The Solution:**
 * How this function solves the problem and what approach it uses.
 * 
 * **Key Settings Explained:**
 * - Setting 1: Why this value is used
 * - Setting 2: Why this value is used
 * 
 * **Reference:**
 * - Plugin Structural Rules: [link to relevant section]
 * - Plugin Code: [file path and line numbers]
 * 
 * @param type $param Description
 * @return type Description
 */
```

**Inline Comments for Critical Code:**
```php
// CRITICAL: Explanation of why this setting is critical
// Example: 'field' => 'slug' (more reliable than name/ID for taxonomy queries)

// Why this approach is used
// Example: Conditional loading improves performance by avoiding unnecessary assets

// What this prevents
// Example: Prevents fatal errors if ACF is not installed
```

### Documentation Requirements by Code Type

**1. CPT Registration:**
- Why CPTs are used (structured content management)
- Naming convention rationale (prefix, conflicts)
- Key settings explained (`public`, `has_archive`, `rewrite`, `show_in_rest`, `publicly_queryable`)
- Reference to structural rules section

**2. Taxonomy Registration:**
- Why taxonomies are used (categorization, filtering)
- Key settings explained (`hierarchical`, `show_in_rest`, `field => 'slug'`)
- How shortcodes use them for filtering
- Reference to structural rules section

**3. ACF Filters:**
- Why filters are required (version control, auto-sync)
- What they do (save/load behavior)
- Benefits (version control, deployment)
- Reference to structural rules section

**4. ACF Helper Functions:**
- Why wrapper functions exist (prevent fatal errors, graceful degradation)
- Benefits (consistent error handling, safe operation)
- Usage patterns and examples

**5. Shortcode Registration:**
- Why shortcodes are used (dynamic content insertion)
- Naming convention rationale (prefix, conflicts)
- Usage examples
- Reference to structural rules section

**6. External CDN Dependencies:**
- Why function exists (plugin skips external URLs)
- The problem (specific error message)
- The solution (manual enqueuing approach)
- Load order considerations
- Reference to structural rules section

**7. Shortcode Execution Filters:**
- Why filters are needed (Gutenberg block rendering issues)
- Priority explanation (why specific priority is used)
- What they prevent (literal shortcode text rendering)
- Reference to structural rules section

**8. Taxonomy Queries:**
- Why `'field' => 'slug'` is critical (more reliable than name/ID)
- Shortcode usage examples
- Sanitization notes

### Rules

- ✅ **Always document WHY code exists, not just WHAT it does**
- ✅ **Explain critical settings and their rationale**
- ✅ **Reference structural rules sections where applicable**
- ✅ **Include usage examples for complex functions**
- ✅ **Document load order considerations for scripts/styles**
- ✅ **Explain error prevention (fatal errors, graceful degradation)**
- ✅ **Use clear section headers in docblocks (Why, Problem, Solution, Reference)**
- ❌ **Do NOT write comments that just repeat the code**
- ❌ **Do NOT omit reasoning for critical settings**
- ❌ **Do NOT skip documentation for "obvious" code**

### Why This Matters

- **Maintainability:** Future developers understand why decisions were made
- **Debugging:** Clear documentation helps identify issues quickly
- **Onboarding:** New team members can understand code faster
- **Consistency:** Standardized documentation across all projects
- **Knowledge Transfer:** Documentation preserves institutional knowledge

**Example of Well-Documented Code:**
```php
/**
 * Enqueue Leaflet.js and dependencies for coverage page.
 * 
 * **Why This Function Exists:**
 * The Vibe Code Deploy plugin's AssetService::extract_head_assets() intentionally
 * skips external URLs (CDN scripts) for security and performance reasons.
 * 
 * **The Problem:**
 * - coverage.html includes: <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
 * - Plugin skips this during asset extraction (see AssetService.php lines 97-98)
 * - map.js depends on Leaflet.js and fails with: "Leaflet.js is required"
 * 
 * **The Solution:**
 * Manually enqueue external CDN dependencies in theme functions.php using WordPress
 * wp_enqueue_script() and wp_enqueue_style() functions.
 * 
 * **Load Order Critical:**
 * - Leaflet.js must load BEFORE map.js (which is enqueued by plugin with defer)
 * - WordPress dependency system ensures correct order
 * 
 * **Reference:**
 * - Plugin Structural Rules: VibeCodeCircle/plugins/vibecode-deploy/docs/STRUCTURAL_RULES.md
 * - Section: "External CDN Dependencies"
 */
function project_enqueue_leaflet_scripts() {
    // Only enqueue on coverage page to avoid loading unnecessary assets
    // This improves performance by conditionally loading heavy map libraries
    if ( ! is_page( 'coverage' ) ) {
        return;
    }
    
    // Enqueue Leaflet base CSS (required for map styling)
    // No dependencies - this is the base stylesheet
    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        array(), // No dependencies
        '1.9.4' // Version for cache busting
    );
    
    // Enqueue Leaflet JS (must load before map.js)
    // CRITICAL: This loads WITHOUT defer so it's available when map.js executes
    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        array(), // No dependencies
        '1.9.4',
        true // Load in footer (better performance than head)
    );
}
```

---

## Theme File Structure

### ✅ REQUIRED: Theme Files Organization

**Problem:** Theme files must be organized correctly for plugin to deploy them properly.

**Solution:** Follow the standard theme file structure.

### Theme Directory Structure

```
vibecode-deploy-staging/
└── theme/
    ├── functions.php          # REQUIRED: CPT, shortcode, filter registrations
    └── acf-json/              # REQUIRED: ACF field group definitions
        ├── group_bgp_product.json
        ├── group_bgp_case_study.json
        └── group_bgp_faq.json
```

### functions.php Structure

**Recommended Order:**

```php
<?php
/**
 * Theme Functions
 * 
 * @package ProjectName
 */

// 1. ACF JSON Save/Load Filters (at the top)
add_filter( 'acf/settings/save_json', function( $path ) {
    return get_stylesheet_directory() . '/acf-json';
} );

add_filter( 'acf/settings/load_json', function( $paths ) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
} );

// 2. ACF Helper Functions
function bgp_is_acf_available() {
    return function_exists( 'get_field' ) && class_exists( 'ACF' );
}

function bgp_get_field( $field_name, $post_id = false, $fallback = null ) {
    // ... implementation
}

// 3. CPT Registrations
function bgp_register_post_types() {
    register_post_type( 'bgp_product', array(
        // ... CPT args
    ) );
}
add_action( 'init', 'bgp_register_post_types' );

// 4. Taxonomy Registrations
function bgp_register_taxonomies() {
    register_taxonomy( 'bgp_product_type', 'bgp_product', array(
        // ... taxonomy args
    ) );
}
add_action( 'init', 'bgp_register_taxonomies' );

// 5. Shortcode Registrations
function bgp_products_shortcode( $atts ) {
    // ... shortcode logic
}
add_shortcode( 'bgp_products', 'bgp_products_shortcode' );

// 6. Shortcode Execution Filters (at the end)
function bgp_ensure_shortcode_execution( $block_content, $block ) {
    // ... filter logic
}
add_filter( 'render_block', 'bgp_ensure_shortcode_execution', 5, 2 );
```

### ACF JSON Files

**Location:** `theme/acf-json/` directory

**Naming Convention:** `group_{field_group_key}.json`

**Example:**
- `group_bgp_product.json`
- `group_bgp_case_study.json`
- `group_bgp_faq.json`

**Plugin Behavior:**
- Copies ACF JSON files to child theme's `acf-json/` directory
- ACF automatically loads field groups from this directory
- Field groups are version-controlled with project

**Rules:**
- ✅ ACF JSON files MUST be in `theme/acf-json/` directory
- ✅ File names MUST match ACF field group keys
- ✅ JSON structure MUST match ACF export format
- ❌ Do NOT place ACF JSON files in root or other directories

**Why This Matters:**
- ACF filters must be registered early (before ACF loads)
- Helper functions should be defined before shortcodes use them
- CPTs and taxonomies should be registered before shortcodes query them
- Shortcode execution filters should be at the end (they process output)
- ACF JSON files enable version control of field groups

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/ThemeDeployService.php`
- Copies `theme/acf-json/*.json` to child theme's `acf-json/` directory

---

## CPT Single Templates and 404 Template

### ✅ REQUIRED: Staging Must Include Single-CPT and 404 Templates

**Critical Principle:** The plugin is **prefix-agnostic**. Template requirements and class naming apply to any project; use your project's class prefix (e.g. `cfa-` for CFA, `bgp-` for BioGasPros) in template markup.

**Plugin Behavior (Automatic Creation):**
- The plugin can **automatically create** default block templates for single CPT views (`single-{post_type}.html`) and a generated 404 template (`404.html`) at deploy time when they are missing.
- These auto-generated templates use minimal structure and do not use your project's header/footer, layout, or class prefix.
- Relying on plugin defaults results in single-CPT and 404 pages that do not match your site's theme styling.

**Required Staging Content:**
- Staging **must** include a custom **`single-{post_type}.html`** for **each** Custom Post Type used by the project (e.g. `single-advisory.html`, `single-product.html` — exact filename matches the CPT slug used by the theme).
- Staging **must** include a custom **`404.html`** template.
- Templates must use the **project's class prefix** (e.g. `cfa-`, `bgp-`) and the same layout structure as the rest of the site (e.g. shared header/footer, same main wrapper and semantic blocks) so single and 404 views match theme styling.

**Why This Matters:**
- Single-CPT and 404 pages that use your header, footer, and prefix-based classes stay visually and structurally consistent with the rest of the site.
- Omitting these templates causes either plugin-generated defaults (inconsistent look) or fallback theme behavior that may not match the deployed design.

**Rules:**
- ✅ Include `single-{post_type}.html` in `templates/` for every CPT registered by the project (prefix-agnostic: filename uses the actual CPT slug, e.g. `single-cfa_advisory.html` or `single-bgp_product.html`).
- ✅ Include `404.html` in `templates/`.
- ✅ Use the project's class prefix in template markup (e.g. `{prefix}-main`, `{prefix}-page-content`, `{prefix}-container`) so styling and layout match the rest of the site.
- ✅ Reuse the same template-part and block structure as other templates (e.g. template-parts for header/footer, same main wrapper).
- ❌ Do not rely on plugin-generated single or 404 templates if you need consistent theme styling.

**Reference:**
- Project-specific structural rules (e.g. CFA, BGP) should list the exact CPT slugs and required template filenames for that project.
- Plugin code: template creation in `DeployService` / block template handling.

---

## Plugin Agnosticism and Generic Verification

### ✅ REQUIRED: Plugin is Fully Agnostic

**Critical Principle:** The plugin is fully agnostic and works with any project's bespoke CPTs and shortcodes. It does NOT validate or check for specific CPT names.

**How Plugin Extracts/Merges:**
- Extracts CPT registrations by pattern matching: `register_post_type` calls
- Extracts function definitions containing `register_post_type`
- Extracts `add_action('init', 'function_name')` patterns
- Merges based on function names and patterns, not specific CPT slugs
- Verification checks for generic patterns, not specific names

**What Projects Must Do (Predictable Coding):**
- Use named functions for CPT registration (e.g., `{project}_register_post_types()`)
- Use project prefix for CPT slugs (e.g., `{project}_product`, `{project}_advisory`)
- Hook functions to `init` action: `add_action('init', 'function_name')`
- Follow consistent structure so plugin can extract/merge correctly

**What Plugin Does NOT Do:**
- Does NOT check for specific CPT names (e.g., 'bgp_product', 'cfa_advisory')
- Does NOT validate that specific CPTs exist
- Does NOT assume any project's CPT structure
- Verification is pattern-based, not name-based

**Verification Output:**
- Checks if `register_post_type` calls exist (generic)
- Counts how many CPTs are registered (generic)
- Checks if `add_action('init', ...)` patterns exist (generic)
- Optionally lists CPTs with project prefix (if project_slug available)
- Never validates specific CPT names

**Why This Matters:**
- Each project has unique CPTs (bgp_product, cfa_advisory, etc.)
- Plugin must work generically with any project's structure
- Projects must code predictably for reliable extraction/merging
- Verification helps debug issues without assuming specific CPT names

**Example:**
```php
// ✅ Good: Named function with project prefix - plugin can extract this
function bgp_register_post_types() {
    register_post_type( 'bgp_product', array( /* ... */ ) );
    register_post_type( 'bgp_case_study', array( /* ... */ ) );
}
add_action( 'init', 'bgp_register_post_types' );

// ✅ Good: Plugin extracts by pattern, not by specific name
// Works for: bgp_product, cfa_advisory, any_project_cpt, etc.
```

**Plugin Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/ThemeDeployService.php`
- `extract_cpt_registrations()` - Pattern-based extraction
- `merge_cpt_registrations()` - Generic merge logic
- `deploy_theme_files()` - Generic verification

---

## EtchWP Compatibility

### ✅ REQUIRED: EtchWP Content Storage

**Critical Principle:** When using **EtchWP plugin**, `post_content` must **always be populated** even when custom block templates exist.

**Why This Matters:**
- EtchWP templates use the core WordPress `<!-- wp:post-content -->` block
- The `wp:post-content` block reads from the `post_content` database field
- Empty `post_content` = empty editor and empty frontend rendering
- Templates provide structure, `post_content` provides content

**How It Works:**
1. **Standard Block Themes:**
   - Template file contains the page content
   - `post_content` is cleared when template exists
   - WordPress uses template file for rendering

2. **EtchWP:**
   - Template file contains structure with `<!-- wp:post-content -->` block
   - `post_content` must contain the page content (Gutenberg blocks)
   - `wp:post-content` block reads from `post_content` and renders it

**Example EtchWP Template:**
```html
<!-- wp:etch/element {"tag":"main"} -->
<!-- wp:post-content {"align":"full","layout":{"type":"default"}} /-->
<!-- /wp:etch/element -->
```

**Plugin Behavior:**
- vibecode-deploy automatically detects EtchWP via `defined('ETCH_PLUGIN_FILE')`
- When EtchWP is active, `post_content` is **preserved** even when templates exist
- Content is stored in `post_content` as Gutenberg blocks
- Templates are created with `wp:post-content` block structure

**Rules:**
- ✅ EtchWP sites: `post_content` always contains page content
- ✅ Templates use `wp:post-content` block to display content
- ✅ Editor shows content because `post_content` is populated
- ✅ Frontend renders correctly via `wp:post-content` block
- ❌ Do NOT manually clear `post_content` for EtchWP sites

**Detection:**
- Plugin automatically detects EtchWP when `ETCH_PLUGIN_FILE` constant is defined
- No manual configuration needed
- Works automatically when EtchWP plugin is active

**Reference:**
- **Compatibility Report:** `VibeCodeCircle/reference/etchwp/COMPATIBILITY_REPORT.md`
- **Plugin Code:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php` (lines 972-994, 1289-1332)

### ✅ REQUIRED: Comprehensive EtchWP Compliance

**Purpose:** Ensure all deployed pages meet EtchWP requirements for optimal editability and functionality.

**Compliance Areas:**

1. **Block Editability:**
   - All blocks must have `etchData` metadata for EtchWP IDE editability
   - Target: 95%+ of blocks have etchData
   - Semantic blocks (wp:paragraph, wp:heading, wp:image) should have etchData
   - HTML blocks (wp:html) should also have etchData when possible

2. **Post Content Preservation:**
   - `post_content` must be populated when EtchWP is active
   - Templates use `wp:post-content` block which requires populated content
   - Content should not be cleared for EtchWP sites

3. **Template Structure:**
   - Block templates (`.html` files) not PHP templates
   - Template parts (header/footer) use block markup
   - No `get_header()` or `get_footer()` calls in templates

4. **Image Handling:**
   - Image blocks must have absolute URLs (Media Library or plugin assets)
   - Image blocks must have `etchData` metadata
   - Images should be accessible and properly formatted

5. **Block Conversion Accuracy:**
   - Semantic elements convert to proper blocks (wp:paragraph, wp:heading, etc.)
   - Lists use `wp:list` not nested `wp:list-item` blocks
   - Inline elements stay inline (not converted to blocks)
   - Minimal HTML blocks (prefer semantic blocks)

6. **Child Theme Structure:**
   - Child theme structure matches official Etch child theme
   - `functions.php` smart merge compatibility
   - ACF JSON file handling correct
   - Required directories present

**Automated Compliance Checking:**
- Plugin includes `EtchWPComplianceService` for comprehensive checks
- Compliance checks run automatically after deployment (when EtchWP active)
- Manual compliance checks available in **Health Check** page
- Results include pass/fail status and detailed issues

**How to Run Compliance Checks:**
1. **Automatic:** Runs after each deployment (logged, non-blocking)
2. **Manual:** Go to **Vibe Code Deploy → Health Check → EtchWP Compliance Check**
3. **Select Page:** Choose a deployed page from dropdown
4. **View Results:** See compliance status for all areas

**Compliance Scores:**
- **100%:** All checks pass (excellent)
- **70-99%:** Most checks pass (warning, minor issues)
- **<70%:** Multiple failures (fail, needs attention)

**Code Reference:**
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/EtchWPComplianceService.php` - Compliance checking service
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Admin/HealthCheckPage.php` - Compliance check UI
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php` - Automatic compliance checks after deployment

**Review Process:**
- Monthly compliance reviews (see `docs/COMPLIANCE_REVIEW_PROCESS.md`)
- Automated checks help identify issues early
- Compliance reports generated for documentation

---

## Summary Checklist

Before deploying a project, verify:

### Staging Structure
- [ ] Staging zip has correct root directory (`vibecode-deploy-staging/` or `{project-slug}-deployment/`)
- [ ] Required directories exist: `pages/`, `css/`, `js/`, `resources/`
- [ ] Zip created with command that excludes macOS metadata

### Asset Paths
- [ ] CSS files in `css/` directory, referenced as `css/styles.css`
- [ ] JS files in `js/` directory, referenced as `js/main.js`
- [ ] Images in `resources/images/` directory, referenced as `resources/images/logo.png`
- [ ] No `images/` paths used (not rewritten by plugin)

### HTML Structure
- [ ] All pages have `<main>` tag with content
- [ ] CSS links in `<head>` section
- [ ] Scripts in `<head>` with `defer` attribute (recommended)
- [ ] Semantic structure: `<header>`, `<main>`, `<footer>`

### Config File
- [ ] `vibecode-deploy-shortcodes.json` has `version`, `defaults`, `pages` sections
- [ ] `pages` section exists (even if empty)
- [ ] Shortcode names match registered shortcodes
- [ ] Taxonomy terms in shortcodes match registered terms

### CPT Single and 404 Templates
- [ ] Staging `templates/` includes `single-{post_type}.html` for each CPT used by the project
- [ ] Staging `templates/` includes `404.html`
- [ ] Templates use the project's class prefix and match site layout (header/footer, main wrapper)

### Theme Files
- [ ] `theme/functions.php` exists with CPT, taxonomy, shortcode registrations
- [ ] ACF JSON save/load filters in `functions.php`
- [ ] ACF helper functions defined
- [ ] ACF JSON files in `theme/acf-json/` directory
- [ ] `functions.php` follows recommended structure order

### CPT and Shortcodes
- [ ] CPTs have `'publicly_queryable' => true` if shortcodes query them
- [ ] Taxonomy queries use `'field' => 'slug'`
- [ ] Each post has ONE primary taxonomy term
- [ ] Shortcodes use `wp_kses_post()` for WYSIWYG fields
- [ ] Shortcodes check ACF availability before using ACF functions

---

## References

- **Plugin Architecture:** `VibeCodeCircle/plugins/vibecode-deploy/docs/ARCHITECTURE.md`
- **Staging Validation:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Staging.php`
- **Asset Service:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php`
- **Deploy Service:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php`
- **Shortcode Validation:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/ShortcodePlaceholderService.php`

---

**Last Updated:** 2026-01-26  
**Plugin Version:** 0.1.57+  
**Status:** Active Standard - Source of Truth
