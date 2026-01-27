<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

final class HtmlToEtchConverter {
	/**
	 * Convert HTML to Gutenberg blocks with EtchWP metadata.
	 *
	 * @param string $html HTML content to convert.
	 * @param string $build_root Optional build root path for image uploads during deployment.
	 * @return string Gutenberg block markup.
	 */
	public static function convert( string $html, string $build_root = '' ): string {
		return Importer::html_to_etch_blocks( $html, $build_root );
	}
}
