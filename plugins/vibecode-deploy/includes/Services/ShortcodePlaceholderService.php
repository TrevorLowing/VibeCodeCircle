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
	 * Check if a name matches the project prefix.
	 *
	 * Supports flexible format: {project_slug}_ or {project_slug} (with or without underscore).
	 * Case-insensitive comparison.
	 *
	 * @param string $name        Name to check (e.g., 'cfa_investigations', 'cfaadvisories').
	 * @param string $project_slug Project slug (e.g., 'cfa').
	 * @return bool True if name matches project prefix.
	 */
	public static function matches_project_prefix( string $name, string $project_slug ): bool {
		if ( $project_slug === '' || $name === '' ) {
			return false;
		}

		$name_lower = strtolower( $name );
		$slug_lower = strtolower( $project_slug );

		// Check if name starts with {project_slug}_ (with underscore)
		if ( strpos( $name_lower, $slug_lower . '_' ) === 0 ) {
			return true;
		}

		// Check if name starts with {project_slug} (without underscore, but not as substring)
		// e.g., "cfa" matches "cfaadvisories" but not "mycfa_investigations"
		if ( strpos( $name_lower, $slug_lower ) === 0 ) {
			// Ensure it's not just a partial match (e.g., "cf" matching "cfa")
			$next_char = substr( $name_lower, strlen( $slug_lower ), 1 );
			if ( $next_char === '' || $next_char === '_' || ctype_alnum( $next_char ) ) {
				return true;
			}
		}

		return false;
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

		$project_slug = isset( $settings['project_slug'] ) && is_string( $settings['project_slug'] ) ? trim( (string) $settings['project_slug'] ) : '';
		$prefix_validation_mode = isset( $settings['prefix_validation_mode'] ) && is_string( $settings['prefix_validation_mode'] ) ? strtolower( trim( (string) $settings['prefix_validation_mode'] ) ) : 'warn';
		
		// Validate prefix compliance for found shortcodes
		if ( $project_slug !== '' && $prefix_validation_mode !== 'off' ) {
			foreach ( $found as $name ) {
				if ( ! is_string( $name ) || $name === '' ) {
					continue;
				}
				if ( ! self::matches_project_prefix( $name, $project_slug ) ) {
					$msg = 'Shortcode "' . $name . '" on page "' . $slug . '" does not match project prefix "' . $project_slug . '"';
					if ( $prefix_validation_mode === 'fail' ) {
						$out['errors'][] = $msg;
					} else {
						$out['warnings'][] = $msg;
					}
				}
			}
		}

		if ( ! isset( $config['pages'] ) || ! is_array( $config['pages'] ) ) {
			return $out;
		}

		$page = $config['pages'][ $slug ] ?? null;
		if ( ! is_array( $page ) ) {
			return $out;
		}

		$required = self::normalize_shortcode_list( $page['required_shortcodes'] ?? array() );
		$recommended = self::normalize_shortcode_list( $page['recommended_shortcodes'] ?? array() );

		// Validate prefix compliance for config shortcodes
		if ( $project_slug !== '' && $prefix_validation_mode !== 'off' ) {
			foreach ( array_merge( $required, $recommended ) as $name ) {
				if ( ! is_string( $name ) || $name === '' ) {
					continue;
				}
				if ( ! self::matches_project_prefix( $name, $project_slug ) ) {
					$msg = 'Shortcode "' . $name . '" in config for page "' . $slug . '" does not match project prefix "' . $project_slug . '"';
					if ( $prefix_validation_mode === 'fail' ) {
						$out['errors'][] = $msg;
					} else {
						$out['warnings'][] = $msg;
					}
				}
			}
		}

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

	/**
	 * Validate that registered CPTs match the project prefix.
	 *
	 * @param string $project_slug Project slug (e.g., 'cfa').
	 * @param array  $settings     Plugin settings.
	 * @return array Validation result with 'warnings' and 'errors' keys.
	 */
	public static function validate_cpt_prefixes( string $project_slug, array $settings ): array {
		$out = array(
			'warnings' => array(),
			'errors' => array(),
		);

		if ( $project_slug === '' ) {
			return $out;
		}

		$prefix_validation_mode = isset( $settings['prefix_validation_mode'] ) && is_string( $settings['prefix_validation_mode'] ) ? strtolower( trim( (string) $settings['prefix_validation_mode'] ) ) : 'warn';
		$prefix_validation_scope = isset( $settings['prefix_validation_scope'] ) && is_string( $settings['prefix_validation_scope'] ) ? strtolower( trim( (string) $settings['prefix_validation_scope'] ) ) : 'all';

		if ( $prefix_validation_mode === 'off' || $prefix_validation_scope === 'shortcodes' ) {
			return $out;
		}

		// Get all registered CPTs, excluding built-in WordPress types
		$built_in_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
		$all_cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
		$custom_cpts = array_diff( $all_cpts, $built_in_types );

		foreach ( $custom_cpts as $cpt ) {
			if ( ! is_string( $cpt ) || $cpt === '' ) {
				continue;
			}

			if ( ! self::matches_project_prefix( $cpt, $project_slug ) ) {
				$msg = 'CPT "' . $cpt . '" does not match project prefix "' . $project_slug . '"';
				if ( $prefix_validation_mode === 'fail' ) {
					$out['errors'][] = $msg;
				} else {
					$out['warnings'][] = $msg;
				}
			}
		}

		return $out;
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

	/**
	 * Detect shortcodes and CPTs that use the project prefix but aren't in the config.
	 *
	 * These may be orphaned/unused items that should be documented or removed.
	 *
	 * @param string $project_slug Project slug (e.g., 'cfa').
	 * @param array  $config       Shortcode configuration array.
	 * @param array  $settings     Plugin settings.
	 * @return array Detection result with 'warnings' and 'errors' keys.
	 */
	public static function detect_unknown_prefixed_items( string $project_slug, array $config, array $settings ): array {
		$out = array(
			'warnings' => array(),
			'errors' => array(),
		);

		if ( $project_slug === '' ) {
			return $out;
		}

		$prefix_validation_mode = isset( $settings['prefix_validation_mode'] ) && is_string( $settings['prefix_validation_mode'] ) ? strtolower( trim( (string) $settings['prefix_validation_mode'] ) ) : 'warn';
		$prefix_validation_scope = isset( $settings['prefix_validation_scope'] ) && is_string( $settings['prefix_validation_scope'] ) ? strtolower( trim( (string) $settings['prefix_validation_scope'] ) ) : 'all';

		if ( $prefix_validation_mode === 'off' ) {
			return $out;
		}

		// Collect all shortcodes/CPTs mentioned in config
		$config_shortcodes = array();
		$config_cpts = array();

		// Extract shortcodes from pages config
		if ( isset( $config['pages'] ) && is_array( $config['pages'] ) ) {
			foreach ( $config['pages'] as $page_config ) {
				if ( ! is_array( $page_config ) ) {
					continue;
				}
				$required = self::normalize_shortcode_list( $page_config['required_shortcodes'] ?? array() );
				$recommended = self::normalize_shortcode_list( $page_config['recommended_shortcodes'] ?? array() );
				$config_shortcodes = array_merge( $config_shortcodes, $required, $recommended );
			}
		}

		// Extract shortcodes and CPTs from post_types config
		if ( isset( $config['post_types'] ) && is_array( $config['post_types'] ) ) {
			foreach ( $config['post_types'] as $cpt => $rule ) {
				if ( ! is_string( $cpt ) || $cpt === '' || ! is_array( $rule ) ) {
					continue;
				}
				$cpt_sanitized = sanitize_key( $cpt );
				if ( $cpt_sanitized !== '' ) {
					$config_cpts[] = $cpt_sanitized;
				}
				$required = self::normalize_shortcode_list( $rule['required_shortcodes'] ?? array() );
				$recommended = self::normalize_shortcode_list( $rule['recommended_shortcodes'] ?? array() );
				$config_shortcodes = array_merge( $config_shortcodes, $required, $recommended );
			}
		}

		$config_shortcodes = array_values( array_unique( $config_shortcodes ) );
		$config_cpts = array_values( array_unique( $config_cpts ) );

		// Check registered shortcodes
		if ( $prefix_validation_scope === 'all' || $prefix_validation_scope === 'shortcodes' ) {
			global $shortcode_tags;
			if ( is_array( $shortcode_tags ) ) {
				foreach ( array_keys( $shortcode_tags ) as $tag ) {
					if ( ! is_string( $tag ) || $tag === '' ) {
						continue;
					}
					if ( self::matches_project_prefix( $tag, $project_slug ) && ! in_array( $tag, $config_shortcodes, true ) ) {
						$msg = 'Shortcode "' . $tag . '" uses project prefix but is not documented in config';
						if ( $prefix_validation_mode === 'fail' ) {
							$out['errors'][] = $msg;
						} else {
							$out['warnings'][] = $msg;
						}
					}
				}
			}
		}

		// Check registered CPTs
		if ( $prefix_validation_scope === 'all' || $prefix_validation_scope === 'cpts' ) {
			$built_in_types = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
			$all_cpts = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
			$custom_cpts = array_diff( $all_cpts, $built_in_types );

			foreach ( $custom_cpts as $cpt ) {
				if ( ! is_string( $cpt ) || $cpt === '' ) {
					continue;
				}
				if ( self::matches_project_prefix( $cpt, $project_slug ) && ! in_array( $cpt, $config_cpts, true ) ) {
					$msg = 'CPT "' . $cpt . '" uses project prefix but is not documented in config';
					if ( $prefix_validation_mode === 'fail' ) {
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
