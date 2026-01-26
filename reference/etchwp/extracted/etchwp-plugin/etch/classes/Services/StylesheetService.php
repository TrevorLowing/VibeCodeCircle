<?php
/**
 * StylesheetService.php
 *
 * This file contains the StylesheetService class which provides methods for managing stylesheets.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Services
 */

declare(strict_types=1);
namespace Etch\Services;

use Etch\Helpers\Migration;
use Etch\Preprocessor\Utilities\CssPreprocessor;
use Etch\Traits\Singleton;
use WP_Error;

/**
 * StylesheetService
 *
 * This class provides methods for managing stylesheets.
 *
 * @phpstan-type GlobalStylesheet array{
 *   name: string,
 *   css: string,
 * }
 *
 * @phpstan-type GlobalStylesheetObject array<string, GlobalStylesheet>
 *
 * @package Etch\RestApi\Services
 */
class StylesheetService {

	use Singleton;

	/**
	 * Option name where stylesheets are stored.
	 *
	 * @var string
	 */
	private $option_name = 'etch_global_stylesheets';

	/**
	 * Initialize the StylesheetService.
	 *
	 * @return void
	 */
	public function init() {
		// Migrate old REM functions if necessary.
		Migration::run_once(
			'migrate_rem_functions_stylesheets',
			function () {
				$this->migrate_rem_functions();
			}
		);

		// Initialization code if needed.
		$this->ensure_default_stylesheet();

		if ( ! isset( $_GET['etch'] ) || 'magic' !== $_GET['etch'] ) {
			add_action( 'wp_head', array( $this, 'enqueue_stylesheets_inline' ), 99 );
		}

		add_filter( 'block_editor_settings_all', array( $this, 'enqueue_stylesheets_block_editor' ) );
	}


	/**
	 * Enqueue global stylesheets inline.
	 *
	 * @return void
	 */
	public function enqueue_stylesheets_inline(): void {
		$stylesheets = $this->get_stylesheets();
		if ( is_array( $stylesheets ) ) {
			foreach ( $stylesheets as $stylesheetId => $stylesheet ) {
				if ( ! empty( $stylesheet['css'] ) ) {
					$id = sanitize_title( $stylesheet['name'] ) . $stylesheetId;
					$processed_css = CssPreprocessor::preprocess_css( $stylesheet['css'], '' );

					printf( '<style type="text/css" id="%s">%s</style>', esc_attr( $id ), $processed_css ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			}
		}
	}

	/**
	 * Enqueue global stylesheets in the block editor.
	 *
	 * @param array{ styles?: array<array{css: string}> } $editor_settings The current editor settings.
	 *
	 * @return array{ styles: array<array{css: string}> } Modified editor settings with global stylesheets included.
	 */
	public function enqueue_stylesheets_block_editor( $editor_settings ) {
		$stylesheets = $this->get_stylesheets();
		if ( is_array( $stylesheets ) ) {

			if ( ! isset( $editor_settings['styles'] ) ) {
				$editor_settings['styles'] = array();
			}

			foreach ( $stylesheets as $stylesheet ) {
				if ( ! empty( $stylesheet['css'] ) ) {
					$processed_css = CssPreprocessor::preprocess_css( $stylesheet['css'], '' );

					// Add the custom CSS to the editor settings
					$editor_settings['styles'][] = array(
						'css' => $processed_css,
					);
				}
			}
		}
		return $editor_settings;
	}


	/**
	 * Ensure there is at least one default stylesheet.
	 *
	 * @return void
	 */
	private function ensure_default_stylesheet() {
		$stylesheets = get_option( $this->option_name, array() );
		if ( empty( $stylesheets ) ) {
			$default_stylesheet = array(
				'name' => 'Main',
				'css'  => '/* Add your global styles here */',
			);
			$stylesheets = array( 'default' => $default_stylesheet );
			update_option( $this->option_name, $stylesheets );
		}
	}

	/**
	 * Retrieve all stylesheets.
	 *
	 * @return GlobalStylesheetObject
	 */
	public function get_stylesheets() {
		$stylesheets = get_option( $this->option_name, array() );

		if ( ! is_array( $stylesheets ) ) {
			$stylesheets = array();
		}

		return $stylesheets;
	}

	/**
	 * Update multiple global stylesheets.
	 *
	 * @param GlobalStylesheetObject $new_styles New styles to save.
	 * @return void
	 */
	public function update_stylesheets( $new_styles ) {
		update_option( $this->option_name, $new_styles );
	}

	/**
	 * Create a new stylesheet.
	 *
	 * @param GlobalStylesheet $stylesheet The stylesheet data.
	 * @return string|WP_Error The ID of the created stylesheet or a WP_Error object on failure.
	 */
	public function create_stylesheet( $stylesheet ) {
		$stylesheets = get_option( $this->option_name, array() );

		if ( ! is_array( $stylesheets ) ) {
			$stylesheets = array();
		}

		// Generate a unique ID for the new stylesheet.
		$id = substr( uniqid(), -7 );
		$new_stylesheet = array(
			'name' => sanitize_text_field( $stylesheet['name'] ),
			'css'  => $stylesheet['css'],
		);

		$stylesheets[ $id ] = $new_stylesheet;
		update_option( $this->option_name, $stylesheets );

		return $id;
	}

	/**
	 * Update an existing stylesheet.
	 *
	 * @param string           $id         The ID of the stylesheet to update.
	 * @param GlobalStylesheet $stylesheet The updated stylesheet data.
	 * @return void|WP_Error
	 */
	public function update_stylesheet( $id, $stylesheet ) {
		$stylesheets = get_option( $this->option_name, array() );

		if ( ! is_array( $stylesheets ) || ! isset( $stylesheets[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Stylesheet not found', array( 'status' => 404 ) );
		}

		$stylesheets[ $id ]['name'] = sanitize_text_field( $stylesheet['name'] );
		$stylesheets[ $id ]['css'] = $stylesheet['css'];

		update_option( $this->option_name, $stylesheets );
	}

	/**
	 * Delete a stylesheet by ID.
	 *
	 * @param string $id The ID of the stylesheet to delete.
	 * @return void|WP_Error
	 */
	public function delete_stylesheet( $id ) {
		$stylesheets = get_option( $this->option_name, array() );

		if ( ! is_array( $stylesheets ) || ! isset( $stylesheets[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Stylesheet not found', array( 'status' => 404 ) );
		}

		unset( $stylesheets[ $id ] );
		update_option( $this->option_name, $stylesheets );
	}

	/**
	 * Migrate old REM functions in stylesheets to the new format.
	 *
	 * @return void
	 */
	private function migrate_rem_functions(): void {
		$stylesheets = $this->get_stylesheets();

		foreach ( $stylesheets as $id => $stylesheet ) {
			$updated_css = preg_replace_callback(
				'/(?<![a-zA-Z0-9_-])rem\(/',
				fn() => 'to-rem(',
				$stylesheet['css']
			) ?? $stylesheet['css'];

			$stylesheets[ $id ]['css'] = $updated_css;
		}

		$this->update_stylesheets( $stylesheets );
	}
}
