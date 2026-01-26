<?php
/**
 * Script Manager for Preprocessor system
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Registry;

/**
 * ScriptManager class for the Preprocessor system.
 *
 * Collects scripts during block processing and renders them in wp_head.
 */
class ScriptRegister {

	/**
	 * Global script registry to collect scripts during block processing.
	 * Uses script hash as key and base64 encoded script as value.
	 *
	 * @var array<string, string>
	 */
	private static array $scripts = array();

	/**
	 * Constructor for the ScriptManager class.
	 *
	 * Initializes the script manager by hooking into wp_head.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'enqueue_scripts' ), 99 );
	}

	/**
	 * Register a script to be enqueued.
	 * Called during block processing when script data is found.
	 *
	 * @param string $script_base64 Base64 encoded script content.
	 * @return void
	 */
	public static function register_script( string $script_base64 ): void {
		if ( empty( $script_base64 ) ) {
			return;
		}

		$script_id = self::generate_script_id( $script_base64 );

		if ( ! isset( self::$scripts[ $script_id ] ) ) {
			self::$scripts[ $script_id ] = $script_base64;
		}
	}

	/**
	 * Decode the base64 string to actual JS script.
	 *
	 * @param string $script_base64 Script to be decoded.
	 * @return string The JS code.
	 */
	private static function decode_script( string $script_base64 ): string {
		return base64_decode( $script_base64 );
	}

	/**
	 * Creates a short hash based on the base64 string and add a little prefix.
	 *
	 * @param string $script_base64 String that will be used to create a short hash.
	 * @return string Unique ID for the script.
	 */
	private static function generate_script_id( string $script_base64 ): string {
		$hash = base64_encode( hash( 'sha256', $script_base64, true ) );
		$hash = strtr( $hash, '+/', '-_' );
		return 'etch-script-' . strtolower( substr( $hash, 0, 5 ) );
	}

	/**
	 * Enqueue all registered scripts in the head.
	 *
	 * @return void
	 */
	public function enqueue_scripts(): void {
		foreach ( self::$scripts as $script_id => $script_base64 ) {
			$script_inline = self::decode_script( $script_base64 );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			printf( '<script id="%s" type="module" defer>%s</script>', esc_attr( $script_id ), $script_inline );
		}

		// Clear scripts after output to prevent duplicate output
		self::$scripts = array();
	}

	/**
	 * Get all registered scripts (for testing purposes).
	 *
	 * @return array<string, string> Array of script ID => base64 content.
	 */
	public static function get_registered_scripts(): array {
		return self::$scripts;
	}

	/**
	 * Clear all registered scripts (for testing purposes).
	 *
	 * @return void
	 */
	public static function clear_registered_scripts(): void {
		self::$scripts = array();
	}
}
