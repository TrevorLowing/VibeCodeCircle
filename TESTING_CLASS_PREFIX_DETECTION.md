# Testing Class Prefix Auto-Detection

## Files Ready for Testing

Both distribution files have been built and are ready for testing:

### Plugin ZIP
- **Location:** `VibeCodeCircle/dist/vibecode-deploy.zip`
- **Size:** 116K
- **Version:** 0.1.1
- **Contains:** Updated plugin with automatic class prefix detection feature

### Staging ZIP
- **Location:** `VibeCodeCircle/dist/vibecode-deploy-staging.zip` (or `CFA/vibecode-deploy-staging.zip`)
- **Size:** 275K
- **Contains:** CFA staging files with `cfa-` prefixed classes

## Testing Steps

### 1. Install/Update Plugin

1. Go to WordPress Admin → Plugins
2. If plugin is already installed:
   - Deactivate the old version
   - Delete the old version
3. Install new version:
   - Click "Add New" → "Upload Plugin"
   - Upload `dist/vibecode-deploy.zip`
   - Activate the plugin

### 2. Clear Class Prefix Setting (To Test Auto-Detection)

1. Go to **Vibe Code Deploy → Settings**
2. **Clear the Class Prefix field** (leave it empty)
3. Click "Save Changes"

### 3. Upload Staging ZIP (Test Auto-Detection)

1. Go to **Vibe Code Deploy → Deploy**
2. In the "Upload Staging ZIP" section:
   - Click "Choose File"
   - Select `dist/vibecode-deploy-staging.zip`
   - Click "Upload ZIP"
3. **Expected Result:**
   - ZIP should upload successfully (no error about missing class prefix)
   - You should see a notice: **"Class prefix auto-detected: `cfa-`"**
   - The class prefix should now be set in Settings automatically

### 4. Verify Settings Updated

1. Go to **Vibe Code Deploy → Settings**
2. **Verify:** Class Prefix field should now show `cfa-`
3. This confirms auto-detection worked

### 5. Run Preflight (Optional)

1. Go to **Vibe Code Deploy → Deploy**
2. Select the uploaded fingerprint
3. Click "Run Preflight"
4. Verify preflight completes successfully

### 6. Deploy (Optional)

1. After preflight, click "Deploy"
2. Verify deployment completes
3. Check that templates use `cfa-` prefixed classes (not hardcoded)

## What to Look For

### ✅ Success Indicators

- **Upload succeeds** even when class prefix is empty
- **Notice appears:** "Class prefix auto-detected: `cfa-`"
- **Settings updated:** Class prefix field shows `cfa-` after upload
- **Templates use prefix:** Generated templates use `cfa-main`, `cfa-hero`, etc.

### ❌ Failure Indicators

- **Upload blocked:** Error about missing class prefix (should NOT happen)
- **No detection:** Warning that prefix could not be detected
- **Wrong prefix:** Detected prefix doesn't match `cfa-`
- **Templates wrong:** Templates still use hardcoded `cfa-` instead of configured prefix

## Testing Different Prefixes

To test with a different prefix:

1. Create test staging files with different prefix (e.g., `my-site-main`, `my-site-hero`)
2. Build staging ZIP
3. Clear class prefix in Settings
4. Upload test staging ZIP
5. Verify correct prefix is detected

## Manual Verification

You can also verify the detection logic works by checking:

1. **HTML files** in staging should contain classes like `class="cfa-main"`, `class="cfa-hero"`
2. **CSS files** in staging should contain selectors like `.cfa-main`, `.cfa-hero`
3. The detector scans both HTML and CSS files
4. The most common prefix found is used

## Troubleshooting

### Prefix Not Detected

- **Check staging files:** Ensure HTML/CSS files contain prefixed classes
- **Check common classes:** Detector looks for: `main`, `hero`, `header`, `footer`, `container`, `button`, `page-section`, `page-card`
- **Check format:** Prefix must be lowercase letters, numbers, and hyphens only

### Wrong Prefix Detected

- **Multiple prefixes:** If staging files have mixed prefixes, the most common one is used
- **Check files:** Review HTML/CSS files to see which prefix is most common

### Upload Still Blocked

- **Check plugin version:** Ensure you installed the latest version with auto-detection
- **Check error message:** Should not mention "Class Prefix is required" anymore
- **Check logs:** Go to Vibe Code Deploy → Logs to see detailed error messages

---

**Files Ready:**
- ✅ Plugin: `VibeCodeCircle/dist/vibecode-deploy.zip`
- ✅ Staging: `VibeCodeCircle/dist/vibecode-deploy-staging.zip`

**Ready to test!**
