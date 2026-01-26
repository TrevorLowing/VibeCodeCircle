<?php
/**
 * WideEvent - Collects structured data throughout request lifecycle.
 *
 * Based on the "wide events" / "canonical log lines" pattern:
 * One rich, structured event per request instead of many scattered log lines.
 *
 * @package Etch\Lib\WideEvents
 */

namespace Etch\Lib\WideEvents;

/**
 * WideEvent class.
 *
 * Collects key-value pairs throughout a request lifecycle.
 * Values can be strings, numbers, or nested arrays.
 */
class WideEvent {

	/**
	 * The collected event data.
	 *
	 * @var array<string, mixed>
	 */
	private array $data = array();

	/**
	 * Whether an error has been recorded.
	 *
	 * @var bool
	 */
	private bool $has_error = false;

	/**
	 * Set a value in the event.
	 *
	 * @param string $key   The key (supports dot notation for nesting).
	 * @param mixed  $value The value (string, number, or nested array).
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->set_nested( $this->data, $key, $value );
		return $this;
	}

	/**
	 * Append a value to an array in the event.
	 *
	 * If the key doesn't exist, creates an array.
	 * If the key exists but isn't an array, converts it to one.
	 *
	 * @param string $key   The key (supports dot notation for nesting).
	 * @param mixed  $value The value to append.
	 * @return self
	 */
	public function append( string $key, $value ): self {
		$current = $this->get( $key );

		if ( null === $current ) {
			$this->set( $key, array( $value ) );
		} elseif ( is_array( $current ) && ! $this->is_associative( $current ) ) {
			$current[] = $value;
			$this->set( $key, $current );
		} else {
			$this->set( $key, array( $current, $value ) );
		}

		return $this;
	}

	/**
	 * Set an error value in the event.
	 *
	 * Same as set(), but also marks the event as having an error.
	 * This affects sampling decisions (errors are always logged).
	 *
	 * @param string $key   The key (supports dot notation for nesting).
	 * @param mixed  $value The error value.
	 * @return self
	 */
	public function set_error( string $key, $value ): self {
		$this->has_error = true;
		return $this->set( $key, $value );
	}

	/**
	 * Get a value from the event.
	 *
	 * @param string $key The key (supports dot notation for nesting).
	 * @return mixed|null The value or null if not found.
	 */
	public function get( string $key ) {
		return $this->get_nested( $this->data, $key );
	}

	/**
	 * Whether the event has recorded an error.
	 *
	 * @return bool
	 */
	public function has_error(): bool {
		return $this->has_error;
	}

	/**
	 * Get all event data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		return $this->data;
	}

	/**
	 * Reset the event data.
	 *
	 * @return self
	 */
	public function reset(): self {
		$this->data = array();
		$this->has_error = false;
		return $this;
	}

	/**
	 * Set a value using dot notation for nested keys.
	 *
	 * @param array<string, mixed> $array The array to modify.
	 * @param string               $key   The dot-notation key.
	 * @param mixed                $value The value to set.
	 * @return void
	 */
	private function set_nested( array &$array, string $key, $value ): void {
		$keys = explode( '.', $key );
		$current = &$array;

		foreach ( $keys as $i => $k ) {
			if ( count( $keys ) - 1 === $i ) {
				$current[ $k ] = $value;
			} else {
				if ( ! isset( $current[ $k ] ) || ! is_array( $current[ $k ] ) ) {
					$current[ $k ] = array();
				}
				$current = &$current[ $k ];
			}
		}
	}

	/**
	 * Get a value using dot notation for nested keys.
	 *
	 * @param array<string, mixed> $array The array to read from.
	 * @param string               $key   The dot-notation key.
	 * @return mixed|null The value or null if not found.
	 */
	private function get_nested( array $array, string $key ) {
		$keys = explode( '.', $key );
		$current = $array;

		foreach ( $keys as $k ) {
			if ( ! is_array( $current ) || ! isset( $current[ $k ] ) ) {
				return null;
			}
			$current = $current[ $k ];
		}

		return $current;
	}

	/**
	 * Check if an array is associative (has string keys).
	 *
	 * @param array<int|string, mixed> $array The array to check.
	 * @return bool
	 */
	private function is_associative( array $array ): bool {
		if ( empty( $array ) ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
}
