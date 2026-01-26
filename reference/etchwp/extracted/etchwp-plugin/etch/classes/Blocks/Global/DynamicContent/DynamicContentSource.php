<?php
/**
 * Dynamic Content Source
 *
 * Lightweight representation of a single `{key, source}` entry.
 *
 * @package Etch\Blocks\Global\DynamicContent
 */

namespace Etch\Blocks\Global\DynamicContent;

/**
 * DynamicContentSource class.
 */
class DynamicContentSource {

	/**
	 * Root key used in expressions (e.g. `item`, `props`, `this`).
	 *
	 * Root key.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Arbitrary source value.
	 *
	 * @var mixed
	 */
	private $source;

	/**
	 * Create a dynamic content source.
	 *
	 * @param string $key Root key.
	 * @param mixed  $source Source value.
	 */
	public function __construct( string $key, $source ) {
		$this->key = $key;
		$this->source = $source;
	}

	/**
	 * Get the source key.
	 *
	 * @return string
	 */
	public function get_key(): string {
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
	 * Convert the source to an array.
	 *
	 * @return array{key: string, source: mixed}
	 */
	public function to_array(): array {
		return array(
			'key'    => $this->key,
			'source' => $this->source,
		);
	}
}
