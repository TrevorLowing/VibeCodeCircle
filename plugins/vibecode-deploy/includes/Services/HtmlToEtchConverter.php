<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

final class HtmlToEtchConverter {
	public static function convert( string $html ): string {
		return Importer::html_to_etch_blocks( $html );
	}
}
