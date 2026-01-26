<?php
/**
 * Etch Flag file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

use DigitalGravy\FeatureFlag\FeatureFlagStore;
use DigitalGravy\FeatureFlag\Exception\Flag_Key_Not_Found;
use DigitalGravy\FeatureFlag\Storage\Exception\FileNotFoundException;
use DigitalGravy\FeatureFlag\Storage\JsonFile;
use Etch\Plugin;

/**
 * Etch Flag class.
 */
class Flag {

	/**
	 * The flag store.
	 *
	 * @var FeatureFlagStore|null
	 */
	private static ?FeatureFlagStore $flag_store = null;

	/**
	 * Initialize the flag store.
	 *
	 * @return void
	 * @throws FlagsNotInMainFileException If there are flags in flags.dev.json or flags.user.json that are not in flags.json.
	 */
	public static function init() {
		// flags.json contains the feature flags that users will receive.
		$flags_prod = ( new JsonFile( ETCH_PLUGIN_DIR . 'config/flags.json' ) )->get_flags();
		$error_message = '';
		try {
			$flags_dev = ( new JsonFile( ETCH_PLUGIN_DIR . 'config/flags.dev.json' ) )->get_flags();
			// flags.dev.json contains the feature flags that we use locally, and can override flags in flags.json.
		} catch ( FileNotFoundException $e ) {
			$flags_dev = array(); // This file shouldn't exist on production, so catch the exception.
		}
		try {
			// flags.user.json allows the user to override any flag via a JSON file in the uploads directory.
			$flags_user = ( new JsonFile( Plugin::get_dynamic_uploads_dir() . '/flags.user.json' ) )->get_flags();
		} catch ( FileNotFoundException $e ) {
			$flags_user = array(); // It's ok if this file doesn't exist.
		}
		// Make sure $flags_dev doesn't contain any flags that are already in $flags_prod.
		$flags_dev_not_in_prod = array_diff_key( $flags_dev, $flags_prod );
		if ( ! empty( $flags_dev_not_in_prod ) ) {
			$error_message .=
				sprintf(
					"%s: The following flags.dev.json flags will be ignored because they are not in flags.json:\n%s\n",
					__METHOD__,
					esc_html( implode( "\n", array_keys( $flags_dev_not_in_prod ) ) )
				);
		}
		$flags_dev = array_diff_key( $flags_dev, $flags_dev_not_in_prod );
		// Make sure $flags_user doesn't contain any flags that are already in $flags_prod.
		$flags_user_not_in_prod = array_diff_key( $flags_user, $flags_prod );
		if ( ! empty( $flags_user_not_in_prod ) ) {
			$error_message .=
				sprintf(
					"%s: The following flags.user.json flags will be ignored because they are not in flags.json:\n%s\n",
					__METHOD__,
					esc_html( implode( "\n", array_keys( $flags_user_not_in_prod ) ) )
				);
		}
		$flags_user = array_diff_key( $flags_user, $flags_user_not_in_prod );
		// All flags are merged into a single store.
		$flag_store = new FeatureFlagStore( $flags_prod, $flags_dev, $flags_user );
		self::$flag_store = $flag_store;
		// This would have been a simple Logger::log() call, but Logger isn't available yet. This is a workaround.
		if ( ! empty( $error_message ) ) {
			throw new FlagsNotInMainFileException( esc_html( $error_message ) );
		}
	}

	/**
	 * Inject the feature flags into the Etch global.
	 *
	 * @param EtchGlobal $etch_global The Etch global instance.
	 * @return void
	 */
	public static function inject_flag_into_etch_global( $etch_global ): void {
		$etch_global->add_to_etch_global(
			array(
				'featureFlags' => self::get_flags(),
			)
		);
	}

	/**
	 * Checks if a development flag is on.
	 * A flag is on when the constant is defined and its value is true.
	 *
	 * @param string $flag_name The name of the flag to check.
	 * @return boolean
	 * @throws Flag_Key_Not_Found If the flag key is not found.
	 */
	public static function is_on( string $flag_name ): bool {
		if ( null === self::$flag_store ) {
			return false;
		}
		return self::$flag_store->is_on( $flag_name );
	}

	/**
	 * Get all flags.
	 *
	 * @return array<string, string>
	 */
	public static function get_flags(): array {
		return self::$flag_store ? self::$flag_store->get_flags() : array();
	}

	/**
	 * Check if the flag store has been initialized.
	 *
	 * @return bool
	 */
	public static function is_initialized(): bool {
		return null !== self::$flag_store;
	}
}
