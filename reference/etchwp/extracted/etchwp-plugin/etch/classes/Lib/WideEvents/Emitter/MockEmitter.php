<?php
/**
 * Mock emitter for testing WideEventLogger.
 *
 * @package Etch\Lib\WideEvents\Emitter
 */

namespace Etch\Lib\WideEvents\Emitter;

/**
 * Mock emitter that captures output instead of writing to files.
 */
class MockEmitter implements EmitterInterface {
	/**
	 * Captured output.
	 *
	 * @var array<int, string>
	 */
	public array $captured = array();

	/**
	 * Emit (capture) a formatted event.
	 *
	 * @param string $formatted_data The formatted event string.
	 * @return bool Always returns true.
	 */
	public function emit( string $formatted_data ): bool {
		$this->captured[] = $formatted_data;
		return true;
	}

	/**
	 * Get the last captured event as decoded JSON.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get_last_event(): ?array {
		if ( empty( $this->captured ) ) {
			return null;
		}
		$last = end( $this->captured );
		$decoded = json_decode( $last, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Get the count of captured events.
	 *
	 * @return int
	 */
	public function get_count(): int {
		return count( $this->captured );
	}
}
