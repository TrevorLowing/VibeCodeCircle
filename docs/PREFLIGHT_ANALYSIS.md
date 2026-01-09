# Preflight Deep Analysis Report

## Issue Summary
Preflight button does nothing - no feedback, no errors, no results displayed.

## Code Flow Analysis

### 1. Form Submission Flow

**Form Location:** `ImportPage.php` line 359
```php
echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vibecode-deploy-import' ) ) . '">';
wp_nonce_field( 'vibecode_deploy_preflight', 'vibecode_deploy_preflight_nonce' );
```

**Submit Button:** Line 370
```php
echo '<p><input type="submit" class="button" name="vibecode_deploy_preflight" value="' . esc_attr__( 'Run Preflight', 'vibecode-deploy' ) . '" /></p>';
```

**POST Processing:** Lines 103-148
- Checks for `isset( $_POST['vibecode_deploy_preflight'] )`
- Verifies nonce with `check_admin_referer()`
- Extracts fingerprint from POST
- Calls `Importer::preflight()`
- Sets `$preflight` variable

**Result Display:** Lines 355-450
- Checks `if ( is_array( $preflight ) )`
- Displays notices and results

### 2. Potential Issues Identified

#### Issue A: WordPress POST Data Persistence
**Problem:** WordPress admin pages may clear `$_POST` data after processing, or redirect to prevent duplicate submissions.

**Evidence:**
- No redirect is performed after POST processing
- `$preflight` variable is set during POST processing but needs to persist for display
- WordPress typically uses `admin-post.php` for form submissions that need to redirect

**Impact:** If WordPress clears `$_POST` or redirects, the `$preflight` variable would be lost.

#### Issue B: Nonce Verification Failure
**Problem:** `check_admin_referer()` will `wp_die()` if nonce is invalid, but this might be happening silently or the error might not be visible.

**Evidence:**
- Nonce field is generated with `wp_nonce_field( 'vibecode_deploy_preflight', 'vibecode_deploy_preflight_nonce' )`
- Verification uses `check_admin_referer( 'vibecode_deploy_preflight', 'vibecode_deploy_preflight_nonce' )`
- If nonce fails, WordPress calls `wp_die()` which should show an error page

**Impact:** If nonce fails, user would see an error page, not "nothing happens".

#### Issue C: Form Not Submitting
**Problem:** JavaScript error, form validation, or browser issue preventing form submission.

**Evidence:**
- No JavaScript validation visible in code
- Form uses standard HTML POST submission
- No `onsubmit` handlers that might prevent submission

**Impact:** If form doesn't submit, no POST data would be received.

#### Issue D: Preflight Method Returning Empty/Invalid Result
**Problem:** `Importer::preflight()` might be returning `null` or an invalid structure.

**Evidence:**
- Code checks `if ( ! is_array( $preflight ) )` but this might not catch all cases
- Display logic checks `if ( is_array( $preflight ) )` - if `$preflight` is `null`, nothing displays
- Added try-catch but exceptions might not be caught if they're PHP errors

**Impact:** If preflight returns `null` or invalid structure, nothing displays.

#### Issue E: Build Root Path Issue
**Problem:** `BuildService::build_root_path()` might be returning incorrect path.

**Evidence:**
- Path construction: `uploads/vibecode-deploy/staging/{project_slug}/{fingerprint}`
- New package format uses `{project-name}-deployment/` root
- Staging extraction might not match expected structure

**Impact:** If build root doesn't exist or is wrong, preflight fails silently.

### 3. Debugging Added

**Enhanced Logging:**
1. Log when POST is detected (line 48)
2. Log after nonce verification (line 108)
3. Log fingerprint extraction (line 111)
4. Log build_root calculation and existence (line 118)
5. Log preflight method return value (line 125)
6. Log completion status (line 147)

**Error Handling:**
1. Try-catch around preflight call
2. Validation that preflight is always an array
3. Warning message if preflight attempted but result is null

### 4. Recommended Fixes

#### Fix 1: Use admin-post.php Pattern (Recommended)
**Change:** Use WordPress `admin-post.php` pattern for form submission with redirect.

**Benefits:**
- Prevents duplicate submissions
- Ensures POST data is processed before redirect
- Standard WordPress pattern

**Implementation:**
```php
// Register handler
add_action( 'admin_post_vibecode_deploy_preflight', array( __CLASS__, 'handle_preflight' ) );

// Handler method
public static function handle_preflight(): void {
    // Process preflight
    // Store result in transient or session
    // Redirect back to page with result
}
```

#### Fix 2: Store Preflight Result in Transient
**Change:** Store preflight result in WordPress transient instead of relying on variable persistence.

**Benefits:**
- Survives redirects
- Can be retrieved on next page load
- Standard WordPress pattern

**Implementation:**
```php
// After preflight completes
set_transient( 'vibecode_deploy_preflight_' . get_current_user_id(), $preflight, 300 );

// On page load
$preflight = get_transient( 'vibecode_deploy_preflight_' . get_current_user_id() );
```

#### Fix 3: Add JavaScript Debugging
**Change:** Add JavaScript to log form submission and detect if form is actually submitting.

**Benefits:**
- Identifies if form submission is the issue
- Provides client-side debugging

**Implementation:**
```javascript
document.querySelector('form').addEventListener('submit', function(e) {
    console.log('Form submitting', e);
    console.log('Form data', new FormData(this));
});
```

#### Fix 4: Verify Build Root Structure
**Change:** Add validation to ensure build root exists and has correct structure.

**Benefits:**
- Identifies path issues early
- Provides clear error messages

**Implementation:**
```php
if ( ! is_dir( $build_root ) ) {
    $error = 'Build directory not found: ' . esc_html( $build_root );
    // Log detailed path information
}
```

### 5. Testing Steps

1. **Check Logs:** After clicking preflight, check `Vibe Code Deploy → Logs` for:
   - "Preflight POST detected" - confirms form submitted
   - "Preflight nonce verified" - confirms nonce passed
   - "Preflight fingerprint extracted" - confirms fingerprint received
   - "Preflight build_root calculated" - shows calculated path
   - "Preflight method returned" - shows what preflight returned

2. **Check Browser Console:** Look for JavaScript errors that might prevent form submission.

3. **Check Network Tab:** Verify POST request is being sent to correct URL.

4. **Check Build Root:** Manually verify the build root directory exists and contains `pages/` directory.

### 6. Most Likely Root Cause

Based on analysis, the most likely issue is **Issue E: Build Root Path Issue**.

**Reasoning:**
- Form submission appears to work (no errors from nonce)
- Preflight method might be failing silently if build root doesn't exist
- New package format (`CFA-deployment/`) might not match expected structure
- Staging extraction might not be creating the expected directory structure

**Next Steps:**
1. Check logs after clicking preflight
2. Verify build root directory exists
3. Verify `pages/` directory exists within build root
4. Check if staging extraction is working correctly for new package format

## Files Modified

- `plugins/vibecode-deploy/includes/Admin/ImportPage.php`
  - Added extensive logging throughout preflight process
  - Added try-catch error handling
  - Added validation for preflight result
  - Added warning message for null results

## Version

- Plugin Version: 0.1.1
- Analysis Date: 2026-01-03
