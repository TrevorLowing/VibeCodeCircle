<?php

namespace VibeCode\Deploy;

defined( 'ABSPATH' ) || exit;

final class Bootstrap {
	public static function init(): void {
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Settings.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Staging.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Logger.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/BuildService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/AssetService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ShortcodePlaceholderService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/HtmlToEtchConverter.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/DeployService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/CleanupService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ManifestService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/RollbackService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/EnvService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/TemplateService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/RulesPackService.php';
		// Temporarily disable ThemeSetupService
		// require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Services/ThemeSetupService.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Importer.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Cli.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/SettingsPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/ImportPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/BuildsPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/LogsPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/RulesPackPage.php';
		require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/TemplatesPage.php';
		// Temporarily disable HelpPage
		// require_once VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/Admin/HelpPage.php';

		add_action( 'plugins_loaded', array( __CLASS__, 'register' ) );
	}

	public static function register(): void {
		if ( is_admin() ) {
			\VibeCode\Deploy\Admin\SettingsPage::init();
			\VibeCode\Deploy\Admin\ImportPage::init();
			\VibeCode\Deploy\Admin\BuildsPage::init();
			\VibeCode\Deploy\Admin\LogsPage::init();
			\VibeCode\Deploy\Admin\RulesPackPage::init();
			\VibeCode\Deploy\Admin\TemplatesPage::init();
			// Temporarily disable HelpPage
			// \VibeCode\Deploy\Admin\HelpPage::init();
		}

		add_action( 'wp_enqueue_scripts', array( '\\VibeCode\\Deploy\\Importer', 'enqueue_assets_for_current_page' ), 15 );

		\VibeCode\Deploy\Cli::init();
	}
}
