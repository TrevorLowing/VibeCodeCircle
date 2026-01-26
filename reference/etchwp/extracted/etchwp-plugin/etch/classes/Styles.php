<?php
/**
 * Etch Styles Class
 *
 * Handles collection and rendering of block styles for the Etch plugin.
 *
 * @package Etch
 */

namespace Etch;

use Etch\Helpers\Migration;
use Etch\Traits\Singleton;
use WP_Error;

/**
 * Styles Handler
 *
 * Manages block style collection, storage, and rendering for frontend and editor.
 */
class Styles {

	use Singleton;

	/**
	 * Option name for style storage.
	 *
	 * @var string
	 */
	private const STYLES_OPTION_NAME = 'etch_styles';

	/**
	 * All registered styles.
	 *
	 * @var array<string, array{type: string, selector: string, css: string, readonly?: bool}>
	 */
	private array $cached_styles = array();

	/**
	 * Initialize hooks and default styles
	 *
	 * @return void
	 */
	public function init() {
		// Migrate old REM functions if necessary.
		Migration::run_once(
			'migrate_rem_functions_styles',
			function () {
				$this->migrate_rem_functions();
			}
		);

		$initialization_result = $this->initialize_default_styles();
		if ( is_wp_error( $initialization_result ) ) {
			error_log( 'Etch Styles Error: ' . $initialization_result->get_error_message() );
		}
	}


	/**
	 * Initialize default system styles
	 *
	 * @return bool|WP_Error True on success, error on failure
	 */
	private function initialize_default_styles(): bool|WP_Error {
		$default_styles = array(
			'etch-section-style' => array(
				'collection' => 'default',
				'selector' => ':where([data-etch-element="section"])',
				'css' => esc_html( "inline-size: 100%;\n  display: flex;\n  flex-direction: column;\n  align-items: center;" ),
				'readonly' => true,
				'type' => 'element',
			),
			'etch-container-style' => array(
				'collection' => 'default',
				'selector' => ':where([data-etch-element="container"])',
				'css' => esc_html( "inline-size: 100%;\n  display: flex;\n  flex-direction: column;\n  max-width: var(--content-width, 1366px);\n  align-self: center; margin-inline: auto;" ),
				'readonly' => true,
				'type' => 'element',
			),
			'etch-flex-div-style' => array(
				'collection' => 'default',
				'selector' => ':where([data-etch-element="flex-div"])',
				'css' => esc_html( "inline-size: 100%;\n  display: flex;\n  flex-direction: column;" ),
				'readonly' => true,
				'type' => 'element',
			),
			'etch-iframe-style' => array(
				'collection' => 'default',
				'selector' => ':where([data-etch-element="iframe"])',
				'css' => esc_html( "inline-size: 100%;\n  height: auto;\n aspect-ratio: 16/9;" ),
				'readonly' => true,
				'type' => 'element',
			),
			'etch-global-variable-style' => array(
				'collection' => 'default',
				'selector' => ':root',
				'css' => '',
				'readonly' => false,
				'type' => 'element',
			),
		);

		$existing_styles = $this->get_all_styles();
		$needs_update = false;

		foreach ( $default_styles as $key => $style ) {
			// if the styles change or the key does not exist create it
			if ( ! isset( $existing_styles[ $key ] ) ) {
				$needs_update = true;
				$existing_styles[ $key ] = $style;
			}
			// if the style exists but changed, update it
			if ( $existing_styles[ $key ] != $style ) {
				// Do not update the readonly = false styles TODO: We need to improve this to still update everything but the css
				if ( isset( $existing_styles[ $key ]['readonly'] ) && true === $existing_styles[ $key ]['readonly'] ) {
					$needs_update = true;
					$existing_styles[ $key ] = $style;
				}
			}
		}

		if ( $needs_update ) {
			return $this->persist_styles( $existing_styles );
		}

		return true;
	}

	/**
	 * Get all registered styles from cache or database
	 *
	 * @return array<string, array{type: string, selector: string, css: string, readonly?: bool}>
	 */
	private function get_all_styles(): array {
		if ( empty( $this->cached_styles ) ) {
			$styles = get_option( self::STYLES_OPTION_NAME, array() );
			$this->cached_styles = is_array( $styles ) ? $styles : array();
		}

		return $this->cached_styles;
	}

	/**
	 * Save styles to database
	 *
	 * @param array<string, array{type: string, selector: string, css: string, readonly?: bool}> $styles Styles array to save.
	 * @return bool|WP_Error True on success, error on failure
	 */
	private function persist_styles( array $styles ): bool|WP_Error {
		$this->cached_styles = $styles;
		if ( ! update_option( self::STYLES_OPTION_NAME, $styles ) ) {
			return new WP_Error( 'persist_failed', 'Failed to save styles to database' );
		}
		return true;
	}


	/**
	 * Migrate old REM functions in styles to new format
	 *
	 * @return void
	 */
	private function migrate_rem_functions(): void {
		$styles = $this->get_all_styles();

		foreach ( $styles as $key => $style ) {
			if ( empty( $style['css'] ) ) {
				continue;
			}

			$updated_css = preg_replace_callback(
				'/(?<![a-zA-Z0-9_-])rem\(/',
				fn() => 'to-rem(',
				$style['css']
			) ?? $style['css'];

			$styles[ $key ]['css'] = $updated_css;
		}

		$this->persist_styles( $styles );
	}
}
