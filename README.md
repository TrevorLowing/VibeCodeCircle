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

## Installation

1. Download the **plugin ZIP file** (`vibecode-deploy.zip`)
2. Go to **Plugins → Add New** in WordPress
3. Click **Upload Plugin** and select the plugin ZIP
4. Activate the plugin

## Quick Start

### 1. Prepare Your HTML Files

Organize your HTML files with the correct structure. See the **Staging Zip Structure** section below.

### 2. Create a Staging ZIP

Use the build script or manually create a ZIP with the required structure:
```bash
# From the testing directory
./scripts/build-staging-zip.sh
```

### 3. Deploy

1. Go to **Vibe Code Deploy → Import Build**
2. Upload your **staging ZIP** (NOT the plugin ZIP)
3. Run preflight to review changes
4. Deploy when ready

## Staging Zip Structure (IMPORTANT)

Your staging ZIP MUST follow this structure:

```
your-staging.zip
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
    └── rules.md                   # Optional
```

**⚠️ CRITICAL**: Do NOT upload the plugin ZIP as a staging ZIP. They are different files:
- **Plugin ZIP**: Contains the plugin code (install once)
- **Staging ZIP**: Contains your HTML pages and assets (upload for deployment)

## HTML Structure Guidelines

For best results, follow these rules:

1. **Skip Link**: Must be first element in body
   ```html
   <a class="cfa-skip-link" href="#main">Skip to main content</a>
   ```

2. **Header**: Elements repeated on every page should be inside header
   ```html
   <header class="cfa-header" role="banner">
       <!-- Top bar, navigation, logo -->
   </header>
   ```

3. **Main Content**: Only this content is imported as page content
   ```html
   <main id="main" class="cfa-main" role="main">
       <!-- Page content -->
   </main>
   ```

4. **CSS Classes**: Use BEM naming convention with project prefix (e.g., `cfa-`)

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

See the **Help** page in the plugin admin area for:
- System status checker
- Detailed troubleshooting guide
- Feature reference
- Best practices

## Support

- **GitHub Repository**: https://github.com/VibeCodeCircle/vibecode-deploy
- **Issues**: https://github.com/VibeCodeCircle/vibecode-deploy/issues
- **Documentation**: See Help page in plugin admin

## License

GPL v2.0

## Contributing

Pull requests are welcome. Please see the contributing guidelines for more information.
