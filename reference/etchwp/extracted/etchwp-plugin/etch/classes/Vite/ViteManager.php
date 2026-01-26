<?php
/**
 * Main Vite integration coordinator.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite;

use Etch\Vite\Apps\AppConfig;
use Etch\Vite\Apps\AppRegistry;
use Etch\Vite\Apps\AppRenderer;
use Etch\Vite\Assets\DevAssetLoader;
use Etch\Vite\Assets\ProdAssetLoader;
use Etch\Vite\Assets\ScriptTagModifier;

/**
 * Class ViteManager
 *
 * Coordinates Vite integration Elements.
 */
class ViteManager {

	/**
	 * Development mode flag.
	 *
	 * @var bool
	 */
	private bool $is_dev_mode;

	/**
	 * App registry instance.
	 *
	 * @var AppRegistry
	 */
	private AppRegistry $app_registry;

	/**
	 * App renderer instance.
	 *
	 * @var AppRenderer
	 */
	private AppRenderer $app_renderer;

	/**
	 * Development asset loader instance.
	 *
	 * @var DevAssetLoader
	 */
	private DevAssetLoader $dev_loader;

	/**
	 * Production asset loader instance.
	 *
	 * @var ProdAssetLoader
	 */
	private ProdAssetLoader $prod_loader;

	/**
	 * Constructor.
	 *
	 * @param bool $is_dev_mode Whether to load assets from Vite dev server.
	 */
	public function __construct( bool $is_dev_mode ) {
		$this->is_dev_mode = $is_dev_mode;

		// Initialize required instances first
		$this->app_registry = new AppRegistry();
		$this->app_renderer = new AppRenderer();
		$this->dev_loader = new DevAssetLoader();
		$this->prod_loader = new ProdAssetLoader();

		// Initialize all Vite-related functionality
		$this->init_apps( ETCH_PLUGIN_DIR . '/apps.config.json' );
		$this->init_assets();
		$this->init_rendering();

		$this->set_render_condition( 'builder', 'magic-area' );

		add_filter(
			'script_loader_tag',
			array( ScriptTagModifier::class, 'add_module_type' ),
			10,
			3
		);
	}

	/**
	 * Initialize app loading.
	 *
	 * @param string $config_file Path to configuration file.
	 * @return void
	 */
	public function init_apps( string $config_file ): void {
		$this->app_registry->load_from_config( $config_file );
	}

	/**
	 * Initialize asset loading.
	 *
	 * @return void
	 */
	public function init_assets(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_app_assets' ) );
	}

	/**
	 * Initialize app rendering.
	 *
	 * @return void
	 */
	public function init_rendering(): void {
		add_action( 'wp_footer', array( $this, 'render_app_divs' ) );
	}

	/**
	 * Set render condition for an application.
	 *
	 * @param string $app_name Application name.
	 * @param string $env Environment condition.
	 * @return void
	 */
	public function set_render_condition( string $app_name, string $env = 'frontend' ): void {
		$this->app_renderer->set_render_condition( $app_name, $env );
	}

	/**
	 * Enqueue assets for applications.
	 *
	 * @return void
	 */
	public function enqueue_app_assets(): void {
		$apps_to_render = $this->app_renderer->get_apps_to_render();

		foreach ( $apps_to_render as $app_name ) {
			$app = $this->app_registry->get_app_by_name( $app_name );
			if ( null === $app ) {
				continue;
			}

			if ( $this->is_dev_mode ) {
				$this->dev_loader->enqueue_assets( $app );
			} else {
				$this->prod_loader->enqueue_assets( $app );
			}
		}
	}

	/**
	 * Render application mount points.
	 *
	 * @return void
	 */
	public function render_app_divs(): void {
		$this->app_renderer->render_app_divs();
	}

	/**
	 * Get app registry instance.
	 *
	 * @return AppRegistry
	 */
	public function get_app_registry(): AppRegistry {
		return $this->app_registry;
	}

	/**
	 * Get app renderer instance.
	 *
	 * @return AppRenderer
	 */
	public function get_app_renderer(): AppRenderer {
		return $this->app_renderer;
	}
}
