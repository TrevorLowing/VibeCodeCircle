<?php
/**
 * Handles persistence of dynamically registered Etch styles.
 *
 * @package Etch\Filters
 */

declare(strict_types=1);

namespace Etch\Filters;

use Etch\Traits\Singleton;

/**
 * Manages the registration and persistence of Etch styles added via hooks.
 */
class CustomStyles {

	use Singleton;

	/**
	 * Option name for style storage.
	 *
	 * @var string
	 */
	private const STYLES_OPTION_NAME = 'etch_styles';

	/**
	 * Flag to ensure persistence logic runs only once per request.
	 *
	 * @var bool
	 */
	private bool $did_persist_run = false;

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook into WordPress initialization, but after most theme/plugin setup.
		add_action( 'init', array( $this, 'register_and_persist_styles' ), 20 );
	}

	/**
	 * Runs the action hook for registering styles and persists them to the database if changed.
	 *
	 * This method fetches the current styles, allows other code to modify them via an action hook,
	 * and then saves the result back to the options table if any changes were made.
	 *
	 * @return void
	 */
	public function register_and_persist_styles(): void {
		// Prevent running multiple times in a single request.
		if ( $this->did_persist_run ) {
			return;
		}
		$this->did_persist_run = true;

		$current_styles = get_option( self::STYLES_OPTION_NAME, array() );
		if ( ! is_array( $current_styles ) ) {
			$current_styles = array();
		}

		// Make a copy to compare later.
		$original_styles = $current_styles;

		/**
		 * Action hook to register persistent Etch global styles.
		 *
		 * Callbacks should add their style definitions to the provided array.
		 * The array is passed by reference, so modifications are direct.
		 * Each style should be an array keyed by a unique ID, containing:
		 * - 'type': (string) 'class', 'id', 'element', or 'attribute'.
		 * - 'name': (string) The selector name (e.g., class name, ID, element tag, attribute string like 'data-attr=value').
		 * - 'css': (string) The CSS rules.
		 * - 'readonly': (bool, optional) If true, indicates the style might be non-editable in UI.
		 *
		 * @since x.x.x
		 *
		 * @param array<string, array{type: string, name: string, css: string, readonly?: bool}> &$styles The array of Etch global styles, passed by reference.
		 */
		do_action_ref_array( 'etch/register_custom_styles', array( &$current_styles ) );

		// Only update the option if something actually changed.
		if ( $current_styles !== $original_styles ) {
			// Simple validation: ensure the result is still an array before saving.
			if ( is_array( $current_styles ) ) {
				update_option( self::STYLES_OPTION_NAME, $current_styles );
			}
		}
	}
}
