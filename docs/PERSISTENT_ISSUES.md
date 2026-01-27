# Persistent Issues - Vibe Code Deploy Plugin

**Last Updated:** January 26, 2026  
**Current Plugin Version:** 0.1.95  
**Status:** Active Issues Document

This document tracks all persistent issues encountered during development and deployment of the Vibe Code Deploy plugin. Issues are documented with their symptoms, attempted fixes, and current status.

---

## Issue #1: Shortcodes Showing as Text on Front-End

### Symptoms
- Shortcodes appear as literal text on the front-end instead of executing
- Example: `[bgp_products type="system"]` displays as text instead of rendering product list
- Shortcode rendering filter functions may not be extracted or merged correctly

### Root Causes Identified
1. **Filter Extraction Missing**: `extract_helper_functions()` was not capturing `add_filter()` calls that immediately follow function definitions
2. **Incomplete Function Blocks**: Only the function definition was extracted, not the associated filter/action registrations
3. **Merge Logic**: When replacing existing functions, the merge logic wasn't capturing the full function block including filter calls

### Attempted Fixes

#### v0.1.91
- Updated `extract_helper_functions()` to dynamically include project-specific prefixes (like `bgp_`)
- Enhanced extraction to parse and include `add_filter()` or `add_action()` calls that immediately follow function definitions

#### v0.1.92
- Refined merge logic in `merge_helper_functions()` to correctly replace the full function block including filter/action calls
- Added logic to find and replace entire blocks (function + associated filter/action calls)

#### v0.1.93
- Further refinement of extraction logic to capture ~20 lines after function definition for filter/action calls
- Improved pattern matching for filter/action detection

#### v0.1.94
- Added logging to track shortcode rendering filter extraction
- Logs when functions are extracted, replaced, or added
- Helps verify filters are properly merged into functions.php

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.94
- **Notes**: Logging added but issue may still persist. Requires verification after deployment.

### Verification Steps
1. Deploy plugin and check functions.php for `bgp_ensure_shortcode_execution` function
2. Verify `add_filter()` calls are present for shortcode functions
3. Test shortcodes on front-end - should execute, not show as text
4. Check deployment logs for extraction/merge messages

---

## Issue #2: Menu Nesting Not Working

### Symptoms
- Parent menu item (e.g., "BGP") appears in admin menu
- CPTs (Products, Case Studies, FAQs) appear as separate menu items on the same level
- **Expected**: CPTs should appear as submenu items under "BGP" when hovered
- **Actual**: All items appear at the same level
- Parent menu item sometimes disappears entirely after deployment

### Root Causes Identified
1. **Hook Timing Issue**: `init` hook runs BEFORE `admin_menu` hook, so parent menu doesn't exist when filter tries to set `show_in_menu`
2. **Filter Approach Flaw**: `register_post_type_args` filter runs during CPT registration (on `init`), but parent menu is created on `admin_menu` hook
3. **Global Array Modification**: Attempting to modify `$wp_post_types` global array on `admin_menu` hook may run too late or conflict with WordPress core menu building

### Attempted Fixes

#### v0.1.87 (Initial Attempt)
- Added logic to ensure `show_ui = true` and `show_in_menu = true` for prefixed CPTs after registration
- Introduced `ensure_cpt_menu_structure()` to generate menu functions
- Used `admin_menu` hook (priority 99) to set `show_in_menu` on `$wp_post_types` global

#### v0.1.89 (Fatal Error Fix)
- Removed explicit `include $theme_file` call that was causing "Cannot redeclare" errors
- Plugin now only calls `$registration_function()` if it already `function_exists()`

#### v0.1.90 (Filter Approach)
- Split menu creation into two generated functions:
  - `{$project_slug}_create_cpt_menu_parent()`: Creates parent menu on `admin_menu` hook (priority 5)
  - `{$project_slug}_create_cpt_menu()`: Registers `register_post_type_args` filter on `init` hook (priority 5)
- Updated `merge_cpt_registrations()` to remove `show_in_menu` and `menu_position` from staging CPTs
- Filter sets `show_in_menu` to parent menu slug during CPT registration

#### v0.1.94 (Admin Menu Hook Approach - FAILED)
- **Attempted**: Replace filter approach with `admin_menu` hook (priority 10) to modify CPTs AFTER parent menu exists
- **Result**: Parent menu disappeared entirely
- **Rolled Back**: v0.1.95 reverted to `register_post_type_args` filter approach

### Current Status
- **Status**: Unresolved
- **Current Approach**: `register_post_type_args` filter on `init` hook (priority 5)
- **Last Tested**: v0.1.95
- **Notes**: Filter approach has timing issues. Parent menu creation may not be working correctly.

### Verification Steps
1. Deploy plugin and check admin menu
2. Verify parent menu "BGP" appears in admin menu
3. Verify CPTs appear as submenus under "BGP" on hover (not on same level)
4. Check functions.php for menu creation functions
5. Verify `register_post_type_args` filter is registered before CPT registration

---

## Issue #3: CPTs Not Appearing in ACF Settings

### Symptoms
- CPTs are registered and visible in WordPress admin menu
- CPTs do not appear in ACF Post Types settings dropdown
- Cannot select CPTs for ACF field group location rules
- Non-public CPTs (like FAQ with `public => false`) are especially problematic

### Root Causes Identified
1. **ACF Detection Requirements**: ACF requires specific settings for CPT detection:
   - `show_ui => true` (required)
   - `public => true` OR `publicly_queryable => true` (preferred)
   - `show_in_rest => true` (for REST API compatibility)
2. **Non-Public CPTs**: CPTs with `public => false` are not automatically included in ACF's post type list
3. **Missing ACF Filter**: No explicit filter to include non-public CPTs in ACF settings

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

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.94
- **Notes**: ACF filter added in v0.1.94, but issue may persist if filter isn't executing or CPTs aren't being detected.

### Verification Steps
1. Deploy plugin and navigate to ACF → Field Groups
2. Create or edit a field group
3. Check Post Types location rule dropdown - all CPTs should be listed
4. Verify non-public CPTs (like FAQ) appear in the list
5. Check functions.php for `{$project_slug}_acf_include_cpts` function
6. Verify `acf/get_post_types` filter is registered

---

## Issue #4: Block Passthrough (CORE/SHORTCODE and CORE/LIST)

### Symptoms
- Shortcode blocks appear as non-editable passthrough blocks in EtchWP IDE
- List blocks appear as non-editable passthrough blocks
- Blocks show as "CORE/SHORTCODE" or "CORE/LIST" instead of being editable
- Blocks cannot be edited or modified in EtchWP editor

### Root Causes Identified
1. **Missing etchData Metadata**: Shortcode blocks created by `comment_to_shortcode_block()` don't include `etchData` metadata
2. **EtchWP Editability Requirement**: EtchWP requires `etchData` metadata in block attributes for blocks to be editable
3. **Block Structure**: Without proper `etchData`, EtchWP treats blocks as passthrough (non-editable HTML)

### Attempted Fixes

#### v0.1.94
- **Shortcode Blocks**: Added `etchData` metadata to `ShortcodePlaceholderService::comment_to_shortcode_block()`
  - Added `metadata.etchData` with `origin: 'etch'` and `block.type: 'shortcode'`
  - Uses `wp_json_encode()` to match `block_open()` format
- **List Blocks**: Verified list blocks already have correct `etchData` structure in `Importer::convert_element()`
  - Uses `build_etch_data()` helper function
  - Structure appears correct

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.94
- **Notes**: Shortcode blocks should now have `etchData`. List blocks already had correct structure. Requires verification in EtchWP editor.

### Verification Steps
1. Deploy plugin and open page in EtchWP editor
2. Check shortcode blocks - should be editable (not passthrough)
3. Check list blocks - should be editable (not passthrough)
4. Verify block markup includes `etchData` in metadata
5. Test editing blocks in EtchWP IDE

---

## Issue #5: CPT Registration Not Immediate

### Symptoms
- CPTs are not immediately available after deployment
- CPTs may appear briefly then disappear
- ACF cannot detect CPTs immediately after deployment
- Requires page refresh or plugin reactivation to see CPTs

### Root Causes Identified
1. **Functions.php Loading**: WordPress loads `functions.php` on page load, but CPTs registered on `init` hook may not be immediately available
2. **Opcode Caching**: PHP opcode cache may serve cached version of `functions.php` before new code is loaded
3. **Registration Timing**: CPT registration function may not be called immediately after file write

### Attempted Fixes

#### v0.1.86
- Added logic in `deploy_theme_files()` to programmatically include `functions.php` and call CPT registration function directly
- Added `opcache_reset()` to clear PHP opcode cache after file write
- Direct function call: `$registration_function();` if `function_exists()`

#### v0.1.89
- Removed explicit `include $theme_file` call (was causing fatal errors)
- Plugin now relies on WordPress automatic loading of `functions.php`

### Current Status
- **Status**: Partially Resolved
- **Last Tested**: v0.1.89
- **Notes**: Opcode cache clearing may help, but WordPress automatic loading is primary mechanism.

### Verification Steps
1. Deploy plugin and immediately check admin menu
2. Verify CPTs appear without page refresh
3. Check ACF settings - CPTs should be immediately available
4. Verify no "flash" where CPTs appear then disappear

---

## Issue #6: Fatal Error - Cannot Redeclare Functions

### Symptoms
- Fatal PHP error: `Cannot redeclare bgp_register_post_types()`
- Error occurs when `functions.php` is included multiple times
- Site breaks with white screen or error message

### Root Causes Identified
1. **Multiple Includes**: `functions.php` was being included explicitly in addition to WordPress automatic loading
2. **No Function Existence Check**: Functions were being redeclared without checking if they already exist
3. **Smart Merge Issues**: Merge logic may have duplicated function definitions

### Attempted Fixes

#### v0.1.88
- Removed explicit `include $theme_file` statement
- Plugin now only calls `$registration_function()` if it already `function_exists()`
- Relies on WordPress automatic loading of `functions.php`

#### v0.1.89
- Confirmed removal of explicit include
- Added checks to prevent redeclaration

### Current Status
- **Status**: Resolved
- **Last Tested**: v0.1.89
- **Notes**: Issue should be resolved by removing explicit include and relying on WordPress loading.

### Verification Steps
1. Deploy plugin multiple times
2. Verify no fatal errors occur
3. Check error logs for redeclaration errors
4. Verify functions.php is not included multiple times

---

## Issue #7: Plugin Agnosticism Violations

### Symptoms
- Hardcoded project-specific values (e.g., `bgp`, `cfa`) in plugin code
- Plugin logic assumes specific project structure
- Plugin not reusable across different projects

### Root Causes Identified
1. **Hardcoded Prefixes**: Project prefixes were hardcoded in verification logic
2. **Project-Specific Patterns**: Code assumed specific naming conventions
3. **Lack of Configuration**: No way to configure project-specific behavior

### Attempted Fixes

#### v0.1.85
- Removed all hardcoded project-specific values from `ThemeDeployService.php`
- Refactored verification logic to be generic pattern-based
- Updated documentation to emphasize plugin agnosticism
- Uses `$project_slug` from settings or config file

### Current Status
- **Status**: Resolved
- **Last Tested**: v0.1.85
- **Notes**: Plugin should now be fully agnostic. All project-specific logic uses `$project_slug` dynamically.

### Verification Steps
1. Deploy to multiple projects (BGP, CFA)
2. Verify plugin works for both without hardcoded values
3. Check code for any remaining hardcoded project prefixes
4. Verify `$project_slug` is used consistently

---

## Summary of Current Issues

### Critical (Unresolved)
1. **Menu Nesting** - CPTs not appearing as submenus under parent menu
2. **ACF Integration** - CPTs not appearing in ACF settings (filter added but may not be working)

### Moderate (Partially Resolved)
1. **Shortcode Rendering** - May still show as text (logging added for debugging)
2. **Block Passthrough** - Shortcode blocks should have `etchData` but needs verification
3. **CPT Registration Timing** - May require refresh to see CPTs

### Resolved
1. **Fatal Errors** - Redeclaration errors fixed
2. **Plugin Agnosticism** - Hardcoded values removed

---

## Recommended Next Steps

1. **Menu Nesting Investigation**
   - Debug why parent menu disappears or CPTs don't nest
   - Consider alternative approach: Set `show_in_menu` directly in CPT registration code (not via filter)
   - Verify parent menu function is being called correctly

2. **ACF Integration Verification**
   - Test `acf/get_post_types` filter is executing
   - Verify filter receives correct CPT slugs
   - Check if ACF plugin is active when filter runs

3. **Shortcode Rendering Debug**
   - Use added logging to verify filter extraction
   - Check deployment logs for extraction/merge messages
   - Verify `add_filter()` calls are present in functions.php

4. **Block Passthrough Testing**
   - Test in EtchWP editor to verify blocks are editable
   - Check block markup for `etchData` presence
   - Verify EtchWP version compatibility

5. **Comprehensive Testing**
   - Deploy to test site and verify all fixes
   - Test menu nesting, ACF integration, shortcode rendering, block editability
   - Document any remaining issues

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
