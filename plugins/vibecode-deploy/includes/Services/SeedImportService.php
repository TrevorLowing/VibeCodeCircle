<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Imports CPT and core post content from seed/*.json files in the build root.
 *
 * Seed files are JSON with post_type and items[]. Each item has post_title,
 * post_content, optional meta{}, and optional terms{} (taxonomy => [slug, ...]).
 * When ACF is active, meta keys that match ACF field names are applied via
 * update_field() so repeaters, file, post_object, etc. are stored correctly.
 * Idempotent: matches existing by title + post_type + terms; update or create.
 *
 * @package VibeCode\Deploy\Services
 */
final class SeedImportService {

	/** @var array<string, array<string>> Cache of post_type => ACF field names. */
	private static $acf_field_names_by_post_type = array();

	/**
	 * Run seed import from seed/*.json in the build root.
	 *
	 * @param string $build_root   Path to extracted staging (build root).
	 * @param string $project_slug Project slug for logging.
	 * @return array{created: int, updated: int, errors: array<int, string>}
	 */
	public static function import_from_build( string $build_root, string $project_slug ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'errors'  => array(),
		);

		$build_root = rtrim( $build_root, '/\\' );
		$seed_dir   = $build_root . DIRECTORY_SEPARATOR . 'seed';
		if ( ! is_dir( $seed_dir ) || ! is_readable( $seed_dir ) ) {
			return $result;
		}

		$json_files = glob( $seed_dir . DIRECTORY_SEPARATOR . '*.json' );
		if ( ! is_array( $json_files ) || empty( $json_files ) ) {
			return $result;
		}

		foreach ( $json_files as $path ) {
			$raw = file_get_contents( $path );
			if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
				continue;
			}
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) || empty( $data['post_type'] ) || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
				Logger::warning( 'Seed import: invalid or empty file.', array( 'path' => $path ), $project_slug );
				continue;
			}

			$post_type = sanitize_key( $data['post_type'] );
			if ( $post_type !== 'post' && ! post_type_exists( $post_type ) ) {
				Logger::warning( 'Seed import: post type not registered, skipping file.', array(
					'path'      => $path,
					'post_type' => $post_type,
				), $project_slug );
				continue;
			}

			foreach ( $data['items'] as $item ) {
				if ( ! is_array( $item ) || empty( $item['post_title'] ) ) {
					continue;
				}
				$title = trim( (string) $item['post_title'] );
				if ( $title === '' ) {
					continue;
				}
				$content   = isset( $item['post_content'] ) ? (string) $item['post_content'] : '';
				$excerpt   = isset( $item['post_excerpt'] ) ? (string) $item['post_excerpt'] : '';
				$meta      = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
				$terms     = isset( $item['terms'] ) && is_array( $item['terms'] ) ? $item['terms'] : array();
				$existing  = self::find_existing_post( $post_type, $title, $terms );
				if ( $existing ) {
					$post_id = wp_update_post(
						array(
							'ID'           => $existing,
							'post_content' => $content,
							'post_excerpt' => $excerpt,
							'post_type'    => $post_type,
						),
						true
					);
					if ( is_wp_error( $post_id ) || $post_id === 0 ) {
						$result['errors'][] = sprintf( '%s: update failed for "%s"', $post_type, substr( $title, 0, 50 ) );
						continue;
					}
					self::apply_meta( $post_id, $post_type, $meta );
					self::set_terms( $post_id, $terms );
					$result['updated']++;
				} else {
					$post_id = wp_insert_post(
						array(
							'post_title'   => $title,
							'post_content' => $content,
							'post_excerpt' => $excerpt,
							'post_status'  => 'publish',
							'post_type'    => $post_type,
						),
						true
					);
					if ( is_wp_error( $post_id ) || $post_id === 0 ) {
						$result['errors'][] = sprintf( '%s: insert failed for "%s"', $post_type, substr( $title, 0, 50 ) );
						continue;
					}
					self::apply_meta( $post_id, $post_type, $meta );
					self::set_terms( $post_id, $terms );
					$result['created']++;
				}
			}
		}

		if ( $result['created'] > 0 || $result['updated'] > 0 ) {
			Logger::info( 'Seed import completed.', array(
				'created' => $result['created'],
				'updated' => $result['updated'],
				'errors'  => count( $result['errors'] ),
			), $project_slug );
		}
		if ( ! empty( $result['errors'] ) ) {
			Logger::warning( 'Seed import had errors.', array( 'errors' => $result['errors'] ), $project_slug );
		}

		return $result;
	}

	/**
	 * Compute per-CPT seed counts (3 × per_page) from shortcode config. Saves to option vibecode_deploy_seed_counts.
	 *
	 * @param array  $placeholder_config Config from vibecode-deploy-shortcodes.json (pages, post_types).
	 * @param string $project_slug       Project slug for filter context.
	 * @return void
	 */
	public static function compute_and_save_seed_counts_from_config( array $placeholder_config, string $project_slug ): void {
		$counts = self::compute_seed_counts_from_config( $placeholder_config, $project_slug );
		if ( ! empty( $counts ) ) {
			update_option( 'vibecode_deploy_seed_counts', $counts, false );
		}
	}

	/**
	 * Compute per-CPT seed counts (3 × max per_page/limit) from shortcode config.
	 *
	 * @param array  $placeholder_config Config with pages and post_types.
	 * @param string $project_slug       Project slug.
	 * @return array<string, int> CPT slug => count.
	 */
	public static function compute_seed_counts_from_config( array $placeholder_config, string $project_slug ): array {
		$shortcode_to_cpt = array(
			'cfa_advisories'            => 'cfa_advisory',
			'cfa_recent_advisories'     => 'cfa_advisory',
			'cfa_advisories_home_teaser' => 'cfa_advisory',
			'cfa_investigations'        => 'cfa_investigation',
			'cfa_foia_index'            => 'cfa_foia_request',
			'cfa_surveys'               => 'cfa_survey',
			'cfa_investigation_foia'    => 'cfa_foia_request',
		);
		$shortcode_to_cpt = apply_filters( 'vibecode_deploy_seed_count_shortcode_to_cpt', $shortcode_to_cpt, $project_slug );

		$per_cpt = array();
		$collect = function ( array $shortcodes ) use ( $shortcode_to_cpt, &$per_cpt ) {
			foreach ( $shortcodes as $def ) {
				$name  = isset( $def['name'] ) ? (string) $def['name'] : '';
				$attrs = isset( $def['attrs'] ) && is_array( $def['attrs'] ) ? $def['attrs'] : array();
				$cpt   = isset( $shortcode_to_cpt[ $name ] ) ? $shortcode_to_cpt[ $name ] : null;
				if ( $cpt === null ) {
					continue;
				}
				$per_page = isset( $attrs['per_page'] ) && is_numeric( $attrs['per_page'] ) ? (int) $attrs['per_page'] : null;
				$limit    = isset( $attrs['limit'] ) && is_numeric( $attrs['limit'] ) ? (int) $attrs['limit'] : null;
				$n        = $per_page !== null ? $per_page : $limit;
				if ( $n !== null && $n >= 1 ) {
					$target = 3 * $n;
					if ( ! isset( $per_cpt[ $cpt ] ) || $target > $per_cpt[ $cpt ] ) {
						$per_cpt[ $cpt ] = $target;
					}
				}
			}
		};

		if ( ! empty( $placeholder_config['pages'] ) && is_array( $placeholder_config['pages'] ) ) {
			foreach ( $placeholder_config['pages'] as $page_slug => $page_config ) {
				if ( ! is_array( $page_config ) ) {
					continue;
				}
				if ( ! empty( $page_config['required_shortcodes'] ) ) {
					$collect( $page_config['required_shortcodes'] );
				}
				if ( ! empty( $page_config['recommended_shortcodes'] ) ) {
					$collect( $page_config['recommended_shortcodes'] );
				}
			}
		}
		if ( ! empty( $placeholder_config['post_types'] ) && is_array( $placeholder_config['post_types'] ) ) {
			foreach ( $placeholder_config['post_types'] as $cpt_slug => $cpt_config ) {
				if ( ! is_array( $cpt_config ) || empty( $cpt_config['recommended_shortcodes'] ) ) {
					continue;
				}
				$collect( $cpt_config['recommended_shortcodes'] );
			}
		}

		return $per_cpt;
	}

	/**
	 * Apply meta to a post. When ACF is active, keys that match ACF field names use update_field(); otherwise update_post_meta().
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @param array  $meta      Meta key => value (ACF field names supported when ACF active).
	 * @return void
	 */
	private static function apply_meta( int $post_id, string $post_type, array $meta ): void {
		$acf_field_names = array();
		if ( function_exists( '\update_field' ) && function_exists( '\acf_get_field_groups' ) ) {
			$acf_field_names = self::get_acf_field_names_for_post_type( $post_type );
		}
		foreach ( $meta as $meta_key => $meta_value ) {
			$key = sanitize_key( $meta_key );
			if ( $key === '' ) {
				continue;
			}
			if ( ! empty( $acf_field_names ) && in_array( $meta_key, $acf_field_names, true ) ) {
				\update_field( $meta_key, $meta_value, $post_id );
			} else {
				update_post_meta( $post_id, $key, $meta_value );
			}
		}
	}

	/**
	 * Get ACF field names (top-level) that apply to a post type. Cached per request.
	 *
	 * @param string $post_type Post type slug.
	 * @return array<string> Field names (name, not key).
	 */
	private static function get_acf_field_names_for_post_type( string $post_type ): array {
		if ( isset( self::$acf_field_names_by_post_type[ $post_type ] ) ) {
			return self::$acf_field_names_by_post_type[ $post_type ];
		}
		$names = array();
		if ( ! function_exists( '\acf_get_field_groups' ) ) {
			self::$acf_field_names_by_post_type[ $post_type ] = $names;
			return $names;
		}
		$groups = \acf_get_field_groups( array( 'post_type' => $post_type ) );
		if ( ! is_array( $groups ) ) {
			self::$acf_field_names_by_post_type[ $post_type ] = $names;
			return $names;
		}
		foreach ( $groups as $group ) {
			$fields = isset( $group['key'] ) && function_exists( '\acf_get_fields' ) ? \acf_get_fields( $group['key'] ) : null;
			if ( ! is_array( $fields ) ) {
				continue;
			}
			foreach ( $fields as $field ) {
				if ( ! empty( $field['name'] ) && is_string( $field['name'] ) ) {
					$names[] = $field['name'];
				}
			}
		}
		self::$acf_field_names_by_post_type[ $post_type ] = array_unique( $names );
		return self::$acf_field_names_by_post_type[ $post_type ];
	}

	/**
	 * Find existing post by title, post type, and optional terms (must match all given term slugs).
	 *
	 * @param string $post_type Post type.
	 * @param string $title     Post title.
	 * @param array  $terms     Taxonomy slug => array of term slugs.
	 * @return int Post ID or 0.
	 */
	private static function find_existing_post( string $post_type, string $title, array $terms ): int {
		global $wpdb;
		$id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status != 'trash' LIMIT 1",
				$post_type,
				$title
			)
		);
		if ( $id === 0 ) {
			return 0;
		}
		if ( empty( $terms ) ) {
			return $id;
		}
		foreach ( $terms as $taxonomy => $slugs ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $slugs ) || empty( $slugs ) ) {
				continue;
			}
			$object_terms = wp_get_object_terms( $id, $taxonomy );
			$object_slugs = is_array( $object_terms ) && ! is_wp_error( $object_terms )
				? array_map( function ( $t ) {
					return $t->slug;
				}, $object_terms )
				: array();
			$want_slugs = array_map( 'sanitize_title', $slugs );
			sort( $object_slugs );
			sort( $want_slugs );
			if ( $object_slugs !== $want_slugs ) {
				return 0;
			}
		}
		return $id;
	}

	/**
	 * Set taxonomy terms on a post. Resolves slugs to term IDs; creates category terms if missing.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $terms   Taxonomy slug => array of term slugs.
	 * @return void
	 */
	private static function set_terms( int $post_id, array $terms ): void {
		foreach ( $terms as $taxonomy => $slugs ) {
			if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $slugs ) || empty( $slugs ) ) {
				continue;
			}
			$resolved = array();
			foreach ( $slugs as $slug ) {
				$slug = sanitize_title( $slug );
				if ( $slug === '' ) {
					continue;
				}
				$term = get_term_by( 'slug', $slug, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					$resolved[] = (int) $term->term_id;
				} elseif ( $taxonomy === 'category' ) {
					$name   = ucfirst( str_replace( array( '-', '_' ), ' ', $slug ) );
					$insert = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
					if ( ! is_wp_error( $insert ) && isset( $insert['term_id'] ) ) {
						$resolved[] = (int) $insert['term_id'];
					}
				}
			}
			if ( ! empty( $resolved ) ) {
				wp_set_object_terms( $post_id, $resolved, $taxonomy );
			}
		}
	}
}
