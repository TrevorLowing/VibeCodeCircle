<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

final class RollbackService {
	private static function delete_owned_post( int $post_id, string $project_slug ): bool {
		if ( $post_id <= 0 ) {
			return false;
		}
		$existing_owner = (string) get_post_meta( $post_id, Importer::META_PROJECT_SLUG, true );
		if ( $existing_owner !== $project_slug ) {
			return false;
		}
		return (bool) wp_delete_post( $post_id, true );
	}

	private static function restore_post_snapshot( int $post_id, array $before, array $before_meta ): bool {
		$postarr = array(
			'ID' => $post_id,
			'post_content' => (string) ( $before['post_content'] ?? '' ),
			'post_title' => (string) ( $before['post_title'] ?? '' ),
			'post_status' => (string) ( $before['post_status'] ?? 'publish' ),
		);

		$res = wp_update_post( $postarr, true );
		if ( is_wp_error( $res ) ) {
			return false;
		}

		self::restore_post_meta_snapshot( $post_id, $before_meta );
		return true;
	}

	private static function restore_post_meta_snapshot( int $post_id, array $meta_snapshot ): void {
		$keys = array(
			Importer::META_PROJECT_SLUG,
			Importer::META_SOURCE_PATH,
			Importer::META_FINGERPRINT,
			Importer::META_ASSET_CSS,
			Importer::META_ASSET_JS,
		);

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $meta_snapshot ) ) {
				$val = $meta_snapshot[ $key ];
				if ( $val === null ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $val );
				}
			} else {
				delete_post_meta( $post_id, $key );
			}
		}
	}

	public static function rollback_deploy( string $project_slug, string $fingerprint ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		if ( $project_slug === '' || $fingerprint === '' ) {
			return array( 'ok' => false, 'error' => 'Missing project or fingerprint.' );
		}

		$manifest = ManifestService::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			return array( 'ok' => false, 'error' => 'Manifest not found.' );
		}

		$deleted = 0;
		$restored = 0;
		$errors = 0;

		$created_pages = isset( $manifest['created_pages'] ) && is_array( $manifest['created_pages'] ) ? $manifest['created_pages'] : array();
		foreach ( $created_pages as $it ) {
			$post_id = (int) ( is_array( $it ) ? ( $it['post_id'] ?? 0 ) : $it );
			if ( $post_id <= 0 ) {
				continue;
			}
			if ( self::delete_owned_post( $post_id, $project_slug ) ) {
				$deleted++;
			} else {
				$errors++;
			}
		}

		$created_template_parts = isset( $manifest['created_template_parts'] ) && is_array( $manifest['created_template_parts'] ) ? $manifest['created_template_parts'] : array();
		foreach ( $created_template_parts as $it ) {
			$post_id = (int) ( is_array( $it ) ? ( $it['post_id'] ?? 0 ) : $it );
			if ( $post_id <= 0 ) {
				continue;
			}
			if ( self::delete_owned_post( $post_id, $project_slug ) ) {
				$deleted++;
			} else {
				$errors++;
			}
		}

		$created_templates = isset( $manifest['created_templates'] ) && is_array( $manifest['created_templates'] ) ? $manifest['created_templates'] : array();
		foreach ( $created_templates as $it ) {
			$post_id = (int) ( is_array( $it ) ? ( $it['post_id'] ?? 0 ) : $it );
			if ( $post_id <= 0 ) {
				continue;
			}
			if ( self::delete_owned_post( $post_id, $project_slug ) ) {
				$deleted++;
			} else {
				$errors++;
			}
		}

		$updated_pages = isset( $manifest['updated_pages'] ) && is_array( $manifest['updated_pages'] ) ? $manifest['updated_pages'] : array();
		foreach ( $updated_pages as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}

			$post_id = (int) ( $it['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			$before = isset( $it['before'] ) && is_array( $it['before'] ) ? $it['before'] : array();
			$before_meta = isset( $it['before_meta'] ) && is_array( $it['before_meta'] ) ? $it['before_meta'] : array();

			if ( self::restore_post_snapshot( $post_id, $before, $before_meta ) ) {
				$restored++;
			} else {
				$errors++;
			}
		}

		$updated_template_parts = isset( $manifest['updated_template_parts'] ) && is_array( $manifest['updated_template_parts'] ) ? $manifest['updated_template_parts'] : array();
		foreach ( $updated_template_parts as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$post_id = (int) ( $it['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}
			$before = isset( $it['before'] ) && is_array( $it['before'] ) ? $it['before'] : array();
			$before_meta = isset( $it['before_meta'] ) && is_array( $it['before_meta'] ) ? $it['before_meta'] : array();
			if ( self::restore_post_snapshot( $post_id, $before, $before_meta ) ) {
				$restored++;
			} else {
				$errors++;
			}
		}

		$updated_templates = isset( $manifest['updated_templates'] ) && is_array( $manifest['updated_templates'] ) ? $manifest['updated_templates'] : array();
		foreach ( $updated_templates as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}
			$post_id = (int) ( $it['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}
			$before = isset( $it['before'] ) && is_array( $it['before'] ) ? $it['before'] : array();
			$before_meta = isset( $it['before_meta'] ) && is_array( $it['before_meta'] ) ? $it['before_meta'] : array();
			if ( self::restore_post_snapshot( $post_id, $before, $before_meta ) ) {
				$restored++;
			} else {
				$errors++;
			}
		}

		$front_before = isset( $manifest['front_before'] ) && is_array( $manifest['front_before'] ) ? $manifest['front_before'] : array();
		if ( array_key_exists( 'show_on_front', $front_before ) ) {
			update_option( 'show_on_front', (string) $front_before['show_on_front'] );
		}
		if ( array_key_exists( 'page_on_front', $front_before ) ) {
			update_option( 'page_on_front', (int) $front_before['page_on_front'] );
		}

		$active_before = isset( $manifest['active_before'] ) ? (string) $manifest['active_before'] : '';
		if ( $active_before !== '' ) {
			BuildService::set_active_fingerprint( $project_slug, $active_before );
		} else {
			BuildService::clear_active_fingerprint( $project_slug );
		}

		return array(
			'ok' => $errors === 0,
			'deleted' => $deleted,
			'restored' => $restored,
			'errors' => $errors,
		);
	}
}
