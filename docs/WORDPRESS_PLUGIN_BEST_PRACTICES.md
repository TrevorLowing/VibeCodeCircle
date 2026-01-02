# WordPress Plugin Development Best Practices

This document outlines best practices for developing WordPress plugins, specifically for the Vibe Code Deploy plugin and any extensions or modifications.

## Table of Contents

1. [Plugin Lifecycle Hooks](#plugin-lifecycle-hooks)
2. [WordPress Coding Standards](#wordpress-coding-standards)
3. [Security Best Practices](#security-best-practices)
4. [Database Management](#database-management)
5. [Internationalization (i18n)](#internationalization-i18n)
6. [Plugin Headers](#plugin-headers)
7. [Readme.txt Format](#readmetxt-format)
8. [Hooks and Filters](#hooks-and-filters)
9. [Admin UI Standards](#admin-ui-standards)
10. [Performance](#performance)
11. [Error Handling](#error-handling)
12. [Testing](#testing)

## Plugin Lifecycle Hooks

### Activation Hook

**Purpose**: Run code when the plugin is activated.

**Implementation**:
```php
register_activation_hook( __FILE__, 'vibecode_deploy_activate' );

function vibecode_deploy_activate(): void {
    // Set default options (only if not already set)
    if ( ! get_option( 'vibecode_deploy_version' ) ) {
        add_option( 'vibecode_deploy_version', VIBECODE_DEPLOY_PLUGIN_VERSION );
    }
    
    // Set default settings
    if ( ! get_option( 'vibecode_deploy_settings' ) ) {
        $defaults = \VibeCode\Deploy\Settings::defaults();
        add_option( 'vibecode_deploy_settings', $defaults );
    }
    
    // Flush rewrite rules if needed
    flush_rewrite_rules();
    
    // Schedule cron jobs if needed
    if ( ! wp_next_scheduled( 'vibecode_deploy_cron' ) ) {
        wp_schedule_event( time(), 'hourly', 'vibecode_deploy_cron' );
    }
}
```

**Best Practices**:
- Always check if options exist before setting defaults
- Use `add_option()` for new options, `update_option()` for existing
- Flush rewrite rules after registering custom post types
- Schedule cron jobs only if needed
- Never delete user data on activation

### Deactivation Hook

**Purpose**: Run code when the plugin is deactivated.

**Implementation**:
```php
register_deactivation_hook( __FILE__, 'vibecode_deploy_deactivate' );

function vibecode_deploy_deactivate(): void {
    // Clear scheduled cron jobs
    wp_clear_scheduled_hook( 'vibecode_deploy_cron' );
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Clear transients (optional)
    delete_transient( 'vibecode_deploy_cache' );
}
```

**Best Practices**:
- Clear scheduled cron jobs
- Flush rewrite rules
- Clear transients (optional)
- **DO NOT** delete user data, options, or database tables
- **DO NOT** delete files

### Uninstall Hook

**Purpose**: Run code when the plugin is uninstalled.

**File**: `uninstall.php` (must be in plugin root directory)

**Implementation**:
```php
<?php
/**
 * Plugin Uninstall Handler
 *
 * Fired when the plugin is uninstalled via WordPress admin.
 * Cleans up options, transients, and scheduled tasks.
 *
 * @package    Vibe Code Deploy
 * @since      1.0.0
 */

// If uninstall not called from WordPress, exit immediately
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete plugin options
 */
delete_option( 'vibecode_deploy_version' );
delete_option( 'vibecode_deploy_settings' );

// Delete all plugin-specific options
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'vibecode_deploy_%'"
);

/**
 * Clear transients
 */
delete_transient( 'vibecode_deploy_cache' );

// Delete all plugin-specific transients
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_vibecode_deploy_%'"
);
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_vibecode_deploy_%'"
);

/**
 * Clear scheduled cron jobs
 */
wp_clear_scheduled_hook( 'vibecode_deploy_cron' );

/**
 * Note: We intentionally do NOT delete user-submitted data
 * (form submissions, posts, etc.) as that may be valuable business data
 * that should be preserved. Uncomment deletion code if needed.
 */
```

**Best Practices**:
- Always check `WP_UNINSTALL_PLUGIN` constant
- Delete plugin options and transients
- Clear scheduled cron jobs
- **DO NOT** delete user-submitted data by default (preserve business data)
- **DO NOT** delete files unless explicitly required
- Use `LIKE` queries to clean up all plugin-specific options/transients

## WordPress Coding Standards

### PHP Standards

**Reference**: [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

**Key Requirements**:
- **Indentation**: Use tabs (not spaces)
- **Brace Style**: Opening brace on same line
- **Naming**: Use `snake_case` for functions, `PascalCase` for classes
- **Type Declarations**: Use type hints for parameters and return types (PHP 7.0+)
- **Visibility**: Always declare visibility (`public`, `private`, `protected`)
- **Final Classes**: Use `final` for service classes that shouldn't be extended

**Example**:
```php
final class MyService {
    public static function do_something( string $param ): array {
        // Implementation
        return array();
    }
    
    private static function helper_method( int $id ): bool {
        // Implementation
        return true;
    }
}
```

### HTML Standards

**Reference**: [WordPress HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)

**Key Requirements**:
- Use semantic HTML5 elements
- Always close tags
- Use lowercase for attributes
- Quote all attribute values
- Use proper indentation

### CSS Standards

**Reference**: [WordPress CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)

**Key Requirements**:
- Use tabs for indentation
- Use lowercase for selectors and properties
- Use one space after colon in property declarations
- Use one selector per line
- Avoid `!important` unless absolutely necessary

### JavaScript Standards

**Reference**: [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)

**Key Requirements**:
- Use tabs for indentation
- Use camelCase for variables and functions
- Use UPPERCASE for constants
- Always use `const` or `let` (never `var`)
- Use IIFE for encapsulation
- Use `'use strict';` in IIFE

## Security Best Practices

### Input Sanitization

**Always sanitize user input**:
```php
// Text fields
$name = sanitize_text_field( $_POST['name'] ?? '' );

// Email
$email = sanitize_email( $_POST['email'] ?? '' );

// URLs
$url = esc_url_raw( $_POST['url'] ?? '' );

// Keys/slugs
$slug = sanitize_key( $_POST['slug'] ?? '' );

// Textarea
$message = sanitize_textarea_field( $_POST['message'] ?? '' );
```

### Output Escaping

**Always escape output**:
```php
// HTML content
echo esc_html( $variable );

// HTML attributes
echo '<div class="' . esc_attr( $class ) . '">';

// URLs
echo '<a href="' . esc_url( $url ) . '">';

// JavaScript
echo '<script>var data = ' . wp_json_encode( $data ) . ';</script>';
```

### Nonce Verification

**Always verify nonces for form submissions**:
```php
// In form
wp_nonce_field( 'my_action', 'my_nonce' );

// In handler
if ( ! isset( $_POST['my_nonce'] ) || ! wp_verify_nonce( $_POST['my_nonce'], 'my_action' ) ) {
    wp_die( 'Security check failed' );
}
```

### Capability Checks

**Always check user capabilities**:
```php
// For admin pages
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions' );
}

// For specific capabilities
if ( ! current_user_can( 'edit_posts' ) ) {
    return;
}
```

### SQL Injection Prevention

**Always use prepared statements**:
```php
global $wpdb;

// Good
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}table WHERE id = %d AND name = %s",
        $id,
        $name
    )
);

// Bad (vulnerable to SQL injection)
$results = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}table WHERE id = {$id} AND name = '{$name}'"
);
```

## Database Management

### Schema Versioning

**Track database schema versions**:
```php
define( 'VIBECODE_DEPLOY_DB_VERSION', '1.0.0' );

function vibecode_deploy_check_db_version(): void {
    $installed_version = get_option( 'vibecode_deploy_db_version' );
    
    if ( $installed_version !== VIBECODE_DEPLOY_DB_VERSION ) {
        vibecode_deploy_upgrade_database( $installed_version );
        update_option( 'vibecode_deploy_db_version', VIBECODE_DEPLOY_DB_VERSION );
    }
}
```

### Upgrade Procedures

**Handle database upgrades**:
```php
function vibecode_deploy_upgrade_database( string $old_version ): void {
    global $wpdb;
    
    if ( version_compare( $old_version, '1.1.0', '<' ) ) {
        // Upgrade to 1.1.0
        $wpdb->query( "ALTER TABLE {$wpdb->prefix}table ADD COLUMN new_field VARCHAR(255)" );
    }
    
    if ( version_compare( $old_version, '1.2.0', '<' ) ) {
        // Upgrade to 1.2.0
        // Additional upgrade logic
    }
}
```

### Table Creation

**Use `dbDelta()` for table creation**:
```php
function vibecode_deploy_create_tables(): void {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$wpdb->prefix}vibecode_deploy_logs (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        project_slug varchar(255) NOT NULL,
        level varchar(20) NOT NULL,
        message text NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY project_slug (project_slug),
        KEY level (level)
    ) $charset_collate;";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
```

## Internationalization (i18n)

### Text Domain

**Always use a consistent text domain**:
```php
// In plugin header
Text Domain: vibecode-deploy

// In code
__( 'Hello World', 'vibecode-deploy' );
_e( 'Hello World', 'vibecode-deploy' );
esc_html__( 'Hello World', 'vibecode-deploy' );
esc_html_e( 'Hello World', 'vibecode-deploy' );
```

### Translation Functions

**Use appropriate translation functions**:
```php
// Simple string
echo __( 'Hello World', 'vibecode-deploy' );

// Echo string
_e( 'Hello World', 'vibecode-deploy' );

// With context
echo _x( 'Post', 'verb', 'vibecode-deploy' );

// Plural
echo _n( 'One item', '%d items', $count, 'vibecode-deploy' );

// With context and plural
echo _nx( 'One post', '%d posts', $count, 'noun', 'vibecode-deploy' );
```

### Escaped Translations

**Always escape translated output**:
```php
// HTML content
echo esc_html__( 'Hello World', 'vibecode-deploy' );

// HTML attributes
echo '<div class="' . esc_attr__( 'my-class', 'vibecode-deploy' ) . '">';

// URLs
echo '<a href="' . esc_url( __( 'https://example.com', 'vibecode-deploy' ) ) . '">';
```

## Plugin Headers

### Required Headers

```php
<?php
/**
 * Plugin Name: Vibe Code Deploy
 * Plugin URI: https://github.com/VibeCodeCircle/vibecode-deploy
 * Description: Gutenberg-first deployment and import tooling (Etch conversion optional).
 * Version: 1.0.0
 * Author: Vibe Code Circle
 * Author URI: https://github.com/VibeCodeCircle
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: vibecode-deploy
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Network: false
 */
```

### Recommended Headers

- **Plugin URI**: Link to plugin homepage
- **Author URI**: Link to author website
- **License**: Plugin license
- **Text Domain**: For translations
- **Domain Path**: Path to language files
- **Requires at least**: Minimum WordPress version
- **Requires PHP**: Minimum PHP version
- **Network**: Whether plugin is network-wide (multisite)

## Readme.txt Format

**For WordPress.org repository**, use standard readme.txt format:

```
=== Vibe Code Deploy ===
Contributors: vibecodecircle
Tags: deployment, gutenberg, blocks, import
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==

Vibe Code Deploy is a WordPress plugin that converts static HTML websites into Gutenberg-based WordPress sites.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/vibecode-deploy`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in Vibe Code Deploy → Configuration

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes, but Etch theme is recommended for full functionality.

== Changelog ==

= 1.0.0 =
* Initial release
```

## Hooks and Filters

### Actions

**Fire actions at appropriate times**:
```php
// Fire before deployment
do_action( 'vibecode_deploy_before_deploy', $project_slug, $build_root );

// Fire after deployment
do_action( 'vibecode_deploy_after_deploy', $project_slug, $results );
```

### Filters

**Allow filtering of data**:
```php
// Filter deployment results
$results = apply_filters( 'vibecode_deploy_results', $results, $project_slug );

// Filter settings defaults
$defaults = apply_filters( 'vibecode_deploy_default_settings', $defaults );
```

### Naming Conventions

**Use consistent naming**:
- Actions: `{plugin_slug}_{event}` (e.g., `vibecode_deploy_before_deploy`)
- Filters: `{plugin_slug}_{data}` (e.g., `vibecode_deploy_results`)

## Admin UI Standards

### Page Structure

**Use WordPress admin page structure**:
```php
function render_admin_page(): void {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <!-- Page content -->
    </div>
    <?php
}
```

### Form Tables

**Use WordPress form table structure**:
```php
<form method="post" action="options.php">
    <?php settings_fields( 'my_settings_group' ); ?>
    <?php do_settings_sections( 'my_settings_page' ); ?>
    
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="my_field">My Field</label>
            </th>
            <td>
                <input type="text" id="my_field" name="my_field" value="<?php echo esc_attr( get_option( 'my_field' ) ); ?>" class="regular-text" />
                <p class="description">Field description</p>
            </td>
        </tr>
    </table>
    
    <?php submit_button(); ?>
</form>
```

### Notices

**Use WordPress notice classes**:
```php
// Success
echo '<div class="notice notice-success"><p>' . esc_html__( 'Success message', 'vibecode-deploy' ) . '</p></div>';

// Error
echo '<div class="notice notice-error"><p>' . esc_html__( 'Error message', 'vibecode-deploy' ) . '</p></div>';

// Warning
echo '<div class="notice notice-warning"><p>' . esc_html__( 'Warning message', 'vibecode-deploy' ) . '</p></div>';

// Info
echo '<div class="notice notice-info"><p>' . esc_html__( 'Info message', 'vibecode-deploy' ) . '</p></div>';
```

## Performance

### Caching

**Use WordPress transients for caching**:
```php
// Get cached data
$data = get_transient( 'vibecode_deploy_cache' );

if ( false === $data ) {
    // Generate data
    $data = expensive_operation();
    
    // Cache for 1 hour
    set_transient( 'vibecode_deploy_cache', $data, HOUR_IN_SECONDS );
}
```

### Database Queries

**Optimize database queries**:
```php
// Use WP_Query for posts
$query = new WP_Query( array(
    'post_type' => 'my_post_type',
    'posts_per_page' => 10,
    'no_found_rows' => true, // Skip pagination count
    'update_post_meta_cache' => false, // Skip meta cache
    'update_post_term_cache' => false, // Skip term cache
) );
```

### Asset Loading

**Load assets only when needed**:
```php
// Only load on admin pages
if ( is_admin() ) {
    wp_enqueue_style( 'my-admin-style', plugins_url( 'css/admin.css', __FILE__ ) );
}

// Only load on frontend
if ( ! is_admin() ) {
    wp_enqueue_script( 'my-frontend-script', plugins_url( 'js/frontend.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );
}
```

## Error Handling

### WordPress Error Handling

**Use WordPress error handling**:
```php
// Use WP_Error for errors
$result = new WP_Error( 'error_code', 'Error message' );

if ( is_wp_error( $result ) ) {
    echo esc_html( $result->get_error_message() );
}

// Add error data
$error = new WP_Error( 'error_code', 'Error message' );
$error->add_data( array( 'status' => 500 ) );
```

### Logging

**Use WordPress logging**:
```php
// Log errors
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Error message: ' . print_r( $data, true ) );
}

// Or use plugin-specific logger
\VibeCode\Deploy\Logger::error( 'Error message', array( 'data' => $data ), $project_slug );
```

## Testing

### Unit Testing

**Use PHPUnit with WP_UnitTestCase**:
```php
class MyServiceTest extends WP_UnitTestCase {
    public function test_my_method(): void {
        $result = MyService::my_method( 'test' );
        $this->assertIsArray( $result );
        $this->assertTrue( isset( $result['ok'] ) );
    }
}
```

### Integration Testing

**Test full workflows**:
1. Create test staging ZIP
2. Upload via ImportPage
3. Run preflight
4. Deploy
5. Verify pages created
6. Verify assets copied
7. Verify templates extracted

## References

- **WordPress Plugin Handbook**: https://developer.wordpress.org/plugins/
- **WordPress Coding Standards**: https://developer.wordpress.org/coding-standards/
- **WordPress Plugin API**: https://developer.wordpress.org/plugins/
- **WordPress Database**: https://developer.wordpress.org/reference/classes/wpdb/
- **WordPress Hooks**: https://developer.wordpress.org/plugins/hooks/
