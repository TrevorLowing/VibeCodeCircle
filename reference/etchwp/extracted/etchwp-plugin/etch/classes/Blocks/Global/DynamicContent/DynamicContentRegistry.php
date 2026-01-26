<?php
/**
 * DynamicContentRegistry
 *
 * Global dynamic content registry.
 *
 * Mirrors the Builder runtime ability to enqueue/dequeue global dynamic content
 * sources by key. The registry maintains insertion order; re-enqueuing an
 * existing key moves it to the end (most recent wins).
 *
 * @package Etch\Blocks\Global\DynamicContent
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\DynamicContent;

/**
 * DynamicContentRegistry class.
 */
class DynamicContentRegistry {

	/**
	 * Global registry map.
	 *
	 * @var array<string, mixed>
	 */
	private static array $registry = array();

	/**
	 * Enqueue a global dynamic content source.
	 *
	 * If the key already exists, it is replaced and moved to the end.
	 *
	 * @param string $key Source key.
	 * @param mixed  $source Source value.
	 * @return void
	 */
	public static function enqueue( string $key, $source ): void {
		$key = trim( $key );
		if ( '' === $key ) {
			return;
		}

		if ( array_key_exists( $key, self::$registry ) ) {
			unset( self::$registry[ $key ] );
		}

		self::$registry[ $key ] = $source;
	}

	/**
	 * Dequeue a global dynamic content source.
	 *
	 * @param string $key Source key.
	 * @return void
	 */
	public static function dequeue( string $key ): void {
		$key = trim( $key );
		if ( '' === $key ) {
			return;
		}

		unset( self::$registry[ $key ] );
	}

	/**
	 * List registered sources in insertion order.
	 *
	 * @return array<int, array{key: string, source: mixed}>
	 */
	public static function list(): array {
		$result = array();
		foreach ( self::$registry as $key => $source ) {
			$result[] = array(
				'key'    => $key,
				'source' => $source,
			);
		}
		return $result;
	}

	/**
	 * Clear the registry (for testing).
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$registry = array();
	}
}
