<?php
/**
 * Plugin Name: Vibe Code Deploy
 * Description: Gutenberg-first deployment and import tooling (Etch conversion optional).
 * Version: 0.1.0
 * Author: Vibe Code Deploy
 */

defined( 'ABSPATH' ) || exit;

define( 'VIBECODE_DEPLOY_PLUGIN_FILE', __FILE__ );
define( 'VIBECODE_DEPLOY_PLUGIN_DIR', __DIR__ );
define( 'VIBECODE_DEPLOY_PLUGIN_VERSION', '0.1.0' );

require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Bootstrap.php';

VibeCode\Deploy\Bootstrap::init();
