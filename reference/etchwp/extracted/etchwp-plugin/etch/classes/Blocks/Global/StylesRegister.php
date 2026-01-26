<?php
/**
 * Styles Register for Blocks system
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Global;

use Etch\Helpers\Logger;
use Etch\Blocks\Utilities\CssProcessor;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * StylesRegister class for the Blocks system.
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
	 * Check if a class value contains a dynamic expression pattern.
	 *
	 * @param string $class_value The class attribute value to check.
	 * @return bool True if class contains dynamic expression pattern ({something.}).
	 */
	public static function has_dynamic_class( string $class_value ): bool {
		// Check if it contains {something. pattern
		return (bool) preg_match( '/\{[^}]*\./', $class_value );
	}

	/**
	 * Register block styles including dynamic styles from resolved attributes.
	 * Combines original styles with dynamically matched styles based on class attribute.
	 *
	 * @param array<string>        $original_styles Original style IDs from block attributes.
	 * @param array<string, mixed> $original_attributes Original attributes (before resolution) to check for dynamic patterns.
	 * @param array<string, mixed> $resolved_attributes Resolved attributes after dynamic expression processing.
	 * @return void
	 */
	public static function register_block_styles( array $original_styles, array $original_attributes, array $resolved_attributes ): void {
		$all_style_ids = $original_styles;

		// Check if class attribute had dynamic expressions and find matching styles
		if ( isset( $original_attributes['class'] ) && is_string( $original_attributes['class'] ) && self::has_dynamic_class( $original_attributes['class'] ) ) {
			$dynamic_styles = self::find_dynamic_styles_from_attribute( 'class', $resolved_attributes );
			if ( ! empty( $dynamic_styles ) ) {
				$all_style_ids = array_merge( $all_style_ids, $dynamic_styles );
			}
		}

		// Register all styles at once
		if ( ! empty( $all_style_ids ) ) {
			self::register_styles( $all_style_ids );
		}
	}

	/**
	 * Find dynamic styles that match a specific attribute from resolved attributes.
	 *
	 * @param string               $attr_name The attribute name to check (e.g., 'class').
	 * @param array<string, mixed> $resolved_attributes Resolved attributes after dynamic expression processing.
	 * @return array<string> Array of matching style IDs.
	 */
	public static function find_dynamic_styles_from_attribute( string $attr_name, array $resolved_attributes ): array {
		if ( ! isset( $resolved_attributes[ $attr_name ] ) ) {
			return array();
		}

		$attr_value = $resolved_attributes[ $attr_name ];
		// Ensure attribute value is a string for type safety
		$attr_value_string = EtchTypeAsserter::to_string( $attr_value );
		$attributes = array( $attr_name => $attr_value_string );

		return self::find_matching_styles_from_attributes( $attributes );
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
			$css = CssProcessor::preprocess_css( $style['css'], $selector );
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
	 * Find style IDs that match the given attributes array.
	 * This is used for dynamic style resolution when component properties affect selectors.
	 *
	 * @param array<string, string> $attributes Array of attribute name => value pairs.
	 * @return array<string> Array of matching style IDs.
	 */
	private static function find_matching_styles_from_attributes( array $attributes ): array {
		$all_styles = self::get_all_styles();
		$matching_style_ids = array();

		if ( empty( $attributes ) ) {
			return $matching_style_ids;
		}

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
	 * Check if a selector matches any of the given attributes.
	 * Currently only supports class attribute matching.
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
			}
		}

		return false;
	}
}
