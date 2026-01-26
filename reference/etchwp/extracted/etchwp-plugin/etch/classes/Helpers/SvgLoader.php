<?php
/**
 * SvgLoader helper file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;
use Etch\Svg;

/**
 * SVG Loading Helper for Etch
 *
 * Handles fetching, caching, and invalidation of remote SVGs.
 */
class SvgLoader {

	/**
	 * Option name for storing the global cache version.
	 *
	 * Incrementing this value will invalidate all cached SVGs.
	 *
	 * @var string
	 */
	private static $cache_version_option = 'etch_svg_version';

	/**
	 * Fetch an SVG from a remote URL with sanitization.
	 *
	 * This method sanitizes the SVG content to prevent XSS attacks by removing
	 * malicious elements like script tags, event handlers, and javascript: URLs.
	 *
	 * @param string $url The SVG URL.
	 *
	 * @return string|\WP_Error Sanitized SVG markup string or WP_Error on failure.
	 */
	public static function get_sanitized_remote_svg( $url ) {
		if ( empty( $url ) ) {
			return new \WP_Error( 'invalid_url', 'The URL provided is empty.' );
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error( 'invalid_url', 'The URL provided is not valid.' );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'headers'   => array(
					'Accept' => 'image/svg+xml,text/xml,application/xml',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! is_string( $body ) || strpos( $body, '<svg' ) === false ) {
			return new \WP_Error( 'invalid_svg', 'The fetched content is not a valid SVG.' );
		}

		// Sanitize the SVG to prevent XSS attacks
		$sanitized = Svg::sanitize_svg_uploads( $body );

		// sanitize_svg_uploads returns string when given string input
		return is_string( $sanitized ) ? $sanitized : new \WP_Error( 'sanitization_failed', 'SVG sanitization failed.' );
	}

	/**
	 * Fetch an SVG from a remote URL with sanitization.
	 *
	 * @deprecated Use get_sanitized_remote_svg() instead.
	 *
	 * @param string $url The SVG URL.
	 *
	 * @return string|\WP_Error Sanitized SVG markup string or WP_Error on failure.
	 */
	public static function get_remote_svg( $url ) {
		return self::get_sanitized_remote_svg( $url );
	}

	/**
	 * Fetch an SVG from a URL with caching.
	 *
	 * @param string $url  The SVG URL.
	 * @param int    $ttl  Cache lifetime in seconds (default: 12 hours).
	 *
	 * @return string SVG markup string, or fallback SVG on failure.
	 */
	public static function fetch_svg_cached( $url, $ttl = 43200 ) {
		// on empty URL, return fallback
		if ( empty( $url ) ) {
			return self::get_fallback_svg();
		}

		$version   = self::get_cache_version();
		$cache_key = 'etch_svg_' . $version . '_' . md5( $url );

		// Check cache first
		$svg = get_transient( $cache_key );

		if ( false !== $svg && is_string( $svg ) ) {
			return $svg;
		}

		$body = false;

		$upload_dir = wp_get_upload_dir();
		// Handle locally stored SVGs in the uploads directory
		if ( strpos( $url, $upload_dir['baseurl'] ) === 0 ) {
			// Convert to local path
			$relative_path = str_replace( $upload_dir['baseurl'], '', $url );
			$file_path     = $upload_dir['basedir'] . $relative_path;

			if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
				$body = file_get_contents( $file_path );
			}
		} else if ( strpos( $url, '/' ) === 0 ) {

			// Check if it's in uploads directory
			if ( strpos( $url, '/wp-content/uploads/' ) !== false ) {
				// Replace URL base with filesystem base
				$search = parse_url( $upload_dir['baseurl'], PHP_URL_PATH ) ?? '';
				if ( false === $search ) {
					$search = '';
				}

				$file_path = str_replace(
					$search,
					$upload_dir['basedir'],
					$url
				);
			} else {
				// For other paths, convert from site root
				$file_path = ABSPATH . ltrim( $url, '/' );
			}

			// If its already a relative path, try to load it directly
			if ( file_exists( $file_path ) && is_readable( $file_path ) ) {
				$body = file_get_contents( $file_path );
			}
		} else if ( strpos( $url, 'data:image/svg+xml' ) === 0 ) {
			// Handle encoded SVG data URLs
			// Extract base64 part
			if ( strpos( $url, 'base64,' ) !== false ) {
				$base64 = substr( $url, strpos( $url, 'base64,' ) + 7 );
				$body   = base64_decode( $base64 );
			} else {
				// Not base64, just raw data URI
				$body = urldecode( substr( $url, strpos( $url, ',' ) + 1 ) );
			}
		}

		if ( false === $body ) {
			$response = self::get_sanitized_remote_svg( $url );
			if ( is_wp_error( $response ) ) {
				return self::get_fallback_svg();
			}
			$body = $response;
		} else {
			// Sanitize locally loaded SVG content to prevent XSS
			$body = Svg::sanitize_svg_uploads( $body );
		}

		if ( ! is_string( $body ) || strpos( $body, '<svg' ) === false ) {
			return self::get_fallback_svg();
		}

		set_transient( $cache_key, $body, $ttl );
		return $body;
	}

	/**
	 * Bump the global cache version to invalidate all SVG caches.
	 *
	 * @return void
	 */
	public static function bump_cache_version() {
		$version = self::get_cache_version() + 1;
		update_option( self::$cache_version_option, $version );
	}

	/**
	 * Get the current cache version.
	 *
	 * @return int
	 */
	private static function get_cache_version() {
		$version = get_option( self::$cache_version_option, 1 );

		if ( ! is_int( $version ) ) {
			$version = 1;
		}

		return $version;
	}

	/**
	 * Get a fallback SVG in case of errors. (The Etch logo)
	 *
	 * @return string Fallback SVG markup.
	 */
	private static function get_fallback_svg() {
		return '<svg width="48" height="48" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M11.8 80.5H42.1121C51.7977 80.5 60.031 73.4613 61.4888 63.9347C61.5535 62.6244 61.6624 61.585 61.782 61C66.3271 38.7595 86.112 22 109.8 22H188.2C193.609 22 198 26.3687 198 31.75V51.25C198 56.6313 193.609 61 188.2 61H101.261C78.9151 61 60.8 79.0234 60.8 101.255C60.8 111.331 52.5899 119.5 42.4622 119.5H11.8C6.39114 119.5 2 115.131 2 109.75V90.25C2 84.8687 6.39114 80.5 11.8 80.5ZM109.8 80.5H188.2C193.609 80.5 198 84.8687 198 90.25V109.75C198 115.131 193.609 119.5 188.2 119.5H109.8C101.101 119.5 93.2786 123.267 87.8939 129.25C86.217 131.113 84.7763 133.191 83.6194 135.437C81.5614 139.431 80.4 143.957 80.4 148.75C80.4 164.893 67.2263 178 51 178H11.8C6.39114 178 2 173.631 2 168.25V148.75C2 143.369 6.39114 139 11.8 139H51C59.6995 139 67.5214 135.233 72.9061 129.25C73.0813 129.055 73.254 128.858 73.4241 128.659C77.7747 123.558 80.4 116.957 80.4 109.75C80.4 104.957 81.5614 100.431 83.6194 96.4367C88.4922 86.9787 98.3917 80.5 109.8 80.5ZM109.8 139H188.2C193.609 139 198 143.369 198 148.75V168.25C198 173.631 193.609 178 188.2 178H109.8C104.391 178 100 173.631 100 168.25V148.75C100 143.369 104.391 139 109.8 139Z" fill="currentColor" clip-rule="evenodd" fill-rule="evenodd" data-etch-context="eyJvcmlnaW4iOiJldGNoIiwibmFtZSI6IlBhdGgiLCJzdHJ1Y3R1cmVTdGF0ZSI6Im9wZW4iLCJyZWYiOiJoeHRyMjB4In0="> </path></svg>';
	}

	/**
	 * Sanitize SVG for output.
	 *
	 * @param string                                          $svg     SVG markup.
	 * @param array{strip_colors?: bool, attributes?: string} $options Optional settings.
	 *
	 * @return string Sanitized SVG markup.
	 */
	public static function prepare_svg_for_output( $svg, $options = array() ): string {
		// Ensure no malicious content is loaded
		$svg = Svg::sanitize_svg_uploads( $svg );

		// ensure ?xml declaration is removed
		$svg = preg_replace( '/<\?xml.*?\?>/', '', $svg, 1 ) ?? '';

		// also remove xmlns attribute if present
		$svg = preg_replace( '/\s*xmlns="[^"]*"/', '', $svg, 1 ) ?? '';

		if ( ! empty( $options['attributes'] ) ) {
			// add the attributes to the SVG content
			$svg = preg_replace( '/<svg([^>]*)>/', '<svg$1' . $options['attributes'] . '>', $svg, 1 ) ?? '';
		}

		if ( isset( $options['strip_colors'] ) && $options['strip_colors'] ) {
			$svg = self::strip_svg_colors( EtchTypeAsserter::to_string( $svg ) );
		}

		return EtchTypeAsserter::to_string( $svg );
	}

	/**
	 * Replace color attributes in the SVG with 'currentColor'.
	 *
	 * @param string $svg SVG markup.
	 *
	 * @return string SVG with colors stripped.
	 */
	private static function strip_svg_colors( string $svg ): string {
		$attrs = array(
			'fill',
			'stroke',
			'color',
			'stop-color',
			'flood-color',
			'lighting-color',
		);

		$pattern = '/\b(' . implode( '|', array_map( 'preg_quote', $attrs ) ) . ')\s*=\s*([\'"])(?!none\2)(.*?)\2/i';

		return EtchTypeAsserter::to_string( preg_replace( $pattern, '$1=$2currentColor$2', $svg ) );
	}
}
