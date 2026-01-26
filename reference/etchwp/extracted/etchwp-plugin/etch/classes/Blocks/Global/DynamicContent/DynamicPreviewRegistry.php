<?php
/**
 * DynamicPreviewRegistry
 *
 * Optional registry for preview-scoped sources.
 *
 * This is a parity hook for Builder's `preview` dynamic content type. It is a
 * no-op unless preview sources are enqueued.
 *
 * @package Etch\Blocks\Global\DynamicContent
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\DynamicContent;

/**
 * DynamicPreviewRegistry class.
 */
class DynamicPreviewRegistry {

	/**
	 * Preview sources keyed by accessor key.
	 *
	 * @var array<string, mixed>
	 */
	private static array $preview_sources = array();

	/**
	 * Enqueue a preview source.
	 *
	 * @param string $key Accessor key.
	 * @param mixed  $source Source value.
	 * @return void
	 */
	public static function enqueue( string $key, $source ): void {
		$key = trim( $key );
		if ( '' === $key ) {
			return;
		}

		self::$preview_sources[ $key ] = $source;
	}

	/**
	 * Dequeue a preview source.
	 *
	 * @param string $key Accessor key.
	 * @return void
	 */
	public static function dequeue( string $key ): void {
		$key = trim( $key );
		if ( '' === $key ) {
			return;
		}

		unset( self::$preview_sources[ $key ] );
	}

	/**
	 * List preview sources.
	 *
	 * @return array<int, array{key: string, source: mixed}>
	 */
	public static function list(): array {
		$out = array();
		foreach ( self::$preview_sources as $key => $source ) {
			$out[] = array(
				'key' => $key,
				'source' => $source,
			);
		}
		return $out;
	}

	/**
	 * Clear all preview sources.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$preview_sources = array();
	}
}
