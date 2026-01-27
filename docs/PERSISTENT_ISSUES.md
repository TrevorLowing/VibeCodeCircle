# Persistent Issues - Vibe Code Deploy Plugin

**Last Updated:** January 26, 2026  
**Current Plugin Version:** 0.1.95  
**Status:** Active Issues Document - Core Focus Areas

This document tracks the **primary persistent issues** encountered during development and deployment of the Vibe Code Deploy plugin. The focus is on the core functionality that directly impacts deployment success: CPT creation, ACF integration, map rendering, and HTML-to-EtchWP block conversion.

---

## Issue #1: Custom Post Type (CPT) Creation Upon Deploy

### Symptoms
- CPTs are not immediately available after deployment
- CPTs may appear briefly then disappear
- CPTs are not visible in WordPress admin menu
- Requires page refresh or plugin reactivation to see CPTs
- CPT registration function may not be executing correctly

### Root Causes Identified
1. **Functions.php Loading Timing**: WordPress loads `functions.php` on page load, but CPTs registered on `init` hook may not be immediately available
2. **Opcode Caching**: PHP opcode cache may serve cached version of `functions.php` before new code is loaded
3. **Registration Function Not Called**: CPT registration function may not be called immediately after file write
4. **Extraction Issues**: CPT registration code may not be properly extracted from staging files
5. **Merge Conflicts**: Smart merge logic may fail to properly merge CPT registration functions

### Technical Details

#### CPT Registration Pattern
CPTs are registered via named functions in `functions.php`:
```php
function bgp_register_post_types() {
    register_post_type('bgp_product', array(
        'public' => true,
        'show_ui' => true,
        // ... other args
    ));
}
add_action('init', 'bgp_register_post_types');
```

#### Extraction Process
- `ThemeDeployService::extract_cpt_registrations()` extracts CPT registration code from staging `theme/functions.php`
- Looks for named functions matching pattern: `{$project_slug}_register_post_types`
- Extracts function definition and associated `add_action('init', ...)` call

#### Merge Process
- `ThemeDeployService::merge_cpt_registrations()` merges extracted CPTs into existing theme `functions.php`
- Uses smart merge to replace existing CPT registrations or add new ones
- Removes conflicting `show_in_menu` and `menu_position` settings to prevent menu conflicts

### Attempted Fixes

#### v0.1.86
- Added logic in `deploy_theme_files()` to programmatically call CPT registration function directly
- Added `opcache_reset()` to clear PHP opcode cache after file write
- Direct function call: `$registration_function();` if `function_exists()`

#### v0.1.89
- Removed explicit `include $theme_file` call (was causing fatal "Cannot redeclare" errors)
- Plugin now relies on WordPress automatic loading of `functions.php`
- Added checks to prevent function redeclaration

#### v0.1.90
- Enhanced `register_post_type_args` filter to ensure CPTs have proper settings:
  - `show_ui = true` (required for menu visibility)
  - `publicly_queryable = true` (if not already set, for ACF compatibility)
  - `show_in_rest = true` (if not already set, for ACF compatibility)

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.95
- **Notes**: Opcode cache clearing may help, but WordPress automatic loading is primary mechanism. CPTs may still require page refresh to appear.

### Verification Steps
1. Deploy plugin and immediately check admin menu
2. Verify CPTs appear without page refresh
3. Check functions.php for CPT registration function
4. Verify `add_action('init', ...)` call is present
5. Check deployment logs for CPT extraction/merge messages
6. Verify no "flash" where CPTs appear then disappear

### Related Files
- `ThemeDeployService::extract_cpt_registrations()`
- `ThemeDeployService::merge_cpt_registrations()`
- `ThemeDeployService::deploy_theme_files()`

---

## Issue #2: ACF Integration - CPTs Not Appearing in ACF Settings

### Symptoms
- CPTs are registered and visible in WordPress admin menu
- CPTs do not appear in ACF Post Types settings dropdown
- Cannot select CPTs for ACF field group location rules
- Non-public CPTs (like FAQ with `public => false`) are especially problematic
- ACF field groups cannot be assigned to project CPTs

### Root Causes Identified
1. **ACF Detection Requirements**: ACF requires specific settings for CPT detection:
   - `show_ui => true` (required)
   - `public => true` OR `publicly_queryable => true` (preferred)
   - `show_in_rest => true` (for REST API compatibility)
2. **Non-Public CPTs**: CPTs with `public => false` are not automatically included in ACF's post type list
3. **Missing ACF Filter**: No explicit filter to include non-public CPTs in ACF settings
4. **Filter Timing**: ACF filter may not be executing at the right time or may not be receiving correct CPT slugs

### Technical Details

#### ACF Post Type Detection
ACF uses the `acf/get_post_types` filter to determine which post types appear in settings:
```php
add_filter('acf/get_post_types', function($post_types, $args) {
    // Add custom CPTs to the list
    $post_types[] = 'bgp_product';
    return $post_types;
}, 10, 2);
```

#### Current Implementation (v0.1.94)
- Added `{$project_slug}_acf_include_cpts()` function to explicitly include all project CPTs
- Filter registered on `acf/get_post_types` hook
- Ensures all CPTs (including non-public) appear in ACF Post Types settings

### Attempted Fixes

#### v0.1.90
- Enhanced `register_post_type_args` filter to ensure:
  - `show_ui = true`
  - `publicly_queryable = true` (if not already set)
  - `show_in_rest = true` (if not already set)

#### v0.1.94
- Added `acf/get_post_types` filter to explicitly include all project CPTs
- Filter function: `{$project_slug}_acf_include_cpts()`
- Ensures all CPTs (including non-public) appear in ACF Post Types settings
- Filter runs on `acf/get_post_types` hook with priority 10

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.94
- **Notes**: ACF filter added in v0.1.94, but issue may persist if:
  - Filter isn't executing (ACF plugin not active when filter runs)
  - Filter receives incorrect CPT slugs
  - ACF version compatibility issues

### Verification Steps
1. Deploy plugin and navigate to ACF → Field Groups
2. Create or edit a field group
3. Check Post Types location rule dropdown - all CPTs should be listed
4. Verify non-public CPTs (like FAQ) appear in the list
5. Check functions.php for `{$project_slug}_acf_include_cpts` function
6. Verify `acf/get_post_types` filter is registered
7. Test creating field group with CPT location rule
8. Verify ACF plugin is active when filter runs

### Related Files
- `ThemeDeployService::ensure_cpt_menu_structure()` (generates ACF filter function)
- Generated function: `{$project_slug}_acf_include_cpts()`

---

## Issue #3: Map Rendering - Leaflet.js Dependency Issues

### Symptoms
- Console error: `Leaflet.js is required for map functionality`
- Maps do not render on front-end
- `map.js` executes before Leaflet.js is available
- Map functionality fails silently or shows error messages

### Root Causes Identified
1. **CDN Script Extraction**: Plugin's `AssetService::extract_head_assets()` skips external URLs (CDN scripts like Leaflet.js)
2. **Dependency Order**: `map.js` depends on Leaflet.js but plugin enqueues scripts without proper dependencies
3. **Defer Attribute**: `map.js` has `defer` attribute, but Leaflet.js may not be loaded yet when it executes
4. **WordPress Dependency System**: Plugin doesn't set explicit dependencies between `map.js` and `leaflet-js` handle
5. **Script Loading Timing**: Leaflet.js must load synchronously (no defer) before `map.js` executes

### Technical Details

#### Current Map.js Implementation
`map.js` uses polling to wait for Leaflet.js:
```javascript
function waitForLeaflet() {
    if (typeof L !== 'undefined') {
        // Leaflet available, initialize map
        initializeMap();
    } else {
        // Poll for Leaflet availability
        const checkLeaflet = setInterval(() => {
            if (typeof L !== 'undefined') {
                clearInterval(checkLeaflet);
                initializeMap();
            }
        }, 100);
        // Timeout after 5 seconds
        setTimeout(() => {
            clearInterval(checkLeaflet);
            console.error('Leaflet.js is required for map functionality');
        }, 5000);
    }
}
```

#### Leaflet.js Enqueuing
Leaflet.js must be enqueued in `functions.php`:
```php
// Enqueue Leaflet base CSS
wp_enqueue_style('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');

// Enqueue Leaflet JS (must load before map.js, no defer)
wp_enqueue_script('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), null, false);

// Enqueue MarkerCluster (depends on Leaflet)
wp_enqueue_script('leaflet-markercluster', 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js', array('leaflet-js'), null, false);
```

#### Plugin Asset Extraction
- `AssetService::extract_head_assets()` extracts scripts from `<head>` section
- **Problem**: Skips external URLs (CDN scripts) - only extracts local files
- Leaflet.js CDN link is not automatically enqueued during deployment

### Attempted Fixes

#### Manual Fix (in functions.php)
- Added manual enqueuing of Leaflet.js in `functions.php`
- Set `map.js` dependency on `leaflet-js` handle
- Added filter to remove `defer` from `map.js` script tag

#### v0.1.78 (Partial Fix)
- Modified `map.js` to use `waitForLeaflet()` polling mechanism
- Enqueued `leaflet-js` in `<head>` via plugin
- Increased priority of `bgp_remove_defer_from_map_js` filter

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.78
- **Notes**: 
  - Manual enqueuing in `functions.php` works but requires manual setup
  - Plugin should automatically detect and enqueue CDN scripts
  - `waitForLeaflet()` polling is a workaround, not a solution

### Verification Steps
1. Deploy plugin and check page source for Leaflet.js script tag
2. Verify Leaflet.js loads before `map.js`
3. Check console for "Leaflet.js is required" errors
4. Test map rendering on front-end
5. Verify `map.js` has `leaflet-js` as dependency
6. Check that `defer` is removed from `map.js` script tag

### Recommended Solution
1. **Enhance Asset Extraction**: Modify `AssetService::extract_head_assets()` to detect and enqueue CDN scripts
2. **Automatic Dependency Detection**: Parse script dependencies from HTML comments or data attributes
3. **Leaflet.js Auto-Enqueue**: Detect `map.js` and automatically enqueue Leaflet.js as dependency

### Related Files
- `AssetService::extract_head_assets()` (skips CDN scripts)
- `biogaspros/js/map.js` (contains `waitForLeaflet()` polling)
- `biogaspros/vibecode-deploy-staging/theme/functions.php` (manual Leaflet enqueuing)

---

## Issue #4: HTML-to-EtchWP Block Conversion - Lists and Shortcodes

### Symptoms
- Shortcode blocks appear as non-editable passthrough blocks in EtchWP IDE
- List blocks (`<ul>`, `<ol>`) appear as non-editable passthrough blocks
- Blocks show as "CORE/SHORTCODE" or "CORE/LIST" instead of being editable
- Blocks cannot be edited or modified in EtchWP editor
- Shortcodes show as text on front-end instead of executing

### Root Causes Identified

#### Shortcode Blocks
1. **Missing etchData Metadata**: Shortcode blocks created by `comment_to_shortcode_block()` don't include `etchData` metadata
2. **EtchWP Editability Requirement**: EtchWP requires `etchData` metadata in block attributes for blocks to be editable
3. **Block Structure**: Without proper `etchData`, EtchWP treats blocks as passthrough (non-editable HTML)

#### List Blocks
1. **etchData Structure**: List blocks may have incorrect `etchData` structure
2. **Block Type Mismatch**: Block type may not match EtchWP expectations
3. **Metadata Format**: `etchData` format may not match EtchWP requirements

#### Shortcode Execution
1. **Filter Extraction Missing**: `extract_helper_functions()` may not be capturing `add_filter()` calls
2. **Incomplete Function Blocks**: Only function definition extracted, not associated filter registrations
3. **Merge Logic**: Merge logic may not be capturing full function block including filter calls

### Technical Details

#### EtchWP Block Structure
EtchWP requires blocks to have `etchData` metadata:
```html
<!-- wp:shortcode {"metadata":{"name":"Shortcode","etchData":{"origin":"etch","block":{"type":"shortcode","tag":"shortcode"}}}} -->
[bgp_products type="system"]
<!-- /wp:shortcode -->
```

#### Current Shortcode Block Generation (v0.1.94)
```php
// ShortcodePlaceholderService::comment_to_shortcode_block()
$shortcode_attrs = array(
    'metadata' => array(
        'name' => 'Shortcode',
        'etchData' => array(
            'origin' => 'etch',
            'block' => array(
                'type' => 'shortcode',
                'tag' => 'shortcode',
            ),
        ),
    ),
);
return '<!-- wp:shortcode ' . wp_json_encode($shortcode_attrs) . ' -->' . "\n" .
    $shortcode . "\n" .
    '<!-- /wp:shortcode -->' . "\n";
```

#### List Block Generation
List blocks use `build_etch_data()` helper:
```php
// Importer::convert_element() for <ul>/<ol>
$list_attrs['metadata'] = array(
    'name' => 'List',
    'etchData' => self::build_etch_data($tag, $attrs),
);
```

#### Shortcode Execution Filters
Shortcode rendering requires filter functions:
```php
function bgp_ensure_shortcode_execution($content) {
    return do_shortcode($content);
}
add_filter('the_content', 'bgp_ensure_shortcode_execution', 11);
add_filter('render_block', 'bgp_ensure_shortcode_execution_content', 10, 2);
```

### Attempted Fixes

#### Shortcode Block Passthrough (v0.1.94)
- **Shortcode Blocks**: Added `etchData` metadata to `ShortcodePlaceholderService::comment_to_shortcode_block()`
  - Added `metadata.etchData` with `origin: 'etch'` and `block.type: 'shortcode'`
  - Uses `wp_json_encode()` to match `block_open()` format

#### List Block Structure (v0.1.94)
- **List Blocks**: Verified list blocks already have correct `etchData` structure in `Importer::convert_element()`
  - Uses `build_etch_data()` helper function
  - Structure appears correct

#### Shortcode Execution (v0.1.91-0.1.94)
- **v0.1.91**: Updated `extract_helper_functions()` to dynamically include project-specific prefixes
- **v0.1.92**: Enhanced extraction to parse and include `add_filter()` or `add_action()` calls
- **v0.1.93**: Refined extraction logic to capture ~20 lines after function definition
- **v0.1.94**: Added logging to track shortcode rendering filter extraction

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.94
- **Notes**: 
  - Shortcode blocks should now have `etchData` (needs verification)
  - List blocks already had correct structure (needs verification)
  - Shortcode execution filters may still not be extracted/merged correctly

### Verification Steps

#### Shortcode Block Editability
1. Deploy plugin and open page in EtchWP editor
2. Check shortcode blocks - should be editable (not passthrough)
3. Verify block markup includes `etchData` in metadata
4. Test editing shortcode blocks in EtchWP IDE

#### List Block Editability
1. Deploy plugin and open page in EtchWP editor
2. Check list blocks - should be editable (not passthrough)
3. Verify block markup includes `etchData` in metadata
4. Test editing list blocks in EtchWP IDE

#### Shortcode Execution
1. Deploy plugin and check functions.php for `bgp_ensure_shortcode_execution` function
2. Verify `add_filter()` calls are present for shortcode functions
3. Test shortcodes on front-end - should execute, not show as text
4. Check deployment logs for extraction/merge messages

### Related Files
- `ShortcodePlaceholderService::comment_to_shortcode_block()` (shortcode block generation)
- `Importer::convert_element()` (list block generation)
- `Importer::build_etch_data()` (etchData helper)
- `ThemeDeployService::extract_helper_functions()` (shortcode filter extraction)
- `ThemeDeployService::merge_helper_functions()` (shortcode filter merging)

---

## Summary of Current Issues

### Critical (Unresolved)
1. **CPT Creation** - CPTs not immediately available after deployment, may require refresh
2. **ACF Integration** - CPTs not appearing in ACF settings (filter added but may not be working)
3. **Map Rendering** - Leaflet.js dependency issues, requires manual enqueuing
4. **Block Conversion** - Shortcode and list blocks may still appear as passthrough in EtchWP

### Partially Resolved
1. **Shortcode Execution** - Filters may not be extracted/merged correctly (logging added)
2. **Block Editability** - `etchData` added but needs verification in EtchWP editor

---

## Recommended Next Steps

### Priority 1: CPT Creation & ACF Integration
1. **Debug CPT Registration**
   - Verify CPT registration function is being called
   - Check if opcode cache clearing is working
   - Test immediate CPT availability after deployment
   - Consider forcing CPT registration via direct function call

2. **ACF Filter Verification**
   - Test `acf/get_post_types` filter is executing
   - Verify filter receives correct CPT slugs
   - Check if ACF plugin is active when filter runs
   - Add logging to track filter execution

### Priority 2: Map Rendering
1. **Enhance Asset Extraction**
   - Modify `AssetService::extract_head_assets()` to detect CDN scripts
   - Automatically enqueue Leaflet.js when `map.js` is detected
   - Set proper dependencies between scripts

2. **Automatic Dependency Detection**
   - Parse script dependencies from HTML comments
   - Detect common CDN patterns (Leaflet, Google Maps, etc.)
   - Auto-enqueue dependencies before dependent scripts

### Priority 3: Block Conversion
1. **Verify Block Editability**
   - Test shortcode blocks in EtchWP editor
   - Test list blocks in EtchWP editor
   - Verify `etchData` structure matches EtchWP requirements
   - Check EtchWP version compatibility

2. **Shortcode Execution Debug**
   - Use added logging to verify filter extraction
   - Check deployment logs for extraction/merge messages
   - Verify `add_filter()` calls are present in functions.php
   - Test shortcode execution on front-end

---

## Related Documentation

- **Plugin Structural Rules**: `VibeCodeCircle/plugins/vibecode-deploy/docs/STRUCTURAL_RULES.md`
- **Project-Specific Rules**: 
  - `biogaspros/docs/STRUCTURAL_RULES.md`
  - `CFA/docs/STRUCTURAL_RULES.md`
- **Developer Guide**: `VibeCodeCircle/docs/DEVELOPER_GUIDE.md`
- **Deployment Guide**: `VibeCodeCircle/docs/DEPLOYMENT-GUIDE.md`

---

## Version History

- **v0.1.95** (Current): Rolled back menu nesting to `register_post_type_args` filter approach
- **v0.1.94**: Added shortcode `etchData`, ACF filter, logging, attempted admin_menu hook approach (failed)
- **v0.1.93**: Refined shortcode extraction and merge logic
- **v0.1.92**: Enhanced function block extraction to include filter/action calls
- **v0.1.91**: Added project-specific prefix detection to shortcode extraction
- **v0.1.90**: Split menu creation into parent + filter functions, added ACF compatibility settings
- **v0.1.89**: Removed explicit `include` to fix fatal errors
- **v0.1.86**: Added immediate CPT registration and opcode cache clearing
- **v0.1.85**: Removed hardcoded project-specific values (plugin agnosticism)
