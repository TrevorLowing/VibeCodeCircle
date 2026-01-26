<?php
/**
 * WordPress Users loop handler for Etch.
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\LoopHandlers;

use WP_User_Query;
use WP_User;
use Etch\Traits\DynamicData;
use Etch\Helpers\Logger;

/**
 * Handles loops that use WordPress WP_User_Query.
 */
class WpUsersLoopHandler extends LoopHandlerInterface {

	use DynamicData;

	/**
	 * Get loop data for the specified query/preset name using WP_User_Query.
	 *
	 * @param string               $query_name The name of the query/loop preset.
	 * @param array<string, mixed> $loop_params The loop parameters.
	 * @return array<int, array<string, mixed>> Array of term data for the loop.
	 */
	public function get_loop_data( string $query_name, array $loop_params = array() ): array {

		/**
		 * Query arguments for WP_User_Query.
		 *
		 * @var array{blog_id?: int, role?: array<string>|string, role__in?: array<string>, role__not_in?: array<string>, meta_key?: array<string>|string, meta_value?: array<string>|string, meta_compare?: string, meta_compare_key?: string, ...}|null $query_args
		 */
		$query_args = $this->get_query_args( $query_name, $loop_params );

		if ( empty( $query_args ) || ! is_array( $query_args ) ) {
			return array();
		}

		$query = new WP_User_Query( $query_args );

		$users = $query->get_results();

		// Handle different return types - ensure we only work with arrays of WP_User objects
		if ( ! is_array( $users ) ) {
			return array();
		}

		$processed_users = array();
		foreach ( $users as $index => $user ) {
			// Only process actual WP_User objects
			if ( $user instanceof WP_User ) {
				$user_data = $this->get_dynamic_user_data( $user );
				if ( ! empty( $user_data ) ) {
					$processed_users[] = $user_data;
				}
			}
		}

		return $processed_users;
	}
}
