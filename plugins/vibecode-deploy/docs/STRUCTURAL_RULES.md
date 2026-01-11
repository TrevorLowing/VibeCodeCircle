# Structural Rules for Vibe Code Deploy Projects

**Purpose:** This document serves as the **source of truth** for all structural rules and standards that projects must follow when using the Vibe Code Deploy plugin.

**Status:** Active Standard  
**Last Updated:** 2026-01-11  
**Plugin Version:** 0.1.47+

**Note:** All projects (BGP, CFA, and future projects) should reference this document and align their structural rules accordingly.

---

## Table of Contents

1. [Staging Directory Structure](#staging-directory-structure)
2. [Asset Path Conventions](#asset-path-conventions)
3. [HTML Structure Requirements](#html-structure-requirements)
4. [JavaScript Asset Placement](#javascript-asset-placement)
5. [Image Path Conventions](#image-path-conventions)
6. [URL Rewriting Rules](#url-rewriting-rules)
7. [Config File Structure](#config-file-structure)
8. [CPT and Shortcode Standards](#cpt-and-shortcode-standards)
9. [ACF Integration Standards](#acf-integration-standards)
10. [Theme File Structure](#theme-file-structure)

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
2. **`defaults`** (required): Validation mode settings
3. **`pages`** (required): Page-specific shortcode requirements - **CRITICAL: Plugin expects this section**
4. **`post_types`** (optional): CPT-specific shortcode requirements

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
- ✅ `pages` section MUST exist (even if empty: `"pages": {}`)
- ✅ Each page slug must match actual HTML filename (minus `.html`)
- ✅ Shortcode names must match registered shortcode tags
- ✅ Attributes must match shortcode handler expectations
- ❌ Do NOT use `shortcodes` section (old format, not recognized)

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

**Last Updated:** 2026-01-11  
**Plugin Version:** 0.1.47+  
**Status:** Active Standard - Source of Truth
