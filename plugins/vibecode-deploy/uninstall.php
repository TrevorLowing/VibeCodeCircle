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
 * (form submissions, posts, pages, templates, etc.) as that may be valuable business data
 * that should be preserved. Uncomment deletion code if needed.
 */
