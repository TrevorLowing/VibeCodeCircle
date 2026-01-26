<?php
/**
 * DynamicContentProcessor
 *
 * New sources-based dynamic content processing API.
 *
 * This lives under Blocks/Global/Utilities (not Preprocessor) and is introduced
 * without changing existing call sites yet.
 *
 * @package Etch\Blocks\Global\Utilities
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\Utilities;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * DynamicContentProcessor class.
 */
class DynamicContentProcessor {
	/**
	 * Split an expression into parts (dot/bracket/paren aware).
	 *
	 * This is a Blocks-based equivalent of the legacy preprocessor split logic.
	 *
	 * @param string $expression Expression path.
	 * @return array<int, string>
	 */
	public static function split_expression_path( string $expression ): array {
		return ExpressionPath::split( $expression );
	}

	/**
	 * Infer a literal value from an expression string.
	 *
	 * Mirrors the TS processor behavior: quoted strings, numbers, booleans, and
	 * JSON objects/arrays are treated as literals.
	 *
	 * @param string $expression Expression string.
	 * @return array{resolved: bool, value: mixed}
	 */
	public static function infer_literal( string $expression ): array {
		$expression = trim( $expression );

		static $cache = array();
		if ( isset( $cache[ $expression ] ) ) {
			return $cache[ $expression ];
		}

		if ( '' === $expression ) {
			$result = array(
				'resolved' => true,
				'value'    => $expression,
			);
			$cache[ $expression ] = $result;
			return $result;
		}

		// Quoted strings.
		if ( preg_match( '/^(["\'])(.*)\1$/s', $expression, $matches ) ) {
			$result = array(
				'resolved' => true,
				'value'    => $matches[2],
			);
			$cache[ $expression ] = $result;
			return $result;
		}

		// Numeric.
		if ( is_numeric( $expression ) ) {
			$result = array(
				'resolved' => true,
				'value'    => str_contains( $expression, '.' ) ? (float) $expression : (int) $expression,
			);
			$cache[ $expression ] = $result;
			return $result;
		}

		// Booleans.
		if ( 'true' === strtolower( $expression ) ) {
			$result = array(
				'resolved' => true,
				'value'    => true,
			);
			$cache[ $expression ] = $result;
			return $result;
		}
		if ( 'false' === strtolower( $expression ) ) {
			$result = array(
				'resolved' => true,
				'value'    => false,
			);
			$cache[ $expression ] = $result;
			return $result;
		}

		// JSON object/array.
		if (
			( str_starts_with( $expression, '[' ) && str_ends_with( $expression, ']' ) )
			|| ( str_starts_with( $expression, '{' ) && str_ends_with( $expression, '}' ) )
		) {
			$json_value = json_decode( $expression, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				$result = array(
					'resolved' => true,
					'value'    => $json_value,
				);
				$cache[ $expression ] = $result;
				return $result;
			}
		}

		$result = array(
			'resolved' => false,
			'value'    => null,
		);
		if ( count( $cache ) > 2000 ) {
			$cache = array();
		}
		$cache[ $expression ] = $result;
		return $result;
	}

	/**
	 * Resolve an expression from a sources list.
	 *
	 * This is the sources-based equivalent of legacy expression processing.
	 *
	 * @param string               $expression Expression string.
	 * @param array<string, mixed> $options Processor options.
	 * @return mixed|null
	 */
	public static function resolve_from_sources( string $expression, array $options = array() ) {
		return self::process_expression( $expression, $options );
	}

	/**
	 * Apply dynamic content to a value.
	 *
	 * Supports strings (templates and full expressions), arrays, and objects.
	 *
	 * Options:
	 * - sources: array<int, array{key?: string, source?: mixed}>
	 * - specialType: string|null (e.g. 'loop')
	 *
	 * @param mixed                $value The value to process.
	 * @param array<string, mixed> $options Processor options.
	 * @return mixed
	 */
	public static function apply( $value, array $options = array() ) {
		if ( empty( $value ) ) {
			return $value;
		}

		$sources = $options['sources'] ?? array();
		if ( ! is_array( $sources ) ) {
			$sources = array();
		}

		$special_type = isset( $options['specialType'] ) && is_string( $options['specialType'] )
			? $options['specialType']
			: null;

		if ( is_string( $value ) ) {
			if ( self::is_dynamic_expression( $value ) ) {
				return self::process_expression(
					substr( $value, 1, -1 ),
					array(
						'sources' => $sources,
						'specialType' => $special_type,
					)
				);
			}

			return self::replace_templates(
				$value,
				array(
					'sources' => $sources,
					'specialType' => $special_type,
				)
			);
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$as_array = EtchTypeAsserter::to_array( $value );
			foreach ( $as_array as $key => $item ) {
				$as_array[ $key ] = self::apply( $item, $options );
			}
			return $as_array;
		}

		return $value;
	}


	/**
	 * Replace `{...}` templates inside a string.
	 *
	 * @param string               $value The string to process.
	 * @param array<string, mixed> $options Processor options.
	 * @return string
	 */
	public static function replace_templates( string $value, array $options = array() ): string {
		$sources = $options['sources'] ?? array();
		if ( ! is_array( $sources ) ) {
			$sources = array();
		}

		$special_type = isset( $options['specialType'] ) && is_string( $options['specialType'] )
			? $options['specialType']
			: null;

		return self::find_and_replace_expressions(
			$value,
			function ( string $expression ) use ( $sources, $special_type ) {
				$expression_result = self::process_expression(
					$expression,
					array(
						'sources' => $sources,
						'specialType' => $special_type,
					)
				);

				if ( null === $expression_result ) {
					return '';
				}

				if ( is_array( $expression_result ) && array_is_list( $expression_result ) ) {
					return implode(
						', ',
						array_map(
							static function ( $item ) {
								return EtchTypeAsserter::to_string( $item );
							},
							$expression_result
						)
					);
				}

				return EtchTypeAsserter::to_string( $expression_result );
			}
		);
	}

	/**
	 * Process a single expression (without wrapping braces).
	 *
	 * @param string               $expression Expression string.
	 * @param array<string, mixed> $options Processor options.
	 * @return mixed
	 */
	public static function process_expression( string $expression, array $options = array() ) {
		$sources = $options['sources'] ?? array();
		if ( ! is_array( $sources ) ) {
			$sources = array();
		}

		$special_type = isset( $options['specialType'] ) && is_string( $options['specialType'] )
			? $options['specialType']
			: null;
		$literal = self::infer_literal( $expression );
		if ( true === $literal['resolved'] ) {
			return $literal['value'];
		}

		return SourceResolver::resolve_from_sources( $expression, $sources, array(), $special_type );
	}

	/**
	 * Check if a string is a standalone dynamic expression.
	 *
	 * @param string $value The value to check.
	 * @return bool
	 */
	private static function is_dynamic_expression( string $value ): bool {
		if ( '' === $value || strlen( $value ) < 2 || '{' !== $value[0] || '}' !== $value[-1] ) {
			return false;
		}

		$brace_depth = 0;
		$in_string = false;
		$string_char = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( $brace_depth > 0 && ( '"' === $char || "'" === $char || '`' === $char ) ) {
				$is_escaped = $i > 0 && '\\' === $value[ $i - 1 ];
				if ( ! $is_escaped ) {
					if ( ! $in_string ) {
						$in_string = true;
						$string_char = $char;
					} elseif ( $char === $string_char ) {
						$in_string = false;
					}
				}
			}

			if ( ! $in_string ) {
				if ( '{' === $char ) {
					$brace_depth++;
					continue;
				} elseif ( '}' === $char ) {
					$brace_depth--;
					if ( 0 === $brace_depth && $i < $length - 1 ) {
						return false;
					}

					if ( $brace_depth < 0 ) {
						return false;
					}
				}
			}
		}

		return 0 === $brace_depth;
	}

	/**
	 * Extract and replace dynamic expressions in a string using a callback.
	 *
	 * @param string   $value The string containing dynamic expressions.
	 * @param callable $replace_callback The callback to process each dynamic expression.
	 * @return string The string with dynamic expressions replaced.
	 */
	private static function find_and_replace_expressions( string $value, callable $replace_callback ): string {
		if ( '' === $value || strpos( $value, '{' ) === false ) {
			return $value;
		}

		static $cache = array();
		if ( isset( $cache[ $value ] ) ) {
			$result = '';
			foreach ( $cache[ $value ] as $token ) {
				if ( 'literal' === $token['type'] ) {
					$result .= $token['value'];
					continue;
				}
				$result .= $replace_callback( $token['value'] );
			}
			return $result;
		}

		$tokens = array();
		$literal = '';
		$brace_depth = 0;
		$current_expression = '';
		$in_string = false;
		$string_char = '';
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $value[ $i ];

			if ( $brace_depth > 0 && ( '"' === $char || "'" === $char || '`' === $char ) ) {
				$is_escaped = $i > 0 && '\\' === $value[ $i - 1 ];
				if ( ! $is_escaped ) {
					if ( ! $in_string ) {
						$in_string = true;
						$string_char = $char;
					} elseif ( $char === $string_char ) {
						$in_string = false;
					}
				}
			}

			if ( ! $in_string ) {
				if ( '{' === $char ) {
					if ( 0 === $brace_depth && '' !== $literal ) {
						$tokens[] = array(
							'type'  => 'literal',
							'value' => $literal,
						);
						$literal = '';
					}
					if ( $brace_depth > 0 ) {
						$current_expression .= $char;
					}
					$brace_depth++;
					continue;
				} elseif ( '}' === $char ) {
					$brace_depth = max( 0, $brace_depth - 1 );
					if ( 0 === $brace_depth ) {
						$tokens[] = array(
							'type'  => 'expression',
							'value' => $current_expression,
						);
						$current_expression = '';
					} else {
						$current_expression .= $char;
					}
					continue;
				}
			}

			if ( $brace_depth > 0 ) {
				$current_expression .= $char;
			} else {
				$literal .= $char;
			}
		}

		if ( '' !== $current_expression && $brace_depth > 0 ) {
			$literal .= '{' . $current_expression;
		}

		if ( '' !== $literal ) {
			$tokens[] = array(
				'type'  => 'literal',
				'value' => $literal,
			);
		}

		if ( count( $cache ) > 1000 ) {
			$cache = array();
		}
		$cache[ $value ] = $tokens;

		$result = '';
		foreach ( $tokens as $token ) {
			if ( 'literal' === $token['type'] ) {
				$result .= $token['value'];
				continue;
			}
			$result .= $replace_callback( $token['value'] );
		}

		return $result;
	}
}
