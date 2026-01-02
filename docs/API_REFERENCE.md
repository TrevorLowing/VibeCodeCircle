# Vibe Code Deploy - API Reference

## Service Classes

### ShortcodePlaceholderService

Handles shortcode placeholder comments in HTML.

#### Methods

**`get_placeholder_prefix(): string`**
- Returns the configured placeholder prefix (default: 'VIBECODE_SHORTCODE').

**`load_config( string $build_root ): array`**
- Loads shortcode configuration from `vibecode-deploy-shortcodes.json`.
- Returns configuration array or empty array if not found.

**`is_placeholder_comment( string $comment ): bool`**
- Checks if an HTML comment is a shortcode placeholder.

**`parse_placeholder_comment( string $comment ): array`**
- Parses a placeholder comment into shortcode name and attributes.
- Returns array with 'ok', 'name', 'attrs' keys.

**`comment_to_shortcode_block( string $comment, string $project_slug_for_logs = '' ): ?string`**
- Converts a placeholder comment to a Gutenberg shortcode block.
- Returns block HTML or null on error.

### DeployService

Core deployment service.

#### Methods

**`preflight( string $project_slug, string $fingerprint ): array`**
- Runs preflight validation before deployment.
- Returns array with 'pages_total', 'items', 'warnings', 'errors', etc.

**`deploy( string $project_slug, string $fingerprint, array $options ): array`**
- Deploys a build to WordPress.
- Options include: 'set_front_page', 'extract_header_footer', 'generate_404', etc.
- Returns array with 'pages_created', 'pages_updated', 'templates_created', etc.

### Settings

Plugin settings management.

#### Methods

**`defaults(): array`**
- Returns default settings array.

**`get_all(): array`**
- Returns all settings with defaults merged.

**`sanitize( $input ): array`**
- Sanitizes and validates settings input.

#### Settings Keys

- `project_slug`: Project identifier
- `class_prefix`: CSS class prefix (e.g., 'cfa-')
- `staging_dir`: Staging folder name
- `placeholder_prefix`: Shortcode placeholder prefix
- `env_errors_mode`: How to handle environment errors ('warn' or 'fail')
- `on_missing_required`: Mode for missing required placeholders
- `on_missing_recommended`: Mode for missing recommended placeholders
- `on_unknown_placeholder`: Mode for invalid placeholders

### AssetService

Asset management service.

#### Methods

**`extract_head_assets( \DOMDocument $dom ): array`**
- Extracts CSS and JS links from HTML head.
- Returns array with 'css' and 'js' keys.

**`copy_assets( string $build_root, string $project_slug ): array`**
- Copies assets from build to plugin directory.
- Returns array with 'copied', 'skipped', 'errors' keys.

### TemplateService

Template management service.

#### Methods

**`extract_header_footer( string $home_html_path, string $project_slug ): array`**
- Extracts header and footer from home.html.
- Returns array with 'header', 'footer', 'errors' keys.

**`create_template_part( string $slug, string $content, string $area, string $project_slug ): ?int`**
- Creates a WordPress template part.
- Returns post ID or null on error.

### EnvService

Environment validation service.

#### Methods

**`get_critical_errors(): array`**
- Returns array of critical environment errors.
- Checks WordPress version, theme support, etc.

**`validate_environment(): array`**
- Validates the WordPress environment.
- Returns array with 'supported', 'warnings', 'errors' keys.

## Hooks

### Actions

**`vibecode_deploy_before_deploy`**
- Fired before deployment starts.
- Parameters: `$project_slug`, `$fingerprint`

**`vibecode_deploy_after_deploy`**
- Fired after deployment completes.
- Parameters: `$project_slug`, `$fingerprint`, `$result`

**`vibecode_deploy_before_page_create`**
- Fired before creating a page.
- Parameters: `$slug`, `$content`, `$project_slug`

**`vibecode_deploy_after_page_create`**
- Fired after creating a page.
- Parameters: `$post_id`, `$slug`, `$project_slug`

### Filters

**`vibecode_deploy_html_content`**
- Filter HTML content before conversion.
- Parameters: `$html`, `$slug`, `$project_slug`
- Return: Filtered HTML string

**`vibecode_deploy_page_slug`**
- Filter page slug before creation.
- Parameters: `$slug`, `$project_slug`
- Return: Filtered slug string

**`vibecode_deploy_asset_url`**
- Filter asset URLs during rewriting.
- Parameters: `$url`, `$asset_type`, `$project_slug`
- Return: Filtered URL string

## Constants

- `VIBECODE_DEPLOY_PLUGIN_FILE`: Plugin main file path
- `VIBECODE_DEPLOY_PLUGIN_DIR`: Plugin directory path
- `VIBECODE_DEPLOY_PLUGIN_VERSION`: Plugin version

## Examples

### Using ShortcodePlaceholderService

```php
use VibeCode\Deploy\Services\ShortcodePlaceholderService;

$comment = '<!-- VIBECODE_SHORTCODE my_shortcode attr="value" -->';
$block = ShortcodePlaceholderService::comment_to_shortcode_block( $comment );
// Returns: '<!-- wp:shortcode -->[my_shortcode attr="value"]<!-- /wp:shortcode -->'
```

### Using DeployService

```php
use VibeCode\Deploy\Services\DeployService;

$preflight = DeployService::preflight( 'my-project', 'abc123' );
if ( empty( $preflight['errors'] ) ) {
    $result = DeployService::deploy( 'my-project', 'abc123', array(
        'set_front_page' => true,
        'extract_header_footer' => true,
    ) );
}
```

### Using Settings

```php
use VibeCode\Deploy\Settings;

$settings = Settings::get_all();
$prefix = $settings['placeholder_prefix']; // 'VIBECODE_SHORTCODE'
$project = $settings['project_slug']; // 'my-project'
```

### Hooking into Deployment

```php
add_action( 'vibecode_deploy_after_deploy', function( $project_slug, $fingerprint, $result ) {
    if ( ! empty( $result['pages_created'] ) ) {
        // Do something after pages are created
    }
}, 10, 3 );
```
