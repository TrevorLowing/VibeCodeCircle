<?php
/**
 * CssPreprocessor class for Etch plugin
 *
 * This file contains the CssPreprocessor preprocssing css for output.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities;

use Etch\Helpers\Flag;

/**
 * CssPreprocessor class for handling CSS preprocessing.
 *
 * Supports various CSS preprocessing tasks such as variable replacement,
 * nesting, and other transformations.
 * All methods are static for easy access across the preprocessor system.
 */
class CssPreprocessor {

	/**
	 * Preprocess CSS by applying various transformations.
	 *
	 * @param string $css CSS string to preprocess.
	 * @param string $selector Base selector for nested rules.
	 * @return string Preprocessed CSS.
	 */
	public static function preprocess_css( string $css, string $selector ): string {
		$css = self::sanitize_css( $css );
		$css = self::parse_scss_like_syntax( $css, $selector );
		$css = self::convert_rem_functions( $css );

		return $css;
	}


	/**
	 * Normalize CSS string.
	 *
	 * @param string $css CSS string to sanitize.
	 * @return string Sanitized CSS.
	 */
	private static function sanitize_css( string $css ): string {
		// ! Important: sanitize text field is WAY to aggressive here
		// ! Additionally wp_kses and wp_strip_all_tags are also too harsh and kill modern ranges f.e.
		// ! Essentially we just want to paste the user CSS as is
		// ! But we for sure need to ensure we don't break out of <style>
		return str_replace( '</style>', '', $css );

		// ! Had a quick talk with Matteo about this, and we both agree that this is good enough for now
		// ! We want to have an independent security audit, so we may need to revisit if this is truly the best approach
	}

	/**
	 * Parse SCSS-like syntax for BEM elements and modifiers.
	 * Replaces &__ and &-- with the selector followed by __ or --.
	 *
	 * @param string $css CSS to parse.
	 * @param string $selector Base selector.
	 * @return string Parsed CSS.
	 */
	private static function parse_scss_like_syntax( string $css, string $selector ): string {
		// Handle BEM element syntax: &__element -> .selector__element

		$types = array(
			'__',
			'--',
			'_',
			'-',
		);

		$parsed_css = $css;

		foreach ( $types as $type ) {
			$result = preg_replace( '/&' . $type . '([a-zA-Z0-9_-]+)/', $selector . $type . '$1', $parsed_css );
			$parsed_css = EtchTypeAsserter::to_string( $result, $parsed_css );
		}

		return $parsed_css;
	}

	/**
	 * Converts e.g. to-rem(500px) to its corresponding rem value at 100% root font size
	 *
	 * @param string $css CSS to parse.
	 * @return string Parsed CSS.
	 */
	private static function convert_rem_functions( string $css ): string {
		// We are assuming that the user Root Font Size is 100% which make the font-size as 16px
		$base_font_size = 16;
		return preg_replace_callback(
			'/to-rem\(\s*([\d.]+)\s*px\s*\)/',
			function ( $matches ) use ( $base_font_size ) {
				$px_value  = (float) $matches[1];
				$rem_value = $px_value / $base_font_size;

				// Avoid unnecessary trailing zeros (e.g., "1.0rem" → "1rem")
				return rtrim( rtrim( number_format( $rem_value, 4, '.', '' ), '0' ), '.' ) . 'rem';
			},
			$css
		) ?? $css;
	}
}
