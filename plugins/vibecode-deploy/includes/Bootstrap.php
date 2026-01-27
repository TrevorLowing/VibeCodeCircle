<?php

namespace VibeCode\Deploy;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap and initialization.
 *
 * Loads all required files and registers hooks.
 *
 * @package VibeCode\Deploy
 */
final class Bootstrap {
	/**
	 * Initialize the plugin.
	 *
	 * Loads all service classes and admin pages, then registers hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Settings.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Staging.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Logger.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/BuildService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ClassPrefixDetector.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/AssetService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/MediaLibraryService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ShortcodePlaceholderService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/HtmlToEtchConverter.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/DeployService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/CleanupService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ManifestService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/RollbackService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/EnvService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/TemplateService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/RulesPackService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/StarterPackService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/DeploymentValidator.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ThemeSetupService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ThemeDeployService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/TestDataService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/HtmlTestPageService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/HtmlTestPageAuditService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/EtchWPComplianceService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Importer.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Cli.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/SettingsPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/ImportPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/BuildsPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/LogsPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/RulesPackPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/StarterPackPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/HealthCheckPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/TemplatesPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/TestDataPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/HelpPage.php';

		add_action( 'plugins_loaded', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register admin pages and hooks.
	 *
	 * Called on 'plugins_loaded' action.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( is_admin() ) {
			\VibeCode\Deploy\Admin\SettingsPage::init();
			\VibeCode\Deploy\Admin\ImportPage::init();
			\VibeCode\Deploy\Admin\BuildsPage::init();
			\VibeCode\Deploy\Admin\LogsPage::init();
			\VibeCode\Deploy\Admin\RulesPackPage::init();
			\VibeCode\Deploy\Admin\StarterPackPage::init();
			\VibeCode\Deploy\Admin\HealthCheckPage::init();
			\VibeCode\Deploy\Admin\TemplatesPage::init();
			\VibeCode\Deploy\Admin\TestDataPage::init();
			\VibeCode\Deploy\Admin\HelpPage::init();
		}

		// Enqueue fonts at priority 1 (before WordPress core styles at priority 10)
		add_action( 'wp_enqueue_scripts', array( '\\VibeCode\\Deploy\\Importer', 'enqueue_fonts' ), 1 );
		// Enqueue other assets at priority 15 (after WordPress core styles)
		add_action( 'wp_enqueue_scripts', array( '\\VibeCode\\Deploy\\Importer', 'enqueue_assets_for_current_page' ), 15 );
		add_filter( 'body_class', array( '\\VibeCode\\Deploy\\Importer', 'add_body_class_from_meta' ), 10, 1 );

		\VibeCode\Deploy\Cli::init();
	}
}
