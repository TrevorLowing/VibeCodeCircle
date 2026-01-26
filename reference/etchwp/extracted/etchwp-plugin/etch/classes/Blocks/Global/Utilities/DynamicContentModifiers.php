<?php
/**
 * DynamicContentModifiers
 *
 * Sources-aware modifier application.
 *
 * This is a Blocks-side adapter that mirrors the TS modifier registry pattern
 * (`getModifier`) while delegating modifier implementations to the Blocks
 * modifier library.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

/**
 * DynamicContentModifiers class.
 */
class DynamicContentModifiers {

	/**
	 * Apply a modifier to a value.
	 *
	 * Options:
	 * - sources: array<int, mixed>
	 *
	 * @param mixed                $value Value to modify.
	 * @param string               $modifier Modifier string like `applyData()`.
	 * @param array<string, mixed> $options Modifier options.
	 * @return mixed
	 */
	public static function apply_modifier( $value, string $modifier, array $options = array() ) {
		$modifier = trim( $modifier );
		if ( '' === $modifier || ! ModifierParser::is_modifier( $modifier ) ) {
			return $value;
		}

		$sources = $options['sources'] ?? array();
		if ( ! is_array( $sources ) ) {
			$sources = array();
		}

		$parsed = ModifierParser::parse( $modifier );
		$method = $parsed['method'];

		// TS applyData() calls DynamicContentProcessor.apply(value, options).
		if ( 'applyData' === $method ) {
			return DynamicContentProcessor::apply(
				$value,
				array(
					'sources' => $sources,
				)
			);
		}

		return Modifiers::apply_modifier( $value, $modifier, $sources );
	}
}
