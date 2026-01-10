<?php
/**
 * Plugin Name: Vibe Code Deploy
 * Description: Gutenberg-first deployment and import tooling (Etch conversion optional).
 * Version: 0.1.42
 * Author: Vibe Code Deploy
 * Text Domain: vibecode-deploy
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'VIBECODE_DEPLOY_PLUGIN_FILE', __FILE__ );
define( 'VIBECODE_DEPLOY_PLUGIN_DIR', __DIR__ );
define( 'VIBECODE_DEPLOY_PLUGIN_VERSION', '0.1.42' );

require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Bootstrap.php';

/**
 * Activation hook: Set default options and initialize settings.
 *
 * @return void
 */
function vibecode_deploy_activate(): void {
	// Set default options (only if not already set)
	if ( ! get_option( 'vibecode_deploy_version' ) ) {
		add_option( 'vibecode_deploy_version', VIBECODE_DEPLOY_PLUGIN_VERSION );
	}

	// Set default settings
	require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Settings.php';
	if ( ! get_option( \VibeCode\Deploy\Settings::OPTION_NAME ) ) {
		$defaults = \VibeCode\Deploy\Settings::defaults();
		add_option( \VibeCode\Deploy\Settings::OPTION_NAME, $defaults );
	}

	// Flush rewrite rules (in case CPTs are registered)
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'vibecode_deploy_activate' );

/**
 * Deactivation hook: Clean up scheduled events and flush rewrite rules.
 *
 * @return void
 */
function vibecode_deploy_deactivate(): void {
	// Clear scheduled cron jobs
	wp_clear_scheduled_hook( 'vibecode_deploy_cron' );

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'vibecode_deploy_deactivate' );

VibeCode\Deploy\Bootstrap::init();
