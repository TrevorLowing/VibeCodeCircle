<?php
/**
 * SourceResolver
 *
 * Sources-based resolver for dynamic expressions.
 *
 * This mirrors the Builder/TS behavior where a list of `{key, source}` entries
 * is searched in reverse order (last pushed wins).
 *
 * NOTE: This is introduced as a building block and is not yet wired into
 * production rendering paths.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

/**
 * SourceResolver class.
 */
class SourceResolver {

	/**
	 * Resolve an expression against a list of sources.
	 *
	 * @param string               $expression Expression string (e.g. `item.title.applyData()`).
	 * @param array<int, mixed>    $sources Sources list.
	 * @param array<string, mixed> $context Legacy context map (transitional; used for modifier arg resolution).
	 * @param string|null          $special_type Optional loop special type.
	 * @return mixed|null
	 */
	public static function resolve_from_sources( string $expression, array $sources, array $context = array(), ?string $special_type = null ) {
		if ( '' === $expression ) {
			return $expression;
		}

		if ( empty( $sources ) ) {
			return null;
		}

		// Reverse iteration: last pushed source wins.
		foreach ( array_reverse( $sources ) as $source_entry ) {
			if ( ! is_array( $source_entry ) ) {
				continue;
			}

			$key = 'item';
			if ( isset( $source_entry['key'] ) && is_string( $source_entry['key'] ) && '' !== $source_entry['key'] ) {
				$key = $source_entry['key'];
			}

			if ( ! array_key_exists( 'source', $source_entry ) ) {
				continue;
			}

			$source = $source_entry['source'];

			if ( $expression !== $key && ! str_starts_with( $expression, $key . '.' ) && ! str_starts_with( $expression, $key . '[' ) ) {
				continue;
			}

			$parts = ExpressionPath::split( $expression );
			if ( empty( $parts ) ) {
				continue;
			}

			$result = $source;
			foreach ( array_slice( $parts, 1 ) as $part ) {
				if ( self::is_loop_prop_structure( $result ) ) {
					$result = $result['data'] ?? null;
				}

				if ( is_array( $result ) && array_key_exists( $part, $result ) ) {
					$result = $result[ $part ];
					continue;
				}

				if ( is_object( $result ) && property_exists( $result, $part ) ) {
					$result = $result->$part;
					continue;
				}

				if ( ModifierParser::is_modifier( $part ) ) {
					$result = DynamicContentModifiers::apply_modifier(
						$result,
						$part,
						array(
							'sources' => $sources,
							'context' => $context,
						)
					);
					continue;
				}

				$result = null;
				break;
			}

			if ( null === $result ) {
				continue;
			}

			if ( is_array( $result ) && self::is_loop_prop_structure( $result ) ) {
				$loop_key = $result['key'] ?? null;
				$loop_data = $result['data'] ?? null;
				return 'loop' === $special_type ? $loop_key : $loop_data;
			}

			return $result;
		}

		return null;
	}

	/**
	 * Check if a value is a loop prop structure.
	 *
	 * Loop prop structures are arrays with 'prop-type' => 'loop'. During the
	 * migration, some call sites may omit 'key' or 'data'.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 * @phpstan-assert-if-true array{prop-type: string, key?: string, data?: mixed} $value
	 */
	private static function is_loop_prop_structure( $value ): bool {
		return is_array( $value )
			&& isset( $value['prop-type'] )
			&& 'loop' === $value['prop-type'];
	}
}
