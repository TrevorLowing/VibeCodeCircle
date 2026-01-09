# Vibe Code Deploy - Deployment Guide

This guide provides step-by-step instructions for deploying static HTML sites to WordPress using Vibe Code Deploy.

## Prerequisites

1. **WordPress Site**: Running WordPress 6.0+ with PHP 8.0+
2. **Plugin Installed**: Vibe Code Deploy plugin activated
3. **Theme**: Etch theme or child theme (recommended)
4. **HTML Files**: Prepared with correct structure

## Step 1: Prepare Your HTML Files

### Required Structure
Each HTML page must follow this structure:

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/icons.css">
</head>
<body>
    <!-- Skip Link (Required) -->
    <a class="{project-prefix}-skip-link" href="#main">Skip to main content</a>
    
    <!-- Header (repeated elements) -->
    <header class="{project-prefix}-header" role="banner">
        <!-- Navigation, logo, top bar -->
    </header>
    
    <!-- Main Content (only this is imported) -->
    <main id="main" class="{project-prefix}-main" role="main">
        <!-- Page content -->
    </main>
    
    <!-- Footer -->
    <footer class="{project-prefix}-footer" role="contentinfo">
        <!-- Footer content -->
    </footer>
</body>
</html>
```

### Important Rules
1. **Skip Link**: Must be the first element in `<body>`
2. **Header**: Elements repeated on every page must be INSIDE `<header>`
3. **Main**: Only content inside `<main>` becomes page content
4. **CSS Classes**: Use BEM naming with project prefix (e.g., `my-site-*` or configure in plugin settings)

## Step 2: Create Staging ZIP

### Option A: Use Build Script
```bash
cd testing/plugins/vibecode-deploy
./scripts/build-staging-zip.sh
```

### Option B: Manual Creation
1. Create folder structure:
```
vibecode-deploy-staging/
├── pages/
│   ├── home.html
│   ├── about.html
│   └── ...
├── css/
│   ├── styles.css
│   └── icons.css
├── js/
│   └── main.js
└── resources/
    └── images/
```

2. ZIP the folder:
```bash
zip -r vibecode-deploy-staging.zip vibecode-deploy-staging/ \
  -x "*.DS_Store" "__MACOSX/*" "*/__MACOSX/*" "._*"
```

**Standard Location:** For consistency, copy the staging zip to `VibeCodeCircle/dist/`:
```bash
cp vibecode-deploy-staging.zip /path/to/VibeCodeCircle/dist/
```

**Note:** All distribution files (plugin and staging zips) should be placed in `VibeCodeCircle/dist/` directory. See [Build Guide](docs/BUILD.md) for complete build instructions.

## Step 3: Deploy to WordPress

### 1. Upload Staging ZIP
1. Go to **Vibe Code Deploy → Import Build**
2. Click **Choose File** and select your staging ZIP
3. Click **Upload Staging Zip**

### 2. Run Preflight
1. Select the build fingerprint from dropdown
2. Click **Run Preflight**
3. Review:
   - Pages to be created/updated
   - Warnings (if any)
   - Template parts to be extracted

### 3. Deploy
1. Configure options:
   - Set as front page (for home.html)
   - Extract header/footer from home.html
   - Generate 404 template
   - Force claim unowned pages
   - Validate CPT shortcodes
2. Click **Deploy**

## Step 4: Post-Deployment Tasks

### 1. Check Pages
- Visit each page to verify content
- Check for styling issues
- Verify navigation links work

### 2. Configure Theme
If not using Etch theme, manually configure:

#### Create Theme Files
```php
// index.php
<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <?php the_content(); ?>
<?php endwhile; endif; ?>
<?php get_footer(); ?>
```

```php
// page.php
<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <?php the_content(); ?>
<?php endwhile; ?>
<?php get_footer(); ?>
```

#### Enqueue Assets (functions.php)
```php
add_action('wp_enqueue_scripts', function() {
    if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/styles.css')) {
        wp_enqueue_style('vibecode-deploy-styles', plugins_url('assets/css/styles.css', 'vibecode-deploy'));
    }
    if (file_exists(WP_PLUGIN_DIR . '/vibecode-deploy/assets/css/icons.css')) {
        wp_enqueue_style('vibecode-deploy-icons', plugins_url('assets/css/icons.css', 'vibecode-deploy'));
    }
}, 20);
```

### 3. Set Front Page
1. Go to **Settings → Reading**
2. Select "A static page"
3. Choose "Home" as front page
4. Save changes

### 4. Check Templates
1. Go to **Appearance → Editor**
2. Verify Header and Footer template parts exist
3. Check they contain the correct content
4. **Automatic Template Assignment**: Custom page templates (`page-{slug}.php`) are automatically assigned during deployment
5. **Automatic CPT Templates**: Default `single-{post_type}.html` block templates are automatically created for all public CPTs

## Troubleshooting

### Preflight Shows Nothing
- **Cause**: Wrong ZIP or incorrect structure
- **Solution**: Verify you're uploading staging ZIP, not plugin ZIP
- **Check**: ZIP contains `vibecode-deploy-staging/` folder

### Assets 404 Error
- **Cause**: Assets not copied to plugin folder
- **Solution**: Re-run import
- **Manual**: Add enqueue code to theme's functions.php

### Header/Footer Missing
- **Cause**: Template parts not created
- **Solution**: Ensure "Extract header/footer" is checked
- **Check**: Appearance → Editor for template parts

### White Screen After Install
- **Cause**: PHP error or plugin conflict
- **Solution**: Delete plugin via FTP
- **Check**: PHP error logs

### Pages Not Styled
- **Cause**: CSS not enqueued
- **Solution**: Add enqueue code to theme
- **Check**: Browser dev tools for CSS loading

## Common Mistakes

1. **Wrong ZIP**: Uploading plugin ZIP instead of staging ZIP
2. **Incorrect Structure**: Missing `vibecode-deploy-staging/` folder
3. **Missing home.html**: Required for template extraction
4. **Wrong HTML Structure**: Skip link or main element missing
5. **Theme Issues**: Not using compatible theme

## Best Practices

1. **Test Locally**: Deploy to staging site first
2. **Backup**: Always backup before deployment
3. **Check Logs**: Review Vibe Code Deploy → Logs for errors
4. **Use Preflight**: Always run preflight before deployment
5. **Verify Links**: Check all internal links work after deployment
6. **Monitor Assets**: Ensure CSS/JS files load correctly

## CLI Deployment

For automated deployments:

```bash
# Upload and extract staging zip
wp vibecode-deploy upload-staging /path/to/staging.zip --project=mysite

# Run preflight
wp vibecode-deploy preflight --project=mysite --fingerprint=abc123

# Deploy
wp vibecode-deploy deploy --project=mysite --fingerprint=abc123 --set-front-page

# Rollback if needed
wp vibecode-deploy rollback --project=mysite --to=previous
```

## Support

- **Documentation**: See Help page in plugin admin
- **Issues**: GitHub repository
- **Logs**: Vibe Code Deploy → Logs page
