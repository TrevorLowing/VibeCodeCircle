<?php
/**
 * Dynamic Content Entry
 *
 * Typed stack entry used to model Builder-like scoping (global/component/loop/slot/etc.).
 * This is intentionally minimal and side-effect free (pure data).
 *
 * @package Etch\Blocks\Global\DynamicContent
 */

namespace Etch\Blocks\Global\DynamicContent;

/**
 * DynamicContentEntry class.
 */
class DynamicContentEntry {

	/**
	 * Entry type (e.g. global, component, component-slots, loop, loop-index, slot, preview).
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Root key used in expressions for this entry.
	 * Some entry types (e.g. slot markers) may not have a key.
	 *
	 * @var string|null
	 */
	private ?string $key;

	/**
	 * Arbitrary source value.
	 *
	 * @var mixed
	 */
	private $source;

	/**
	 * Optional metadata bag (e.g. parentDynamicContent for components).
	 *
	 * @var array<string, mixed>
	 */
	private array $metadata;

	/**
	 * Create a dynamic content entry.
	 *
	 * @param string               $type Entry type.
	 * @param string|null          $key Root key.
	 * @param mixed                $source Source value.
	 * @param array<string, mixed> $metadata Optional metadata.
	 */
	public function __construct( string $type, ?string $key = null, $source = null, array $metadata = array() ) {
		$this->type = $type;
		$this->key = $key;
		$this->source = $source;
		$this->metadata = $metadata;
	}

	/**
	 * Get the entry type.
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get the root key.
	 */
	public function get_key(): ?string {
		return $this->key;
	}

	/**
	 * Get the source value.
	 *
	 * @return mixed
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * Get the entry metadata.
	 *
	 * @return array<string, mixed>
	 */
	public function get_metadata(): array {
		return $this->metadata;
	}

	/**
	 * Convert the entry to an array.
	 *
	 * @return array{type: string, key?: string, source?: mixed, metadata?: array<string, mixed>}
	 */
	public function to_array(): array {
		$out = array(
			'type' => $this->type,
		);

		if ( null !== $this->key ) {
			$out['key'] = $this->key;
		}

		if ( null !== $this->source ) {
			$out['source'] = $this->source;
		}

		if ( ! empty( $this->metadata ) ) {
			$out['metadata'] = $this->metadata;
		}

		return $out;
	}
}
