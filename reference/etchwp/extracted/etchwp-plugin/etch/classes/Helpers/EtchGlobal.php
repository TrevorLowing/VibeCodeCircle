<?php
/**
 * EthGlobal helper file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

use DOMDocument;
use DOMXPath;
use Etch\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * EtchGlobal class.
 */
class EtchGlobal {

	use Singleton;

	/**
	 * The name for the window object that will hold the global data.
	 *
	 * @var string
	 */
	private $object_name = 'etchGlobal';

	/**
	 * The script handle for the global script.
	 *
	 * @var string
	 */
	private $script_handle = 'etch-global-script';

	/**
	 * The script path for the global script.
	 *
	 * @var string
	 */
	private $script_path = ETCH_PLUGIN_URL . 'etch-global.js';

	/**
	 * Initialize the Etch API.
	 *
	 * @return void
	 */
	public function init() {
		// Important: This must run after the other scripts are registered.
		// Otherwise, some data might not be available.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Enqueue and register scripts
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_register_script(
			$this->script_handle,
			$this->script_path,
			array(),
			null,
			true
		);

		if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) {
			$etch_global = wp_cache_get( 'etch_global_data', 'etch' );

			if ( ! is_array( $etch_global ) ) {
				$etch_global = array();
			}

			wp_add_inline_script(
				$this->script_handle,
				'var ' . $this->object_name . ' =' . json_encode( $etch_global ),
				'before'
			);

			wp_enqueue_script( $this->script_handle );
		}
	}

	/**
	 * Add data to the etchGlobal object.
	 *
	 * @param array<string, mixed> $data An associative array of data to be added to etchGlobal.
	 * @return void
	 */
	public function add_to_etch_global( array $data ) {
		$this->prepare_etch_global_data( $data );
	}

	/**
	 * Function to add dependencies to the global script.
	 *
	 * @param array<string> $dependencies An array of script handles that the global script depends on.
	 * @return void
	 */
	public function add_script_dependencies( array $dependencies ) {
		global $wp_scripts;

		if ( isset( $wp_scripts->registered[ $this->script_handle ] ) ) {
			// Get current dependencies
			$current_deps = $wp_scripts->registered[ $this->script_handle ]->deps;

			// Merge and unique the dependencies
			$wp_scripts->registered[ $this->script_handle ]->deps = array_unique(
				array_merge( $current_deps, $dependencies )
			);
		}
	}

	/**
	 * Prepares data to be added to the etchGlobal object.
	 *
	 * @param array<string, mixed> $new_data An associative array of data to be added to etchGlobal.
	 * @return array<string, mixed> The combined data, ready to be added to etchGlobal.
	 */
	public function prepare_etch_global_data( array $new_data ): array {
		$existing_data = wp_cache_get( 'etch_global_data', 'etch' );
		if ( ! is_array( $existing_data ) ) {
			$existing_data = array();
		}
		$combined_data = array_merge( $existing_data, $new_data );
		wp_cache_set( 'etch_global_data', $combined_data, 'etch' );
		return $combined_data;
	}

	/**
	 * Remove the wp-site-blocks wrapper from an HTML string
	 *
	 * @param string $html The HTML string to remove the wrapper from.
	 * @return string Block template markup without the wp-site-blocks wrapper.
	 */
	public static function remove_wp_site_blocks_wrapper( string $html ): string {
		// If HTML is empty, just return an empty string
		if ( empty( $html ) ) {
			return '';
		}

		// Special case for simple HTML without a wp-site-blocks wrapper
		if ( false === strpos( $html, 'wp-site-blocks' ) ) {
			return $html;
		}

		$dom = new DOMDocument();

		// Configure to preserve entities and prevent manipulation
		$dom->preserveWhiteSpace = true;
		$dom->formatOutput = false;
		$dom->substituteEntities = false; // This prevents entity manipulation

		// Suppress warnings for malformed HTML and auto-fix it
		libxml_use_internal_errors( true );
		$dom->loadHTML(
			'<?xml encoding="utf-8" ?>' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOBLANKS
		);
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );
		$nodes = $xpath->query( '//div[contains(@class, "wp-site-blocks")]' );

		// PHPStan-safe check for query result and item access
		if ( $nodes && $nodes->length > 0 ) {
			$wrapper = $nodes->item( 0 );

			if ( $wrapper ) {
				$inner_html = '';
				foreach ( $wrapper->childNodes as $child ) {
					$inner_html .= $dom->saveHTML( $child );
				}
				return $inner_html;
			}
		}

		// If no wrapper found or couldn't access inner HTML, return original HTML
		return $html;
	}
}
