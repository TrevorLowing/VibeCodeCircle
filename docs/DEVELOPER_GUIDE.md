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
- **Importer**: Converts HTML to Gutenberg blocks with semantic block conversion (paragraphs, lists, images, etc.)
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
- **Importer**: HTML to semantic Gutenberg block conversion and asset enqueueing (v0.1.57+ converts paragraphs, lists, images, etc. to editable blocks)
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

**WordPress Coding Standards**: Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/). See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#wordpress-coding-standards) for detailed requirements.

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

**Security Best Practices**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#security-best-practices) for comprehensive security guidelines.

### Error Handling

- Use `Logger::error()` for errors
- Use `Logger::info()` for informational messages
- Return arrays with 'ok' key for operation results
- Use exceptions only for critical failures

**Error Handling Patterns**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#error-handling) for WordPress-specific error handling.

## Plugin Lifecycle

### Activation Hook

The plugin uses `register_activation_hook()` to set default options and initialize settings when activated.

**Implementation**: See `vibecode-deploy.php` for activation hook registration.

**Best Practices**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#activation-hook) for activation hook guidelines.

### Deactivation Hook

The plugin uses `register_deactivation_hook()` to clean up scheduled events and flush rewrite rules when deactivated.

**Implementation**: See `vibecode-deploy.php` for deactivation hook registration.

**Best Practices**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#deactivation-hook) for deactivation hook guidelines.

### Uninstall Hook

The plugin includes `uninstall.php` to clean up options, transients, and scheduled tasks when uninstalled.

**Implementation**: See `uninstall.php` in plugin root directory.

**Best Practices**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#uninstall-hook) for uninstall hook guidelines.

## Internationalization (i18n)

### Text Domain

The plugin uses the text domain `vibecode-deploy` for all translatable strings.

### Translation Functions

Use WordPress translation functions:
- `__()` for simple strings
- `_e()` for echoed strings
- `esc_html__()` for escaped HTML output
- `esc_html_e()` for echoed escaped HTML output

**Best Practices**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#internationalization-i18n) for comprehensive i18n guidelines.

## Database Management

### Schema Versioning

The plugin tracks database schema versions using options to handle upgrades.

**Best Practices**: See [WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md#database-management) for database management guidelines.

## WordPress Plugin Best Practices

For comprehensive WordPress plugin development best practices, see:

**[WordPress Plugin Best Practices](WORDPRESS_PLUGIN_BEST_PRACTICES.md)**

This document covers:
- Plugin lifecycle hooks (activation, deactivation, uninstall)
- WordPress coding standards (PHP, HTML, CSS, JavaScript)
- Security best practices (sanitization, escaping, nonces, capabilities)
- Database management (schema versioning, upgrades)
- Internationalization (i18n) requirements
- Plugin headers and readme.txt format
- Hooks and filters
- Admin UI standards
- Performance optimization
- Error handling
- Testing strategies

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

## Etch Theme Compatibility

### Official Repository

The plugin is designed to work with the official **Etch theme** and child themes:

- **Official Etch Child Theme Repository**: https://github.com/Digital-Gravy/etch-child-theme
- **Etch Theme Documentation**: https://etchwp.com/

### Monitoring for Compatibility

**IMPORTANT**: Developers should monitor the official Etch theme repository to ensure generated child themes remain compatible with official updates.

**Monitoring Process:**

1. **Watch the Repository**: Star/watch the [official Etch child theme repository](https://github.com/Digital-Gravy/etch-child-theme) on GitHub to receive notifications of updates
2. **Check Releases**: Regularly check for new releases or tags that may introduce breaking changes
3. **Review Changes**: When updates are released, review:
   - Changes to `style.css` header structure
   - Changes to required theme files (`index.php`, `functions.php`, etc.)
   - Changes to template structure or naming conventions
   - Changes to theme hooks or filters
4. **Test Compatibility**: After official updates:
   - Test theme file deployment with updated Etch theme
   - Verify smart merge still works correctly
   - Check that generated child themes function properly
   - Test CPT and shortcode functionality
5. **Update Plugin**: If breaking changes are detected:
   - Update `ThemeDeployService` to handle new requirements
   - Update `ThemeSetupService` if basic theme structure changes
   - Update documentation with compatibility notes
   - Test thoroughly before releasing

**Compatibility Checklist:**

- [ ] Child theme `style.css` header matches official structure
- [ ] Required theme files (`index.php`, `functions.php`) are compatible
- [ ] Template structure aligns with official Etch theme
- [ ] Theme hooks and filters work correctly
- [ ] Smart merge preserves compatibility with parent theme
- [ ] CPT and shortcode registrations don't conflict with parent theme

**Smart Merge Compatibility:**

The `ThemeDeployService` uses smart merge to preserve existing code while updating CPTs/shortcodes. This approach:
- Preserves custom theme code that doesn't conflict with parent theme
- Updates only CPT registrations and shortcode handlers
- Maintains compatibility with parent theme updates
- Allows child themes to extend parent functionality safely

## Resources

- **WordPress Plugin Handbook**: https://developer.wordpress.org/plugins/
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **Gutenberg Block Editor**: https://developer.wordpress.org/block-editor/
- **Official Etch Child Theme Repository**: https://github.com/Digital-Gravy/etch-child-theme
- **Etch Theme Documentation**: https://etchwp.com/