<?php
/**
 * MigrationChecker class.
 *
 * Checks if migration is needed by scanning post content for legacy "etchData" markers.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

use Etch\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * MigrationChecker class.
 *
 * Checks all public post types for "etchData" in post content to determine
 * if the custom block migration is needed.
 */
class MigrationChecker {

	/**
	 * Migration key for tracking if this check has been run.
	 *
	 * @var string
	 */
	private const MIGRATION_KEY = 'custom_block_migration_check';

	/**
	 * Check all posts for legacy "etchData" and set migration flag accordingly.
	 *
	 * This method scans all public post types for "etchData" in post_content.
	 * If found, migration is needed (flag = false).
	 * If not found, migration is not needed (flag = true).
	 *
	 * @return void
	 */
	public static function check_and_set_migration_flag(): void {
		// Check if migration flag is already set to true (migration completed)
		$settings = get_option( 'etch_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['custom_block_migration_completed'] ) ) {
			Logger::log( sprintf( '%s: Migration already completed, skipping check', __METHOD__ ) );
			return;
		}

		// Use Migration::run_once() to ensure this only runs once
		Migration::run_once(
			self::MIGRATION_KEY,
			function () {
				Logger::log( sprintf( '%s: Starting migration check', __METHOD__ ) );

				$has_etch_data = self::has_etch_data_in_posts();

				// Get current settings
				$settings = get_option( 'etch_settings', array() );
				if ( ! is_array( $settings ) ) {
					$settings = array();
				}

				// Set migration flag based on findings
				// If etchData found → migration needed → false
				// If no etchData found → fresh install → true
				$settings['custom_block_migration_completed'] = ! $has_etch_data;

				update_option( 'etch_settings', $settings );

				Logger::log(
					sprintf(
						'%s: Migration check completed. Found etchData: %s. Migration flag set to: %s',
						__METHOD__,
						$has_etch_data ? 'yes' : 'no',
						$settings['custom_block_migration_completed'] ? 'true' : 'false'
					)
				);
			}
		);
	}

	/**
	 * Check if any posts contain "etchData" in their content.
	 *
	 * @return bool True if "etchData" is found in any post content, false otherwise.
	 */
	private static function has_etch_data_in_posts(): bool {
		// Get all public post types
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		// Remove attachment from the list as it's not relevant for block content
		$post_types = array_diff( $post_types, array( 'attachment' ) );

		if ( empty( $post_types ) ) {
			Logger::log( sprintf( '%s: No public post types found', __METHOD__ ) );
			return false;
		}

		// Query posts in batches to check for "etchData"
		// We only need to find ONE instance to know migration is needed
		$batch_size = 100;
		$offset = 0;

		while ( true ) {
			$query_args = array(
				'post_type'      => $post_types,
				'post_status'    => 'any', // Check all statuses
				'posts_per_page' => $batch_size,
				'offset'         => $offset,
				'fields'         => 'ids', // Only get IDs for performance
				'no_found_rows'  => true, // Skip pagination count for performance
			);

			$query = new \WP_Query( $query_args );

			if ( ! $query->have_posts() ) {
				// No more posts to check
				break;
			}

			// Check each post's content for "etchData"
			foreach ( $query->posts as $post_id ) {
				// Ensure $post_id is an integer (when 'fields' => 'ids' is set)
				if ( ! is_int( $post_id ) ) {
					continue;
				}

				$post_content = get_post_field( 'post_content', $post_id );

				if ( is_string( $post_content ) && false !== strpos( $post_content, 'etchData' ) ) {
					$post_type = get_post_type( $post_id );
					Logger::log(
						sprintf(
							'%s: Found "etchData" in post ID %d (%s)',
							__METHOD__,
							$post_id,
							is_string( $post_type ) ? $post_type : 'unknown'
						)
					);
					return true;
				}
			}

			// If we got fewer posts than the batch size, we've reached the end
			if ( count( $query->posts ) < $batch_size ) {
				break;
			}

			$offset += $batch_size;
		}

		Logger::log( sprintf( '%s: No "etchData" found in any posts', __METHOD__ ) );
		return false;
	}
}
