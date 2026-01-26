<?php
/**
 * WordPress Query loop handler for Etch.
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\LoopHandlers;

use WP_Query;
use WP_Post;
use Etch\Traits\DynamicData;
use Etch\Helpers\Logger;

/**
 * Handles loops that use WordPress WP_Query.
 */
class WpQueryLoopHandler extends LoopHandlerInterface {

	use DynamicData;

	/**
	 * Get loop data for the specified query/preset name using WP_Query.
	 *
	 * @param string               $query_name The name of the query/loop preset.
	 * @param array<string, mixed> $loop_params Additional parameters for the loop.
	 * @return array<int, array<string, mixed>> Array of post data for the loop.
	 */
	public function get_loop_data( string $query_name, array $loop_params = array() ): array {
		$query_args = $this->get_query_args( $query_name, $loop_params );

		if ( empty( $query_args ) ) {
			return array();
		}

		// Run the query to get posts
		$query = new WP_Query( $query_args );

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
				$loop_data[] = $post_data;
			}
		}

		return $loop_data;
	}
}
