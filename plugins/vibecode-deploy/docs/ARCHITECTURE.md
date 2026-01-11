# Vibe Code Deploy - Plugin Architecture & Processes

**Version:** 0.1.1  
**Last Updated:** January 9, 2025

This document explains the internal architecture, components, and processes of the Vibe Code Deploy plugin. It is intended for developers who need to understand how the plugin works, extend it, or troubleshoot issues.

**Related Documentation:**
- **[Structural Rules](STRUCTURAL_RULES.md)** - Source of truth for all structural standards and requirements that projects must follow

---

## Table of Contents

1. [Plugin Overview](#plugin-overview)
2. [Architecture Components](#architecture-components)
3. [Core Services](#core-services)
4. [Deployment Process Flow](#deployment-process-flow)
5. [Template System](#template-system)
6. [Asset Management](#asset-management)
7. [URL Rewriting](#url-rewriting)
8. [HTML to Block Conversion](#html-to-block-conversion)
9. [Key Workflows](#key-workflows)

---

## Plugin Overview

**Vibe Code Deploy** is a WordPress plugin that imports static HTML sites into WordPress as Gutenberg block templates. It converts HTML pages into WordPress block templates, manages assets (CSS, JS, images), and creates a complete WordPress site structure from a staging ZIP file.

### Key Features

- **Block Template Creation:** Converts HTML pages to WordPress block templates
- **Asset Management:** Copies and enqueues CSS, JS, and image files
- **Template Parts:** Extracts header/footer from HTML into reusable template parts
- **Shortcode Placeholders:** Converts HTML comments to Gutenberg shortcode blocks
- **Theme Integration:** Deploys theme files (functions.php, ACF JSON) with smart merging
- **Rollback Support:** Tracks deployments and enables rollback to previous versions

---

## Architecture Components

### Directory Structure

```
vibecode-deploy/
├── vibecode-deploy.php          # Main plugin file
├── includes/
│   ├── Bootstrap.php            # Plugin initialization
│   ├── Settings.php              # Settings management
│   ├── Staging.php               # Staging ZIP handling
│   ├── Importer.php              # Asset enqueuing
│   ├── Logger.php                # Logging system
│   ├── Cli.php                   # WP-CLI commands
│   ├── Admin/                    # Admin page controllers
│   │   ├── ImportPage.php        # Import/deploy interface
│   │   ├── BuildsPage.php        # Build history
│   │   ├── SettingsPage.php      # Plugin settings
│   │   └── ...
│   └── Services/                 # Core business logic
│       ├── DeployService.php     # Main deployment orchestrator
│       ├── AssetService.php      # Asset copying & URL rewriting
│       ├── TemplateService.php   # Template creation/management
│       ├── HtmlToEtchConverter.php  # HTML → Block conversion
│       └── ...
└── assets/                       # Plugin assets (CSS, JS, resources)
```

### Component Layers

1. **Admin Layer** (`includes/Admin/`): WordPress admin UI pages
2. **Service Layer** (`includes/Services/`): Core business logic (stateless services)
3. **Infrastructure Layer** (`includes/`): Settings, logging, staging handling
4. **Conversion Layer** (`HtmlToEtchConverter.php`): HTML to Gutenberg block conversion

---

## Core Services

### DeployService

**Purpose:** Main orchestrator for deployment process

**Key Methods:**
- `run_import()`: Main deployment entry point
- `create_page_templates_from_pages()`: Auto-generates page templates from HTML files
- `rewrite_urls()`: Converts relative URLs to WordPress URLs

**Responsibilities:**
- Coordinates all deployment steps
- Creates/updates WordPress pages
- Manages template creation
- Handles page content clearing for custom templates
- Tracks deployment statistics

**Process Flow:**
1. Copy assets to plugin folder
2. Process pages (create/update WordPress pages)
3. Extract template parts (header/footer)
4. Create page templates from HTML files
5. Deploy theme files
6. Clear page content for pages with custom templates
7. Flush rewrite rules

### AssetService

**Purpose:** Manages asset files (CSS, JS, images)

**Key Methods:**
- `copy_assets_to_plugin_folder()`: Copies assets from staging to plugin directory
- `extract_head_assets()`: Extracts CSS/JS references from HTML `<head>`
- `rewrite_asset_urls()`: Converts asset paths to plugin URLs

**Asset Locations:**
- **Source:** `vibecode-deploy-staging/{css,js,resources}/`
- **Destination:** `wp-content/plugins/vibecode-deploy/assets/{css,js,resources}/`
- **URL Pattern:** `/wp-content/plugins/vibecode-deploy/assets/{type}/{file}`

**Asset Types:**
- **CSS:** Extracted from `<link rel="stylesheet">` in `<head>`
- **JS:** Extracted from `<script src="">` in `<head>`
- **Resources:** Images and other files from `resources/` directory

### TemplateService

**Purpose:** Manages WordPress block templates and template parts

**Key Methods:**
- `upsert_template()`: Creates or updates a block template
- `upsert_template_part()`: Creates or updates a template part
- `get_template_by_slug()`: Retrieves template by slug
- `auto_extract_template_parts_from_home()`: Extracts header/footer from home.html
- `ensure_post_type_templates()`: Creates default CPT single templates
- `ensure_default_post_templates()`: Creates home.html and archive.html templates

**Template Types:**
- **Page Templates:** `page-{slug}.html` (e.g., `page-home.html`)
- **Template Parts:** `header`, `footer` (reusable components)
- **CPT Templates:** `single-{post_type}.html` (auto-generated)
- **Archive Templates:** `archive.html`, `home.html` (blog index)

### HtmlToEtchConverter

**Purpose:** Converts HTML elements to Gutenberg block markup

**Key Methods:**
- `convert()`: Main conversion entry point
- `convert_element()`: Converts individual DOM elements
- `convert_dom_children()`: Recursively converts child elements

**Conversion Rules:**
- **Sections/Divs:** Converted to `wp:group` blocks
- **Custom HTML:** Wrapped in `wp:html` blocks (preserves classes)
- **Shortcodes:** Converted to `wp:shortcode` blocks
- **Classes:** Preserved in wrapper divs (critical for CSS)
- **Hero Sections:** Preserved as `wp:html` blocks to maintain custom classes

**Class Preservation:**
- Original CSS classes are preserved in wrapper divs
- Example: `<section class="cfa-hero cfa-hero--compact">` → `wp:html` block with original classes intact

### ShortcodePlaceholderService

**Purpose:** Handles shortcode placeholder comments in HTML and validates project prefix compliance

**Placeholder Format:**
```html
<!-- VIBECODE_SHORTCODE shortcode_name param="value" -->
```

**Conversion:**
- Converts to: `<!-- wp:shortcode -->[shortcode_name param="value"]<!-- /wp:shortcode -->`

**Configuration:**
- Loads rules from `vibecode-deploy-shortcodes.json`
- Validates required placeholders per page
- Supports validation modes: `warn` or `fail`

**Project Prefix Validation:**
- **Generalized naming convention**: Validates that shortcodes and CPTs follow project naming conventions
- **Flexible prefix format**: Accepts both `{project_slug}_` (with underscore) and `{project_slug}` (without underscore)
  - Example: For project slug "cfa", validates `cfa_investigations`, `cfaadvisories`, etc.
- **Validation modes**:
  - `warn` (default): Show warnings but allow deployment
  - `fail`: Block deployment if items don't match prefix
  - `off`: Disable prefix validation
- **Validation scope**:
  - `all` (default): Validate both shortcodes and CPTs
  - `shortcodes`: Validate shortcodes only
  - `cpts`: Validate CPTs only
- **Unknown item detection**: Warns about shortcodes/CPTs that use the project prefix but aren't documented in config (potential orphaned/unused items)
- **Integration**: Prefix validation runs alongside shortcode placeholder validation during deployment

### ThemeDeployService

**Purpose:** Deploys theme files with smart merging

**Key Methods:**
- `deploy_theme_files()`: Main theme deployment entry point
- `smart_merge_functions_php()`: Merges functions.php without breaking existing code

**Smart Merge Strategy:**
- Extracts CPT registrations from staging `functions.php`
- Removes existing CPT registrations from theme `functions.php`
- Inserts new CPT registrations
- Same process for shortcodes and helper functions
- Validates PHP syntax after merge
- Creates backup before merge, restores on failure

**Deployed Files:**
- `functions.php`: Smart merged with existing theme code
- `acf-json/*.json`: ACF field group definitions

### BuildService

**Purpose:** Manages build fingerprints and deployment history

**Key Methods:**
- `create_build()`: Creates new build record
- `get_active_fingerprint()`: Gets currently active deployment
- `set_active_fingerprint()`: Sets active deployment
- `list_builds()`: Lists all builds for a project

**Build Tracking:**
- Each deployment gets a unique fingerprint (timestamp-based)
- Builds stored in WordPress options
- Enables rollback to previous deployments

### RollbackService

**Purpose:** Enables rollback to previous deployments

**Key Methods:**
- `rollback_to_fingerprint()`: Rolls back to specific deployment
- `get_rollback_data()`: Retrieves data needed for rollback

**Rollback Process:**
- Restores pages from previous deployment
- Restores templates and template parts
- Restores theme files
- Updates active fingerprint

---

## Deployment Process Flow

### Step 1: Upload Staging ZIP

**File:** `includes/Staging.php`

**Process:**
1. User uploads ZIP file via admin interface
2. ZIP extracted to temporary directory
3. Validates staging structure (requires `pages/`, `css/`, `js/`)
4. Creates build record with fingerprint
5. Stores staging files in uploads directory

**Staging ZIP Naming:**
- **Recommended:** Use project-prefixed names (e.g., `{project-slug}-vibecode-deploy-staging.zip`) to avoid conflicts when working with multiple projects in the same workspace
- **Plugin accepts:** Any ZIP filename - the plugin does not enforce specific naming conventions
- **Example:** For a project with slug `cfa`, use `cfa-vibecode-deploy-staging.zip` instead of generic `vibecode-deploy-staging.zip`

**Staging Structure:**
```
vibecode-deploy-staging/
├── pages/              # HTML page files
├── css/                # CSS files
├── js/                 # JavaScript files
├── resources/          # Images and assets
├── templates/          # Optional: Manual templates
├── template-parts/     # Optional: Manual template parts
├── theme/              # Optional: Theme files
│   ├── functions.php
│   └── acf-json/
└── vibecode-deploy-shortcodes.json  # Optional: Shortcode rules
```

### Step 2: Preflight Analysis

**File:** `includes/Services/DeploymentValidator.php`

**Process:**
1. Scans staging directory
2. Lists pages to be created/updated
3. Validates shortcode placeholders (if config exists)
4. Checks for missing template parts
5. Reports warnings/errors

**Output:**
- List of pages
- List of assets
- Validation warnings
- Template parts to extract

### Step 3: Deploy

**File:** `includes/Services/DeployService.php` → `run_import()`

**Process Flow:**

#### 3.1 Copy Assets
```php
AssetService::copy_assets_to_plugin_folder($build_root);
```
- Copies `css/`, `js/`, `resources/` to plugin assets directory
- Creates directory structure if needed
- Preserves file structure

#### 3.2 Process Pages
```php
foreach ($pages as $path) {
    // Extract content from <main> tag
    // Rewrite URLs (page links, resources)
    // Convert HTML to block markup
    // Create/update WordPress page
    // Store CSS/JS assets in post meta
}
```

**Page Processing Steps:**
1. Parse HTML file with DOMDocument
2. Extract `<main>` content
3. Extract CSS/JS from `<head>` (via `AssetService::extract_head_assets()`)
4. Rewrite URLs:
   - `rewrite_asset_urls()` FIRST (resources → plugin URL)
   - `rewrite_urls()` SECOND (page links, skip already-converted resources)
5. Convert HTML to block markup (via `HtmlToEtchConverter`)
6. Check if custom template exists for page
7. If template exists, clear `post_content` (WordPress will use template)
8. Create/update WordPress page
9. Store CSS/JS assets in post meta for later enqueuing

#### 3.3 Extract Template Parts
```php
TemplateService::auto_extract_template_parts_from_home();
```
- Extracts `<header>` from `home.html`
- Extracts `<footer>` from `home.html`
- Converts to template parts
- Creates `wp_template_part` posts

#### 3.4 Create Page Templates
```php
DeployService::create_page_templates_from_pages();
```
- Iterates through `pages/*.html` files
- Extracts `<main>` content from each
- Converts to block markup
- Wraps with header/footer template parts
- Creates `page-{slug}.html` templates

**Template Structure:**
```html
<!-- wp:template-part {"slug":"header"} /-->

<!-- wp:group {"tagName":"main"} -->
<main>
  <!-- Converted page content (blocks) -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer"} /-->
```

#### 3.5 Deploy Theme Files
```php
ThemeDeployService::deploy_theme_files();
```
- Smart merges `functions.php`
- Copies ACF JSON files
- Validates PHP syntax
- Creates backup before merge

#### 3.6 Clear Page Content for Custom Templates
```php
// Post-deployment cleanup
foreach ($pages as $path) {
    if (template_exists) {
        wp_update_post(['post_content' => '']);
    }
}
```
- Ensures WordPress uses block templates instead of page content
- Prevents content override issues

#### 3.7 Flush Rewrite Rules
```php
flush_rewrite_rules(false);
```
- Refreshes permalinks after template creation
- Required for single post URLs to work

---

## Template System

### Template Hierarchy

WordPress block theme template hierarchy:
1. `page-{slug}.html` (specific page template)
2. `page-{id}.html` (page ID template)
3. `page.html` (generic page template)
4. `singular.html` (all singular posts)
5. `index.html` (fallback)

### Template Creation

**Automatic Template Creation:**
- Templates are auto-generated from `pages/*.html` files
- Extracts `<main>` content
- Converts to block markup
- Wraps with header/footer template parts

**Manual Templates:**
- If `templates/page-{slug}.html` exists in staging, auto-creation is skipped
- Manual templates take precedence

### Template Parts

**Header/Footer Extraction:**
- Extracted from `home.html` during deployment
- Stored as `wp_template_part` posts
- Referenced in templates via `wp:template-part` blocks

**Template Part Structure:**
```html
<!-- wp:html -->
<header class="cfa-header">
  <!-- Header content -->
</header>
<!-- /wp:html -->
```

### Template Validation

**Page Template Rules:**
- Must NOT contain `wp:post-content` blocks
- Page templates define full page structure
- Page editor content is cleared when template exists

**Validation:**
- `TemplateService::validate_page_template()` checks for `wp:post-content`
- Rejects templates that include `wp:post-content` (would cause content duplication)

---

## Asset Management

### Asset Copying

**Source:** `vibecode-deploy-staging/{css,js,resources}/`  
**Destination:** `wp-content/plugins/vibecode-deploy/assets/{css,js,resources}/`

**Process:**
```php
AssetService::copy_assets_to_plugin_folder($build_root);
```
- Recursively copies all files
- Preserves directory structure
- Creates directories as needed

### Asset Enqueuing

**File:** `includes/Importer.php` → `enqueue_assets_for_current_page()`

**Process:**
1. Hook: `wp_enqueue_scripts` (priority 15)
2. Get current page/post
3. Retrieve CSS/JS assets from post meta
4. Enqueue each asset file
5. Add cache-busting query string (fingerprint)

**CSS Loading Order (Critical for Visual Parity):**
1. **WordPress Core Styles** (priority 10)
   - WordPress block library (`wp-block-library`)
   - Theme styles (if any)

2. **Google Fonts** (priority 15, step 0)
   - Extracted automatically from HTML `<head>` during import
   - Stored in post meta: `META_ASSET_FONTS`
   - Enqueued per-page if available

3. **WordPress Resets CSS** (priority 15, step 0.5)
   - **File:** `assets/css/wordpress-resets.css` (plugin file)
   - **Purpose:** Neutralizes WordPress default styles
   - **Location:** `plugins/vibecode-deploy/assets/css/wordpress-resets.css`
   - **Automatic:** Loads on every page for all projects
   - **Generalized:** No project-specific configuration needed

4. **Project CSS** (priority 15, step 1-2)
   - Per-page CSS from staging (`css/styles.css`, `css/icons.css`)
   - Page-specific CSS (e.g., `css/secure-drop.css`)
   - Projects can override resets if needed (cascade order ensures this works)

**Enqueuing Logic:**
```php
// Step 0: Google Fonts (if extracted from HTML)
foreach ($fonts as $font_url) {
    wp_enqueue_style($handle, $font_url, array(), null);
}

// Step 0.5: WordPress Resets CSS
$resets_url = plugins_url('assets/css/wordpress-resets.css', VIBECODE_DEPLOY_PLUGIN_FILE);
wp_enqueue_style('vibecode-deploy-wordpress-resets', $resets_url, array(), $version);

// Step 1-2: Project CSS
foreach ($css_assets as $css_file) {
    $handle = 'vibecode-deploy-' . sanitize_key($css_file);
    $url = plugins_url('assets/css/' . $css_file, VIBECODE_DEPLOY_PLUGIN_FILE);
    wp_enqueue_style($handle, $url, [], $fingerprint);
}
```

**WordPress Resets CSS:**
- **File Location:** `plugins/vibecode-deploy/assets/css/wordpress-resets.css`
- **Reset Rules:** Admin bar fix, entry-content margins, block group spacing, alignfull/alignwide, screen reader text
- **Generalization:** Uses generic selectors, no project-specific classes
- **Override Capability:** Projects can override resets via their own CSS (loads after resets)

**Cache Busting:**
- Uses deployment fingerprint as version for project CSS
- Uses plugin version for WordPress resets CSS
- Ensures updated assets load after redeployment

### Asset URL Rewriting

**Two-Stage Process:**

**Stage 1: Asset URLs (Resources)**
```php
AssetService::rewrite_asset_urls($html, $project_slug);
```
- Converts: `resources/cfa-logo.webp` → `/wp-content/plugins/vibecode-deploy/assets/resources/cfa-logo.webp`
- Pattern: `/(href|src)="(css|js|resources)\/([^"]+)"/`
- Runs FIRST (before page URL rewriting)

**Stage 2: Page URLs**
```php
DeployService::rewrite_urls($html, $slug_set, $resources_base_url);
```
- Converts: `contact-us` → `/contact-us/`
- Converts: `resources/` → uploads directory (but skips if already converted)
- Runs SECOND (after asset URL rewriting)

**Critical Order:**
- Asset URL rewriting MUST run first
- Page URL rewriting skips already-converted plugin URLs
- Prevents resources from pointing to wrong location

---

## URL Rewriting

### URL Rewriting Order (Critical)

**Correct Order:**
1. `AssetService::rewrite_asset_urls()` - Converts `resources/` → plugin URL
2. `DeployService::rewrite_urls()` - Converts page links, skips already-converted resources

**Why Order Matters:**
- If `rewrite_urls()` runs first, it converts `resources/` to uploads directory
- Then `rewrite_asset_urls()` can't match the pattern (already converted)
- Result: Images point to wrong location (404 errors)

**Implementation:**
```php
// In DeployService::create_page_templates_from_pages()
$content = AssetService::rewrite_asset_urls($content, $project_slug);  // FIRST
$content = self::rewrite_urls($content, $slug_set, $resources_base_url); // SECOND
```

### URL Conversion Rules

**Resources (Images):**
- Source: `resources/cfa-logo.webp`
- Target: `/wp-content/plugins/vibecode-deploy/assets/resources/cfa-logo.webp`
- Handled by: `AssetService::rewrite_asset_urls()`

**Page Links:**
- Source: `contact-us`
- Target: `/contact-us/`
- Handled by: `DeployService::rewrite_urls()`

**External URLs:**
- Skipped (http://, https://, mailto:, tel:, data:, javascript:)
- Preserved as-is

---

## HTML to Block Conversion

### Conversion Process

**File:** `includes/Services/HtmlToEtchConverter.php`

**Process:**
1. Parse HTML with DOMDocument
2. Iterate through DOM elements
3. Convert each element to block markup
4. Preserve CSS classes and attributes
5. Wrap custom HTML in `wp:html` blocks

### Conversion Rules

**Generic Elements (div, section, etc.):**
```html
<!-- Source -->
<section class="cfa-hero cfa-hero--compact">
  <div class="cfa-hero__container">...</div>
</section>

<!-- Converted -->
<!-- wp:group {"className":"cfa-hero cfa-hero--compact"} -->
<section class="cfa-hero cfa-hero--compact wp-block-group">
  <!-- wp:group {"className":"cfa-hero__container"} -->
  <div class="cfa-hero__container wp-block-group">...</div>
  <!-- /wp:group -->
</section>
<!-- /wp:group -->
```

**Shortcode Placeholders:**
```html
<!-- Source -->
<!-- VIBECODE_SHORTCODE cfa_advisories per_page="10" -->

<!-- Converted -->
<!-- wp:shortcode -->
[cfa_advisories per_page="10"]
<!-- /wp:shortcode -->
```

**Custom HTML (Hero Sections):**
```html
<!-- Source -->
<section class="cfa-hero">
  <img src="resources/cfa-logo.webp" />
</section>

<!-- Converted -->
<!-- wp:html -->
<section class="cfa-hero">
  <img src="/wp-content/plugins/vibecode-deploy/assets/resources/cfa-logo.webp" />
</section>
<!-- /wp:html -->
```

### Class Preservation

**Critical for CSS:**
- Original CSS classes are preserved in wrapper divs
- Example: `cfa-hero--compact` must remain for styling to work
- Classes added to both wrapper and original element

**Implementation:**
```php
// In HtmlToEtchConverter::convert_element()
$class_attr = ' class="' . esc_attr($original_classes) . ' wp-block-group"';
```

---

## Key Workflows

### Workflow 1: Full Deployment

1. **Upload Staging ZIP**
   - Extract to temporary directory
   - Validate structure
   - Create build record

2. **Preflight**
   - Analyze staging files
   - Validate shortcode placeholders
   - Report warnings

3. **Deploy**
   - Copy assets to plugin folder
   - Process pages (create/update)
   - Extract template parts
   - Create page templates
   - Deploy theme files
   - Clear page content for templates
   - Flush rewrite rules

4. **Post-Deployment**
   - Set front page (if home.html)
   - Update active fingerprint
   - Log deployment results

### Workflow 2: Template Creation

1. **Read HTML File**
   - Parse with DOMDocument
   - Extract `<main>` content

2. **URL Rewriting**
   - Rewrite asset URLs (resources → plugin URL)
   - Rewrite page URLs (relative → absolute)

3. **HTML to Block Conversion**
   - Convert elements to blocks
   - Preserve classes
   - Wrap custom HTML in `wp:html` blocks

4. **Template Assembly**
   - Add header template part
   - Add main content (converted blocks)
   - Add footer template part

5. **Template Storage**
   - Create/update `wp_template` post
   - Store in database
   - WordPress uses via template hierarchy

### Workflow 3: Asset Enqueuing

1. **Page Load**
   - WordPress loads page
   - Fires `wp_enqueue_scripts` hook

2. **Asset Retrieval**
   - Get current page/post ID
   - Retrieve CSS/JS assets from post meta
   - Get deployment fingerprint

3. **Enqueue Assets**
   - Loop through CSS files
   - Enqueue each with fingerprint version
   - Loop through JS files
   - Enqueue each with fingerprint version

4. **Browser Load**
   - WordPress outputs `<link>` and `<script>` tags
   - Browser loads assets from plugin directory

### Workflow 4: Rollback

1. **Select Build**
   - User selects previous build from history
   - Retrieve build fingerprint

2. **Restore Pages**
   - Get page data from build record
   - Restore page content
   - Restore post meta (assets, fingerprint)

3. **Restore Templates**
   - Get template data from build record
   - Restore template content
   - Restore template parts

4. **Restore Theme Files**
   - Get theme file data from build record
   - Restore functions.php
   - Restore ACF JSON files

5. **Update Active Fingerprint**
   - Set active fingerprint to rolled-back build
   - Site now uses previous deployment

---

## Data Storage

### WordPress Database

**Post Meta:**
- `_vibecode_deploy_project_slug`: Project identifier
- `_vibecode_deploy_source_path`: Source file path
- `_vibecode_deploy_fingerprint`: Deployment fingerprint
- `_vibecode_deploy_assets_css`: CSS files for page
- `_vibecode_deploy_assets_js`: JS files for page

**Post Types:**
- `wp_template`: Block templates (page-{slug}.html)
- `wp_template_part`: Template parts (header, footer)

**Options:**
- `vibecode_deploy_builds_{project}`: Build history
- `vibecode_deploy_active_{project}`: Active deployment fingerprint
- `vibecode_deploy_settings`: Plugin settings

### File System

**Plugin Assets:**
- `wp-content/plugins/vibecode-deploy/assets/css/`
- `wp-content/plugins/vibecode-deploy/assets/js/`
- `wp-content/plugins/vibecode-deploy/assets/resources/`

**Staging Files:**
- `wp-content/uploads/vibecode-deploy/staging/{project}/{fingerprint}/`
- Temporary storage for staging ZIP contents

**Theme Files:**
- `wp-content/themes/{theme}/functions.php` (merged)
- `wp-content/themes/{theme}/acf-json/` (copied)

---

## Error Handling & Logging

### Logging System

**File:** `includes/Logger.php`

**Log Levels:**
- `info()`: General information
- `warning()`: Warnings (non-fatal)
- `error()`: Errors (may be fatal)

**Log Storage:**
- Stored in WordPress options
- Rotated (keeps last N entries)
- Viewable in admin interface (Vibe Code Deploy → Logs)

**Log Format:**
```php
Logger::info('Message', ['key' => 'value'], $project_slug);
```

### Error Handling

**Validation:**
- Preflight validates staging structure
- Template validation prevents invalid templates
- PHP syntax validation for functions.php

**Recovery:**
- Backup created before functions.php merge
- Restored on validation failure
- Rollback available for failed deployments

---

## Extension Points

### Filters

**HTML Processing:**
- `vibecode_deploy_html_before_conversion`: Modify HTML before block conversion
- `vibecode_deploy_html_after_conversion`: Modify blocks after conversion

**Template Creation:**
- `vibecode_deploy_template_content`: Modify template content before storage

### Actions

**Deployment Events:**
- `vibecode_deploy_before_import`: Before deployment starts
- `vibecode_deploy_after_import`: After deployment completes
- `vibecode_deploy_page_created`: When page is created
- `vibecode_deploy_page_updated`: When page is updated

---

## Performance Considerations

### Optimization Strategies

1. **Asset Caching:**
   - Assets served from plugin directory (static files)
   - Cache-busting via fingerprint version
   - Browser caching enabled

2. **Template Caching:**
   - WordPress caches block templates
   - Template parts cached
   - No database queries on page load

3. **Lazy Processing:**
   - Assets enqueued only for pages that need them
   - Template creation deferred until deployment
   - No processing on page load

### Scalability

**Large Sites:**
- Processes pages in batches (if needed)
- Template creation is efficient (single database write)
- Asset copying is file system operation (fast)

**Memory Usage:**
- DOMDocument parsing loads entire HTML in memory
- Large HTML files may require increased PHP memory limit
- Consider chunking for very large files

---

## Troubleshooting Guide

### Common Issues

**Images Not Displaying:**
- Check URL rewriting order (asset URLs must run first)
- Verify image files copied to plugin assets directory
- Check browser console for 404 errors
- Verify image URL in template HTML

**Templates Not Used:**
- Check if `page-{slug}.html` template exists
- Verify page `post_content` is empty (for custom templates)
- Check WordPress template hierarchy
- Verify template is registered in database

**CSS Not Loading:**
- Check assets copied to plugin directory
- Verify CSS files in post meta
- Check enqueuing hook fires
- Verify cache-busting version

**PHP Parse Errors:**
- Check functions.php syntax after merge
- Review backup file if available
- Validate PHP syntax manually
- Check ThemeDeployService merge logic

---

## Development Guidelines

### Adding New Features

1. **Service Layer:**
   - Create new service class in `includes/Services/`
   - Follow existing service patterns (static methods)
   - Add logging for debugging

2. **Admin Interface:**
   - Create admin page in `includes/Admin/`
   - Register in `Bootstrap.php`
   - Follow WordPress admin UI standards

3. **Testing:**
   - Add tests in `tests/` directory
   - Test edge cases
   - Validate error handling

### Code Standards

- **PHP 8.1+:** Use modern PHP features
- **Type Declarations:** Always declare parameter and return types
- **Error Handling:** Use exceptions for errors
- **Logging:** Log important operations
- **Documentation:** Document all public methods

---

## Summary

Vibe Code Deploy is a sophisticated plugin that converts static HTML sites into WordPress block templates. The architecture is modular, with clear separation between admin UI, business logic (services), and infrastructure (settings, logging).

**Key Takeaways:**
- **Service-Oriented:** Core logic in stateless service classes
- **Block-First:** All templates use Gutenberg block markup
- **Asset Management:** Assets copied to plugin directory, enqueued per-page
- **Smart Merging:** Theme files merged intelligently to preserve custom code
- **Rollback Support:** Full deployment history enables rollback

For more information, see:
- `README.md` - Plugin overview and usage
- `README-TESTING.md` - Testing guide
- `docs/` - Additional documentation
