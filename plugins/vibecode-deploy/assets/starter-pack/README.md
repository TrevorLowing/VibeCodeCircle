# Vibe Code Deploy Project Starter Pack

This starter pack contains build scripts and templates for creating deployment packages from your static HTML project.

## Contents

- `build-deployment-package.sh` - Main build script
- `generate-manifest.php` - Manifest generator
- `generate-functions-php.php` - Functions.php generator
- `README.md` - This file
- `.cursorrules.template` - Template for project rules
- `example-structure/` - Example project structure

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
