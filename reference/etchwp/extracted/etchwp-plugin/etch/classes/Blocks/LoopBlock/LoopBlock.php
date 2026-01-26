<?php
/**
 * Loop Block
 *
 * Renders its inner blocks for each item of a resolved collection.
 * Supports loop presets via LoopHandlerManager and arbitrary targets resolved
 * through dynamic expressions with modifiers. Loop params can be dynamic.
 *
 * @package Etch\Blocks\LoopBlock
 */

namespace Etch\Blocks\LoopBlock;

use Etch\Blocks\Types\LoopAttributes;
use Etch\Blocks\Global\ScriptRegister;
use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Utilities\Utils;
use Etch\Blocks\Global\Utilities\DynamicContentModifiers;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Global\Utilities\ExpressionPath;
use Etch\Blocks\Global\Utilities\ModifierParser;
use Etch\Preprocessor\Utilities\LoopHandlerManager;

/**
 * LoopBlock class
 */
class LoopBlock {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the block
	 *
	 * @return void
	 */
	public function register_block() {
		register_block_type(
			'etch/loop',
			array(
				'api_version' => '3',
				'attributes' => array(
					'target' => array(
						'type' => 'string',
						'default' => '',
					),
					'itemId' => array(
						'type' => 'string',
						'default' => '',
					),
					'indexId' => array(
						'type' => 'string',
						'default' => '',
					),
					'loopId' => array(
						'type' => 'string',
						'default' => null,
					),
					'loopParams' => array(
						'type' => 'object',
						'default' => null,
					),
				),
				'supports' => array(
					'html' => false,
					'className' => false,
					'customClassName' => false,
					'innerBlocks' => true,
				),
				'render_callback' => array( $this, 'render_block' ),
				'skip_inner_blocks' => true,
			)
		);
	}

	/**
	 * Render the block
	 *
	 * Two distinct pipelines for loop resolution:
	 * 1. loopId pipeline - Direct loop preset reference, uses loopParams attribute for arguments.
	 *    When loopId is set, target can contain modifiers (e.g., "slice(1)") to apply to the loop data.
	 * 2. target pipeline - Expression-based resolution, params extracted from target string
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content Block content (unused - we render children manually).
	 * @param \WP_Block|null       $block WP_Block instance (provides access to inner blocks and parent).
	 * @return string Rendered HTML for all loop iterations.
	 */
	public function render_block( array $attributes, string $content = '', $block = null ): string {
		$attrs = LoopAttributes::from_array( $attributes );

		ScriptRegister::register_script( $attrs );

		$sources = DynamicContextProvider::get_sources_for_wp_block( $block );

		$resolved_items = array();

		if ( null !== $attrs->loopId && '' !== $attrs->loopId ) {
			// === loopId pipeline ===
			// Uses loopParams attribute for arguments
			$resolved_loop_params = $this->resolve_loop_params( $attrs->loopParams, $sources );
			$loop_key = LoopHandlerManager::strip_loop_params_from_string( $attrs->loopId );

			if ( LoopHandlerManager::is_valid_loop_id( $loop_key ) ) {
				$resolved_items = LoopHandlerManager::get_loop_preset_data( $loop_key, $resolved_loop_params );
			}

			// Apply modifiers from target attribute if present
			if ( null !== $attrs->target && '' !== $attrs->target ) {
				$resolved_items = $this->apply_target_modifiers( $resolved_items, $attrs->target, $sources );
			}
		} else {
			// === target pipeline ===
			$resolved_items = $this->parse_loop_target( $attrs->target ?? '', $sources );
		}

		if ( empty( $resolved_items ) ) {
			return '';
		}

		// Prepare item and index keys
		$item_key = null !== $attrs->itemId && '' !== $attrs->itemId ? $attrs->itemId : 'item';
		$index_key = null !== $attrs->indexId && '' !== $attrs->indexId ? $attrs->indexId : null;

		// Render inner blocks for each item
		$rendered = '';
		$inner_blocks = array();
		if ( $block instanceof \WP_Block && isset( $block->parsed_block['innerBlocks'] ) && is_array( $block->parsed_block['innerBlocks'] ) ) {
			$inner_blocks = $block->parsed_block['innerBlocks'];
		}

		foreach ( $resolved_items as $index => $item ) {
			DynamicContextProvider::push(
				new DynamicContentEntry(
					'loop',
					$item_key,
					$item,
					array(
						'currentIndex' => $index,
					)
				)
			);
			if ( null !== $index_key ) {
				DynamicContextProvider::push(
					new DynamicContentEntry(
						'loop-index',
						$index_key,
						$index,
						array(
							'currentIndex' => $index,
						)
					)
				);
			}

			foreach ( $inner_blocks as $child ) {
				$rendered .= render_block( $child );
			}

			if ( null !== $index_key ) {
				DynamicContextProvider::pop();
			}
			DynamicContextProvider::pop();
		}

		return $rendered;
	}

	/**
	 * Parse a loop target string into loop items.
	 *
	 * @param string                                        $target  The target string.
	 * @param array<int, array{key: string, source: mixed}> $sources The current sources for expression resolution.
	 * @return array<int|string, mixed> The resolved loop items.
	 */
	private function parse_loop_target( string $target, array $sources ): array {
		if ( '' === $target ) {
			return array();
		}

		// Handle JSON arrays/objects directly (inline array loops)
		if (
			( str_starts_with( $target, '[' ) && str_ends_with( $target, ']' ) ) ||
			( str_starts_with( $target, '{' ) && str_ends_with( $target, '}' ) )
		) {
			$json_value = json_decode( $target, true );
			if ( is_array( $json_value ) ) {
				if ( ! array_is_list( $json_value ) ) {
					return array( $json_value );
				}
				return $json_value;
			}
		}

		// Use standard expression parsing to split the path
		$parts = ExpressionPath::split( $target );

		$current_data = null;
		$is_loop_resolved = false;

		foreach ( $parts as $i => $part ) {
			// Check if part is a modifier (e.g. "slice(2)")
			$is_modifier = ModifierParser::is_modifier( $part );

			// If we have resolved the loop data, treat remaining parts as modifiers
			if ( $is_loop_resolved ) {
				$current_data = DynamicContentModifiers::apply_modifier(
					$current_data,
					$part,
					array(
						'sources' => $sources,
					)
				);
				continue;
			}

			$potential_loop_key = $part;
			$loop_args = array();

			if ( $is_modifier ) {
				$parsed_modifier = ModifierParser::parse( $part );
				$potential_loop_key = $parsed_modifier['method'];

				$arg_parts = ModifierParser::split_args( $parsed_modifier['args'] );
				$loop_args = Utils::parse_keyword_args( $arg_parts, $sources );
			}

			$next_value = null;

			if ( null === $current_data ) {
				if ( LoopHandlerManager::is_valid_loop_id( $potential_loop_key ) ) {
					$next_value = LoopHandlerManager::get_loop_preset_data( $potential_loop_key, $loop_args );
					$is_loop_resolved = true;
				} else {
					$next_value = DynamicContentProcessor::process_expression(
						$potential_loop_key,
						array(
							'sources' => $sources,
							'specialType' => 'loop',
						)
					);
				}
			} elseif ( is_object( $current_data ) && isset( $current_data->$potential_loop_key ) ) {
				$next_value = $current_data->$potential_loop_key;
			} elseif ( is_array( $current_data ) && isset( $current_data[ $potential_loop_key ] ) ) {
				$next_value = $current_data[ $potential_loop_key ];
			}

			$current_data = $next_value;

			if ( $this->is_loop_prop_structure( $current_data ) ) {
				$current_data = $current_data['key'] ?? '';
			}

			if ( is_string( $current_data ) && ! $is_loop_resolved ) {
				if ( LoopHandlerManager::is_valid_loop_id( $current_data ) ) {
					$current_data = LoopHandlerManager::get_loop_preset_data( $current_data, $loop_args );
					$is_loop_resolved = true;
				} else {
					$resolved = DynamicContentProcessor::process_expression(
						$current_data,
						array(
							'sources' => $sources,
							'specialType' => 'loop',
						)
					);
					if ( is_array( $resolved ) ) {
						$current_data = $resolved;
						$is_loop_resolved = true;
					}
				}
			}
		}

		if ( is_array( $current_data ) ) {
			// If it's a list (sequential 0-indexed keys), return as-is for iteration
			// If it's an associative array (like a single post from at()), wrap it for single-item iteration
			if ( ! array_is_list( $current_data ) ) {
				return array( $current_data );
			}
			return $current_data;
		}

		return array();
	}

	/**
	 * Resolve loop parameters (process string expressions against sources)
	 * Skips empty string values so loop config defaults ($param ?? default) can work.
	 *
	 * @param array<string, mixed>|null                     $params Loop params.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for expression resolution.
	 * @return array<string, mixed>
	 */
	private function resolve_loop_params( ?array $params, array $sources ): array {
		if ( empty( $params ) ) {
			return array();
		}

		$resolved = array();
		foreach ( $params as $key => $value ) {
			if ( is_string( $value ) ) {
				$resolved_value = DynamicContentProcessor::process_expression(
					$value,
					array(
						'sources' => $sources,
					)
				);

				if ( null === $resolved_value ) {
					$resolved[ $key ] = $value;
					continue;
				}

				if ( '' === $resolved_value ) {
					continue;
				}

				$resolved[ $key ] = $resolved_value;
			} else {
				$resolved[ $key ] = $value;
			}
		}

		return $resolved;
	}

	/**
	 * Apply modifiers from the target attribute to resolved loop items.
	 * When loopId is used, the target may contain just modifiers (e.g. "slice(1)")
	 * that should be applied to the loop data.
	 *
	 * @param array<int|string, mixed>                      $items   The resolved loop items.
	 * @param string                                        $target  The target string containing modifiers.
	 * @param array<int, array{key: string, source: mixed}> $sources Sources for expression resolution.
	 * @return array<int|string, mixed> The items after applying modifiers.
	 */
	private function apply_target_modifiers( array $items, string $target, array $sources ): array {
		if ( '' === $target ) {
			return $items;
		}

		// Split the target into parts to check for modifiers
		$parts = ExpressionPath::split( $target );

		// Apply each part as a modifier
		$current_data = $items;
		foreach ( $parts as $part ) {
			if ( ModifierParser::is_modifier( $part ) ) {
				$current_data = DynamicContentModifiers::apply_modifier(
					$current_data,
					$part,
					array(
						'sources' => $sources,
					)
				);
			}
		}

		// Ensure we return an array
		if ( is_array( $current_data ) ) {
			return $current_data;
		}

		return $items;
	}

	/**
	 * Check if a value is a loop prop structure.
	 *
	 * @param mixed $value Value to check.
	 * @return bool
	 */
	private function is_loop_prop_structure( $value ): bool {
		return is_array( $value )
			&& isset( $value['prop-type'] )
			&& 'loop' === $value['prop-type'];
	}
}
