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

use VibeCode\Deploy\Settings;

/**
 * Test Data Service
 *
 * Creates example posts for registered CPTs. Seed count per CPT comes from
 * option vibecode_deploy_seed_counts, filter vibecode_deploy_seed_count_{cpt}, or default (filter vibecode_deploy_seed_default_count or 30).
 * When ACF is active, fills all ACF fields with placeholder values.
 */
class TestDataService {

	/** Default number of posts to create per CPT when no option/filter is set. */
	private const DEFAULT_SEED_COUNT = 30;

	/**
	 * Get the number of posts to seed for a CPT.
	 *
	 * @param string $cpt_slug CPT slug.
	 * @return int Count (>= 1).
	 */
	private static function get_seed_count_for_cpt( string $cpt_slug ): int {
		$counts = get_option( 'vibecode_deploy_seed_counts', array() );
		if ( is_array( $counts ) && isset( $counts[ $cpt_slug ] ) && is_numeric( $counts[ $cpt_slug ] ) ) {
			$n = (int) $counts[ $cpt_slug ];
			return $n >= 1 ? $n : self::DEFAULT_SEED_COUNT;
		}
		$filter_name = 'vibecode_deploy_seed_count_' . $cpt_slug;
		if ( has_filter( $filter_name ) ) {
			$n = (int) apply_filters( $filter_name, self::DEFAULT_SEED_COUNT );
			return $n >= 1 ? $n : self::DEFAULT_SEED_COUNT;
		}
		$default = (int) apply_filters( 'vibecode_deploy_seed_default_count', self::DEFAULT_SEED_COUNT );
		return $default >= 1 ? $default : self::DEFAULT_SEED_COUNT;
	}

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
	 * @param bool  $force_seed    If true, seed even when CPT already has published posts (adds more posts).
	 * @return array Results with 'created', 'skipped', 'errors' keys.
	 */
	public static function seed_test_data( array $selected_cpts = array(), bool $force_seed = false ): array {
		$results = array(
			'created' => array(),
			'skipped' => array(),
			'errors' => array(),
		);

		// Get all registered custom post types (exclude built-in types)
		$public_cpts = get_post_types( array(
			'public' => true,
			'_builtin' => false,
		), 'names' );

		// Also include non-public CPTs that have show_ui enabled
		$non_public_cpts = get_post_types( array(
			'public' => false,
			'show_ui' => true,
			'_builtin' => false,
		), 'names' );

		$all_cpts = array_merge( $public_cpts, $non_public_cpts );
		$all_cpts = array_unique( $all_cpts );

		// Only allow CPTs deployed by this plugin (project-prefixed).
		$project_slug = Settings::get_all()['project_slug'] ?? '';
		if ( $project_slug !== '' ) {
			$all_cpts = array_values( array_filter( $all_cpts, function( $cpt ) use ( $project_slug ) {
				return strpos( $cpt, $project_slug . '_' ) === 0;
			} ) );
		} else {
			$all_cpts = array();
		}

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

			// Check if CPT already has posts (skip unless force_seed)
			$existing = wp_count_posts( $cpt );
			if ( ! $force_seed && (int) $existing->publish > 0 ) {
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
		$created      = array();
		$count        = self::get_seed_count_for_cpt( $cpt_slug );

		for ( $i = 1; $i <= $count; $i++ ) {
			$title = sprintf( 'Sample %s Post %d', $display_name, $i );

			$post_id = wp_insert_post(
				array(
					'post_title'   => $title,
					'post_content' => self::lorem_ipsum( 2 ),
					'post_status'  => 'publish',
					'post_type'    => $cpt_slug,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				error_log( sprintf( 'TestDataService: Failed to create post for CPT %s: %s', $cpt_slug, $post_id->get_error_message() ) );
				continue;
			}

			if ( $post_id === 0 ) {
				error_log( sprintf( 'TestDataService: wp_insert_post returned 0 for CPT %s', $cpt_slug ) );
				continue;
			}

			if ( function_exists( '\update_field' ) && function_exists( '\acf_get_field_groups' ) ) {
				self::fill_acf_placeholders( $post_id, $cpt_slug, $i, $created );
			} else {
				update_post_meta( $post_id, $cpt_slug . '_test_field_1', 'Lorem ipsum value 1' );
				update_post_meta( $post_id, $cpt_slug . '_test_field_2', 'Lorem ipsum value 2' );
				update_post_meta( $post_id, $cpt_slug . '_test_field_3', 'Sample test data ' . $i );
				update_post_meta( $post_id, $cpt_slug . '_test_date', gmdate( 'Y-m-d', strtotime( '-' . $i . ' days' ) ) );
			}

			$created[] = $post_id;
		}

		return $created;
	}

	/**
	 * Fill ACF fields for a post with placeholder values based on field type.
	 *
	 * @param int      $post_id   Post ID.
	 * @param string   $cpt_slug  Post type.
	 * @param int      $index     1-based index of this post in the seed run (for date/variation).
	 * @param int[]    $previous_ids Previously created post IDs in this CPT (for post_object/relationship).
	 * @return void
	 */
	private static function fill_acf_placeholders( int $post_id, string $cpt_slug, int $index, array $previous_ids ): void {
		if ( ! function_exists( '\acf_get_field_groups' ) || ! function_exists( '\acf_get_fields' ) || ! function_exists( '\update_field' ) ) {
			return;
		}
		$groups = \acf_get_field_groups( array( 'post_type' => $cpt_slug ) );
		if ( ! is_array( $groups ) ) {
			return;
		}
		foreach ( $groups as $group ) {
			$fields = isset( $group['key'] ) ? \acf_get_fields( $group['key'] ) : null;
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $field ) {
				$value = self::placeholder_value_for_acf_field( $field, $index, $post_id, $cpt_slug, $previous_ids );
				if ( $value !== null ) {
					\update_field( $field['name'], $value, $post_id );
				}
			}
		}
	}

	/**
	 * Get a placeholder value for an ACF field (and sub_fields for repeater/group).
	 *
	 * @param array    $field        ACF field array (with type, name, choices, sub_fields, etc.).
	 * @param int      $index        1-based index for date/variation.
	 * @param int      $post_id      Post ID (context).
	 * @param string   $cpt_slug     Post type.
	 * @param int[]    $previous_ids Previously created post IDs for post_object/relationship.
	 * @return mixed Value for update_field, or null to skip.
	 */
	private static function placeholder_value_for_acf_field( array $field, int $index, int $post_id, string $cpt_slug, array $previous_ids ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';
		$name = isset( $field['name'] ) ? $field['name'] : '';

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'url':
				return 'Sample ' . $name . ' ' . $index;
			case 'textarea':
			case 'wysiwyg':
				return 'Lorem ipsum for ' . $name . '. ' . substr( self::lorem_ipsum( 1 ), 3, 120 );
			case 'number':
			case 'range':
				return $index;
			case 'date_picker':
			case 'date_time_picker':
				return gmdate( 'Y-m-d', strtotime( '-' . $index . ' days' ) );
			case 'select':
			case 'radio':
			case 'button_group':
				$choices = isset( $field['choices'] ) && is_array( $field['choices'] ) ? $field['choices'] : array();
				if ( ! empty( $choices ) ) {
					$vals = array_values( $choices );
					return is_array( $vals[0] ) ? ( $vals[0]['value'] ?? $vals[0] ) : $vals[0];
				}
				return '';
			case 'checkbox':
				return array();
			case 'true_false':
				return false;
			case 'repeater':
				$sub_fields = isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array();
				$rows      = array();
				$row_count = min( 2, empty( $sub_fields ) ? 0 : 2 );
				for ( $r = 0; $r < $row_count; $r++ ) {
					$row = array();
					foreach ( $sub_fields as $sub ) {
						$sub_val = self::placeholder_value_for_acf_field( $sub, $index + $r, $post_id, $cpt_slug, $previous_ids );
						if ( $sub_val !== null && isset( $sub['name'] ) ) {
							$row[ $sub['name'] ] = $sub_val;
						}
					}
					if ( ! empty( $row ) ) {
						$rows[] = $row;
					}
				}
				return $rows;
			case 'group':
				$sub_fields = isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ? $field['sub_fields'] : array();
				$group_val  = array();
				foreach ( $sub_fields as $sub ) {
					$sub_val = self::placeholder_value_for_acf_field( $sub, $index, $post_id, $cpt_slug, $previous_ids );
					if ( $sub_val !== null && isset( $sub['name'] ) ) {
						$group_val[ $sub['name'] ] = $sub_val;
					}
				}
				return $group_val;
			case 'file':
			case 'image':
				return 0;
			case 'post_object':
			case 'relationship':
				if ( ! empty( $previous_ids ) && ( ! isset( $field['multiple'] ) || ! $field['multiple'] ) ) {
					return $previous_ids[0];
				}
				if ( ! empty( $previous_ids ) ) {
					return array_slice( $previous_ids, 0, 2 );
				}
				return 0;
			default:
				return 'Sample ' . $index;
		}
	}
}
