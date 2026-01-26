<?php
/**
 * WordPress Terms loop handler for Etch.
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\LoopHandlers;

use WP_Term_Query;
use WP_Term;
use Etch\Traits\DynamicData;
use Etch\Helpers\Logger;

/**
 * Handles loops that use WordPress WP_Term_Query.
 */
class WpTermsLoopHandler extends LoopHandlerInterface {

	use DynamicData;

	/**
	 * Get loop data for the specified query/preset name using WP_Term_Query.
	 *
	 * @param string               $query_name The name of the query/loop preset.
	 * @param array<string, mixed> $loop_params The loop parameters.
	 * @return array<int, array<string, mixed>> Array of term data for the loop.
	 */
	public function get_loop_data( string $query_name, array $loop_params = array() ): array {

		/**
		 * Query arguments for WP_Term_Query.
		 *
		 * @var array{taxonomy?: array<string>|string, object_ids?: array<int>|int, orderby?: string, order?: string, hide_empty?: bool|int, include?: array<int>|string, exclude?: array<int>|string, exclude_tree?: array<int>|string, fields?: string} $query_args
		 */
		$query_args = $this->get_query_args( $query_name, $loop_params );

		if ( empty( $query_args ) || ! is_array( $query_args ) ) {
			return array();
		}

		// Ensure we get WP_Term objects
		if ( ! isset( $query_args['fields'] ) ) {
			$query_args['fields'] = 'all';
		}

		$query = new WP_Term_Query( $query_args );

		$terms = $query->get_terms();

		// Handle different return types - ensure we only work with arrays of WP_Term objects
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$processed_terms = array();
		foreach ( $terms as $index => $term ) {
			// Only process actual WP_Term objects
			if ( $term instanceof WP_Term ) {
				$processed_terms[ $index ] = $this->get_dynamic_term_data( $term );
			}
		}

		if ( empty( $processed_terms ) ) {
			return array();
		}

		return $processed_terms;
	}
}
