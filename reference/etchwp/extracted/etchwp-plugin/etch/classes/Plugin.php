<?php
/**
 * Main plugin file for Etch.
 *
 * This file contains the main namespace and use statements for the Etch plugin.
 *
 * @package Etch
 * @gplv2
 */

namespace Etch;

use Etch\Helpers\Logger;
use Etch\Helpers\Logger\LoggerConfig;
use Etch\Helpers\WideEventLogger;
use Etch\Helpers\WideEventLoggerInstance;
use Etch\Traits\Singleton;
use Etch\WpApi;
use Etch\Styles;
use Etch\Hooks;
use Etch\Helpers\EtchGlobal;
use WP_Post;

// CLI.
use Etch\Cli\EtchCliLoader;

// Elements.
use Etch\Preprocessor\Preprocessor;
use Etch\Blocks\BlocksRegistry;
use Etch\Helpers\Flag;
use Etch\Assets\AssetRegistry;
use Etch\Vite\ViteManager;
use Etch\Services\ContentTypeService;
use Etch\Services\StylesheetService;

// WpAdmin
use Etch\WpAdmin\WpAdmin;
use Etch\WpAdmin\License;
use Etch\Filters\FiltersManager;
use Etch\Helpers\FlagsNotInMainFileException;
use Etch\CustomFields\CustomFieldService;
use Etch\Helpers\ServerTiming;
use Etch\Helpers\MigrationChecker;

/**
 * Main Class.
 */
class Plugin {


	use Singleton;

	/**
	 * Vite manager instance.
	 *
	 * @var ViteManager
	 */
	private ViteManager $vite_manager;


	/**
	 * Is development mode flag.
	 *
	 * @var bool
	 */
	private bool $is_dev_mode;

	/**
	 * Is logger enabled flag.
	 *
	 * @var bool
	 */
	private bool $is_logger_enabled;

	/**
	 * Logger config instance.
	 *
	 * @var LoggerConfig
	 */
	private LoggerConfig $logger_config;

	/**
	 * Asset registry instance.
	 *
	 * @var AssetRegistry
	 */
	private AssetRegistry $asset_registry;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		// Initialize the feature flags.
		$flag_error_message = ''; // Need to catch an Exception to store the error message because Logger isn't available yet.
		try {
			Flag::init();
			$flag_error_message = '';
		} catch ( FlagsNotInMainFileException $e ) {
			$flag_error_message = $e->getMessage();
		}
		// Initialize dev_mode to load Svelte from Vite.
		$this->is_dev_mode = Flag::is_on( 'LOAD_SVELTE_FROM_VITE' );
		// Initialize logger config.
		$this->is_logger_enabled = Flag::is_on( 'ENABLE_DEBUG_LOG' );
		$this->logger_config = new LoggerConfig( $this->is_logger_enabled, $this );
		Logger::set_config( $this->logger_config );
		// Start logging.
		Logger::log_header();
		Logger::log( sprintf( '%s: is_dev_mode: %s', __METHOD__, $this->is_dev_mode ? 'true' : 'false' ) );
		Logger::log( sprintf( '%s: flags: %s', __METHOD__, print_r( Flag::get_flags(), true ) ) );
		if ( ! empty( $flag_error_message ) ) {
			Logger::log( $flag_error_message );
		}

		// Start output buffering with our ServerTiming callback.
		// This allows us to send headers at the end of the request.
		add_action( 'after_setup_theme', array( ServerTiming::class, 'start_buffering' ) );

		// Initialize Wide Events logging.
		// Sample rate: 100% in dev mode (debug log enabled), 1% in production.
		$wide_events_sample_rate = $this->is_logger_enabled ? 1.0 : 0.01;
		$wide_event_logger = new WideEventLoggerInstance(
			array(
				'sample_rate' => $wide_events_sample_rate,
			)
		);
		$wide_event_logger->init();
		WideEventLogger::set_instance( $wide_event_logger );
		WideEventLogger::set( 'request.uri', Logger::get_redacted_uri() );
		WideEventLogger::set( 'plugin_version', self::get_plugin_version() );
		WideEventLogger::set( 'flags.active', Flag::get_flags() );
		register_shutdown_function( array( WideEventLogger::class, 'emit' ) );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init() {

		$timer = new Helpers\Timer();
		// Prepare etch global interface
		$etch_global = EtchGlobal::get_instance();
		$etch_global->init();
		Flag::inject_flag_into_etch_global( $etch_global );

		// WpAdmin
		WpAdmin::get_instance()->init();

		$this->asset_registry = new AssetRegistry();
		$this->asset_registry->init();

		$this->vite_manager = new ViteManager( $this->is_dev_mode );

		// Register CLI commands
		EtchCliLoader::register_etch_commands();

		// Setup the hooks.
		$this->setup_hooks();

		// Check if migration is needed and set the flag accordingly
		// This runs on init() to catch existing installs that are being updated
		MigrationChecker::check_and_set_migration_flag();

		// Elements.
		// Only initialize Preprocessor if custom block migration is not completed.
		$settings = get_option( 'etch_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$migration_completed = isset( $settings['custom_block_migration_completed'] ) && true === $settings['custom_block_migration_completed'];
		if ( false === $migration_completed ) {
			new Preprocessor();
		}

		// Custom Blocks
		new BlocksRegistry();

		Styles::get_instance()->init();

		// Hooks.
		new Hooks();

		// Initialize the Filters Manager
		FiltersManager::get_instance()->init();

		/**
		 * Magic Area
		 */

		add_action( 'template_redirect', array( $this, 'render_etch_builder_template' ) );
		add_filter( 'template_include', array( $this, 'load_blank_template_for_frontend_context' ) );
		add_action( 'init', array( $this, 'initialize_license' ) );

		add_filter( 'block_categories_all', array( $this, 'register_custom_block_category' ), 10, 2 );

		// Initialize the WP API.
		WpApi::get_instance()->init();

		// Initialize the Content Type Service. ( post types, taxonomies, custom fields, etc.)
		ContentTypeService::get_instance()->init();
		CustomFieldService::get_instance()->init();

		// Initialize the stylesheets service.
		StylesheetService::get_instance()->init();

		// Initialize the Etch CMS.
		// CMS::get_instance()->init();

		$time = $timer->get_time();
		Logger::log( sprintf( '%s: plugin initialized in %f seconds', __METHOD__, $time ) );
	}

	/**
	 * Initialize the License module
	 *
	 * @return void
	 */
	public function initialize_license() {
		License::get_instance()->init();
	}

	/**
	 * Check if the etch parameter is set and load the blank template.
	 *
	 * @return void
	 */
	public function render_etch_builder_template() {
		if ( isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] ) {
			Logger::log( sprintf( '%s: Loading blank template for Etch', __METHOD__ ) );

			// Disable WordPress admin bar.
			add_filter( 'show_admin_bar', '__return_false' );

			// Remove admin bar styles and scripts.
			add_action( 'wp_print_styles', array( $this, 'dequeue_admin_bar_styles' ), 99 );
			add_action( 'wp_print_scripts', array( $this, 'dequeue_admin_bar_scripts' ), 99 );

			// Remove the admin bar margin from the HTML tag.
			add_action( 'get_header', array( $this, 'remove_admin_bar_margin' ) );

			// Hook our script enqueuing to the proper WordPress action
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );

			add_filter( 'template_include', array( $this, 'load_blank_template_for_builder_context' ) );
		}
	}

	/**
	 * Enqueue WordPress media scripts for Etch builder.
	 * This properly loads all dependencies needed for wp.media to be available.
	 *
	 * @return void
	 */
	public function enqueue_media_scripts() {

		// First, make sure we have the wp-api scripts that define window.wp
		wp_enqueue_script( 'wp-api' );

		// Load all media-related scripts and their dependencies
		wp_enqueue_media();

		// Make sure all needed scripts for media library dialog are included
		wp_enqueue_script( 'media-upload' );
		wp_enqueue_script( 'media-views' );
		wp_enqueue_script( 'media-editor' );

		// Ensure backbone and underscore are loaded
		wp_enqueue_script( 'backbone' );
		wp_enqueue_script( 'underscore' );
	}

	/**
	 * Conditionally disables emoji-related scripts and styles when Etch builder is active.
	 *
	 * @return void
	 */
	public function maybe_disable_emojis_for_etch() {
		if ( isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] ) {
			add_action(
				'init',
				function () {
					remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
					remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
					remove_action( 'wp_print_styles', 'print_emoji_styles' );
					remove_action( 'admin_print_styles', 'print_emoji_styles' );
					add_filter( 'emoji_svg_url', '__return_false' );
					add_filter(
						'tiny_mce_plugins',
						function ( $plugins ) {
							return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
						}
					);
				},
				0
			);
		}
	}

	/**
	 * Load the blank template for builder context.
	 *
	 * @return string
	 */
	public function load_blank_template_for_builder_context() {
		return ETCH_PLUGIN_DIR . 'utils/blank-template.php';
	}

	/**
	 * Override the WordPress's default template-canvas.php to remove the wrapper div.wp-site-blocks for the frontend only.
	 *
	 * @param string $template Url of template file.
	 *
	 * @return string
	 */
	public function load_blank_template_for_frontend_context( $template ) {
		if ( false === strpos( $template, 'template-canvas.php' ) || is_admin() || ( isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] ) ) {
			return $template;
		}
		return ETCH_PLUGIN_DIR . 'utils/etch-template-canvas.php';
	}

	/**
	 * Dequeue admin bar styles.
	 *
	 * @return void
	 */
	public function dequeue_admin_bar_styles() {
		wp_dequeue_style( 'admin-bar' );
		wp_deregister_style( 'admin-bar' );
	}

	/**
	 * Dequeue admin bar scripts.

	 * @return void
	 */
	public function dequeue_admin_bar_scripts() {
		wp_dequeue_script( 'admin-bar' );
		wp_deregister_script( 'admin-bar' );
	}

	/**
	 * Remove admin bar margin from HTML tag.

	 * @return void
	 */
	public function remove_admin_bar_margin() {
		remove_action( 'wp_head', '_admin_bar_bump_cb' );
	}



	/**
	 * Initializes the plugin.
	 *
	 * @return void
	 */
	public function run() {
		$timer = new Helpers\Timer();
		$this->setup_translations();
		$time = $timer->get_time();
		Logger::log( sprintf( '%s: plugin ran in %f seconds', __METHOD__, $time ) );
	}

	/**
	 * Setup the hooks.
	 *
	 * @return void
	 */
	public function setup_hooks() {
		register_activation_hook( ETCH_PLUGIN_FILE, array( $this, 'activate_plugin' ) );
		register_deactivation_hook( ETCH_PLUGIN_FILE, array( $this, 'deactivate_plugin' ) );
		add_action( 'plugins_loaded', array( $this, 'maybe_disable_emojis_for_etch' ), 1 );
		// Add row links.
		add_filter( 'page_row_actions', array( $this, 'row_actions' ), 10, 2 ); // TODO: add post_row_actions too?
		Svg::setup_hooks();
	}

	/**
	 * Handle activation of the plugin.
	 *
	 * @return void
	 */
	public function activate_plugin() {
		// Logger::log_header(); // During activation, the header is not automatically logged.
		Logger::log( sprintf( '%s: plugin activated', __METHOD__ ) );

		// Check if migration is needed and set the flag accordingly
		MigrationChecker::check_and_set_migration_flag();
	}

	/**
	 * Handle deactivation of the plugin.
	 *
	 * @return void
	 */
	public function deactivate_plugin() {
		Logger::log( sprintf( '%s: plugin deactivated', __METHOD__ ) );
	}

	/**
	 * Handle plugins_loaded action.
	 *
	 * @return void
	 */
	public function setup_translations() {
		load_plugin_textdomain( 'etch', false, dirname( ETCH_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'languages' ) );
	}

	/**
	 * Add a link to edit the post with Etch.
	 *
	 * @param array<string, string> $actions The existing row actions.
	 * @param WP_Post               $post   The post object.
	 * @return array<string, string>
	 */
	public function row_actions( array $actions, WP_Post $post ): array {
		// TODO: check if user is allowed to edit the post and if post type is supported.
		$edit_url = add_query_arg(
			array(
				'etch' => 'magic',
				'post_id' => $post->ID,
			),
			home_url( '/' )
		);
		$actions['edit_with_etch'] = sprintf(
			'<a href="%s" target="_blank">%s</a>',
			esc_url( $edit_url ),
			__( 'Edit with Etch', 'etch' )
		);
		return $actions;
	}

	/**
	 * Get the plugin's Version
	 *
	 * @return string
	 */
	public static function get_plugin_version() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = get_plugin_data( ETCH_PLUGIN_FILE, true, false );
		$version = $plugin_data['Version'];
		return $version;
	}

	/**
	 * Get the directory where we store the dynamic uploads.
	 * If it doesn't exist, create it.
	 *
	 * This was added to support plugins like S3 Offload that alter the uploads_dir.
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public static function get_dynamic_uploads_dir() {
		$wp_upload_dir = wp_upload_dir();
		$etch_uploads_dir = trailingslashit( $wp_upload_dir['basedir'] ) . 'etch';
		if ( ! file_exists( $etch_uploads_dir ) ) {
			wp_mkdir_p( $etch_uploads_dir );
		}
		self::maybe_create_htaccess( $etch_uploads_dir );
		self::maybe_create_index_php( $etch_uploads_dir );
		return $etch_uploads_dir;
	}

	/**
	 * Create .htaccess file to prevent direct HTTP access to log files.
	 *
	 * @since 2.8.0
	 * @param string $dir The directory path.
	 * @return void
	 */
	private static function maybe_create_htaccess( string $dir ): void {
		$htaccess_file = trailingslashit( $dir ) . '.htaccess';
		if ( file_exists( $htaccess_file ) ) {
			return;
		}

		$rules  = "<FilesMatch \"\.log$\">\n";
		$rules .= "    Order allow,deny\n";
		$rules .= "    Deny from all\n";
		$rules .= "</FilesMatch>\n";
		@file_put_contents( $htaccess_file, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Create index.php file to prevent directory listing.
	 *
	 * @since 2.8.0
	 * @param string $dir The directory path.
	 * @return void
	 */
	private static function maybe_create_index_php( string $dir ): void {
		$index_file = trailingslashit( $dir ) . 'index.php';
		if ( file_exists( $index_file ) ) {
			return;
		}

		@file_put_contents( $index_file, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Get the URL where we store the dynamic uploads.
	 *
	 * This was added to support plugins like S3 Offload that alter the uploads_dir.
	 *
	 * @since 2.6.0
	 * @return string
	 */
	public static function get_dynamic_uploads_url() {
		$wp_upload_dir = wp_upload_dir();
		return trailingslashit( set_url_scheme( $wp_upload_dir['baseurl'] ) ) . 'etch';
	}

	/**
	 * Get asset registry instance.
	 *
	 * @return AssetRegistry
	 */
	public function get_asset_registry(): AssetRegistry {
		return $this->asset_registry;
	}

	/**
	 * Get Vite manager instance.
	 *
	 * @return ViteManager
	 */
	public function get_vite_manager(): ViteManager {
		return $this->vite_manager;
	}

	/**
	 * Register the custom block category.
	 *
	 * @param array<int, array<string, string|null>> $block_categories The existing block categories.
	 * @param object                                 $editor_context The editor context.
	 * @return array<int, array<string, string|null>>
	 */
	public function register_custom_block_category( $block_categories, $editor_context ) {
		if ( ! empty( $editor_context->post ) ) {
			array_push(
				$block_categories,
				array(
					'slug'  => 'etch',
					'title' => __( 'Etch', 'etch' ),
					'icon'  => null,
				)
			);
		}
		return $block_categories;
	}
}
