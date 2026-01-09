<?php
/**
 * Test Data Service
 *
 * Creates example posts for all registered CPTs to help with testing and development.
 * Uses generic lorem ipsum content and automatically detects CPTs.
 *
 * @package VibeCode\Deploy
 */

namespace VibeCode\Deploy\Services;

/**
 * Test Data Service
 */
class TestDataService {

	/**
	 * Generate lorem ipsum text.
	 *
	 * @param int $paragraphs Number of paragraphs to generate.
	 * @return string HTML paragraphs of lorem ipsum text.
	 */
	private static function lorem_ipsum( int $paragraphs = 2 ): string {
		$lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';
		
		$html = '';
		for ( $i = 0; $i < $paragraphs; $i++ ) {
			$html .= '<p>' . esc_html( $lorem ) . '</p>' . "\n";
		}
		return $html;
	}

	/**
	 * Get CPT display name.
	 *
	 * @param string $cpt_slug CPT slug.
	 * @return string Display name or formatted slug.
	 */
	private static function get_cpt_display_name( string $cpt_slug ): string {
		$post_type_obj = get_post_type_object( $cpt_slug );
		if ( $post_type_obj && isset( $post_type_obj->labels->singular_name ) ) {
			return $post_type_obj->labels->singular_name;
		}
		// Fallback: format slug as title
		return ucwords( str_replace( array( '-', '_' ), ' ', $cpt_slug ) );
	}

	/**
	 * Seed test data for all CPTs.
	 *
	 * @param array $selected_cpts Optional array of CPT slugs to seed. If empty, seeds all CPTs.
	 * @return array Results with 'created', 'skipped', 'errors' keys.
	 */
	public static function seed_test_data( array $selected_cpts = array() ): array {
		$results = array(
			'created' => array(),
			'skipped' => array(),
			'errors' => array(),
		);

		// Get all registered custom post types (exclude built-in types)
		$all_cpts = get_post_types( array(
			'public' => true,
			'_builtin' => false,
		), 'names' );

		// Filter to selected CPTs if provided
		if ( ! empty( $selected_cpts ) ) {
			$cpts_to_seed = array_intersect( $all_cpts, $selected_cpts );
		} else {
			$cpts_to_seed = $all_cpts;
		}

		foreach ( $cpts_to_seed as $cpt ) {
			if ( ! post_type_exists( $cpt ) ) {
				$results['skipped'][] = array(
					'cpt' => $cpt,
					'reason' => 'CPT not registered',
				);
				continue;
			}

			// Check if CPT already has posts
			$existing = wp_count_posts( $cpt );
			if ( (int) $existing->publish > 0 ) {
				$results['skipped'][] = array(
					'cpt' => $cpt,
					'reason' => 'CPT already has published posts',
				);
				continue;
			}

			try {
				$created = self::seed_generic_cpt( $cpt );
				$results['created'][ $cpt ] = $created;
			} catch ( \Exception $e ) {
				$results['errors'][] = array(
					'cpt' => $cpt,
					'error' => $e->getMessage(),
				);
			}
		}

		return $results;
	}

	/**
	 * Seed generic posts for a CPT using lorem ipsum content.
	 *
	 * @param string $cpt_slug CPT slug.
	 * @return array Created post IDs.
	 */
	private static function seed_generic_cpt( string $cpt_slug ): array {
		$display_name = self::get_cpt_display_name( $cpt_slug );
		$created = array();

		// Create 3 sample posts per CPT
		for ( $i = 1; $i <= 3; $i++ ) {
			$title = sprintf( 'Sample %s Post %d', $display_name, $i );
			
			$post_id = wp_insert_post(
				array(
					'post_title' => $title,
					'post_content' => self::lorem_ipsum( 2 ),
					'post_status' => 'publish',
					'post_type' => $cpt_slug,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			// Add generic meta fields based on CPT slug
			// Use pattern: {cpt_slug}_test_field_{number}
			update_post_meta( $post_id, $cpt_slug . '_test_field_1', 'Lorem ipsum value 1' );
			update_post_meta( $post_id, $cpt_slug . '_test_field_2', 'Lorem ipsum value 2' );
			update_post_meta( $post_id, $cpt_slug . '_test_field_3', 'Sample test data ' . $i );
			
			// Add a date field if it's a common pattern
			update_post_meta( $post_id, $cpt_slug . '_test_date', gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) ) );

			$created[] = $post_id;
		}

		return $created;
	}
}
