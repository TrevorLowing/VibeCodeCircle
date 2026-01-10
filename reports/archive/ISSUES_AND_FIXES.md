# Font Loading and Section Order Issues - Summary

## Issue 1: Fonts Not Loading ✅ FIXED (Requires Re-import)

**Status**: Code is correct, but pages need to be re-imported to populate font URLs in post meta.

**Root Cause**: Pages imported before the font extraction logic was added don't have font URLs stored in post meta.

**Solution**: **Re-import the staging zip** to populate font URLs for all pages.

**Verification**: After re-import, fonts should load at priority 1 (before WordPress core styles).

---

## Issue 2: "Have Evidence?" Section in Wrong Order ⚠️ NEEDS INVESTIGATION

**Status**: Template file has correct order, but rendered page shows wrong order.

**Template File Order** (CORRECT):
- Line 23: `cfa-page-section--hero-adjacent` (Active Investigations)
- Line 66: `cfa-page-section--charter-dark` (Have Evidence?)

**Rendered Page Order** (WRONG):
- Hero section
- "Have Evidence?" section (appears first)
- "Active Investigations" section (appears second)

**Possible Causes**:
1. **Page Editor Content Override**: If `post_content` is not empty, WordPress might be rendering page content instead of the template
2. **WordPress Block Reordering**: WordPress might be reordering blocks during rendering
3. **Template Not Being Used**: WordPress might not be using the custom template

**Next Steps**:
1. Check if investigations page has empty `post_content` (should be empty for custom templates)
2. Verify template is being used: `get_page_template_slug( $post_id )`
3. If template is correct but page renders wrong, investigate WordPress block rendering order

**Files to Check**:
- `DeployService.php` line 1050+ - Ensure `post_content` is cleared for pages with custom templates
- Template file: `wp-content/themes/cfa-etch-child/templates/page-investigations.html`
