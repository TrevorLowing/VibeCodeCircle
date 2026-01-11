# Testing Guide: Root Cause Analysis Fixes

**Date:** 2026-01-10  
**Plugin Version:** 0.1.45  
**Purpose:** Verify all 5 root cause fixes are working correctly

## Prerequisites

1. **WordPress Environment:** Local Docker or staging site
2. **Plugin Installed:** Vibe Code Deploy v0.1.45 or later
3. **Staging ZIP Ready:** CFA or BGP staging zip with custom templates
4. **Access to Logs:** WordPress admin → Vibe Code Deploy → Logs

## Testing Checklist

### Fix 1: Template Content Clearing ✅

**What to Test:** Pages with custom templates should have empty editor content

**Steps:**
1. Upload staging zip via **Vibe Code Deploy → Import**
2. Run preflight and deploy
3. Go to **Pages → All Pages** in WordPress admin
4. Open a page that should have a custom template (e.g., `secure-drop`, `investigations`)
5. Check the page editor - content should be **empty**
6. View the page on frontend - should show template content (with hero, etc.)

**Verify in Logs:**
```bash
# Check for these log entries:
- "Cleared page content for custom template."
- "Page configured to use block template."
- "Clearing page content for pages with custom templates."
```

**Expected Result:**
- ✅ Page editor is empty
- ✅ Frontend shows template content correctly
- ✅ Logs show content was cleared

**If Failed:**
- Check logs for template detection
- Verify template file exists in theme directory
- Check if template is registered in WordPress

---

### Fix 2: CSS Enqueuing System ✅

**What to Test:** Page-specific CSS (e.g., `secure-drop.css`) should load automatically

**Steps:**
1. Deploy a page with page-specific CSS (e.g., `secure-drop.html` with `css/secure-drop.css`)
2. Visit the page on frontend (e.g., `/secure-drop/`)
3. Open browser DevTools → Network tab
4. Filter by CSS files
5. Look for `secure-drop.css` in the network requests
6. Verify it loads with status 200
7. Check that body has class `cfa-secure-drop-page` (or equivalent)

**Verify in Logs:**
```bash
# Check for these log entries:
- "Extracted CSS assets from HTML head." (during import)
- "Stored CSS assets in post meta." (during import)
- "Enqueuing per-page CSS assets." (on page load)
- "Enqueued page-specific CSS file." (on page load)
- "Added body class from post meta." (on page load)
```

**Verify in Browser:**
1. View page source → Look for `<link rel="stylesheet" href="...secure-drop.css">`
2. Check `<body>` tag has correct class (e.g., `class="cfa-secure-drop-page"`)
3. Verify CSS styles are applying (dark theme for secure-drop)

**Expected Result:**
- ✅ CSS file loads in network tab
- ✅ Body class is applied
- ✅ Styles are visible on page
- ✅ Logs show CSS was extracted, stored, and enqueued

**If Failed:**
- Check logs for extraction/storage/enqueuing steps
- Verify CSS file exists in staging directory
- Check post meta: `_vibecode_deploy_assets_css` should contain `css/secure-drop.css`

---

### Fix 3: functions.php Validation ✅

**What to Test:** PHP syntax errors should be caught before writing files

**Steps:**
1. Create a staging `functions.php` with intentional syntax error (e.g., unmatched brace)
2. Upload staging zip
3. Deploy
4. Check logs for validation errors
5. Verify theme `functions.php` was **NOT** written (if syntax invalid)

**Verify in Logs:**
```bash
# Check for these log entries:
- "Starting functions.php smart merge."
- "Staging functions.php has syntax errors. Cannot merge." (if staging has errors)
- "Existing theme functions.php has syntax errors. Cannot merge safely." (if theme has errors)
- "PHP syntax error after [step] merge. File NOT written." (if merge creates errors)
```

**Test Cases:**
1. **Valid staging file:** Should merge successfully
2. **Invalid staging file:** Should reject and log error
3. **Invalid theme file:** Should reject and log error
4. **Merge creates error:** Should catch at each step and not write file

**Expected Result:**
- ✅ Invalid files are rejected
- ✅ Errors are logged with step information
- ✅ Theme functions.php is never corrupted
- ✅ Valid merges complete successfully

**If Failed:**
- Check logs for validation step that failed
- Verify `validate_php_syntax()` is working
- Check file permissions

---

### Fix 4: Class Preservation ✅

**What to Test:** CSS classes (especially `cfa-hero--compact`) should be preserved during conversion

**Steps:**
1. Deploy a page with `cfa-hero--compact` class in staging HTML
2. Check the deployed page template or content
3. Verify class is present in the HTML output
4. Check logs for class preservation

**Verify in Logs:**
```bash
# Check for these log entries:
- "Preserved CSS classes during conversion."
  - Should show: original_classes, final_classes, classes_preserved: true
```

**Verify in WordPress:**
1. Go to **Appearance → Templates** (or **Vibe Code Deploy → Templates**)
2. Open the page template (e.g., `page-secure-drop.html`)
3. Search for `cfa-hero--compact` - should be present
4. View page source on frontend - class should be in HTML

**Expected Result:**
- ✅ Classes are preserved in templates
- ✅ Classes appear in frontend HTML
- ✅ Logs show class preservation for important classes

**If Failed:**
- Check logs for class preservation entries
- Verify HTML conversion is using correct code path
- Check if classes are being stripped by WordPress/Gutenberg

---

### Fix 5: Template Verification ✅

**What to Test:** Templates should be verified and logged during deployment

**Steps:**
1. Deploy pages with custom templates
2. Check logs for template verification entries
3. Verify templates are registered in WordPress
4. Check for warnings if templates aren't queryable

**Verify in Logs:**
```bash
# Check for these log entries:
- "Page configured to use block template."
  - Should show: template_slug, has_registered, has_file, content_cleared, template_verified: true
- "Template registered but not immediately queryable. May need cache flush." (warning if needed)
- "Cleared page content for custom template."
```

**Verify in WordPress:**
1. Go to **Appearance → Templates** (or **Vibe Code Deploy → Templates**)
2. Verify templates exist (e.g., `page-secure-drop`, `page-investigations`)
3. Check template content has correct structure
4. Verify pages are using these templates (editor content should be empty)

**Expected Result:**
- ✅ Templates are created and registered
- ✅ Logs show template verification
- ✅ Pages use templates correctly
- ✅ Warnings appear if templates aren't queryable

**If Failed:**
- Check logs for template creation/registration
- Verify template files exist in theme directory
- Check WordPress template hierarchy
- Flush rewrite rules if needed

---

## Quick Test Script

Run this in WordPress admin or via WP-CLI to check all fixes:

```php
// Check Fix 1: Template content clearing
$pages_with_templates = get_pages(array(
    'meta_key' => '_wp_page_template',
    'meta_value' => 'page-secure-drop.html'
));
foreach ($pages_with_templates as $page) {
    echo "Page: {$page->post_name}, Content empty: " . (empty($page->post_content) ? 'YES' : 'NO') . "\n";
}

// Check Fix 2: CSS enqueuing
$secure_drop = get_page_by_path('secure-drop');
if ($secure_drop) {
    $css_assets = get_post_meta($secure_drop->ID, '_vibecode_deploy_assets_css', true);
    echo "CSS assets stored: " . (is_array($css_assets) ? implode(', ', $css_assets) : 'NONE') . "\n";
    $body_class = get_post_meta($secure_drop->ID, '_vibecode_deploy_body_class', true);
    echo "Body class stored: " . ($body_class ?: 'NONE') . "\n";
}

// Check Fix 3: functions.php validation
$theme_file = get_stylesheet_directory() . '/functions.php';
if (file_exists($theme_file)) {
    $output = array();
    exec("php -l " . escapeshellarg($theme_file) . " 2>&1", $output);
    echo "functions.php syntax: " . (strpos(implode(' ', $output), 'No syntax errors') !== false ? 'VALID' : 'INVALID') . "\n";
}

// Check Fix 4: Class preservation
$template = get_block_template('cfa-etch-child//page-secure-drop', 'wp_template');
if ($template) {
    $has_compact = strpos($template->content, 'hero--compact') !== false;
    echo "Template has hero--compact class: " . ($has_compact ? 'YES' : 'NO') . "\n";
}

// Check Fix 5: Template verification
$templates = get_block_templates(array('post_type' => 'page'), 'wp_template');
echo "Custom page templates found: " . count($templates) . "\n";
```

---

## Log Analysis

**View Logs:**
1. Go to **Vibe Code Deploy → Logs**
2. Filter by project slug (e.g., `cfa` or `bgp`)
3. Look for entries with these keys:
   - `template_verified`
   - `content_cleared`
   - `classes_preserved`
   - `enqueued_page_specific_css`
   - `php_syntax_error`

**Common Log Patterns:**

**Successful Deployment:**
```
[INFO] Starting functions.php smart merge.
[INFO] Page configured to use block template. {"template_verified":true}
[INFO] Cleared page content for custom template.
[INFO] Enqueued page-specific CSS file.
[INFO] Preserved CSS classes during conversion.
```

**Failed Deployment (Syntax Error):**
```
[ERROR] PHP syntax error after CPT merge. File NOT written.
[ERROR] Staging functions.php has syntax errors. Cannot merge.
```

---

## Manual Testing Steps

### Test 1: Deploy with Custom Templates
1. Upload staging zip
2. Run preflight
3. Deploy
4. Check logs for template verification
5. Visit pages on frontend
6. Verify templates are used (not default page.html)

### Test 2: Deploy with Page-Specific CSS
1. Deploy secure-drop page
2. Visit `/secure-drop/` on frontend
3. Check Network tab for `secure-drop.css`
4. Verify dark theme styles apply
5. Check body has `cfa-secure-drop-page` class

### Test 3: Deploy with Invalid functions.php
1. Create staging zip with syntax error in `functions.php`
2. Upload and deploy
3. Verify deployment fails gracefully
4. Check theme `functions.php` was NOT modified
5. Fix syntax error and redeploy

### Test 4: Verify Class Preservation
1. Deploy page with `cfa-hero--compact` class
2. Check template file has the class
3. View page source on frontend
4. Verify class is present in HTML
5. Check logs for class preservation entry

---

## Expected Outcomes

After all fixes are verified:

- ✅ **All pages (except home) use compact hero**
- ✅ **Secure drop has dark theme styling (CSS enqueued properly)**
- ✅ **Investigations page shows full content with hero (template used)**
- ✅ **No more functions.php parse errors**
- ✅ **Templates are reliably used by WordPress**
- ✅ **wp:html blocks remain editable in EtchWP**
- ✅ **CSS classes preserved during import and editing**

---

## Troubleshooting

**If templates aren't being used:**
- Check logs for "Page configured to use block template"
- Verify template files exist in theme directory
- Check if page content is empty
- Flush rewrite rules: **Vibe Code Deploy → Health Check → Flush Rewrite Rules**

**If CSS isn't loading:**
- Check logs for CSS extraction/storage/enqueuing
- Verify CSS file exists in staging directory
- Check post meta for `_vibecode_deploy_assets_css`
- Verify body class is applied

**If functions.php has errors:**
- Check logs for validation errors
- Verify staging file syntax is valid
- Check theme file syntax is valid
- Review merge step that failed

**If classes are lost:**
- Check logs for class preservation entries
- Verify HTML conversion code path
- Check if WordPress/Gutenberg is stripping classes
- Verify template uses `wp:html` blocks

---

## Next Steps

After testing:
1. Document any issues found
2. Review logs for patterns
3. Test on production-like environment
4. Verify all pages work correctly
5. Update documentation if needed
