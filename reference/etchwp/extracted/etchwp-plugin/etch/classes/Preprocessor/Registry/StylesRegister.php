<?php
/**
 * Styles Register for Preprocessor system
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Registry;

use Etch\Helpers\Logger;
use Etch\Preprocessor\Utilities\CssPreprocessor;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * StylesRegister class for the Preprocessor system.
 *
 * Collects styles during block processing and renders them in wp_head.
 */
class StylesRegister {

	/**
	 * Global style registry to collect styles during block processing.
	 * Uses style ID as key.
	 *
	 * @var array<string, bool>
	 */
	private static array $page_styles = array();

	/**
	 * All registered styles from database.
	 *
	 * @var array<string, array{type: string, selector: string, css: string, readonly?: bool}>|null
	 */
	private static ?array $all_styles = null;

	/**
	 * Option name for style storage.
	 *
	 * @var string
	 */
	private const STYLES_OPTION_NAME = 'etch_styles';

	/**
	 * Constructor for the StylesRegister class.
	 *
	 * Initializes the styles register by hooking into wp_head.
	 */
	public function __construct() {
		add_action( 'wp_head', array( $this, 'render_frontend_styles' ), 99 );
	}

	/**
	 * Register styles to be rendered.
	 * Called during block processing when style data is found.
	 *
	 * @param array<string> $style_ids Array of style identifiers to register.
	 * @return void
	 */
	public static function register_styles( array $style_ids ): void {
		foreach ( $style_ids as $style_id ) {
			if ( ! empty( $style_id ) && is_string( $style_id ) ) {
				self::$page_styles[ $style_id ] = true;
			}
		}
	}

	/**
	 * Get all registered styles from database.
	 *
	 * @return array<string, array{type: string, selector: string, css: string, readonly?: bool}>
	 */
	private static function get_all_styles(): array {
		if ( null === self::$all_styles ) {
			$styles = get_option( self::STYLES_OPTION_NAME, array() );
			self::$all_styles = is_array( $styles ) ? $styles : array();
		}

		return self::$all_styles;
	}

	/**
	 * Collect mandatory styles that should always be loaded.
	 *
	 * @return void
	 */
	private static function collect_mandatory_styles(): void {
		if ( empty( self::$page_styles ) ) {
			return;
		}

		$all_styles = self::get_all_styles();
		foreach ( $all_styles as $style_id => $style ) {
			// Check if the selector of the style is ":root"
			if ( ':root' === $style['selector'] ) {
				self::$page_styles[ $style_id ] = true;
			}
		}
	}

	/**
	 * Validate style structure.
	 *
	 * @param mixed $style Style data.
	 * @return bool True if valid style.
	 */
	private static function is_valid_style( mixed $style ): bool {
		return is_array( $style )
			&& ( isset( $style['selector'] ) || isset( $style['name'] ) )
			&& isset( $style['css'] )
			&& '' !== trim( $style['css'] );
	}

	/**
	 * Compile CSS rules for frontend display.
	 *
	 * @param array<string> $style_ids Array of style identifiers to compile.
	 * @return string CSS rules.
	 */
	private static function compile_style_rules( array $style_ids ): string {
		$all_styles = self::get_all_styles();
		$output = array();

		foreach ( array_unique( $style_ids ) as $style_id ) {
			if ( ! isset( $all_styles[ $style_id ] ) || ! self::is_valid_style( $all_styles[ $style_id ] ) ) {
				continue;
			}

			$style = $all_styles[ $style_id ];
			$selector = $style['selector'];
			$css = CssPreprocessor::preprocess_css( $style['css'], $selector );
			$output[] = sprintf( '%s { %s }', $selector, $css );
		}

		return implode( PHP_EOL, $output );
	}

	/**
	 * Render collected styles in document head.
	 *
	 * @return void
	 */
	public function render_frontend_styles(): void {
		// Collect mandatory styles first
		self::collect_mandatory_styles();

		if ( empty( self::$page_styles ) ) {
			return;
		}

		$style_ids = array_keys( self::$page_styles );
		$style_rules = self::compile_style_rules( $style_ids );

		if ( '' !== $style_rules ) {
			printf( '<style id="etch-page-styles">%s</style>', $style_rules ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// Clear styles after output to prevent duplicate output
		self::$page_styles = array();
	}

	/**
	 * Get all registered page styles (for testing purposes).
	 *
	 * @return array<string, bool> Array of registered style IDs.
	 */
	public static function get_registered_styles(): array {
		return self::$page_styles;
	}

	/**
	 * Clear all registered styles (for testing purposes).
	 *
	 * @return void
	 */
	public static function clear_registered_styles(): void {
		self::$page_styles = array();
	}

	/**
	 * Get all styles from database for dynamic style matching.
	 *
	 * @return array<string, array{type: string, selector: string, css: string, readonly?: bool}>
	 */
	public static function get_all_styles_for_matching(): array {
		return self::get_all_styles();
	}

	/**
	 * Find style IDs that match the given parsed attributes string.
	 * This is used for dynamic style resolution when component properties affect selectors.
	 * Uses a simplified approach that's safer and more reliable.
	 *
	 * @param string $parsed_attributes_string The parsed HTML attributes string.
	 * @return array<string> Array of matching style IDs.
	 */
	public static function find_matching_styles( string $parsed_attributes_string ): array {
		$all_styles = self::get_all_styles();
		$matching_style_ids = array();

		if ( empty( $parsed_attributes_string ) ) {
			return $matching_style_ids;
		}

		// Extract attributes from the parsed string
		$attributes = self::extract_attributes_from_string( $parsed_attributes_string );

		foreach ( $all_styles as $style_id => $style ) {
			if ( ! self::is_valid_style( $style ) ) {
				continue;
			}

			$selector = $style['selector'];

			// Check if this style matches any of our attributes
			if ( self::selector_matches_attributes( $selector, $attributes ) ) {
				$matching_style_ids[] = $style_id;
			}
		}

		return $matching_style_ids;
	}

	/**
	 * Extract attributes from a parsed attributes string.
	 *
	 * @param string $attributes_string The HTML attributes string.
	 * @return array<string, string> Array of attribute name => value pairs.
	 */
	private static function extract_attributes_from_string( string $attributes_string ): array {
		$attributes = array();

		// Match attribute="value" or attribute='value' patterns
		preg_match_all( '/(\w+(?:-\w+)*)=["\']([^"\']*)["\']/', $attributes_string, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$attributes[ $match[1] ] = $match[2];
		}

		return $attributes;
	}

	/**
	 * Check if a selector matches any of the given attributes using simplified logic.
	 *
	 * @param string                $selector CSS selector.
	 * @param array<string, string> $attributes Array of attribute name => value pairs.
	 * @return bool True if the selector matches any attribute.
	 */
	private static function selector_matches_attributes( string $selector, array $attributes ): bool {
		foreach ( $attributes as $attr_name => $attr_value ) {
			// Handle class attribute - look for class names in selector
			if ( 'class' === $attr_name ) {
				$class_names = explode( ' ', $attr_value );
				foreach ( $class_names as $class_name ) {
					$class_name = trim( $class_name );
					if ( ! empty( $class_name ) && false !== strpos( $selector, '.' . $class_name ) ) {
						return true;
					}
				}
			} elseif ( 'id' === $attr_name ) {
				$id = trim( $attr_value );
				if ( ! empty( $id ) && false !== strpos( $selector, '#' . $id ) ) {
					return true;
				}
			} elseif ( false !== strpos( $selector, $attr_name ) ) {
				return true;
			}
		}

		return false;
	}
}
