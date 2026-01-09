# Vibe Code Deploy

A WordPress plugin that converts static HTML websites into Gutenberg-based WordPress sites with automatic asset management and template extraction.

## Features

- **HTML to Gutenberg Conversion**: Convert static HTML pages to WordPress blocks
- **Asset Management**: Automatically copy and rewrite CSS, JS, and resource files
- **Template Extraction**: Extract header/footer from home.html into block template parts
- **Preflight Validation**: Check for issues before deployment
- **Rollback System**: Easy rollback to previous versions
- **CLI Support**: WP-CLI commands for automated deployments
- **Theme Auto-Configuration**: Automatically sets up theme files and settings

## Requirements

- WordPress 6.0+
- PHP 8.0+
- EtchWP plugin (recommended for full functionality)
- Etch theme or child theme (recommended for full functionality)

### Etch Theme Compatibility

This plugin is designed to work with the official **Etch theme** and child themes. The plugin ensures child themes remain compatible with official Etch theme updates.

- **Official Etch Child Theme Repository**: https://github.com/Digital-Gravy/etch-child-theme
- **Etch Theme Documentation**: https://etchwp.com/

When deploying theme files (functions.php, ACF JSON), the plugin uses smart merge to preserve existing code while updating CPT registrations and shortcodes, ensuring compatibility with the parent Etch theme.

**For Developers**: See the [Developer Guide](docs/DEVELOPER_GUIDE.md#etch-theme-compatibility) for detailed information on monitoring the official Etch theme repository for compatibility and maintaining compatibility with official updates.

## Installation

1. Download the **plugin ZIP file** from `dist/vibecode-deploy.zip`
2. Go to **Plugins → Add New** in WordPress
3. Click **Upload Plugin** and select the plugin ZIP
4. Activate the plugin

**Note:** See [Build Guide](docs/BUILD.md) for instructions on building the plugin zip file.

## Quick Start

### 1. Download Project Starter Pack

For new projects, download the starter pack with build scripts:

1. Go to **Vibe Code Deploy → Starter Pack**
2. Click **Download Starter Pack**
3. Extract the ZIP to your project's `scripts/` directory
4. Review and customize `.cursorrules.template` for your project

The starter pack includes:
- `build-deployment-package.sh` - Main build script
- `generate-manifest.php` - Manifest generator
- `generate-functions-php.php` - Functions.php generator
- `README.md` - Setup instructions
- `.cursorrules.template` - Project rules template

### 2. Prepare Your HTML Files

Organize your HTML files with the correct structure. See the **Staging Zip Structure** section below.

### 3. Create a Deployment Package

**Option A: Using Build Script (Recommended)**

```bash
# Run the build script from your project root
./scripts/build-deployment-package.sh
```

This creates a simplified deployment package with:
- `manifest.json` - Package metadata and checksums
- `config.json` - Deployment settings
- All pages, assets, and theme files organized

**Option B: Manual Build (Legacy Format)**

Staging zips can also be built manually. See [Build Guide](docs/BUILD.md) for detailed instructions.

```bash
# Build staging zip from CFA project
cd /path/to/CFA
zip -r vibecode-deploy-staging.zip vibecode-deploy-staging \
  -x "*.DS_Store" "__MACOSX/*" "*/__MACOSX/*" "._*"
```

### 4. Deploy

1. Go to **Vibe Code Deploy → Import Build**
2. Upload your **deployment package ZIP** (NOT the plugin ZIP)
3. Run preflight to review changes
4. Deploy when ready
5. Use **Health Check** page to verify deployment

## Deployment Package Structure

Vibe Code Deploy supports two package formats:

### Simplified Format (Recommended)

```
{project-name}-deployment.zip
└── {project-name}-deployment/
    ├── manifest.json              # Package metadata and checksums
    ├── config.json                # Deployment settings
    ├── pages/                     # HTML pages
    │   ├── home.html
    │   ├── about.html
    │   └── ...
    ├── assets/                    # All assets in one place
    │   ├── css/
    │   │   ├── styles.css
    │   │   └── icons.css
    │   ├── js/
    │   │   └── main.js
    │   └── images/
    │       └── ...
    └── theme/                     # Optional: Theme files
        ├── functions.php          # Smart merge with existing
        └── acf-json/              # ACF field group definitions
            └── group_*.json
```

**Benefits:**
- Simpler structure (assets consolidated)
- Built-in metadata (manifest.json)
- Configuration file (config.json)
- Easier to understand and maintain

**Build:** Use the starter pack build script to generate this format automatically.

### Legacy Format (Still Supported)

```
vibecode-deploy-staging.zip
└── vibecode-deploy-staging/
    ├── pages/
    │   ├── home.html              # Required
    │   ├── about.html
    │   └── services.html
    ├── css/
    │   ├── styles.css
    │   └── icons.css
    ├── js/
    │   └── main.js
    ├── resources/
    │   └── images/
    ├── theme/                     # Optional: Theme files
    │   ├── functions.php          # Smart merge with existing
    │   └── acf-json/              # ACF field group definitions
    │       └── group_*.json
    └── vibecode-deploy-shortcodes.json  # Optional: Shortcode rules
```

**⚠️ CRITICAL**: Do NOT upload the plugin ZIP as a deployment package. They are different files:
- **Plugin ZIP**: Contains the plugin code (install once)
- **Deployment Package**: Contains your HTML pages and assets (upload for deployment)

## HTML Structure Guidelines

For best results, follow these rules:

1. **Skip Link**: Must be first element in body
   ```html
   <a class="{project-prefix}-skip-link" href="#main">Skip to main content</a>
   ```

2. **Header**: Elements repeated on every page should be inside header
   ```html
   <header class="{project-prefix}-header" role="banner">
       <!-- Top bar, navigation, logo -->
   </header>
   ```

3. **Main Content**: Only this content is imported as page content
   ```html
   <main id="main" class="{project-prefix}-main" role="main">
       <!-- Page content -->
   </main>
   ```

4. **CSS Classes**: Use BEM naming convention with project prefix (e.g., `my-site-` or configure in plugin settings)

## Troubleshooting

### Preflight Shows Nothing
- Check that you're uploading a **staging ZIP**, not the plugin ZIP
- Verify the staging ZIP has the correct `vibecode-deploy-staging/` folder structure
- Check the Logs page for errors

### Assets Not Loading (404s)
- Re-run the import to copy assets to the plugin folder
- Manually enqueue assets in your theme's `functions.php`:
  ```php
  add_action('wp_enqueue_scripts', function() {
      if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/styles.css')) {
          wp_enqueue_style('vibecode-deploy-styles', plugins_url('assets/css/styles.css', 'vibecode-deploy'));
      }
  }, 20);
  ```

### Header/Footer Not Showing
- Ensure "Extract header/footer from home.html" is checked during import
- Check Appearance → Editor for template parts
- Verify your theme supports block templates

### Site Breaks After Plugin Install
- The plugin may conflict with your theme
- Delete the plugin via FTP or file manager
- Check if Etch theme is installed and active

## CLI Commands

```bash
# List builds
wp vibecode-deploy list-builds

# Deploy a build
wp vibecode-deploy deploy --project=mysite --fingerprint=abc123

# Rollback
wp vibecode-deploy rollback --project=mysite --to=previous

# Purge templates
wp vibecode-deploy purge --type=template-parts
```

## Documentation

### User Documentation
- **Deployment Guide**: See `DEPLOYMENT-GUIDE.md` for step-by-step instructions
- **Help Page**: See the **Help** page in the plugin admin area for:
  - System status checker
  - Detailed troubleshooting guide
  - Feature reference
  - Best practices

### Developer Documentation
- **Developer Guide**: See `docs/DEVELOPER_GUIDE.md` for extending the plugin
  - **Etch Theme Compatibility**: See [Monitoring for Compatibility](docs/DEVELOPER_GUIDE.md#monitoring-for-compatibility) section for maintaining compatibility with official Etch theme updates
- **WordPress Plugin Best Practices**: See `docs/WORDPRESS_PLUGIN_BEST_PRACTICES.md` for comprehensive WordPress plugin development guidelines
- **API Reference**: See `docs/API_REFERENCE.md` for service class documentation
- **Product Requirements**: See `PRD-VibeCodeDeploy.md` for complete requirements

## Support

- **GitHub Repository**: https://github.com/VibeCodeCircle/vibecode-deploy
- **Issues**: https://github.com/VibeCodeCircle/vibecode-deploy/issues
- **Documentation**: See Help page in plugin admin or `docs/` directory

## License

GPL v2.0

## Contributing

Pull requests are welcome. Please see the contributing guidelines for more information.
