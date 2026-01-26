<?php
/**
 * Dynamic Content Stack
 *
 * Append-only stack of typed dynamic content entries.
 * This mirrors the Builder runtime concept of `options.dynamicContent`.
 *
 * @package Etch\Blocks\Global\DynamicContent
 */

namespace Etch\Blocks\Global\DynamicContent;

/**
 * DynamicContentStack class.
 */
class DynamicContentStack {

	/**
	 * Stack entries.
	 *
	 * @var array<int, DynamicContentEntry>
	 */
	private array $entries;

	/**
	 * Create a new dynamic content stack.
	 *
	 * @param array<int, DynamicContentEntry> $entries Initial entries.
	 */
	public function __construct( array $entries = array() ) {
		$this->entries = $entries;
	}

	/**
	 * Get all stack entries.
	 *
	 * @return array<int, DynamicContentEntry>
	 */
	public function all(): array {
		return $this->entries;
	}

	/**
	 * Get entry count.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->entries );
	}

	/**
	 * Push an entry onto the stack.
	 *
	 * @param DynamicContentEntry $entry Entry to push.
	 */
	public function push( DynamicContentEntry $entry ): void {
		$this->entries[] = $entry;
	}

	/**
	 * Create a new stack with the given entry appended.
	 *
	 * @param DynamicContentEntry $entry Entry to append.
	 */
	public function with_pushed( DynamicContentEntry $entry ): self {
		$copy = new self( $this->entries );
		$copy->push( $entry );
		return $copy;
	}

	/**
	 * Return a new stack containing only entries of the given type.
	 *
	 * @param string $type Type to keep.
	 */
	public function only_type( string $type ): self {
		return new self(
			array_values(
				array_filter(
					$this->entries,
					static function ( DynamicContentEntry $entry ) use ( $type ): bool {
						return $entry->get_type() === $type;
					}
				)
			)
		);
	}

	/**
	 * Convert stack entries to the untyped `{key, source}` list used by the resolver.
	 *
	 * Note: entries without a key are excluded.
	 *
	 * @return array<int, array{key: string, source: mixed}>
	 */
	public function to_sources(): array {
		$sources = array();

		foreach ( $this->entries as $entry ) {
			$key = $entry->get_key();
			if ( null === $key || '' === $key ) {
				continue;
			}

			$sources[] = array(
				'key'    => $key,
				'source' => $entry->get_source(),
			);
		}

		return $sources;
	}

	/**
	 * Create a stack from an array of entries.
	 *
	 * @param array<int, DynamicContentEntry|array<string, mixed>> $entries Mixed entry list.
	 */
	public static function from_mixed_array( array $entries ): self {
		$stack_entries = array();
		foreach ( $entries as $entry ) {
			if ( $entry instanceof DynamicContentEntry ) {
				$stack_entries[] = $entry;
				continue;
			}

			if ( is_array( $entry ) && isset( $entry['type'] ) && is_string( $entry['type'] ) ) {
				$key = null;
				if ( isset( $entry['key'] ) && is_string( $entry['key'] ) ) {
					$key = $entry['key'];
				}

				$metadata = array();
				if ( isset( $entry['metadata'] ) && is_array( $entry['metadata'] ) ) {
					$metadata = $entry['metadata'];
				}

				$stack_entries[] = new DynamicContentEntry(
					$entry['type'],
					$key,
					$entry['source'] ?? null,
					$metadata
				);
			}
		}

		return new self( $stack_entries );
	}
}
