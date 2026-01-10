<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

final class CleanupService {
	private static function delete_dir_recursive( string $dir ): bool {
		$dir = rtrim( $dir, '/\\' );
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return true;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$path = (string) $item->getPathname();
			if ( $item->isDir() ) {
				if ( ! @rmdir( $path ) ) {
					return false;
				}
			} else {
				if ( ! @unlink( $path ) ) {
					return false;
				}
			}
		}

		return @rmdir( $dir );
	}

	public static function purge_uploads_root(): bool {
		$uploads = wp_upload_dir();
		$base = rtrim( (string) $uploads['basedir'], '/\\' );
		$vibecode_deploy_dir = $base . DIRECTORY_SEPARATOR . 'vibecode-deploy';

		if ( ! is_dir( $vibecode_deploy_dir ) ) {
			return true;
		}

		$vibecode_deploy_real = realpath( $vibecode_deploy_dir );
		$base_real = realpath( $base );
		if ( ! is_string( $vibecode_deploy_real ) || ! is_string( $base_real ) || strpos( $vibecode_deploy_real, $base_real ) !== 0 ) {
			return false;
		}

		return self::delete_dir_recursive( $vibecode_deploy_real );
	}

	public static function detach_pages_for_project( string $project_slug ): int {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return 0;
		}

		$q = new \WP_Query(
			array(
				'post_type' => 'page',
				'post_status' => 'any',
				'fields' => 'ids',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query' => array(
					array(
						'key' => Importer::META_PROJECT_SLUG,
						'value' => $project_slug,
						'compare' => '=',
					),
				),
			)
		);

		$count = 0;
		foreach ( $q->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			delete_post_meta( $post_id, Importer::META_PROJECT_SLUG );
			delete_post_meta( $post_id, Importer::META_SOURCE_PATH );
			delete_post_meta( $post_id, Importer::META_FINGERPRINT );
			delete_post_meta( $post_id, Importer::META_ASSET_CSS );
			delete_post_meta( $post_id, Importer::META_ASSET_JS );
			$count++;
		}

		BuildService::clear_active_fingerprint( $project_slug );
		return $count;
	}

	public static function delete_pages_for_project( string $project_slug, array $page_slugs = array() ): int {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return 0;
		}

		$query_args = array(
			'post_type' => 'page',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query' => array(
				array(
					'key' => Importer::META_PROJECT_SLUG,
					'value' => $project_slug,
					'compare' => '=',
				),
			),
		);

		$q = new \WP_Query( $query_args );

		// If specific page slugs provided, filter results by post_name
		if ( ! empty( $page_slugs ) ) {
			$page_slugs = array_map( 'sanitize_key', $page_slugs );
			$page_slugs = array_filter( $page_slugs );
			if ( ! empty( $page_slugs ) ) {
				$filtered_posts = array();
				foreach ( $q->posts as $post_id ) {
					$post = get_post( $post_id );
					if ( $post && isset( $post->post_name ) && in_array( (string) $post->post_name, $page_slugs, true ) ) {
						$filtered_posts[] = $post_id;
					}
				}
				$q->posts = $filtered_posts;
			}
		}

		$count = 0;
		foreach ( $q->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			$res = wp_delete_post( $post_id, true );
			if ( $res ) {
				$count++;
			}
		}

		if ( empty( $page_slugs ) ) {
			BuildService::clear_active_fingerprint( $project_slug );
		}
		return $count;
	}

	public static function delete_all_active_build_options(): int {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! ( $wpdb instanceof \wpdb ) ) {
			return 0;
		}

		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'vibecode_deploy_active_build_%'" );
		if ( ! is_array( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $name ) {
			if ( ! is_string( $name ) || $name === '' ) {
				continue;
			}
			if ( delete_option( $name ) ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Get list of all project-owned pages with slugs.
	 *
	 * @param string $project_slug Project slug.
	 * @return array Array of arrays with 'id', 'slug', 'title' keys.
	 */
	public static function get_project_pages( string $project_slug ): array {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return array();
		}

		$q = new \WP_Query(
			array(
				'post_type' => 'page',
				'post_status' => 'any',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'meta_query' => array(
					array(
						'key' => Importer::META_PROJECT_SLUG,
						'value' => $project_slug,
						'compare' => '=',
					),
				),
			)
		);

		$pages = array();
		foreach ( $q->posts as $post ) {
			if ( ! isset( $post->ID ) || ! isset( $post->post_name ) ) {
				continue;
			}
			$pages[] = array(
				'id' => (int) $post->ID,
				'slug' => (string) $post->post_name,
				'title' => (string) ( $post->post_title ?? '' ),
			);
		}

		return $pages;
	}

	/**
	 * Get list of all project-owned templates.
	 *
	 * @param string $project_slug Project slug.
	 * @return array Array of arrays with 'id', 'slug', 'title' keys.
	 */
	public static function get_project_templates( string $project_slug ): array {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' || ! post_type_exists( 'wp_template' ) ) {
			return array();
		}

		$q = new \WP_Query(
			array(
				'post_type' => 'wp_template',
				'post_status' => 'any',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'meta_query' => array(
					array(
						'key' => Importer::META_PROJECT_SLUG,
						'value' => $project_slug,
						'compare' => '=',
					),
				),
			)
		);

		$templates = array();
		foreach ( $q->posts as $post ) {
			if ( ! isset( $post->ID ) || ! isset( $post->post_name ) ) {
				continue;
			}
			$templates[] = array(
				'id' => (int) $post->ID,
				'slug' => (string) $post->post_name,
				'title' => (string) ( $post->post_title ?? '' ),
			);
		}

		return $templates;
	}

	/**
	 * Get list of all project-owned template parts.
	 *
	 * @param string $project_slug Project slug.
	 * @return array Array of arrays with 'id', 'slug', 'title' keys.
	 */
	public static function get_project_template_parts( string $project_slug ): array {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' || ! post_type_exists( 'wp_template_part' ) ) {
			return array();
		}

		$q = new \WP_Query(
			array(
				'post_type' => 'wp_template_part',
				'post_status' => 'any',
				'posts_per_page' => -1,
				'no_found_rows' => true,
				'meta_query' => array(
					array(
						'key' => Importer::META_PROJECT_SLUG,
						'value' => $project_slug,
						'compare' => '=',
					),
				),
			)
		);

		$parts = array();
		foreach ( $q->posts as $post ) {
			if ( ! isset( $post->ID ) || ! isset( $post->post_name ) ) {
				continue;
			}
			$parts[] = array(
				'id' => (int) $post->ID,
				'slug' => (string) $post->post_name,
				'title' => (string) ( $post->post_title ?? '' ),
			);
		}

		return $parts;
	}

	/**
	 * Delete specific pages by slug array.
	 *
	 * @param string $project_slug Project slug.
	 * @param array  $page_slugs Array of page slugs to delete.
	 * @return int Number of pages deleted.
	 */
	public static function delete_pages_by_slugs( string $project_slug, array $page_slugs ): int {
		return self::delete_pages_for_project( $project_slug, $page_slugs );
	}

	/**
	 * Delete templates by slug array.
	 *
	 * @param string $project_slug Project slug.
	 * @param array  $template_slugs Array of template slugs to delete. If empty, deletes all project templates.
	 * @return int Number of templates deleted.
	 */
	public static function delete_templates_by_slugs( string $project_slug, array $template_slugs = array() ): int {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' || ! post_type_exists( 'wp_template' ) ) {
			return 0;
		}

		$query_args = array(
			'post_type' => 'wp_template',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query' => array(
				array(
					'key' => Importer::META_PROJECT_SLUG,
					'value' => $project_slug,
					'compare' => '=',
				),
			),
		);

		$q = new \WP_Query( $query_args );

		// If specific template slugs provided, filter results by post_name
		if ( ! empty( $template_slugs ) ) {
			$template_slugs = array_map( 'sanitize_key', $template_slugs );
			$template_slugs = array_filter( $template_slugs );
			if ( ! empty( $template_slugs ) ) {
				$filtered_posts = array();
				foreach ( $q->posts as $post_id ) {
					$post = get_post( $post_id );
					if ( $post && isset( $post->post_name ) && in_array( (string) $post->post_name, $template_slugs, true ) ) {
						$filtered_posts[] = $post_id;
					}
				}
				$q->posts = $filtered_posts;
			}
		}

		$count = 0;
		foreach ( $q->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			$res = wp_delete_post( $post_id, true );
			if ( $res ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Delete template parts by slug array.
	 *
	 * @param string $project_slug Project slug.
	 * @param array  $template_part_slugs Array of template part slugs to delete. If empty, deletes all project template parts.
	 * @return int Number of template parts deleted.
	 */
	public static function delete_template_parts_by_slugs( string $project_slug, array $template_part_slugs = array() ): int {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' || ! post_type_exists( 'wp_template_part' ) ) {
			return 0;
		}

		$query_args = array(
			'post_type' => 'wp_template_part',
			'post_status' => 'any',
			'fields' => 'ids',
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query' => array(
				array(
					'key' => Importer::META_PROJECT_SLUG,
					'value' => $project_slug,
					'compare' => '=',
				),
			),
		);

		$q = new \WP_Query( $query_args );

		// If specific template part slugs provided, filter results by post_name
		if ( ! empty( $template_part_slugs ) ) {
			$template_part_slugs = array_map( 'sanitize_key', $template_part_slugs );
			$template_part_slugs = array_filter( $template_part_slugs );
			if ( ! empty( $template_part_slugs ) ) {
				$filtered_posts = array();
				foreach ( $q->posts as $post_id ) {
					$post = get_post( $post_id );
					if ( $post && isset( $post->post_name ) && in_array( (string) $post->post_name, $template_part_slugs, true ) ) {
						$filtered_posts[] = $post_id;
					}
				}
				$q->posts = $filtered_posts;
			}
		}

		$count = 0;
		foreach ( $q->posts as $post_id ) {
			$post_id = (int) $post_id;
			if ( $post_id <= 0 ) {
				continue;
			}

			$res = wp_delete_post( $post_id, true );
			if ( $res ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Delete theme files (functions.php, ACF JSON).
	 *
	 * @param string $project_slug Project slug.
	 * @param array  $theme_files Array of theme file names to delete (e.g., 'functions.php', 'acf-json/*.json').
	 * @return array Results with 'deleted' count and 'errors' array.
	 */
	public static function delete_theme_files( string $project_slug, array $theme_files ): array {
		$project_slug = sanitize_key( $project_slug );
		$results = array(
			'deleted' => 0,
			'errors' => array(),
		);

		if ( $project_slug === '' || empty( $theme_files ) ) {
			return $results;
		}

		// Get active theme
		$theme_slug = sanitize_key( (string) get_stylesheet() );
		if ( $theme_slug === '' ) {
			$results['errors'][] = 'No active theme found.';
			return $results;
		}

		$theme_dir = WP_CONTENT_DIR . '/themes/' . $theme_slug;
		if ( ! is_dir( $theme_dir ) ) {
			$results['errors'][] = "Theme directory not found: {$theme_dir}";
			return $results;
		}

		foreach ( $theme_files as $file ) {
			$file = sanitize_file_name( (string) $file );
			if ( $file === '' ) {
				continue;
			}

			$file_path = '';
			if ( $file === 'functions.php' ) {
				$file_path = $theme_dir . '/functions.php';
			} elseif ( strpos( $file, 'acf-json/' ) === 0 ) {
				// Handle ACF JSON files
				$acf_dir = $theme_dir . '/acf-json';
				if ( $file === 'acf-json/*.json' ) {
					// Delete all JSON files in acf-json directory
					if ( is_dir( $acf_dir ) ) {
						$json_files = glob( $acf_dir . '/*.json' ) ?: array();
						foreach ( $json_files as $json_file ) {
							if ( is_file( $json_file ) && @unlink( $json_file ) ) {
								$results['deleted']++;
							} else {
								$results['errors'][] = "Failed to delete: {$json_file}";
							}
						}
					}
					continue;
				} else {
					$file_path = $theme_dir . '/' . $file;
				}
			} else {
				$file_path = $theme_dir . '/' . $file;
			}

			if ( $file_path !== '' && is_file( $file_path ) ) {
				if ( @unlink( $file_path ) ) {
					$results['deleted']++;
				} else {
					$results['errors'][] = "Failed to delete: {$file_path}";
				}
			}
		}

		return $results;
	}

	/**
	 * Delete CSS/JS assets from uploads directory AND plugin assets directory.
	 *
	 * Note: This does NOT delete staging directories - those are managed separately.
	 * Staging directories are only deleted by purge_uploads_root() or purge_uploads().
	 *
	 * @param string $project_slug Project slug.
	 * @param bool   $include_staging If true, also delete staging directories (default: false).
	 * @return array Results with 'deleted' count and 'errors' array.
	 */
	public static function delete_assets( string $project_slug, bool $include_staging = false ): array {
		$project_slug = sanitize_key( $project_slug );
		$results = array(
			'deleted' => 0,
			'errors' => array(),
		);

		if ( $project_slug === '' ) {
			return $results;
		}

		// Only delete staging directories if explicitly requested (e.g., from purge_uploads)
		if ( $include_staging ) {
			// Delete staging directories from uploads
			$uploads = wp_upload_dir();
			$base = rtrim( (string) $uploads['basedir'], '/\\' );
			$vibecode_deploy_dir = $base . DIRECTORY_SEPARATOR . 'vibecode-deploy' . DIRECTORY_SEPARATOR . 'staging' . DIRECTORY_SEPARATOR . $project_slug;

			if ( is_dir( $vibecode_deploy_dir ) ) {
				// Delete all staging directories for this project
				if ( self::delete_dir_recursive( $vibecode_deploy_dir ) ) {
					$results['deleted']++; // Count as one operation
				} else {
					$results['errors'][] = "Failed to delete assets directory: {$vibecode_deploy_dir}";
				}
			}
		}

		// Also delete plugin assets directory
		$plugin_dir = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) : '';
		if ( $plugin_dir !== '' ) {
			$plugin_assets_dir = $plugin_dir . '/assets';
			if ( is_dir( $plugin_assets_dir ) ) {
				if ( self::delete_dir_recursive( $plugin_assets_dir ) ) {
					$results['deleted']++; // Count plugin assets deletion
				} else {
					$results['errors'][] = "Failed to delete plugin assets directory: {$plugin_assets_dir}";
				}
			}
		}

		return $results;
	}

	/**
	 * Flush all WordPress caches and clear stale data.
	 * 
	 * This ensures a completely clean slate before re-importing.
	 * 
	 * @param string $project_slug Project slug (optional, for project-specific cleanup).
	 * @return array Results with counts and errors.
	 */
	public static function flush_all_caches( string $project_slug = '' ): array {
		$results = array(
			'cache_flushed' => false,
			'transients_cleared' => 0,
			'opcache_cleared' => false,
			'rewrite_rules_flushed' => false,
			'active_fingerprint_cleared' => false,
			'errors' => array(),
		);
		
		// 1. Flush WordPress object cache
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			$results['cache_flushed'] = true;
		}
		
		// 2. Clear all plugin-specific transients
		global $wpdb;
		if ( isset( $wpdb ) && $wpdb instanceof \wpdb ) {
			$transient_query = $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_vibecode_deploy_%',
				'_transient_timeout_vibecode_deploy_%'
			);
			$transients_deleted = $wpdb->query( $transient_query );
			$results['transients_cleared'] = (int) $transients_deleted;
		}
		
		// 3. Clear OPcache if available
		if ( function_exists( 'opcache_reset' ) ) {
			opcache_reset();
			$results['opcache_cleared'] = true;
		}
		
		// 4. Flush rewrite rules
		flush_rewrite_rules( false );
		$results['rewrite_rules_flushed'] = true;
		
		// 5. Clear active fingerprint if project slug provided
		if ( $project_slug !== '' ) {
			$cleared = \VibeCode\Deploy\Services\BuildService::clear_active_fingerprint( $project_slug );
			$results['active_fingerprint_cleared'] = $cleared;
		}
		
		return $results;
	}

	/**
	 * Main entry point for granular nuclear operations.
	 * 
	 * Nuclear operation = clean slate. Deletes all project-related content.
	 * This provides a true clean slate - everything is deleted, nothing is restored.
	 * 
	 * For rollback functionality, use RollbackService::rollback_selection() separately.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $scope Scope type: 'everything', 'by_type', 'by_page'.
	 * @param array  $selected_types Array of selected types (when scope is 'by_type').
	 * @param array  $selected_pages Array of selected page slugs (when scope is 'by_page').
	 * @param string $action Action type: 'delete' (ignored - nuclear always deletes for clean slate).
	 * @return array Results with counts and errors.
	 */
	public static function nuclear_operation( string $project_slug, string $scope, array $selected_types, array $selected_pages, string $action ): array {
		$project_slug = sanitize_key( $project_slug );
		$scope = sanitize_key( $scope );
		$action = sanitize_key( $action );

		$results = array(
			'deleted_pages' => 0,
			'deleted_templates' => 0,
			'deleted_template_parts' => 0,
			'deleted_theme_files' => 0,
			'deleted_assets' => 0,
			'errors' => array(),
		);

		if ( $project_slug === '' ) {
			$results['errors'][] = 'Project slug is required.';
			return $results;
		}

		if ( $scope === 'everything' ) {
			// Delete everything (content only - NOT staging directories)
			// Staging directories are managed separately via "Purge uploads" action
			$results['deleted_pages'] = self::delete_pages_for_project( $project_slug );
			$results['deleted_templates'] = self::delete_templates_by_slugs( $project_slug ); // Gets all
			$results['deleted_template_parts'] = self::delete_template_parts_by_slugs( $project_slug ); // Gets all
			$theme_result = self::delete_theme_files( $project_slug, array( 'functions.php', 'acf-json/*.json' ) );
			$results['deleted_theme_files'] = $theme_result['deleted'];
			$results['errors'] = array_merge( $results['errors'], $theme_result['errors'] );
			// Note: delete_assets() with include_staging=false only deletes plugin assets, not staging directories
			$assets_result = self::delete_assets( $project_slug, false );
			$results['deleted_assets'] = $assets_result['deleted'];
			$results['errors'] = array_merge( $results['errors'], $assets_result['errors'] );
		} elseif ( $scope === 'by_type' ) {
			// Delete by selected types
			$selected_types = array_map( 'sanitize_key', $selected_types );
			if ( in_array( 'pages', $selected_types, true ) ) {
				$results['deleted_pages'] = self::delete_pages_for_project( $project_slug );
			}
			if ( in_array( 'templates', $selected_types, true ) ) {
				$templates = self::get_project_templates( $project_slug );
				$template_slugs = array_column( $templates, 'slug' );
				$results['deleted_templates'] = self::delete_templates_by_slugs( $project_slug, $template_slugs );
			}
			if ( in_array( 'template_parts', $selected_types, true ) ) {
				$parts = self::get_project_template_parts( $project_slug );
				$part_slugs = array_column( $parts, 'slug' );
				$results['deleted_template_parts'] = self::delete_template_parts_by_slugs( $project_slug, $part_slugs );
			}
			if ( in_array( 'theme_files', $selected_types, true ) ) {
				$theme_result = self::delete_theme_files( $project_slug, array( 'functions.php' ) );
				$results['deleted_theme_files'] = $theme_result['deleted'];
				$results['errors'] = array_merge( $results['errors'], $theme_result['errors'] );
			}
			if ( in_array( 'acf_json', $selected_types, true ) ) {
				$acf_result = self::delete_theme_files( $project_slug, array( 'acf-json/*.json' ) );
				$results['deleted_theme_files'] += $acf_result['deleted'];
				$results['errors'] = array_merge( $results['errors'], $acf_result['errors'] );
			}
			if ( in_array( 'assets', $selected_types, true ) ) {
				// When explicitly selected, allow deleting staging directories
				$assets_result = self::delete_assets( $project_slug, true );
				$results['deleted_assets'] = $assets_result['deleted'];
				$results['errors'] = array_merge( $results['errors'], $assets_result['errors'] );
			}
		} elseif ( $scope === 'by_page' ) {
			// Delete by selected pages
			if ( ! empty( $selected_pages ) ) {
				$results['deleted_pages'] = self::delete_pages_by_slugs( $project_slug, $selected_pages );
			}
		}

		// After all deletions, flush all caches
		$flush_results = self::flush_all_caches( $project_slug );
		$results['cache_flushed'] = $flush_results['cache_flushed'];
		$results['transients_cleared'] = $flush_results['transients_cleared'];
		$results['opcache_cleared'] = $flush_results['opcache_cleared'];
		$results['rewrite_rules_flushed'] = $flush_results['rewrite_rules_flushed'];
		$results['active_fingerprint_cleared'] = $flush_results['active_fingerprint_cleared'];
		$results['errors'] = array_merge( $results['errors'], $flush_results['errors'] );

		return $results;
	}
}
