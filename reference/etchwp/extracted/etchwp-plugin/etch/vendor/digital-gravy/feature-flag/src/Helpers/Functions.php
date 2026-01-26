<?php
/**
 * @package DigitalGravy\FeatureFlag\Helpers
 */

namespace DigitalGravy\FeatureFlag\Helpers;

/**
 * Helper functions for the Feature Flag library
 */
class Functions {
	/**
	 * Escapes output for safe display
	 *
	 * @param mixed $value The value to escape.
	 * @return string The escaped value
	 */
	public static function escape_output( $value ): string {
		if ( is_string( $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Using htmlspecialchars for non-WP context.
			return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
		}
		return '';
	}
}
