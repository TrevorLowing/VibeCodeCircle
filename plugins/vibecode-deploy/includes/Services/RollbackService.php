<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Services\MediaLibraryService;

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

	private static function restore_post_snapshot( int $post_id, array $before, array $before_meta ): array {
		// Check if post exists before attempting restore
		$existing_post = get_post( $post_id );
		if ( ! $existing_post ) {
			return array(
				'success' => false,
				'error' => sprintf( 'Post ID %d does not exist (may have been deleted).', $post_id ),
				'error_code' => 'post_not_found',
				'skippable' => true, // This is a warning, not a critical error
			);
		}

		$postarr = array(
			'ID' => $post_id,
			'post_content' => (string) ( $before['post_content'] ?? '' ),
			'post_title' => (string) ( $before['post_title'] ?? '' ),
			'post_status' => (string) ( $before['post_status'] ?? 'publish' ),
		);

		$res = wp_update_post( $postarr, true );
		if ( is_wp_error( $res ) ) {
			$error_code = $res->get_error_code();
			$error_message = $res->get_error_message();
			
			// Check if error is "Invalid post ID" (post was deleted)
			$is_skippable = ( $error_code === 'invalid_post' || strpos( strtolower( $error_message ), 'invalid post' ) !== false );
			
			return array(
				'success' => false,
				'error' => $error_message,
				'error_code' => $error_code,
				'skippable' => $is_skippable,
			);
		}

		self::restore_post_meta_snapshot( $post_id, $before_meta );
		return array( 'success' => true );
	}

	private static function restore_post_meta_snapshot( int $post_id, array $meta_snapshot ): void {
		$keys = array(
			Importer::META_PROJECT_SLUG,
			Importer::META_SOURCE_PATH,
			Importer::META_FINGERPRINT,
			Importer::META_ASSET_CSS,
			Importer::META_ASSET_JS,
			Importer::META_ASSET_CDN_SCRIPTS,
			Importer::META_ASSET_CDN_CSS,
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
		$error_messages = array();
		$deleted_attachments = 0;

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
				$post = get_post( $post_id );
				$error_messages[] = "Failed to delete created template part ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": Post not owned by project or already deleted.";
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
				$post = get_post( $post_id );
				$error_messages[] = "Failed to delete created template ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": Post not owned by project or already deleted.";
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

			$restore_result = self::restore_post_snapshot( $post_id, $before, $before_meta );
			if ( $restore_result['success'] ?? false ) {
				$restored++;
			} else {
				$error_msg = $restore_result['error'] ?? 'Unknown error';
				$is_skippable = $restore_result['skippable'] ?? false;
				$post = get_post( $post_id );
				
				// Only count as error if not skippable (post exists but restore failed)
				// Skippable errors (post doesn't exist) are warnings, not errors
				if ( ! $is_skippable ) {
					$errors++;
				}
				
				$error_messages[] = "Failed to restore page ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": {$error_msg}" . ( $is_skippable ? ' (skipped - post no longer exists)' : '' );
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
			$restore_result = self::restore_post_snapshot( $post_id, $before, $before_meta );
			if ( $restore_result['success'] ?? false ) {
				$restored++;
			} else {
				$error_msg = $restore_result['error'] ?? 'Unknown error';
				$is_skippable = $restore_result['skippable'] ?? false;
				$post = get_post( $post_id );
				
				// Only count as error if not skippable (post exists but restore failed)
				// Skippable errors (post doesn't exist) are warnings, not errors
				if ( ! $is_skippable ) {
					$errors++;
				}
				
				$error_messages[] = "Failed to restore page ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": {$error_msg}" . ( $is_skippable ? ' (skipped - post no longer exists)' : '' );
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
			$restore_result = self::restore_post_snapshot( $post_id, $before, $before_meta );
			if ( $restore_result['success'] ?? false ) {
				$restored++;
			} else {
				$error_msg = $restore_result['error'] ?? 'Unknown error';
				$is_skippable = $restore_result['skippable'] ?? false;
				$post = get_post( $post_id );
				
				// Only count as error if not skippable (post exists but restore failed)
				// Skippable errors (post doesn't exist) are warnings, not errors
				if ( ! $is_skippable ) {
					$errors++;
				}
				
				$error_messages[] = "Failed to restore page ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": {$error_msg}" . ( $is_skippable ? ' (skipped - post no longer exists)' : '' );
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

		// Delete orphaned Media Library attachments created during this deployment
		$created_attachments = isset( $manifest['created_attachments'] ) && is_array( $manifest['created_attachments'] ) ? $manifest['created_attachments'] : array();
		foreach ( $created_attachments as $att ) {
			$attachment_id = isset( $att['attachment_id'] ) ? (int) $att['attachment_id'] : 0;
			if ( $attachment_id > 0 ) {
				// Only delete if truly orphaned (not referenced in any post content)
				if ( MediaLibraryService::is_attachment_orphaned( $attachment_id ) ) {
					$result = wp_delete_attachment( $attachment_id, true ); // true = force delete, removes file
					if ( $result ) {
						$deleted_attachments++;
						Logger::info( 'Deleted orphaned Media Library attachment during rollback.', array(
							'attachment_id' => $attachment_id,
							'project_slug' => $project_slug,
							'fingerprint' => $fingerprint,
						) );
					}
				} else {
					// Attachment is still referenced, preserve it
					Logger::info( 'Preserved Media Library attachment during rollback (still referenced).', array(
						'attachment_id' => $attachment_id,
						'project_slug' => $project_slug,
						'fingerprint' => $fingerprint,
					) );
				}
			}
		}

		// Separate warnings (skippable) from errors (non-skippable)
		$warnings = array();
		$actual_errors = array();
		foreach ( $error_messages as $msg ) {
			if ( strpos( $msg, '(skipped - post no longer exists)' ) !== false ) {
				$warnings[] = $msg;
			} else {
				$actual_errors[] = $msg;
			}
		}

		return array(
			'ok' => $errors === 0,
			'deleted' => $deleted,
			'restored' => $restored,
			'deleted_attachments' => $deleted_attachments,
			'errors' => $errors,
			'error_messages' => $error_messages, // All messages for logging
			'warnings' => $warnings, // Skippable messages
			'actual_errors' => $actual_errors, // Non-skippable errors only
		);
	}

	/**
	 * Rollback specific pages by slugs.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint to rollback from.
	 * @param array  $page_slugs Array of page slugs to rollback.
	 * @return array Results with deleted/restored counts.
	 */
	public static function rollback_pages_by_slugs( string $project_slug, string $fingerprint, array $page_slugs ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$page_slugs = array_map( 'sanitize_key', $page_slugs );
		$page_slugs = array_filter( $page_slugs );

		if ( $project_slug === '' || $fingerprint === '' || empty( $page_slugs ) ) {
			return array( 'ok' => false, 'error' => 'Missing required parameters.', 'deleted' => 0, 'restored' => 0, 'errors' => 0 );
		}

		$manifest = ManifestService::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			return array( 'ok' => false, 'error' => 'Manifest not found.', 'deleted' => 0, 'restored' => 0, 'errors' => 0 );
		}

		$deleted = 0;
		$restored = 0;
		$errors = 0;

		// Get page IDs from slugs (query all project pages, then filter by slug)
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

		// Process created pages
		$created_pages = isset( $manifest['created_pages'] ) && is_array( $manifest['created_pages'] ) ? $manifest['created_pages'] : array();
		foreach ( $created_pages as $it ) {
			$post_id = (int) ( is_array( $it ) ? ( $it['post_id'] ?? 0 ) : $it );
			if ( $post_id <= 0 ) {
				continue;
			}

			// Check if this page's slug is in our selection
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->post_name ) || ! in_array( (string) $post->post_name, $page_slugs, true ) ) {
				continue;
			}

			if ( self::delete_owned_post( $post_id, $project_slug ) ) {
				$deleted++;
			} else {
				$errors++;
			}
		}

		// Process updated pages
		$updated_pages = isset( $manifest['updated_pages'] ) && is_array( $manifest['updated_pages'] ) ? $manifest['updated_pages'] : array();
		foreach ( $updated_pages as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}

			$post_id = (int) ( $it['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			// Check if this page's slug is in our selection
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->post_name ) || ! in_array( (string) $post->post_name, $page_slugs, true ) ) {
				continue;
			}

			$before = isset( $it['before'] ) && is_array( $it['before'] ) ? $it['before'] : array();
			$before_meta = isset( $it['before_meta'] ) && is_array( $it['before_meta'] ) ? $it['before_meta'] : array();

			$restore_result = self::restore_post_snapshot( $post_id, $before, $before_meta );
			if ( $restore_result['success'] ?? false ) {
				$restored++;
			} else {
				$error_msg = $restore_result['error'] ?? 'Unknown error';
				$is_skippable = $restore_result['skippable'] ?? false;
				$post = get_post( $post_id );
				
				// Only count as error if not skippable (post exists but restore failed)
				// Skippable errors (post doesn't exist) are warnings, not errors
				if ( ! $is_skippable ) {
					$errors++;
				}
				
				$error_messages[] = "Failed to restore page ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": {$error_msg}" . ( $is_skippable ? ' (skipped - post no longer exists)' : '' );
			}
		}

		return array(
			'ok' => $errors === 0,
			'deleted' => $deleted,
			'restored' => $restored,
			'errors' => $errors,
		);
	}

	/**
	 * Rollback specific templates by slugs.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint to rollback from.
	 * @param array  $template_slugs Array of template slugs to rollback.
	 * @return array Results with deleted/restored counts.
	 */
	public static function rollback_templates_by_slugs( string $project_slug, string $fingerprint, array $template_slugs ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$template_slugs = array_map( 'sanitize_key', $template_slugs );
		$template_slugs = array_filter( $template_slugs );

		if ( $project_slug === '' || $fingerprint === '' || empty( $template_slugs ) || ! post_type_exists( 'wp_template' ) ) {
			return array( 'ok' => false, 'error' => 'Missing required parameters.', 'deleted' => 0, 'restored' => 0, 'errors' => 0 );
		}

		$manifest = ManifestService::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			return array( 'ok' => false, 'error' => 'Manifest not found.', 'deleted' => 0, 'restored' => 0, 'errors' => 0 );
		}

		$deleted = 0;
		$restored = 0;
		$errors = 0;
		$error_messages = array();

		// Process created templates
		$created_templates = isset( $manifest['created_templates'] ) && is_array( $manifest['created_templates'] ) ? $manifest['created_templates'] : array();
		foreach ( $created_templates as $it ) {
			$post_id = (int) ( is_array( $it ) ? ( $it['post_id'] ?? 0 ) : $it );
			if ( $post_id <= 0 ) {
				continue;
			}

			// Check if this template's slug is in our selection
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->post_name ) || ! in_array( (string) $post->post_name, $template_slugs, true ) ) {
				continue;
			}

			if ( self::delete_owned_post( $post_id, $project_slug ) ) {
				$deleted++;
			} else {
				$errors++;
			}
		}

		// Process updated templates
		$updated_templates = isset( $manifest['updated_templates'] ) && is_array( $manifest['updated_templates'] ) ? $manifest['updated_templates'] : array();
		foreach ( $updated_templates as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}

			$post_id = (int) ( $it['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			// Check if this template's slug is in our selection
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->post_name ) || ! in_array( (string) $post->post_name, $template_slugs, true ) ) {
				continue;
			}

			$before = isset( $it['before'] ) && is_array( $it['before'] ) ? $it['before'] : array();
			$before_meta = isset( $it['before_meta'] ) && is_array( $it['before_meta'] ) ? $it['before_meta'] : array();

			$restore_result = self::restore_post_snapshot( $post_id, $before, $before_meta );
			if ( $restore_result['success'] ?? false ) {
				$restored++;
			} else {
				$error_msg = $restore_result['error'] ?? 'Unknown error';
				$is_skippable = $restore_result['skippable'] ?? false;
				$post = get_post( $post_id );
				
				// Only count as error if not skippable (post exists but restore failed)
				// Skippable errors (post doesn't exist) are warnings, not errors
				if ( ! $is_skippable ) {
					$errors++;
				}
				
				$error_messages[] = "Failed to restore page ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": {$error_msg}" . ( $is_skippable ? ' (skipped - post no longer exists)' : '' );
			}
		}

		return array(
			'ok' => $errors === 0,
			'deleted' => $deleted,
			'restored' => $restored,
			'errors' => $errors,
		);
	}

	/**
	 * Rollback specific template parts by slugs.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint to rollback from.
	 * @param array  $template_part_slugs Array of template part slugs to rollback.
	 * @return array Results with deleted/restored counts.
	 */
	public static function rollback_template_parts_by_slugs( string $project_slug, string $fingerprint, array $template_part_slugs ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$template_part_slugs = array_map( 'sanitize_key', $template_part_slugs );
		$template_part_slugs = array_filter( $template_part_slugs );

		if ( $project_slug === '' || $fingerprint === '' || empty( $template_part_slugs ) || ! post_type_exists( 'wp_template_part' ) ) {
			return array( 'ok' => false, 'error' => 'Missing required parameters.', 'deleted' => 0, 'restored' => 0, 'errors' => 0, 'error_messages' => array() );
		}

		$manifest = ManifestService::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			return array( 'ok' => false, 'error' => 'Manifest not found.', 'deleted' => 0, 'restored' => 0, 'errors' => 0, 'error_messages' => array() );
		}

		$deleted = 0;
		$restored = 0;
		$errors = 0;
		$error_messages = array();

		// Process created template parts
		$created_template_parts = isset( $manifest['created_template_parts'] ) && is_array( $manifest['created_template_parts'] ) ? $manifest['created_template_parts'] : array();
		foreach ( $created_template_parts as $it ) {
			$post_id = (int) ( is_array( $it ) ? ( $it['post_id'] ?? 0 ) : $it );
			if ( $post_id <= 0 ) {
				continue;
			}

			// Check if this template part's slug is in our selection
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->post_name ) || ! in_array( (string) $post->post_name, $template_part_slugs, true ) ) {
				continue;
			}

			if ( self::delete_owned_post( $post_id, $project_slug ) ) {
				$deleted++;
			} else {
				$errors++;
			}
		}

		// Process updated template parts
		$updated_template_parts = isset( $manifest['updated_template_parts'] ) && is_array( $manifest['updated_template_parts'] ) ? $manifest['updated_template_parts'] : array();
		foreach ( $updated_template_parts as $it ) {
			if ( ! is_array( $it ) ) {
				continue;
			}

			$post_id = (int) ( $it['post_id'] ?? 0 );
			if ( $post_id <= 0 ) {
				continue;
			}

			// Check if this template part's slug is in our selection
			$post = get_post( $post_id );
			if ( ! $post || ! isset( $post->post_name ) || ! in_array( (string) $post->post_name, $template_part_slugs, true ) ) {
				continue;
			}

			$before = isset( $it['before'] ) && is_array( $it['before'] ) ? $it['before'] : array();
			$before_meta = isset( $it['before_meta'] ) && is_array( $it['before_meta'] ) ? $it['before_meta'] : array();

			$restore_result = self::restore_post_snapshot( $post_id, $before, $before_meta );
			if ( $restore_result['success'] ?? false ) {
				$restored++;
			} else {
				$error_msg = $restore_result['error'] ?? 'Unknown error';
				$is_skippable = $restore_result['skippable'] ?? false;
				$post = get_post( $post_id );
				
				// Only count as error if not skippable (post exists but restore failed)
				// Skippable errors (post doesn't exist) are warnings, not errors
				if ( ! $is_skippable ) {
					$errors++;
				}
				
				$error_messages[] = "Failed to restore page ID {$post_id}" . ( $post ? " ({$post->post_name})" : '' ) . ": {$error_msg}" . ( $is_skippable ? ' (skipped - post no longer exists)' : '' );
			}
		}

		return array(
			'ok' => $errors === 0,
			'deleted' => $deleted,
			'restored' => $restored,
			'errors' => $errors,
		);
	}

	/**
	 * Restore theme files from manifest or staging.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint to rollback from.
	 * @param array  $theme_files Array of theme file names to restore (e.g., 'functions.php', 'acf-json/*.json').
	 * @return array Results with restored count and errors.
	 */
	public static function rollback_theme_files( string $project_slug, string $fingerprint, array $theme_files ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$results = array(
			'restored' => 0,
			'errors' => array(),
		);

		if ( $project_slug === '' || $fingerprint === '' || empty( $theme_files ) ) {
			$results['errors'][] = 'Missing required parameters.';
			return $results;
		}

		$manifest = ManifestService::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			$results['errors'][] = 'Manifest not found.';
			return $results;
		}

		// Get theme file snapshots from manifest
		$theme_snapshots = isset( $manifest['theme_files'] ) && is_array( $manifest['theme_files'] ) ? $manifest['theme_files'] : array();

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
			$snapshot_key = '';

			if ( $file === 'functions.php' ) {
				$file_path = $theme_dir . '/functions.php';
				$snapshot_key = 'functions.php';
			} elseif ( strpos( $file, 'acf-json/' ) === 0 ) {
				// Handle ACF JSON files
				$acf_dir = $theme_dir . '/acf-json';
				if ( $file === 'acf-json/*.json' ) {
					// Restore all JSON files from manifest
					if ( isset( $theme_snapshots['acf-json'] ) && is_array( $theme_snapshots['acf-json'] ) ) {
						foreach ( $theme_snapshots['acf-json'] as $json_file => $content ) {
							$json_path = $acf_dir . '/' . sanitize_file_name( (string) $json_file );
							if ( ! is_dir( $acf_dir ) ) {
								wp_mkdir_p( $acf_dir );
							}
							if ( is_string( $content ) && file_put_contents( $json_path, $content ) !== false ) {
								$results['restored']++;
							} else {
								$results['errors'][] = "Failed to restore: {$json_path}";
							}
						}
					}
					continue;
				} else {
					$file_path = $theme_dir . '/' . $file;
					$snapshot_key = $file;
				}
			} else {
				$file_path = $theme_dir . '/' . $file;
				$snapshot_key = $file;
			}

			if ( $file_path !== '' && isset( $theme_snapshots[ $snapshot_key ] ) ) {
				$content = $theme_snapshots[ $snapshot_key ];
				if ( is_string( $content ) && file_put_contents( $file_path, $content ) !== false ) {
					$results['restored']++;
				} else {
					$results['errors'][] = "Failed to restore: {$file_path}";
				}
			} elseif ( $file_path !== '' ) {
				$results['errors'][] = "No snapshot found for: {$file}";
			}
		}

		return $results;
	}

	/**
	 * Rollback specific items based on selection.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Fingerprint to rollback from.
	 * @param string $scope Scope type: 'everything', 'by_type', 'by_page'.
	 * @param array  $selected_types Array of selected types (when scope is 'by_type').
	 * @param array  $selected_pages Array of selected page slugs (when scope is 'by_page').
	 * @return array Results with deleted/restored counts.
	 */
	public static function rollback_selection( string $project_slug, string $fingerprint, string $scope, array $selected_types, array $selected_pages ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$scope = sanitize_key( $scope );

		$results = array(
			'ok' => true,
			'deleted_pages' => 0,
			'deleted_templates' => 0,
			'deleted_template_parts' => 0,
			'restored_pages' => 0,
			'restored_templates' => 0,
			'restored_template_parts' => 0,
			'restored_theme_files' => 0,
			'errors' => array(),
		);

		if ( $project_slug === '' || $fingerprint === '' ) {
			$results['ok'] = false;
			$results['errors'][] = 'Project slug and fingerprint are required.';
			return $results;
		}

		if ( $scope === 'everything' ) {
			// Rollback everything
			$full_rollback = self::rollback_deploy( $project_slug, $fingerprint );
			$results['deleted_pages'] = $full_rollback['deleted'] ?? 0;
			$results['restored_pages'] = $full_rollback['restored'] ?? 0;
			$results['ok'] = $full_rollback['ok'] ?? false;
			
			// Merge all messages (errors + warnings) for logging
			$all_messages = isset( $full_rollback['error_messages'] ) && is_array( $full_rollback['error_messages'] ) ? $full_rollback['error_messages'] : array();
			if ( ! empty( $all_messages ) ) {
				$results['errors'] = array_merge( $results['errors'], $all_messages );
			}
			
			// Store actual errors (non-skippable) separately
			$actual_errors = isset( $full_rollback['actual_errors'] ) && is_array( $full_rollback['actual_errors'] ) ? $full_rollback['actual_errors'] : array();
			$results['actual_errors'] = isset( $results['actual_errors'] ) ? array_merge( $results['actual_errors'], $actual_errors ) : $actual_errors;
			
			// Store warnings separately
			$warnings = isset( $full_rollback['warnings'] ) && is_array( $full_rollback['warnings'] ) ? $full_rollback['warnings'] : array();
			$results['warnings'] = isset( $results['warnings'] ) ? array_merge( $results['warnings'], $warnings ) : $warnings;
			
			if ( isset( $full_rollback['errors'] ) && $full_rollback['errors'] > 0 && empty( $all_messages ) ) {
				// Log detailed info for debugging
				\VibeCode\Deploy\Logger::warning( 'Rollback had errors but no error messages.', array(
					'project_slug' => $project_slug,
					'fingerprint' => $fingerprint,
					'rollback_result' => $full_rollback,
					'errors_count' => $full_rollback['errors'] ?? 0,
				), $project_slug );
				$results['errors'][] = sprintf( 'Rollback had %d error(s) but no detailed messages available. Check logs for details.', (int) ( $full_rollback['errors'] ?? 0 ) );
			}
			
			// Also check for error key (string error message)
			if ( isset( $full_rollback['error'] ) && is_string( $full_rollback['error'] ) && $full_rollback['error'] !== '' ) {
				$results['errors'][] = $full_rollback['error'];
				$results['actual_errors'][] = $full_rollback['error'];
				$results['ok'] = false;
			}
		} elseif ( $scope === 'by_type' ) {
			// Rollback by selected types
			$selected_types = array_map( 'sanitize_key', $selected_types );
			if ( in_array( 'pages', $selected_types, true ) ) {
				$pages = CleanupService::get_project_pages( $project_slug );
				$page_slugs = array_column( $pages, 'slug' );
				if ( ! empty( $page_slugs ) ) {
					$page_result = self::rollback_pages_by_slugs( $project_slug, $fingerprint, $page_slugs );
					$results['deleted_pages'] = $page_result['deleted'] ?? 0;
					$results['restored_pages'] = $page_result['restored'] ?? 0;
					if ( ! ( $page_result['ok'] ?? false ) ) {
						$results['ok'] = false;
						$error_msgs = isset( $page_result['error_messages'] ) && is_array( $page_result['error_messages'] ) ? $page_result['error_messages'] : array();
						if ( ! empty( $error_msgs ) ) {
							$results['errors'] = array_merge( $results['errors'], $error_msgs );
						} else {
							$results['errors'][] = 'Page rollback had errors (no details available).';
						}
					}
				}
			}
			if ( in_array( 'templates', $selected_types, true ) ) {
				$templates = CleanupService::get_project_templates( $project_slug );
				$template_slugs = array_column( $templates, 'slug' );
				if ( ! empty( $template_slugs ) ) {
					$template_result = self::rollback_templates_by_slugs( $project_slug, $fingerprint, $template_slugs );
					$results['deleted_templates'] = $template_result['deleted'] ?? 0;
					$results['restored_templates'] = $template_result['restored'] ?? 0;
					if ( ! ( $template_result['ok'] ?? false ) ) {
						$results['ok'] = false;
						$error_msgs = isset( $template_result['error_messages'] ) && is_array( $template_result['error_messages'] ) ? $template_result['error_messages'] : array();
						if ( ! empty( $error_msgs ) ) {
							$results['errors'] = array_merge( $results['errors'], $error_msgs );
						} else {
							$results['errors'][] = 'Template rollback had errors (no details available).';
						}
					}
				}
			}
			if ( in_array( 'template_parts', $selected_types, true ) ) {
				$parts = CleanupService::get_project_template_parts( $project_slug );
				$part_slugs = array_column( $parts, 'slug' );
				if ( ! empty( $part_slugs ) ) {
					$part_result = self::rollback_template_parts_by_slugs( $project_slug, $fingerprint, $part_slugs );
					$results['deleted_template_parts'] = $part_result['deleted'] ?? 0;
					$results['restored_template_parts'] = $part_result['restored'] ?? 0;
					if ( ! ( $part_result['ok'] ?? false ) ) {
						$results['ok'] = false;
						$error_msgs = isset( $part_result['error_messages'] ) && is_array( $part_result['error_messages'] ) ? $part_result['error_messages'] : array();
						if ( ! empty( $error_msgs ) ) {
							$results['errors'] = array_merge( $results['errors'], $error_msgs );
						} else {
							$results['errors'][] = 'Template part rollback had errors (no details available).';
						}
					}
				}
			}
			if ( in_array( 'theme_files', $selected_types, true ) || in_array( 'acf_json', $selected_types, true ) ) {
				$theme_files_to_restore = array();
				if ( in_array( 'theme_files', $selected_types, true ) ) {
					$theme_files_to_restore[] = 'functions.php';
				}
				if ( in_array( 'acf_json', $selected_types, true ) ) {
					$theme_files_to_restore[] = 'acf-json/*.json';
				}
				if ( ! empty( $theme_files_to_restore ) ) {
					$theme_result = self::rollback_theme_files( $project_slug, $fingerprint, $theme_files_to_restore );
					$results['restored_theme_files'] = $theme_result['restored'] ?? 0;
					$results['errors'] = array_merge( $results['errors'], $theme_result['errors'] ?? array() );
				}
			}
		} elseif ( $scope === 'by_page' ) {
			// Rollback by selected pages
			if ( ! empty( $selected_pages ) ) {
				$page_result = self::rollback_pages_by_slugs( $project_slug, $fingerprint, $selected_pages );
				$results['deleted_pages'] = $page_result['deleted'] ?? 0;
				$results['restored_pages'] = $page_result['restored'] ?? 0;
				$results['ok'] = $page_result['ok'] ?? false;
				if ( isset( $page_result['error'] ) ) {
					$results['errors'][] = $page_result['error'];
				}
			}
		}

		return $results;
	}
}
