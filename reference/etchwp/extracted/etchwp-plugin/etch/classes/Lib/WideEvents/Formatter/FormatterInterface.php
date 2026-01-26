<?php
/**
 * FormatterInterface - Contract for formatting wide events.
 *
 * @package Etch\Lib\WideEvents\Formatter
 */

namespace Etch\Lib\WideEvents\Formatter;

/**
 * FormatterInterface.
 *
 * Defines how event data should be formatted for output.
 */
interface FormatterInterface {

	/**
	 * Format event data into a string.
	 *
	 * @param array<string, mixed> $data The event data to format.
	 * @return string The formatted output.
	 */
	public function format( array $data ): string;
}
