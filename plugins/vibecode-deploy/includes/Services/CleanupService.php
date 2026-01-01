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

	public static function delete_pages_for_project( string $project_slug ): int {
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

			$res = wp_delete_post( $post_id, true );
			if ( $res ) {
				$count++;
			}
		}

		BuildService::clear_active_fingerprint( $project_slug );
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
}
