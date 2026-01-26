<?php
/**
 * DynamicContextProvider
 *
 * Sources-stack provider for PHP block rendering.
 *
 * This is a seam: it introduces a first-class "dynamic context stack" API
 * without wiring existing block rendering to it yet.
 *
 * @package Etch\Blocks\Global\DynamicContent
 */

declare(strict_types=1);

namespace Etch\Blocks\Global\DynamicContent;

use Etch\Blocks\Global\DynamicContent\DynamicPreviewRegistry;
use Etch\Traits\DynamicData;

/**
 * DynamicContextProvider class.
 */
class DynamicContextProvider {
	use DynamicData;

	/**
	 * Cached global context (built once per request).
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cached_global_context = null;

	/**
	 * Stack entries for the current render pass.
	 *
	 * @var array<int, DynamicContentEntry>
	 */
	private static array $entries = array();

	/**
	 * Build global context (site, url, options, user, this, term, taxonomy).
	 *
	 * @return array<string, mixed>
	 */
	private static function build_global_context(): array {
		if ( null !== self::$cached_global_context ) {
			return self::$cached_global_context;
		}

		$instance = new self();
		$global_context = array();

		$post = get_post();
		if ( null !== $post ) {
			$global_context['this'] = $instance->get_dynamic_data( $post );
		}

		$current_user = wp_get_current_user();
		if ( $current_user->exists() ) {
			$global_context['user'] = $instance->get_dynamic_user_data( $current_user );
		}

		$global_context['site'] = $instance->get_dynamic_site_data();
		$global_context['url'] = $instance->get_dynamic_url_data();
		$global_context['options'] = $instance->get_dynamic_option_pages_data();
		$global_context['archive'] = $instance->get_dynamic_archive_data();
		$global_context['environment'] = array(
			'current' => 'frontend',
		);

		if ( is_tax() || is_category() || is_tag() ) {
			$queried_object = get_queried_object();
			if ( $queried_object instanceof \WP_Term ) {
				$global_context['term'] = $instance->get_dynamic_term_data( $queried_object );
				$global_context['taxonomy'] = $instance->get_dynamic_tax_data( $queried_object->taxonomy );
			}
		}

		self::$cached_global_context = $global_context;

		return $global_context;
	}

	/**
	 * Push an entry onto the provider stack.
	 *
	 * @param DynamicContentEntry $entry Entry to push.
	 * @return void
	 */
	public static function push( DynamicContentEntry $entry ): void {
		self::$entries[] = $entry;
	}

	/**
	 * Pop the most recent entry.
	 *
	 * @return void
	 */
	public static function pop(): void {
		array_pop( self::$entries );
	}

	/**
	 * Clear the provider stack.
	 *
	 * @return void
	 */
	public static function clear(): void {
		self::$entries = array();
	}

	/**
	 * Get the current dynamic content stack.
	 *
	 * Includes globally-enqueued sources from DynamicContentRegistry.
	 *
	 * @return DynamicContentStack
	 */
	public static function get_stack(): DynamicContentStack {
		$entries = array();

		// Base global context (this/site/user/etc.) should always be present.
		// This mirrors the legacy ContextProvider behavior where global context is
		// available to all blocks, even when they don't receive a WP context.
		foreach ( self::build_global_context() as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			$entries[] = new DynamicContentEntry( 'global', $key, $value );
		}

		foreach ( DynamicContentRegistry::list() as $global_source ) {
			$entries[] = new DynamicContentEntry(
				'global',
				$global_source['key'],
				$global_source['source']
			);
		}

		foreach ( self::$entries as $entry ) {
			$entries[] = $entry;
		}

		// Preview sources should have the highest precedence.
		foreach ( DynamicPreviewRegistry::list() as $preview_source ) {
			$entries[] = new DynamicContentEntry(
				'preview',
				$preview_source['key'],
				$preview_source['source']
			);
		}

		return new DynamicContentStack( $entries );
	}

	/**
	 * Get a WP-provided initial context map for a block.
	 *
	 * This matches the legacy ContextProvider behavior:
	 * - prefer parent->context when present
	 * - otherwise use block->context
	 *
	 * @param \WP_Block|null $block WP block instance.
	 * @return array<string, mixed>
	 */
	private static function get_initial_context_for_wp_block( ?\WP_Block $block ): array {
		if ( ! ( $block instanceof \WP_Block ) ) {
			return array();
		}

		$parent_block = $block->parent ?? null;
		if ( $parent_block instanceof \WP_Block && is_array( $parent_block->context ) && ! empty( $parent_block->context ) ) {
			return $parent_block->context;
		}

		if ( is_array( $block->context ) && ! empty( $block->context ) ) {
			return $block->context;
		}

		return array();
	}

	/**
	 * Convert an associative context array into typed entries.
	 *
	 * @param array<string, mixed> $context Context map.
	 * @param string               $type    Entry type.
	 * @return array<int, DynamicContentEntry>
	 */
	private static function entries_from_context_array( array $context, string $type ): array {
		$entries = array();
		foreach ( $context as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			$entries[] = new DynamicContentEntry( $type, $key, $value );
		}
		return $entries;
	}

	/**
	 * Compute sources for a block, optionally applying a reset policy.
	 *
	 * Reset policies mirror the TS provider:
	 * - none: keep parent + append added
	 * - keepGlobal: keep only global entries from parent, then append added
	 * - all: ignore parent, use only added
	 *
	 * @param array<int, DynamicContentEntry> $added Added entries.
	 * @param string                          $reset Reset mode.
	 * @return array<int, array{key: string, source: mixed}>
	 */
	public static function get_sources_for_block( array $added = array(), string $reset = 'none' ): array {
		$parent_entries = self::get_stack()->all();

		switch ( $reset ) {
			case 'all':
				$entries = $added;
				break;
			case 'keepGlobal':
				$entries = array_merge(
					array_values(
						array_filter(
							$parent_entries,
							static function ( DynamicContentEntry $entry ): bool {
								return 'global' === $entry->get_type();
							}
						)
					),
					$added
				);
				break;
			case 'none':
			default:
				$entries = array_merge( $parent_entries, $added );
				break;
		}

		return ( new DynamicContentStack( $entries ) )->to_sources();
	}

	/**
	 * Compute sources for a WP block render callback.
	 *
	 * Includes WP-provided context (block->context / parent->context) as
	 * local entries layered above the base global context.
	 *
	 * @param \WP_Block|null                  $block WP block instance.
	 * @param array<int, DynamicContentEntry> $added Added entries.
	 * @param string                          $reset Reset mode.
	 * @return array<int, array{key: string, source: mixed}>
	 */
	public static function get_sources_for_wp_block( ?\WP_Block $block, array $added = array(), string $reset = 'none' ): array {
		$initial_context = self::get_initial_context_for_wp_block( $block );
		$initial_entries = self::entries_from_context_array( $initial_context, 'local' );
		if ( 'keepGlobal' === $reset || 'all' === $reset ) {
			$initial_entries = array();
		}

		return self::get_sources_for_block( array_merge( $initial_entries, $added ), $reset );
	}
}
