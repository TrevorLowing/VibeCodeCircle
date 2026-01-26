<?php
/**
 * JsonFormatter - Formats wide events as pretty-printed JSON.
 *
 * @package Etch\Lib\WideEvents\Formatter
 */

namespace Etch\Lib\WideEvents\Formatter;

/**
 * JsonFormatter class.
 *
 * Outputs event data as pretty-printed JSON for readability.
 * Events are separated by blank lines for easy parsing.
 */
class JsonFormatter implements FormatterInterface {

	/**
	 * Format event data as pretty JSON.
	 *
	 * @param array<string, mixed> $data The event data to format.
	 * @return string The formatted JSON with trailing newlines.
	 */
	public function format( array $data ): string {
		$json = json_encode(
			$data,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		if ( false === $json ) {
			$json = json_encode(
				array(
					'_format_error' => 'Failed to encode event data',
					'_error_code'   => json_last_error(),
				),
				JSON_PRETTY_PRINT
			);
		}

		return $json . "\n\n";
	}
}
