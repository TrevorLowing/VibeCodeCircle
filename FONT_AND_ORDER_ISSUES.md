# Font Loading and Section Order Issues - Diagnostic Report

## Issue 1: Fonts Not Loading

### Root Cause
The `enqueue_fonts()` method is correctly implemented and registered at priority 1 (before WordPress core styles). However, **pages that were imported before the font extraction logic was added do not have font URLs stored in their post meta**.

### Solution
**Re-import the staging zip** to populate font URLs in post meta for all pages. The font extraction logic (`AssetService::extract_head_assets()`) now extracts Google Fonts from the HTML `<head>` and stores them in post meta (`_vibecode_deploy_assets_fonts`).

### Verification
After re-import, check that pages have font URLs in post meta:
```php
$fonts = get_post_meta( $post_id, '_vibecode_deploy_assets_fonts', true );
// Should return array of font URLs like:
// array( 'https://fonts.googleapis.com/css2?family=Merriweather:...' )
```

### Code Flow
1. **Extraction**: `DeployService::run_import()` → `AssetService::extract_head_assets()` extracts fonts from HTML `<head>`
2. **Storage**: Font URLs stored in post meta: `update_post_meta( $post_id, Importer::META_ASSET_FONTS, $font_assets )`
3. **Enqueuing**: `Bootstrap.php` registers `Importer::enqueue_fonts()` at `wp_enqueue_scripts` priority 1
4. **Loading**: Fonts load before WordPress core styles (priority 10) and resets CSS (priority 15)

---

## Issue 2: "Have Evidence?" Section in Wrong Order

### Root Cause
The source HTML (`investigations.html`) has the correct order:
1. Hero section (lines 69-83)
2. **Active Investigations section** (lines 85-106)
3. **Have Evidence? section** (lines 108-118)

But the deployed site shows:
1. Hero section
2. **Have Evidence? section** (WRONG - appears before Active Investigations)
3. **Active Investigations section** (WRONG - appears after Have Evidence?)

### Investigation
- The `convert_dom_children()` method in `Importer.php` iterates through `$parent->childNodes` in order (line 368), so order should be preserved during HTML-to-block conversion.
- The conversion happens during template creation in `DeployService::create_page_templates_from_pages()` (line 653).
- The converted blocks are inserted into the template at line 681: `$template_content .= $content_blocks;`

### Possible Causes
1. **WordPress Block Reordering**: WordPress might be reordering blocks during template storage or rendering
2. **Template File Corruption**: The template file itself might have the wrong order stored
3. **Block Group Nesting**: The way `wp:group` blocks are nested might affect rendering order

### Solution
1. **Check the actual template file** in WordPress to see if blocks are in the correct order
2. **Regenerate the template** by re-importing the staging zip
3. **If order is still wrong**, investigate WordPress's block rendering order or add explicit ordering attributes to blocks

### Next Steps
1. Inspect the actual template file: `wp-content/themes/cfa-etch-child/templates/page-investigations.html`
2. Compare block order in template vs. source HTML
3. If template has wrong order, investigate `HtmlToEtchConverter::convert()` or template storage logic
4. If template has correct order but rendered page is wrong, investigate WordPress block rendering

---

## Files Involved

### Font Loading
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` - `enqueue_fonts()` method (line 39)
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Bootstrap.php` - Font enqueue registration (priority 1)
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/AssetService.php` - Font extraction from HTML
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php` - Font storage in post meta (line 1040)

### Section Order
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` - `convert_dom_children()` method (line 365)
- `VibeCodeCircle/plugins/vibecode-deploy/includes/Services/DeployService.php` - Template creation (line 653)
- `CFA/investigations.html` - Source HTML with correct order
