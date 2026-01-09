<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Service for handling shortcode placeholder comments in HTML.
 *
 * Converts HTML comment placeholders (e.g., `<!-- VIBECODE_SHORTCODE shortcode_name -->`)
 * into Gutenberg shortcode blocks during deployment.
 *
 * @package VibeCode\Deploy\Services
 */
final class ShortcodePlaceholderService {
	/** @var string Configuration filename for shortcode rules. */
	public const CONFIG_FILENAME = 'vibecode-deploy-shortcodes.json';
	
	/**
	 * Get the placeholder prefix from settings or use default.
	 *
	 * @return string Placeholder prefix (e.g., 'VIBECODE_SHORTCODE').
	 */
	public static function get_placeholder_prefix(): string {
		$settings = \VibeCode\Deploy\Settings::get_all();
		$prefix = isset( $settings['placeholder_prefix'] ) && is_string( $settings['placeholder_prefix'] ) 
			? trim( (string) $settings['placeholder_prefix'] ) 
			: 'VIBECODE_SHORTCODE';
		return $prefix !== '' ? $prefix : 'VIBECODE_SHORTCODE';
	}

	/**
	 * Load shortcode configuration from build root.
	 *
	 * @param string $build_root Path to build root directory.
	 * @return array Configuration array or empty array if file not found/invalid.
	 */
	public static function load_config( string $build_root ): array {
		$path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . self::CONFIG_FILENAME;
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return array();
		}

		$raw = file_get_contents( $path );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array( '_error' => 'Invalid JSON in ' . self::CONFIG_FILENAME );
		}

		return $decoded;
	}

	/**
	 * Get validation mode from config or settings.
	 *
	 * @param array  $config   Configuration array.
	 * @param array  $settings  Plugin settings.
	 * @param string $key       Setting key to retrieve.
	 * @param string $fallback  Fallback value if not found.
	 * @return string Mode: 'warn' or 'fail'.
	 */
	public static function get_mode( array $config, array $settings, string $key, string $fallback = 'warn' ): string {
		$mode = '';

		if ( isset( $settings[ $key ] ) && is_string( $settings[ $key ] ) ) {
			$mode = strtolower( trim( (string) $settings[ $key ] ) );
		}

		if ( $mode === '' && isset( $config['defaults'] ) && is_array( $config['defaults'] ) ) {
			$defaults = $config['defaults'];
			if ( isset( $defaults[ $key ] ) && is_string( $defaults[ $key ] ) ) {
				$mode = strtolower( trim( (string) $defaults[ $key ] ) );
			}
		}

		if ( $mode !== 'fail' && $mode !== 'warn' ) {
			$mode = $fallback;
		}

		return $mode;
	}

	/**
	 * Check if a comment is a shortcode placeholder.
	 *
	 * Handles both raw comment text and full HTML comment format (<!-- ... -->).
	 *
	 * @param string $comment HTML comment text (with or without <!-- --> markers).
	 * @return bool True if comment is a placeholder.
	 */
	public static function is_placeholder_comment( string $comment ): bool {
		$comment = trim( $comment );
		if ( $comment === '' ) {
			return false;
		}
		// Strip HTML comment markers if present
		$comment = preg_replace( '/^<!--\s*(.*?)\s*-->$/s', '$1', $comment );
		$comment = trim( $comment );
		if ( $comment === '' ) {
			return false;
		}
		$prefix = self::get_placeholder_prefix();
		return stripos( $comment, $prefix ) === 0;
	}

	/**
	 * Parse a placeholder comment into shortcode name and attributes.
	 *
	 * @param string $comment HTML comment text.
	 * @return array Parsed data with 'ok', 'name', 'attrs' keys, or 'ok' => false on error.
	 */
	public static function parse_placeholder_comment( string $comment ): array {
		$comment = trim( $comment );
		// Strip HTML comment markers if present
		$comment = preg_replace( '/^<!--\s*(.*?)\s*-->$/s', '$1', $comment );
		$comment = trim( $comment );
		if ( ! self::is_placeholder_comment( $comment ) ) {
			return array( 'ok' => false );
		}

		$prefix = self::get_placeholder_prefix();
		$rest = preg_replace( '/^' . preg_quote( $prefix, '/' ) . '\b\s*/i', '', $comment );
		$rest = is_string( $rest ) ? trim( $rest ) : '';
		if ( $rest === '' ) {
			return array( 'ok' => false, 'error' => 'Missing shortcode name.' );
		}

		if ( ! preg_match( '/^([a-zA-Z0-9_\-]+)\b(.*)$/', $rest, $m ) ) {
			return array( 'ok' => false, 'error' => 'Unable to parse shortcode name.' );
		}

		$name = sanitize_key( (string) $m[1] );
		$attr_text = isset( $m[2] ) ? (string) $m[2] : '';
		$attrs = array();

		if ( $attr_text !== '' ) {
			if ( preg_match_all( '/([a-zA-Z0-9_\-]+)\s*=\s*"([^"]*)"/', $attr_text, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $pair ) {
					$key = isset( $pair[1] ) ? sanitize_key( (string) $pair[1] ) : '';
					$val = isset( $pair[2] ) ? sanitize_text_field( (string) $pair[2] ) : '';
					if ( $key === '' ) {
						continue;
					}
					$attrs[ $key ] = $val;
				}
			}
		}

		if ( $name === '' ) {
			return array( 'ok' => false, 'error' => 'Invalid shortcode name.' );
		}

		return array(
			'ok' => true,
			'name' => $name,
			'attrs' => $attrs,
		);
	}

	/**
	 * Build shortcode text from name and attributes.
	 *
	 * @param string $name  Shortcode name.
	 * @param array  $attrs Shortcode attributes.
	 * @return string Shortcode text (e.g., '[shortcode attr="value"]').
	 */
	public static function build_shortcode_text( string $name, array $attrs ): string {
		$name = sanitize_key( $name );
		if ( $name === '' ) {
			return '';
		}

		$out = '[' . $name;
		foreach ( $attrs as $k => $v ) {
			if ( ! is_string( $k ) ) {
				continue;
			}
			$key = sanitize_key( (string) $k );
			if ( $key === '' ) {
				continue;
			}
			$val = sanitize_text_field( is_string( $v ) ? $v : (string) $v );
			$val = str_replace( '"', '', $val );
			$out .= ' ' . $key . '="' . $val . '"';
		}
		$out .= ']';

		return $out;
	}

	/**
	 * Convert a placeholder comment to a Gutenberg shortcode block.
	 *
	 * @param string $comment            HTML comment text.
	 * @param string $project_slug_for_logs Project slug for logging.
	 * @return string|null Gutenberg shortcode block HTML or null on error.
	 */
	public static function comment_to_shortcode_block( string $comment, string $project_slug_for_logs = '' ): ?string {
		$parsed = self::parse_placeholder_comment( $comment );
		if ( empty( $parsed['ok'] ) ) {
			if ( self::is_placeholder_comment( $comment ) ) {
				$prefix = self::get_placeholder_prefix();
				Logger::error( 'Invalid ' . $prefix . ' placeholder.', array( 'comment' => $comment, 'error' => $parsed['error'] ?? '' ), $project_slug_for_logs );
			}
			return null;
		}

		$name = isset( $parsed['name'] ) && is_string( $parsed['name'] ) ? (string) $parsed['name'] : '';
		$attrs = isset( $parsed['attrs'] ) && is_array( $parsed['attrs'] ) ? $parsed['attrs'] : array();
		$shortcode = self::build_shortcode_text( $name, $attrs );
		if ( $shortcode === '' ) {
			return null;
		}

		return '<!-- wp:shortcode -->' . $shortcode . '<!-- /wp:shortcode -->';
	}

	public static function extract_placeholders_from_main( \DOMDocument $dom, \DOMNode $main ): array {
		$found = array();
		$invalid = array();

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( './/comment()', $main );
		if ( ! $nodes ) {
			return array( 'found' => array(), 'invalid' => array() );
		}

		foreach ( $nodes as $node ) {
			if ( ! ( $node instanceof \DOMComment ) ) {
				continue;
			}

			$text = trim( (string) $node->data );
			if ( $text === '' ) {
				continue;
			}

			if ( ! self::is_placeholder_comment( $text ) ) {
				continue;
			}

			$parsed = self::parse_placeholder_comment( $text );
			if ( empty( $parsed['ok'] ) ) {
				$invalid[] = $text;
				continue;
			}

			$name = isset( $parsed['name'] ) && is_string( $parsed['name'] ) ? (string) $parsed['name'] : '';
			if ( $name !== '' ) {
				$found[] = $name;
			}
		}

		return array(
			'found' => array_values( array_unique( $found ) ),
			'invalid' => $invalid,
		);
	}

	public static function normalize_shortcode_list( $items ): array {
		$out = array();
		if ( ! is_array( $items ) ) {
			return $out;
		}
		foreach ( $items as $it ) {
			if ( is_string( $it ) ) {
				$name = sanitize_key( $it );
				if ( $name !== '' ) {
					$out[] = $name;
				}
				continue;
			}
			if ( is_array( $it ) && isset( $it['name'] ) && is_string( $it['name'] ) ) {
				$name = sanitize_key( (string) $it['name'] );
				if ( $name !== '' ) {
					$out[] = $name;
				}
			}
		}
		return array_values( array_unique( $out ) );
	}

	public static function validate_page_slug( string $slug, array $found, array $config, array $settings ): array {
		$out = array(
			'warnings' => array(),
			'errors' => array(),
		);

		if ( ! isset( $config['pages'] ) || ! is_array( $config['pages'] ) ) {
			return $out;
		}

		$page = $config['pages'][ $slug ] ?? null;
		if ( ! is_array( $page ) ) {
			return $out;
		}

		$required = self::normalize_shortcode_list( $page['required_shortcodes'] ?? array() );
		$recommended = self::normalize_shortcode_list( $page['recommended_shortcodes'] ?? array() );

		$missing_required = array_values( array_diff( $required, $found ) );
		$missing_recommended = array_values( array_diff( $recommended, $found ) );

		$required_mode = self::get_mode( $config, $settings, 'on_missing_required', 'warn' );
		$recommended_mode = self::get_mode( $config, $settings, 'on_missing_recommended', 'warn' );

		foreach ( $missing_required as $name ) {
			$msg = 'Missing required shortcode placeholder for page "' . $slug . '": ' . $name;
			if ( $required_mode === 'fail' ) {
				$out['errors'][] = $msg;
			} else {
				$out['warnings'][] = $msg;
			}
		}

		foreach ( $missing_recommended as $name ) {
			$msg = 'Missing recommended shortcode placeholder for page "' . $slug . '": ' . $name;
			if ( $recommended_mode === 'fail' ) {
				$out['errors'][] = $msg;
			} else {
				$out['warnings'][] = $msg;
			}
		}

		return $out;
	}

	public static function find_shortcode_in_content( string $content, string $shortcode_name ): bool {
		$shortcode_name = sanitize_key( $shortcode_name );
		if ( $shortcode_name === '' ) {
			return false;
		}
		return (bool) preg_match( '/\[' . preg_quote( $shortcode_name, '/' ) . '\b/i', $content );
	}

	public static function validate_post_types( array $config, array $settings ): array {
		$out = array(
			'warnings' => array(),
			'errors' => array(),
		);

		if ( ! isset( $config['post_types'] ) || ! is_array( $config['post_types'] ) ) {
			return $out;
		}

		$required_mode = self::get_mode( $config, $settings, 'on_missing_required', 'warn' );
		$recommended_mode = self::get_mode( $config, $settings, 'on_missing_recommended', 'warn' );

		foreach ( $config['post_types'] as $post_type => $rule ) {
			if ( ! is_string( $post_type ) || $post_type === '' || ! is_array( $rule ) ) {
				continue;
			}

			$post_type = sanitize_key( $post_type );
			if ( $post_type === '' ) {
				continue;
			}

			$required = self::normalize_shortcode_list( $rule['required_shortcodes'] ?? array() );
			$recommended = self::normalize_shortcode_list( $rule['recommended_shortcodes'] ?? array() );

			if ( empty( $required ) && empty( $recommended ) ) {
				continue;
			}

			$q = new \WP_Query( array(
				'post_type' => $post_type,
				'post_status' => 'publish',
				'posts_per_page' => 50,
				'no_found_rows' => true,
			) );

			if ( empty( $q->posts ) ) {
				continue;
			}

			foreach ( $q->posts as $post ) {
				if ( ! ( $post instanceof \WP_Post ) ) {
					continue;
				}

				$content = (string) ( $post->post_content ?? '' );
				$missing_required = array();
				$missing_recommended = array();

				foreach ( $required as $name ) {
					if ( ! self::find_shortcode_in_content( $content, $name ) ) {
						$missing_required[] = $name;
					}
				}

				foreach ( $recommended as $name ) {
					if ( ! self::find_shortcode_in_content( $content, $name ) ) {
						$missing_recommended[] = $name;
					}
				}

				if ( ! empty( $missing_required ) ) {
					$msg = 'Post type "' . $post_type . '" post #' . (string) $post->ID . ' missing required shortcodes: ' . implode( ', ', $missing_required );
					if ( $required_mode === 'fail' ) {
						$out['errors'][] = $msg;
					} else {
						$out['warnings'][] = $msg;
					}
				}
				if ( ! empty( $missing_recommended ) ) {
					$msg = 'Post type "' . $post_type . '" post #' . (string) $post->ID . ' missing recommended shortcodes: ' . implode( ', ', $missing_recommended );
					if ( $recommended_mode === 'fail' ) {
						$out['errors'][] = $msg;
					} else {
						$out['warnings'][] = $msg;
					}
				}
			}
		}

		return $out;
	}
}
