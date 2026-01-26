<?php
/**
 * WpApi main file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch;

use Etch\Traits\Singleton;
use Etch\Helpers\Logger;
use Etch\Helpers\EtchGlobal;
use Etch\Helpers\Flag;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Etch\RestApi\RoutesRegister;
use Etch\WpAdmin\License;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WpApi class.
 */
class WpApi {

	use Singleton;

	/**
	 * The EtchGlobal instance.
	 *
	 * @var EtchGlobal
	 */
	private $etch_global;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->etch_global = EtchGlobal::get_instance();
	}

	/**
	 * Initialize the Etch API.
	 *
	 * @return void
	 */
	public function init() {
		// Register REST API routes using the RoutesRegister
		RoutesRegister::get_instance()->register_routes();

		add_action( 'wp_enqueue_scripts', array( $this, 'setup_etch_api' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'setup_etch_api' ) );
		add_action( 'init', array( $this, 'register_etch_global_styles' ) );
		add_action( 'init', array( $this, 'register_etch_global_components' ) );
		add_action( 'init', array( $this, 'register_etch_global_loops' ) );
		add_action( 'init', array( $this, 'register_etch_global_ui_state' ) );
		add_action( 'init', array( $this, 'register_etch_global_license_status' ) );
		add_action( 'init', array( $this, 'register_etch_autocompletion_classes' ) );
		add_action( 'init', array( $this, 'register_etch_global_stylesheets' ) );
		add_action( 'init', array( $this, 'register_etch_global_settings' ) );
	}


	/**
	 * Setup the Etch API.
	 *
	 * @return void
	 */
	public function setup_etch_api() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$this->etch_global->add_script_dependencies( array( 'wp-api' ) );

		$this->etch_global->add_to_etch_global(
			array(
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'baseUrl' => esc_url_raw( rest_url() ),
				'url'     => esc_url_raw( home_url() ),
			)
		);
	}

	/**
	 * Sets caching headers dynamically.
	 *
	 * @param int              $cache_time    Time in seconds for max-age.
	 * @param WP_REST_Response $response      The response to set headers for.
	 */
	public function set_caching_headers( int $cache_time, WP_REST_Response $response ): void {
		$e_tag = '"' . hash( 'sha256', (string) json_encode( $response->get_data() ) ) . '"';

		// Add Cache-Control and ETag headers
		$response->header( 'Cache-Control', 'public, max-age=' . $cache_time );
		$response->header( 'ETag', $e_tag );

		// Handle conditional requests
		if ( ! empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $_SERVER['HTTP_IF_NONE_MATCH'] === $e_tag ) {
			$response->set_status( 304 ); // Not Modified
			$response->set_data( null ); // No body for 304
		}
	}

	/**
	 * Registers and enqueues Etch global styles.
	 *
	 * This function retrieves all Etch styles from the WordPress options,
	 * processes them, and enqueues the necessary scripts for the editor.
	 *
	 * @return void
	 */
	public function register_etch_global_styles() {
		$all_styles = get_option( 'etch_styles', array() );
		if ( ! is_array( $all_styles ) ) {
			return;
		}

		// Simply filter out any non-array values
		$global_styles = array_filter( $all_styles, 'is_array' );

		$this->etch_global->add_script_dependencies( array( 'wp-blocks', 'wp-hooks' ) );

		$this->etch_global->add_to_etch_global(
			array( 'styles' => $global_styles )
		);
	}

	/**
	 * Registers and enqueues Etch global stylesheets.
	 *
	 * This function retrieves all Etch stylesheets from the WordPress options,
	 * processes them, and enqueues the necessary scripts for the editor.
	 *
	 * @return void
	 */
	public function register_etch_global_stylesheets() {
		$stylesheets = get_option( 'etch_global_stylesheets', array() );

		if ( ! is_array( $stylesheets ) ) {
			return;
		}

		// Simply filter out any non-array values
		$global_stylesheets = array_filter( $stylesheets, 'is_array' );

		$this->etch_global->add_script_dependencies( array( 'wp-blocks', 'wp-hooks' ) );

		$this->etch_global->add_to_etch_global(
			array( 'stylesheets' => (object) $global_stylesheets )
		);
	}

	/**
	 * Registers and enqueues Etch global components
	 *
	 * This function retrieves all Etch components from the WordPress options,
	 *
	 * @return void
	 */
	public function register_etch_global_components() {
		$all_components = get_option( 'etch_components', array() );
		if ( ! is_array( $all_components ) ) {
			return;
		}

		$this->etch_global->add_script_dependencies( array( 'wp-blocks', 'wp-hooks' ) );

		$this->etch_global->add_to_etch_global(
			array( 'components' => $all_components )
		);
	}


	/**
	 * Registers and enqueues Etch global loops presets
	 *
	 * This function retrieves all Etch loops presets from the WordPress options,
	 *
	 * @return void
	 */
	public function register_etch_global_loops() {
		$all_loops = get_option( 'etch_loops', array() );

		if ( ! is_array( $all_loops ) ) {
			return;
		}

		// Simply filter out any non-array values
		$global_loops = array_filter( $all_loops, 'is_array' );

		// ! Temporary migration for legacy loops
		// If legacy loops are present, merge them with the global loops
		$legacy_loops = get_option( 'etch_queries', array() );
		if ( is_array( $legacy_loops ) && ! empty( $legacy_loops ) ) {
			foreach ( $legacy_loops as $key => $loop ) {
				$name = isset( $loop['name'] ) ? $loop['name'] : $key;
				$type = 'wp' === $loop['type'] ? 'wp-query' : $loop['type'];

				$migratedLoop = array(
					'name' => $name,
					'key' => $key,
					'global' => true, // Set as global by default
					'config' => array(
						'type' => $type,
						'args' => $loop['args'],
					),
				);

				$uniqueId = substr( uniqid(), -7 );
				// Look if a loop with the same key is already present
				foreach ( $global_loops as $uniqueKey => $value ) {
					if ( isset( $value['key'] ) && $value['key'] === $key ) {
						$uniqueId = $uniqueKey;
					}
				}

				$global_loops[ $uniqueId ] = $migratedLoop;
			}

			// Save new global loops to the database and delete legacy loops
			update_option( 'etch_loops', $global_loops );
			delete_option( 'etch_queries' );
		}

		$this->etch_global->add_to_etch_global(
			array( 'loops' => $global_loops )
		);
	}

	/**
	 * Registers and enqueues Etch global ui state
	 *
	 * This function retrieves the Etch ui state for the current user and enqueues it for the editor.
	 * Includes lazy migration from the legacy global wp_option for backwards compatibility.
	 *
	 * @return void
	 */
	public function register_etch_global_ui_state() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		$ui_state = get_user_meta( $user_id, '_etch_ui_state', true );

		if ( empty( $ui_state ) ) {
			$legacy_state = get_option( 'etch_ui_state', array() );

			if ( ! empty( $legacy_state ) && is_array( $legacy_state ) ) {
				update_user_meta( $user_id, '_etch_ui_state', $legacy_state );
				$ui_state = $legacy_state;
			}
		}

		if ( Flag::is_on( 'REMOVE_DEPRECATED_ETCH_UI_STATE' ) ) {
			delete_option( 'etch_ui_state' );
		}

		if ( ! is_array( $ui_state ) ) {
			return;
		}

		$this->etch_global->add_to_etch_global(
			array( 'builderUi' => (object) $ui_state )
		);
	}

	/**
	 * Pass the license status for the Etch Global
	 *
	 * @return void
	 */
	public function register_etch_global_license_status() {
		$status = License::get_instance()->is_active() ? true : false;

		$this->etch_global->add_to_etch_global(
			array( 'licenseActive' => $status )
		);
	}

	/**
	 * Register autocompletion classes for the editor.
	 *
	 * This function allows third-party developers to add their own classes
	 * for autocompletion in the editor through the 'etch_autocomplition_classes' filter.
	 *
	 * @return void
	 */
	public function register_etch_autocompletion_classes() {
		$default_classes = array();

		/**
		 * Filter the list of classes available for autocompletion in the editor.
		 *
		 * Example usage:
		 * add_filter('etch_autocompletion_classes', function($classes) {
		 *     return ['my-custom-class', 'another-custom-class'];
		 * });
		 *
		 * @param array $default_classes Array of class names to be available for autocompletion.
		 */
		$autocompletion_classes = apply_filters( 'etch_autocompletion_classes', $default_classes );

		// Ensure we have an array and convert all values to strings
		$autocompletion_classes = array_map(
			'strval',
			(array) $autocompletion_classes
		);

		// Remove empty values and duplicates
		$autocompletion_classes = array_values(
			array_unique(
				array_filter(
					$autocompletion_classes,
					function ( $class ) {
						return ! empty( $class );
					}
				)
			)
		);

		$this->etch_global->add_to_etch_global(
			array(
				'api' => array(
					'classAutocomplete' => $autocompletion_classes,
				),
			)
		);
	}

	/**
	 * Registers and enqueues Etch global settings
	 *
	 * This function retrieves the Etch settings and enqueues them for the editor.
	 *
	 * @return void
	 */
	public function register_etch_global_settings() {
		$settings = get_option( 'etch_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Set default for custom_block_migration_completed if not present
		if ( ! isset( $settings['custom_block_migration_completed'] ) ) {
			$settings['custom_block_migration_completed'] = false;
		}

		$this->etch_global->add_to_etch_global(
			array( 'settings' => (object) $settings )
		);
	}
}
