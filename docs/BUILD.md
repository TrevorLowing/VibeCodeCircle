# Build Guide - Vibe Code Deploy

This guide explains how to build plugin and staging zip files for distribution.

## Standard Location

All distribution zip files are placed in the **`dist/`** directory at the repository root:

```
VibeCodeCircle/
└── dist/
    ├── vibecode-deploy.zip                    # Plugin install file (latest)
    ├── vibecode-deploy-0.1.1.zip             # Versioned plugin file
    └── vibecode-deploy-staging.zip           # Staging bundle
```

## Building Plugin Zip

The plugin zip contains the WordPress plugin code for installation.

### Quick Build

```bash
./scripts/build-plugin-zip.sh
```

### Output

- **Files:** 
  - `dist/vibecode-deploy.zip` (standard name, overwritten on each build)
  - `dist/vibecode-deploy-{version}.zip` (versioned name, e.g., `vibecode-deploy-0.1.1.zip`)
- **Contents:** `plugins/vibecode-deploy/` directory
- **Excludes:** Test files, git files, documentation, build scripts
- **Version:** Automatically extracted from plugin header

### Manual Build

If you need to build manually:

```bash
cd /path/to/VibeCodeCircle
zip -r dist/vibecode-deploy.zip plugins/vibecode-deploy \
  -x "*.DS_Store" \
  -x "__MACOSX/*" \
  -x "*/__MACOSX/*" \
  -x "._*" \
  -x "*.git*" \
  -x "*/.git/*" \
  -x "*/tests/*" \
  -x "*/test-*.php" \
  -x "*/phpunit.xml.dist" \
  -x "*/README-TESTING.md" \
  -x "*/bin/install-wp-tests.sh" \
  -x "*.log"
```

## Building Staging Zip

Staging zips are typically built from the **CFA project** (or your site project), not from VibeCodeCircle.

### From CFA Project

1. Ensure `vibecode-deploy-staging/` directory exists in CFA root
2. Create zip from CFA root:

```bash
cd /path/to/CFA
zip -r vibecode-deploy-staging.zip vibecode-deploy-staging \
  -x "*.DS_Store" "__MACOSX/*" "*/__MACOSX/*" "._*"
```

3. Copy to VibeCodeCircle dist (optional, for consistency):

```bash
cp vibecode-deploy-staging.zip /path/to/VibeCodeCircle/dist/
```

### From VibeCodeCircle Testing Directory

If using the testing fixtures:

```bash
./testing/plugins/vibecode-deploy/scripts/build-staging-zip.sh
```

**Note:** This requires fixtures to be synced from CFA first using `sync-from-cfa.sh`.

## Building Everything

To build both plugin and staging zips at once:

```bash
./scripts/build-all.sh
```

This will:
1. Build plugin zip to `dist/vibecode-deploy.zip`
2. Build staging zip to `dist/vibecode-deploy-staging.zip` (if fixtures exist)

## Staging Zip Structure

Vibe Code Deploy supports two package formats:

### Format 1: Legacy Format (Still Supported)

```
vibecode-deploy-staging.zip
└── vibecode-deploy-staging/
    ├── pages/                    # Required: HTML page files
    │   ├── home.html            # Required: Home page
    │   └── ...
    ├── css/                      # Required: CSS files
    │   ├── styles.css
    │   └── ...
    ├── js/                       # Required: JavaScript files
    │   ├── main.js
    │   └── ...
    ├── resources/                # Optional: Images and assets
    │   └── ...
    ├── template-parts/           # Optional: Pre-extracted template parts
    │   ├── header.html
    │   └── footer.html
    ├── templates/                # Optional: Block templates
    │   └── ...
    ├── theme/                    # Optional: Theme files
    │   ├── functions.php
    │   └── acf-json/
    │       └── ...
    └── vibecode-deploy-shortcodes.json  # Optional: Shortcode rules
```

**⚠️ CRITICAL:** The zip must contain a top-level folder named **exactly** `vibecode-deploy-staging/`.

### Format 2: Simplified Format (Recommended)

```
{project-name}-deployment.zip
└── {project-name}-deployment/
    ├── manifest.json              # Package metadata and checksums
    ├── config.json                # Deployment settings
    ├── pages/                     # HTML pages
    ├── assets/                    # All assets in one place
    │   ├── css/
    │   ├── js/
    │   └── images/
    └── theme/                     # Theme files
        ├── functions.php
        └── acf-json/
```

**Benefits:**
- Simpler structure (assets consolidated)
- Built-in metadata (manifest.json)
- Configuration file (config.json)
- Easier to understand and maintain

**Build Script:** Use the downloadable starter pack (Vibe Code Deploy → Starter Pack) to get build scripts that generate this format automatically.

## Cleanup

### Remove Old Zip Files

```bash
# Remove all zip files from dist/
rm dist/*.zip

# Or remove specific files
rm dist/vibecode-deploy.zip
rm dist/vibecode-deploy-staging.zip
```

### Clean Build Artifacts

The build scripts automatically clean up temporary staging directories after zipping.

## Git Configuration

- Zip files in `dist/` are **ignored** by git (see `.gitignore`)
- The `dist/` directory structure is preserved via `dist/.gitkeep`
- Always commit updated build scripts and documentation
- Never commit zip files

## Troubleshooting

### "Plugin directory not found"
- Ensure you're running the script from the VibeCodeCircle repository root
- Verify `plugins/vibecode-deploy/` exists

### "Fixtures not found" (staging zip)
- Run `testing/plugins/vibecode-deploy/scripts/sync-from-cfa.sh` first
- Or build staging zip from the CFA project instead

### "Permission denied"
- Make scripts executable: `chmod +x scripts/*.sh`
- Ensure you have write permissions to `dist/` directory

### Zip file too large
- Check for unnecessary files in staging directory
- Verify `.gitignore` patterns are working
- Remove old/unused assets

## Best Practices

1. **Always build from clean state:** Remove old zips before building
2. **Test zips before distribution:** Verify structure and contents
3. **Version your builds:** Consider adding version numbers to filenames for releases
4. **Document changes:** Update this guide when build process changes
5. **Keep dist/ clean:** Remove old zip files regularly
