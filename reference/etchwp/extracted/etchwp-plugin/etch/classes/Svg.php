<?php
/**
 * SVG handling class.
 *
 * @package Etch
 * @gplv2
 */

namespace Etch;

/**
 * Svg class.
 */
class Svg {

	/**
	 * Setup the hooks.
	 *
	 * @return void
	 */
	public static function setup_hooks() {
		add_filter( 'upload_mimes', array( __CLASS__, 'add_svg_to_upload_mimes' ) );
		add_filter( 'wp_handle_upload_prefilter', array( __CLASS__, 'sanitize_svg_uploads' ) );
	}

	/**
	 * Allow SVG uploads for admins
	 *
	 * @param array<string,string> $upload_mimes Mime types keyed by the file extension regex corresponding to those types.
	 * @return array<string,string>
	 **/
	public static function add_svg_to_upload_mimes( $upload_mimes ) {
		if ( current_user_can( 'manage_options' ) ) {
			$upload_mimes['svg'] = 'image/svg+xml';
			$upload_mimes['svgz'] = 'image/svg+xml';
		}
		return $upload_mimes;
	}

	/**
	 * Sanitize SVG uploads to remove potentially malicious code.
	 *
	 * @param array<string,mixed>|string $fileOrContent An array of data for a single file, or the SVG content.
	 * @return array<string,mixed>|string The file array, possibly modified, or the SVG content.
	 */
	public static function sanitize_svg_uploads( $fileOrContent ) {
		if ( is_string( $fileOrContent ) ) {
			return self::sanitize_svg_content( $fileOrContent );
		}

		$file = $fileOrContent;
		// Only process SVG files
		if ( 'image/svg+xml' !== $file['type'] ) {
			return $file;
		}

		// Check if user has proper permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			return $file;
		}

		// Handle the file and sanitize its content
		return self::handle_file_and_sanitize_content( $file );
	}

	/**
	 * Handle the file and sanitize its content.
	 *
	 * @param array<string,mixed> $file An array of data for a single file.
	 * @return array<string,mixed> The file array, possibly modified.
	 */
	private static function handle_file_and_sanitize_content( $file ) {
		if ( ! is_string( $file['tmp_name'] ) || '' === $file['tmp_name'] ) {
			return $file;
		}

		$file_content = file_get_contents( $file['tmp_name'] );
		if ( false === $file_content ) {
			$file['error'] = __( 'Could not read SVG file', 'etch' );
			return $file;
		}

		// Sanitize the SVG content
		$sanitized_content = self::sanitize_svg_content( $file_content );

		// Write sanitized content back to the file
		if ( false === file_put_contents( $file['tmp_name'], $sanitized_content ) ) {
			$file['error'] = __( 'Could not write sanitized SVG file', 'etch' );
			return $file;
		}

		return $file;
	}

	/**
	 * Sanitize the SVG content using enshrined/svg-sanitize.
	 *
	 * @param string $content The SVG content to sanitize.
	 * @return string The sanitized SVG content.
	 */
	private static function sanitize_svg_content( $content ) {
		// Sanitize the SVG content using enshrined/svg-sanitize
		$sanitizer = new \enshrined\svgSanitize\Sanitizer();

		// Set up the sanitizer with default options
		$sanitizer->minify( true );

		// Remove remote references
		$sanitizer->removeRemoteReferences( true );

		// Sanitize the SVG content
		$sanitized_content = $sanitizer->sanitize( $content );

		// Check if sanitization failed
		if ( false === $sanitized_content ) {
			return __( 'Failed to sanitize SVG file', 'etch' );
		}

		return $sanitized_content;
	}
}
