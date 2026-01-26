<?php
/**
 * Hooks system for Etch.
 *
 * @package Etch
 * @subpackage Assets
 */

namespace Etch;

use Etch\Helpers\EtchGlobal;

/**
 * Class Hooks
 *
 * Provides hooks for third-party developers and internal use
 */
class Hooks {

	/**
	 * Constructor for the Elements class.
	 *
	 * Adds action hook to register component classes on init.
	 */
	public function __construct() {
		// Register the hook to enqueue the global data
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_etch_global_hook_data' ) );
	}

	/**
	 * Sanitize and validate a path or URL.
	 *
	 * @param array{id: string, url: string} $asset Array containing 'id' and 'url' keys.
	 * @param string                         $format Which format should be validated 'css' or 'js'.
	 * @return array{id: string, url: string}|false Sanitized array or false if invalid.
	 */
	private function sanitize_asset( array $asset, string $format ): array|false {
		if ( empty( $asset['id'] ) || empty( $asset['url'] ) ) {
			return false;
		}

		if ( ! in_array( $format, array( 'css', 'js' ), true ) ) {
			return false;
		}

		// Check if it's an absolute URL
		if ( filter_var( $asset['url'], FILTER_VALIDATE_URL ) ) {
			// Validate that it's a valid file
			if ( ! preg_match( '/\.' . $format . '(\?.*)?$/', $asset['url'] ) ) {
				return false;
			}

			$asset['url'] = esc_url_raw( $asset['url'] );
			return $asset;
		}

		// Handle relative url
		// Remove any attempts to navigate up directories
		$asset['url'] = str_replace( '..', '', $asset['url'] );

		// Ensure url starts with forward slash
		$asset['url'] = '/' . ltrim( $asset['url'], '/' );

		if ( ! preg_match( '/^\/[\w\/-]+\.' . $format . '$/', $asset['url'] ) ) {
			return false;
		}

		return $asset;
	}

	/**
	 * Enqueue the window object containing registered CSS paths.
	 *
	 * @return void
	 */
	public function enqueue_etch_global_hook_data(): void {
		$additional_assets = $this->load_additional_assets();

		// TODO: remove the filter `etch/preview/additional_stylesheets` in v1.
		// since we changed to `etch/canvas/additional_stylesheets`.
		$additional_stylesheets = apply_filters_deprecated( 'etch/preview/additional_stylesheets', array( array() ), '1.0.0-alpha-16', 'etch/canvas/additional_stylesheets' );

		// $additional_stylesheets @var array<array{id: string, url: string}>
		$additional_stylesheets = apply_filters( 'etch/canvas/additional_stylesheets', $additional_stylesheets );
		$additional_scripts = array();

		if ( ! empty( $additional_assets['styles'] ) ) {
			$additional_stylesheets = array_merge( $additional_stylesheets, $this->convert_wp_queue_to_etch_queue( $additional_assets['styles'] ) );
		}

		if ( ! empty( $additional_assets['scripts'] ) ) {
			$additional_scripts = array_merge( $additional_scripts, $this->convert_wp_queue_to_etch_queue( $additional_assets['scripts'] ) );
		}

		$additional_stylesheets = $this->normalize_assets_queue( $additional_stylesheets, 'css' );
		$additional_scripts = $this->normalize_assets_queue( $additional_scripts, 'js' );

		if ( empty( $additional_stylesheets ) && empty( $additional_scripts ) ) {
			return;
		}

		// Prepare the data for output
		$data = array(
			'iframe' => array(
				'additionalStylesheets' => $additional_stylesheets,
				'additionalScripts' => $additional_scripts,
			),
		);

		EtchGlobal::get_instance()->add_to_etch_global( $data );
	}

	/**
	 * Convert the queue that comes from global $wp_scripts to etch format array<id, url>
	 *
	 * @param array<int, mixed> $wp_queue WP Scripts Queue.
	 * @return array<array{id: string, url: string}> Same queue but in etch format.
	 */
	private function convert_wp_queue_to_etch_queue( array $wp_queue ): array {
		$etch_queue = array();
		foreach ( $wp_queue as $asset ) {
			if ( is_object( $asset ) && isset( $asset->handle ) && isset( $asset->src ) ) {
				$etch_queue[] = array(
					'id' => (string) $asset->handle,
					'url' => (string) $asset->src,
				);
			}
		}

		return $etch_queue;
	}

	/**
	 * Normalize the queue removing the duplicates and filtering the assets based on his format.
	 *
	 * @param array<array{id: string, url: string}> $assets_queue Queue to be normalized.
	 * @param string                                $format Format of the queue to be normalized `css` or 'js'.
	 * @return array<array{id: string, url: string}> Queue normalized.
	 */
	private function normalize_assets_queue( array $assets_queue, string $format ): array {
		// Dedupe the queue
		$normalized_queue = array_unique( $assets_queue, SORT_REGULAR );

		// Sanitize and filter the paths
		$normalized_queue = array_filter(
			array_map(
				function ( $item ) use ( $format ) {
					return $this->sanitize_asset( $item, $format );
				},
				$normalized_queue
			)
		);

		return $normalized_queue;
	}

	/**
	 * Create an array with all assets to be enqueue in the preview.
	 *
	 * @return array<string, array<int, mixed>> List of assets to be enqueued in the preview.
	 */
	private function load_additional_assets(): array {
		global $wp_scripts, $wp_styles;

		$original_wp_scripts_queue = $wp_scripts->queue;
		$original_wp_styles_queue = $wp_styles->queue;

		$wp_scripts->queue = array();
		$wp_styles->queue = array();

		do_action( 'etch/canvas/enqueue_assets' );

		$assets_to_enqueue = array(
			'styles' => array(),
			'scripts' => array(),
		);

		if ( ! empty( $wp_styles->queue ) ) {
			foreach ( $wp_styles->queue as $handle ) {
				$obj = $wp_styles->registered[ $handle ] ?? null;
				if ( $obj ) {
					$assets_to_enqueue['styles'][] = $obj;
				}
			}
		}

		if ( ! empty( $wp_scripts->queue ) ) {
			foreach ( $wp_scripts->queue as $handle ) {
				$obj = $wp_scripts->registered[ $handle ] ?? null;
				if ( $obj ) {
					$assets_to_enqueue['scripts'][] = $obj;
				}
			}
		}

		$wp_styles->queue = $original_wp_styles_queue;
		$wp_scripts->queue = $original_wp_scripts_queue;

		return $assets_to_enqueue;
	}
}
