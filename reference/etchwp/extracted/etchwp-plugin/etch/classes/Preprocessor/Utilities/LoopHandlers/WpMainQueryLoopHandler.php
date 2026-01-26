<?php
/**
 * WordPress Main Query loop handler for Etch.
 *
 * Provides location-aware loop data using the global $wp_query,
 * useful for archive templates, search results, and taxonomy archives.
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\LoopHandlers;

use Etch\Helpers\Logger;
use WP_Query;
use WP_Post;
use Etch\Traits\DynamicData;

/**
 * Handles loops that use WordPress main query (global $wp_query).
 *
 * Unlike WpQueryLoopHandler which creates a new WP_Query from scratch,
 * this handler uses the main query's query_vars as a base and allows
 * optional parameter overrides.
 */
class WpMainQueryLoopHandler extends LoopHandlerInterface {

	use DynamicData;

	/**
	 * Get loop data using the main query as the base.
	 *
	 * @param string               $query_name The name of the query/loop preset.
	 * @param array<string, mixed> $loop_params Additional parameters for the loop.
	 * @return array<int, array<string, mixed>> Array of post data for the loop.
	 */
	public function get_loop_data( string $query_name, array $loop_params = array() ): array {
		global $wp_query;

		// Get base args from main query
		$base_args = array();
		if ( $wp_query instanceof WP_Query && ! empty( $wp_query->query_vars ) ) {
			$base_args = $wp_query->query_vars;
		}

		// Get preset config overrides (with loop params applied)
		$config_args = $this->get_query_args( $query_name, $loop_params );

		// Coerce WP_Query numeric arguments to proper types (they may be strings after param replacement)
		$config_args = $this->coerce_query_arg_types( $config_args );

		// Handle posts_per_page = -1 (all posts) - must set nopaging for proper WP_Query behavior
		if ( isset( $config_args['posts_per_page'] ) && -1 === $config_args['posts_per_page'] ) {
			$config_args['nopaging'] = true;
		}

		// Merge: base args + config args (config overrides base)
		$final_args = array_merge( $base_args, $config_args );

		// Ensure we don't mess with pagination if not explicitly overridden
		if ( ! isset( $config_args['paged'] ) && isset( $base_args['paged'] ) ) {
			$final_args['paged'] = $base_args['paged'];
		}

		// Run the query to get posts
		$query = new WP_Query( $final_args );

		if ( ! $query->have_posts() ) {
			return array();
		}

		$loop_data = array();

		// Loop through each post and get post data
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			// Get post data and add to loop data
			$post_data = $this->get_dynamic_data( $post );
			if ( ! empty( $post_data ) ) {
				// Add raw main query post data under 'main' key
				$post_data['main'] = $this->get_main_query_post_data( $post );
				$loop_data[] = $post_data;
			}
		}

		return $loop_data;
	}

	/**
	 * Get normalized main query post data.
	 *
	 * Returns the raw WP_Post object properties as an array, preserving
	 * all default WordPress post fields from the main query.
	 *
	 * @param WP_Post $post The post object from the main query.
	 * @return array<string, mixed> Normalized post data array.
	 */
	private function get_main_query_post_data( WP_Post $post ): array {
		// Get all object properties as an array
		$post_data = get_object_vars( $post );

		// Ensure all values are arrays/scalars (no objects)
		// This recursively normalizes any nested objects
		$normalized_data = $this->normalize_data_recursively( $post_data );

		// Guard against filters/extensions returning non-arrays
		return is_array( $normalized_data ) ? $normalized_data : array();
	}

	/**
	 * Coerce WP_Query arguments to their proper types.
	 *
	 * After parameter replacement, numeric values may be strings. WP_Query requires
	 * certain arguments (like posts_per_page) to be integers for proper handling.
	 *
	 * @param array<string, mixed> $args The query arguments.
	 * @return array<string, mixed> The arguments with proper types.
	 */
	private function coerce_query_arg_types( array $args ): array {
		// WP_Query arguments that should be integers
		$int_args = array(
			'posts_per_page',
			'paged',
			'offset',
			'p',
			'page_id',
			'attachment_id',
			'posts_per_archive_page',
			'nopaging',
		);

		// WP_Query arguments that should be booleans
		$bool_args = array(
			'ignore_sticky_posts',
			'no_found_rows',
			'cache_results',
			'update_post_meta_cache',
			'update_post_term_cache',
			'suppress_filters',
		);

		foreach ( $args as $key => $value ) {
			if ( in_array( $key, $int_args, true ) && is_string( $value ) && is_numeric( $value ) ) {
				$args[ $key ] = (int) $value;
			} elseif ( in_array( $key, $bool_args, true ) && is_string( $value ) ) {
				$args[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
			}
		}

		return $args;
	}
}
