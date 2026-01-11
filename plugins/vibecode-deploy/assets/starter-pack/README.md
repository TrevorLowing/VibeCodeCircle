# Vibe Code Deploy Project Starter Pack

This starter pack contains build scripts and templates for creating deployment packages from your static HTML project.

## Contents

- `build-deployment-package.sh` - Main build script
- `generate-manifest.php` - Manifest generator
- `generate-functions-php.php` - Functions.php generator
- `js/route-adapter.js` - **Standard route adapter (canonical version)**
- `README.md` - This file
- `.cursorrules.template` - Template for project rules
- `example-structure/` - Example project structure

## Standard Files

### `js/route-adapter.js`

**Canonical route adapter for all Vibe Code Deploy projects.**

This is the standard route adapter that handles URL conversion between:
- **Local development:** Converts extensionless links (e.g., `home`) to `.html` (e.g., `home.html`)
- **WordPress production:** Skips URLs ending with `/` (WordPress permalink format like `/home/`)
- **Production hosts:** Bypasses conversion entirely (uses extensionless URLs)

**Usage:**
1. Copy `js/route-adapter.js` to your project's `js/` directory
2. Update the `productionHosts` array with your production domain(s)
3. Include in all HTML pages: `<script src="js/route-adapter.js" defer></script>`

**Features:**
- ✅ Handles WordPress permalink format (`/page-slug/`)
- ✅ MutationObserver for dynamically added links (Gutenberg blocks)
- ✅ Popstate handler for browser navigation
- ✅ Production host detection to bypass conversion

**Example:**
```javascript
// Update this array with your production domain(s)
const productionHosts = [
    'yourdomain.com',
    'www.yourdomain.com'
];
```

**Why Use the Standard Version:**
- ✅ Consistent behavior across all projects
- ✅ Tested and proven to work with WordPress
- ✅ Automatically handles dynamic content (Gutenberg blocks)
- ✅ Updated in one place, benefits all projects

## Quick Start

1. Copy the build scripts to your project's `scripts/` directory
2. Review and customize `.cursorrules.template` for your project
3. Run `./scripts/build-deployment-package.sh` to create your deployment package
4. Upload the generated ZIP file via Vibe Code Deploy → Import Build

## Build Script Usage

```bash
./scripts/build-deployment-package.sh
```

This will:
- Create a simplified deployment package structure
- Generate `manifest.json` with package metadata
- Generate `config.json` with deployment settings
- Package everything as a ZIP file

## Project Structure

Your project should have this structure:

```
your-project/
├── *.html              # HTML pages
├── css/                 # CSS files
├── js/                   # JavaScript files
├── resources/            # Images and assets
├── scripts/              # Build scripts (from this pack)
└── wp-content/themes/your-theme/  # Theme files (if applicable)
    ├── functions.php
    └── acf-json/
```

## Deployment Package Structure

The build script creates:

```
{project-name}-deployment/
├── manifest.json        # Package metadata and checksums
├── config.json          # Deployment settings
├── pages/               # HTML pages
├── assets/               # All assets in one place
│   ├── css/
│   ├── js/
│   └── images/
└── theme/                # Theme files
    ├── functions.php
    └── acf-json/
```

## For More Information

See the Vibe Code Deploy plugin documentation for detailed deployment instructions.
