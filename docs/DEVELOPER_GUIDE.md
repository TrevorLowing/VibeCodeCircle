# Vibe Code Deploy - Developer Guide

## Overview

This guide is for developers who want to extend, modify, or contribute to the Vibe Code Deploy plugin.

## Architecture

### Service Layer

The plugin uses a service-oriented architecture with static service classes:

- **AssetService**: Handles CSS/JS asset copying and URL rewriting
- **BuildService**: Manages build fingerprints and staging directories
- **CleanupService**: Handles cleanup operations (uploads, templates, pages)
- **DeployService**: Core deployment logic (preflight, page creation, templates)
- **EnvService**: Environment validation (WordPress version, theme support, etc.)
- **HtmlToEtchConverter**: Converts HTML to Gutenberg blocks (optional Etch mode)
- **ManifestService**: Manages deployment manifests for rollback
- **RollbackService**: Handles rollback operations
- **RulesPackService**: Generates rules pack for distribution
- **ShortcodePlaceholderService**: Handles shortcode placeholder conversion
- **TemplateService**: Manages WordPress template parts and templates
- **ThemeSetupService**: Auto-configures theme files (optional)

### Admin Pages

Admin pages are in `includes/Admin/`:

- **SettingsPage**: Plugin configuration
- **ImportPage**: Build upload and deployment interface
- **BuildsPage**: List and manage builds
- **LogsPage**: View deployment logs
- **RulesPackPage**: Export rules pack
- **TemplatesPage**: Manage template parts and templates
- **HelpPage**: Help and documentation

### Core Classes

- **Bootstrap**: Plugin initialization and service loading
- **Importer**: HTML to block conversion and asset enqueueing
- **Logger**: Logging functionality
- **Settings**: Settings management
- **Staging**: Staging ZIP handling
- **Cli**: WP-CLI command registration

## Hooks & Filters

### Actions

- `vibecode_deploy_before_deploy` - Fired before deployment starts
- `vibecode_deploy_after_deploy` - Fired after deployment completes
- `vibecode_deploy_before_page_create` - Fired before creating a page
- `vibecode_deploy_after_page_create` - Fired after creating a page

### Filters

- `vibecode_deploy_html_content` - Filter HTML content before conversion
- `vibecode_deploy_page_slug` - Filter page slug before creation
- `vibecode_deploy_asset_url` - Filter asset URLs during rewriting
- `vibecode_deploy_placeholder_prefix` - Filter placeholder prefix (deprecated, use settings)

## Extending the Plugin

### Adding a New Service

1. Create a new class in `includes/Services/YourService.php`:

```php
<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Your service description.
 *
 * @package VibeCode\Deploy\Services
 */
final class YourService {
    /**
     * Your method description.
     *
     * @param string $param Parameter description.
     * @return array Result description.
     */
    public static function your_method( string $param ): array {
        // Implementation
    }
}
```

2. Require it in `Bootstrap.php`:

```php
require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/YourService.php';
```

### Adding a New Admin Page

1. Create a new class in `includes/Admin/YourPage.php`:

```php
<?php

namespace VibeCode\Deploy\Admin;

defined( 'ABSPATH' ) || exit;

final class YourPage {
    public static function init(): void {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
    }

    public static function register_menu(): void {
        add_submenu_page(
            'vibecode-deploy',
            'Your Page',
            'Your Page',
            'manage_options',
            'vibecode-deploy-your-page',
            array( __CLASS__, 'render' )
        );
    }

    public static function render(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Render your page
    }
}
```

2. Require and register it in `Bootstrap.php`:

```php
require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/YourPage.php';
// In register():
\VibeCode\Deploy\Admin\YourPage::init();
```

## Code Standards

### PHP Standards

- **PHP Version**: 8.0+
- **Type Declarations**: Use type hints for all parameters and return types
- **Visibility**: Use `public static` for service methods
- **Final Classes**: All service classes should be `final`
- **Namespace**: `VibeCode\Deploy` for core, `VibeCode\Deploy\Services` for services, `VibeCode\Deploy\Admin` for admin

### Documentation

- All public methods must have PHPDoc blocks
- Include parameter descriptions and return types
- Document exceptions that may be thrown

### Security

- Always check `ABSPATH` at the top of files
- Use `current_user_can( 'manage_options' )` for admin pages
- Use `check_admin_referer()` for form submissions
- Sanitize all input with appropriate WordPress functions
- Escape all output with `esc_html()`, `esc_attr()`, `esc_url()`

### Error Handling

- Use `Logger::error()` for errors
- Use `Logger::info()` for informational messages
- Return arrays with 'ok' key for operation results
- Use exceptions only for critical failures

## Testing

### Unit Testing

Create tests in `tests/` directory:

```php
<?php

namespace VibeCode\Deploy\Tests;

use VibeCode\Deploy\Services\YourService;
use WP_UnitTestCase;

class YourServiceTest extends WP_UnitTestCase {
    public function test_your_method(): void {
        $result = YourService::your_method( 'test' );
        $this->assertIsArray( $result );
        $this->assertTrue( isset( $result['ok'] ) );
    }
}
```

### Integration Testing

Test full deployment workflow:

1. Create test staging ZIP
2. Upload via ImportPage
3. Run preflight
4. Deploy
5. Verify pages created
6. Verify assets copied
7. Verify templates extracted

## Debugging

### Enable Debug Mode

Add to `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

### View Logs

1. Go to **Vibe Code Deploy → Logs**
2. Filter by project slug, date, or level
3. Export logs if needed

### Common Issues

- **Services not loading**: Check `Bootstrap.php` requires
- **Settings not saving**: Check `register_setting()` call
- **Assets 404**: Check `AssetService::copy_assets()` output
- **Templates not created**: Check `TemplateService::extract_header_footer()` output

## Contributing

### Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add/update tests
5. Update documentation
6. Submit pull request

### Code Review Checklist

- [ ] Code follows PHP standards
- [ ] All methods have PHPDoc
- [ ] Security checks in place
- [ ] Error handling implemented
- [ ] Logging added where appropriate
- [ ] Tests added/updated
- [ ] Documentation updated

## Resources

- **WordPress Plugin Handbook**: https://developer.wordpress.org/plugins/
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **Gutenberg Block Editor**: https://developer.wordpress.org/block-editor/
