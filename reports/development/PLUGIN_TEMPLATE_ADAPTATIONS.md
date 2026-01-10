# Plugin Template Adaptations for Block Templates

**Date:** January 7, 2025  
**Purpose:** Document plugin adaptations to support block templates (Option A) instead of classic PHP templates

## Changes Made

### 1. DeployService.php - Auto-Template Assignment ✅

**File:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php`

**Change:** Updated auto-template assignment logic to check for block templates first, then fall back to PHP templates.

**Before:**
- Only checked for PHP templates (`page-{slug}.php`)
- Assigned via `_wp_page_template` meta field

**After:**
- First checks for block templates (`page-{slug}` slug) using `TemplateService::get_template_by_slug()`
- If block template exists, logs that it will be used (WordPress automatically uses it via template hierarchy)
- Falls back to PHP template check if no block template found
- Assigns PHP template via `_wp_page_template` meta field if found

**Code:**
```php
// Auto-assign custom page template if it exists
// Check for both block templates (templates/page-{slug}.html) and PHP templates (page-{slug}.php)
$theme_dir = get_stylesheet_directory();
$template_slug = 'page-' . $slug;

// First check for block template (preferred for EtchWP)
$block_template = TemplateService::get_template_by_slug( $template_slug );
if ( $block_template && isset( $block_template->ID ) ) {
    // Block templates are automatically used by WordPress template hierarchy
    // No need to set _wp_page_template meta - WordPress will use it automatically
    Logger::info( 'Page will use block template.', array( 'page_slug' => $slug, 'template_slug' => $template_slug ), $project_slug );
} else {
    // Fallback: Check for PHP template (classic template)
    $template_file = $template_slug . '.php';
    $template_path = $theme_dir . '/' . $template_file;
    
    if ( file_exists( $template_path ) ) {
        update_post_meta( $post_id, '_wp_page_template', $template_file );
        Logger::info( 'Page assigned PHP template.', array( 'page_slug' => $slug, 'template_file' => $template_file ), $project_slug );
    }
}
```

### 2. TemplateService.php - Public Method ✅

**File:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/TemplateService.php`

**Change:** Made `get_template_by_slug()` method public so it can be called from DeployService.

**Before:**
```php
private static function get_template_by_slug( string $slug ) {
```

**After:**
```php
public static function get_template_by_slug( string $slug ) {
```

**Reason:** DeployService needs to check for existing block templates during page deployment.

### 3. ThemeSetupService.php - Updated Comments ✅

**File:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/ThemeSetupService.php`

**Change:** Updated `index.php` and `page.php` content to include documentation about block template precedence and empty header/footer files.

**Before:**
- Basic comments about using block template system
- No explanation about empty header/footer files

**After:**
- Detailed comments explaining:
  - These files are fallbacks for WordPress theme requirements
  - Block templates in `templates/` directory take precedence
  - `get_header()` and `get_footer()` are called for compatibility
  - `header.php` and `footer.php` are intentionally empty for block theme compatibility
  - Header/footer are rendered via block template parts in block templates

**Code:**
```php
/**
 * Index Template (Fallback)
 *
 * This file is required for WordPress themes as a fallback.
 * For block themes (EtchWP), block templates in templates/ directory take precedence.
 * This file will only be used if no block template is found.
 *
 * Note: get_header() and get_footer() are called for compatibility,
 * but header.php and footer.php are intentionally empty for block theme compatibility.
 * Header/footer are rendered via block template parts in block templates.
 */
```

## Template Hierarchy Support

The plugin now supports both template systems:

### Block Templates (Preferred for EtchWP)
- **Location:** `templates/page-{slug}.html` (stored as `wp_template` posts in database)
- **Detection:** Uses `TemplateService::get_template_by_slug()` to check for existing block templates
- **Assignment:** Automatic via WordPress template hierarchy (no meta field needed)
- **Usage:** WordPress automatically uses block templates based on template hierarchy

### PHP Templates (Fallback)
- **Location:** `page-{slug}.php` (classic template files)
- **Detection:** Checks if file exists in theme directory
- **Assignment:** Uses `_wp_page_template` meta field
- **Usage:** Assigned explicitly via post meta

## Benefits

1. **EtchWP Compatibility:** Block templates are preferred and automatically detected
2. **Backward Compatibility:** Still supports classic PHP templates as fallback
3. **Automatic Assignment:** Block templates are used automatically by WordPress template hierarchy
4. **Clear Logging:** Plugin logs which template type is being used for each page
5. **Documentation:** Theme setup files now include clear comments about block template precedence

## Testing Checklist

- [ ] Verify block templates are detected during deployment
- [ ] Verify PHP templates are still assigned if no block template exists
- [ ] Verify logging shows correct template type for each page
- [ ] Verify WordPress uses block templates automatically via template hierarchy
- [ ] Verify classic PHP templates still work as fallback

---

**Status:** ✅ All plugin code adapted for block template support.
