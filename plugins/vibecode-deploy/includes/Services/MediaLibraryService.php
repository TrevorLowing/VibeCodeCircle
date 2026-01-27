<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Media Library Service
 *
 * Handles image uploads to WordPress Media Library during deployment.
 * Includes smart duplicate detection and update handling for redeployments.
 *
 * @package VibeCode\Deploy\Services
 */
final class MediaLibraryService {
	/** @var string Post meta key for source path hash. */
	public const META_SOURCE_PATH_HASH = '_vibecode_deploy_source_path_hash';

	/** @var string Post meta key for file hash. */
	public const META_FILE_HASH = '_vibecode_deploy_file_hash';

	/** @var string Post meta key for project slug. */
	public const META_PROJECT_SLUG = '_vibecode_deploy_project_slug';

	/**
	 * Upload image to Media Library or reuse existing attachment.
	 *
	 * Checks if image already exists by source path hash. If exists and file unchanged,
	 * returns existing attachment ID. If file changed, updates attachment. If not exists,
	 * uploads new image.
	 *
	 * @param string $file_path Full path to image file in staging.
	 * @param string $filename Just the filename (e.g., "logo.png").
	 * @param string $source_path Source path from HTML (e.g., "resources/images/logo.png").
	 * @param array  $metadata Optional metadata (alt text, etc.).
	 * @return int|false Attachment ID on success, false on failure.
	 */
	public static function upload_image_to_media_library( string $file_path, string $filename, string $source_path, array $metadata = array() ): int|false {
		// Validate file exists
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			Logger::error( 'Image file not found or not readable.', array( 'file_path' => $file_path ) );
			return false;
		}
		
		// Get file info for validation
		$file_size = filesize( $file_path );
		$file_extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$file_info = wp_check_filetype( $filename );
		$mime_type = $file_info['type'] ?? '';
		
		// Log file details before validation
		Logger::info( 'Validating file before Media Library upload.', array(
			'file_path' => $file_path,
			'filename' => $filename,
			'file_size' => $file_size,
			'file_extension' => $file_extension,
			'mime_type' => $mime_type,
		) );
		
		// Explicitly reject zip files
		if ( $file_extension === 'zip' || strpos( $filename, '.zip' ) !== false || strpos( $file_path, '.zip' ) !== false ) {
			Logger::error( 'File rejected: zip files are not allowed in Media Library.', array(
				'file_path' => $file_path,
				'filename' => $filename,
				'file_extension' => $file_extension,
			) );
			return false;
		}
		
		// Define allowed image extensions
		$allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' );
		
		// Validate file extension
		if ( ! in_array( $file_extension, $allowed_extensions, true ) ) {
			Logger::error( 'File is not an image - invalid extension.', array(
				'file_path' => $file_path,
				'filename' => $filename,
				'file_extension' => $file_extension,
				'allowed_extensions' => $allowed_extensions,
			) );
			return false;
		}
		
		// Validate MIME type
		if ( $mime_type === '' || strpos( $mime_type, 'image/' ) !== 0 ) {
			// Try to get MIME type from file if wp_check_filetype didn't work
			if ( function_exists( 'mime_content_type' ) ) {
				$detected_mime = mime_content_type( $file_path );
				if ( $detected_mime !== false && strpos( $detected_mime, 'image/' ) === 0 ) {
					$mime_type = $detected_mime;
				} else {
					Logger::error( 'File MIME type is not an image type.', array(
						'file_path' => $file_path,
						'filename' => $filename,
						'detected_mime' => $detected_mime !== false ? $detected_mime : 'unknown',
						'wp_check_filetype_mime' => $mime_type,
					) );
					return false;
				}
			} else {
				Logger::error( 'File MIME type is not an image type.', array(
					'file_path' => $file_path,
					'filename' => $filename,
					'mime_type' => $mime_type,
				) );
				return false;
			}
		}
		
		// Check file size - reject suspiciously large files (potential zip files or corrupted images)
		// Most images should be under 50MB, but allow up to 100MB for very large images
		// Zip files are typically much larger
		$max_image_size = 100 * 1024 * 1024; // 100MB
		if ( $file_size > $max_image_size ) {
			Logger::error( 'File rejected: file size exceeds maximum allowed for images.', array(
				'file_path' => $file_path,
				'filename' => $filename,
				'file_size' => $file_size,
				'max_size' => $max_image_size,
			) );
			return false;
		}
		
		// Additional validation: Check if file is actually readable as an image
		// For SVG, we can't use getimagesize, so skip this check
		if ( $file_extension !== 'svg' ) {
			$image_info = @getimagesize( $file_path );
			if ( $image_info === false ) {
				Logger::error( 'File is not a valid image file (getimagesize failed).', array(
					'file_path' => $file_path,
					'filename' => $filename,
					'file_extension' => $file_extension,
				) );
				return false;
			}
		}

		// Get project slug for lookup
		$settings = Settings::get_all();
		$project_slug = isset( $settings['project_slug'] ) && is_string( $settings['project_slug'] ) ? (string) $settings['project_slug'] : '';

		// Calculate source path hash for lookup
		$source_hash = md5( $source_path );

		// Check if attachment already exists
		$existing = self::find_existing_attachment( $source_path, $filename, $project_slug );
		if ( $existing ) {
			Logger::info( 'Found existing Media Library attachment, checking for changes.', array(
				'attachment_id' => $existing,
				'source_path' => $source_path,
				'filename' => $filename,
			) );
			
			// Check if file changed
			$new_file_hash = md5_file( $file_path );
			$existing_file_hash = get_post_meta( $existing, self::META_FILE_HASH, true );

			Logger::info( 'Comparing file hashes for attachment update check.', array(
				'attachment_id' => $existing,
				'new_file_hash' => $new_file_hash,
				'existing_file_hash' => is_string( $existing_file_hash ) ? $existing_file_hash : 'not_set',
				'hashes_match' => $new_file_hash === $existing_file_hash,
			) );

			if ( $new_file_hash === $existing_file_hash ) {
				// File unchanged, reuse existing attachment
				Logger::info( 'Reusing existing Media Library attachment (file unchanged).', array(
					'attachment_id' => $existing,
					'source_path' => $source_path,
					'file_hash' => $new_file_hash,
				) );
				return $existing;
			} else {
				// File changed, update attachment
				Logger::info( 'File changed, updating existing Media Library attachment.', array(
					'attachment_id' => $existing,
					'source_path' => $source_path,
					'old_file_hash' => is_string( $existing_file_hash ) ? $existing_file_hash : 'not_set',
					'new_file_hash' => $new_file_hash,
				) );
				return self::update_attachment_if_changed( $existing, $file_path, $new_file_hash );
			}
		} else {
			Logger::info( 'No existing Media Library attachment found, will create new attachment.', array(
				'source_path' => $source_path,
				'filename' => $filename,
				'project_slug' => $project_slug,
			) );
		}

		// New image, upload to Media Library
		// Log file details before upload
		Logger::info( 'Uploading validated image to Media Library.', array(
			'file_path' => $file_path,
			'filename' => $filename,
			'file_size' => $file_size,
			'file_extension' => $file_extension,
			'mime_type' => $mime_type,
			'source_path' => $source_path,
		) );
		
		$upload = wp_upload_bits( $filename, null, file_get_contents( $file_path ) );
		if ( isset( $upload['error'] ) && $upload['error'] !== false ) {
			Logger::error( 'Failed to upload image to Media Library.', array(
				'file_path' => $file_path,
				'error' => $upload['error'],
			) );
			return false;
		}

		// Create attachment post
		$attachment = array(
			'post_mime_type' => wp_check_filetype( $filename )['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( is_wp_error( $attachment_id ) ) {
			Logger::error( 'Failed to create attachment post.', array(
				'file_path' => $file_path,
				'error' => $attachment_id->get_error_message(),
			) );
			return false;
		}

		// Generate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// Store source path hash and file hash for redeployment lookup
		update_post_meta( $attachment_id, self::META_SOURCE_PATH_HASH, $source_hash );
		update_post_meta( $attachment_id, self::META_FILE_HASH, md5_file( $file_path ) );
		if ( $project_slug !== '' ) {
			update_post_meta( $attachment_id, self::META_PROJECT_SLUG, $project_slug );
		}

		// Store alt text if provided
		if ( isset( $metadata['alt'] ) && is_string( $metadata['alt'] ) && $metadata['alt'] !== '' ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $metadata['alt'] ) );
		}

		Logger::info( 'Uploaded image to Media Library.', array(
			'attachment_id' => $attachment_id,
			'source_path' => $source_path,
			'filename' => $filename,
		) );

		return $attachment_id;
	}

	/**
	 * Get Media Library URL for attachment.
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return string Full URL to attachment file.
	 */
	public static function get_attachment_url( int $attachment_id ): string {
		$url = wp_get_attachment_url( $attachment_id );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Find existing attachment by source path hash.
	 *
	 * Looks up existing attachment by source path hash (stored in post meta).
	 * Falls back to filename lookup if path hash not found.
	 *
	 * @param string $source_path Source path from HTML (e.g., "resources/images/logo.png").
	 * @param string $filename Just the filename (e.g., "logo.png").
	 * @param string $project_slug Project slug for filtering (optional).
	 * @return int|false Attachment ID if found, false otherwise.
	 */
	public static function find_existing_attachment( string $source_path, string $filename, string $project_slug = '' ): int|false {
		// Calculate source path hash
		$source_hash = md5( $source_path );

		Logger::info( 'Searching for existing Media Library attachment.', array(
			'source_path' => $source_path,
			'source_hash' => $source_hash,
			'filename' => $filename,
			'project_slug' => $project_slug,
		) );

		// Build query args
		$query_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => self::META_SOURCE_PATH_HASH,
					'value' => $source_hash,
				),
			),
		);

		// Filter by project slug if provided
		if ( $project_slug !== '' ) {
			$query_args['meta_query'][] = array(
				'key'   => self::META_PROJECT_SLUG,
				'value' => $project_slug,
			);
		}

		$attachments = get_posts( $query_args );
		if ( ! empty( $attachments ) && is_array( $attachments ) ) {
			$found_id = (int) $attachments[0];
			Logger::info( 'Found existing Media Library attachment by source path hash.', array(
				'attachment_id' => $found_id,
				'source_path' => $source_path,
				'source_hash' => $source_hash,
			) );
			return $found_id;
		}

		// Fallback: Look up by filename (less reliable, may have duplicates)
		// Only use if source path hash not found
		$query_args = array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'image',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_wp_attached_file',
					'value'   => $filename,
					'compare' => 'LIKE',
				),
			),
		);

		if ( $project_slug !== '' ) {
			$query_args['meta_query'][] = array(
				'key'   => self::META_PROJECT_SLUG,
				'value' => $project_slug,
			);
		}

		$attachments = get_posts( $query_args );
		if ( ! empty( $attachments ) && is_array( $attachments ) ) {
			$found_id = (int) $attachments[0];
			Logger::info( 'Found existing Media Library attachment by filename fallback.', array(
				'attachment_id' => $found_id,
				'filename' => $filename,
				'source_path' => $source_path,
			) );
			return $found_id;
		}

		Logger::info( 'No existing Media Library attachment found.', array(
			'source_path' => $source_path,
			'filename' => $filename,
			'project_slug' => $project_slug,
		) );

		return false;
	}

	/**
	 * Update attachment file if changed.
	 *
	 * Compares file hash of existing attachment with new file.
	 * If file changed, updates attachment file and regenerates metadata.
	 *
	 * @param int    $attachment_id Existing attachment ID.
	 * @param string $file_path Full path to new image file.
	 * @param string $new_file_hash MD5 hash of new file (optional, calculated if not provided).
	 * @return int|false Attachment ID on success, false on failure.
	 */
	public static function update_attachment_if_changed( int $attachment_id, string $file_path, string $new_file_hash = '' ): int|false {
		if ( $new_file_hash === '' ) {
			$new_file_hash = md5_file( $file_path );
		}

		// Get existing attachment file path
		$existing_file = get_attached_file( $attachment_id );
		if ( ! is_string( $existing_file ) || $existing_file === '' ) {
			Logger::error( 'Failed to get existing attachment file path.', array( 'attachment_id' => $attachment_id ) );
			return false;
		}

		// Replace file
		$upload_dir = wp_upload_dir();
		$upload_dir_path = isset( $upload_dir['path'] ) ? $upload_dir['path'] : '';
		if ( $upload_dir_path === '' ) {
			Logger::error( 'Failed to get upload directory path.' );
			return false;
		}

		// Copy new file to existing location
		if ( ! copy( $file_path, $existing_file ) ) {
			Logger::error( 'Failed to update attachment file.', array(
				'attachment_id' => $attachment_id,
				'file_path' => $file_path,
			) );
			return false;
		}

		// Regenerate attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $existing_file );
		wp_update_attachment_metadata( $attachment_id, $attachment_metadata );

		// Update file hash
		update_post_meta( $attachment_id, self::META_FILE_HASH, $new_file_hash );

		Logger::info( 'Updated existing Media Library attachment (file changed).', array(
			'attachment_id' => $attachment_id,
			'file_path' => $file_path,
		) );

		return $attachment_id;
	}

	/**
	 * Check if attachment is orphaned (not referenced in any post content).
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return bool True if orphaned (not referenced), false if referenced.
	 */
	public static function is_attachment_orphaned( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return true;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_url || ! is_string( $attachment_url ) ) {
			return true; // Attachment doesn't exist
		}

		// Search all posts for attachment URL or ID in block attributes
		global $wpdb;
		$url_pattern = '%' . $wpdb->esc_like( $attachment_url ) . '%';
		$id_pattern = '%"id":' . $attachment_id . '%';
		$id_pattern_alt = '%"id": ' . $attachment_id . '%'; // With space after colon

		$found = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			 WHERE (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)
			 AND post_status != 'trash'",
			$url_pattern,
			$id_pattern,
			$id_pattern_alt
		) );

		return (int) $found === 0;
	}

	/**
	 * Get all Media Library attachments for a project.
	 *
	 * @param string      $project_slug Project slug.
	 * @param string|null $mime_type_filter Optional MIME type filter. Use 'image' for images only (default), 'all' or null for all file types.
	 * @return array Array of attachment IDs.
	 */
	public static function get_project_attachments( string $project_slug, ?string $mime_type_filter = 'image' ): array {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return array();
		}

		$query_args = array(
			'post_type' => 'attachment',
			'posts_per_page' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => self::META_PROJECT_SLUG,
					'value' => $project_slug,
					'compare' => '=',
				),
			),
		);

		// Only filter by MIME type if specified and not 'all'
		if ( $mime_type_filter !== null && $mime_type_filter !== 'all' && $mime_type_filter !== '' ) {
			$query_args['post_mime_type'] = $mime_type_filter;
		}

		$attachments = get_posts( $query_args );

		return is_array( $attachments ) ? array_map( 'intval', $attachments ) : array();
	}

	/**
	 * Delete all Media Library attachments for a project.
	 *
	 * @param string $project_slug Project slug.
	 * @return int Number of attachments deleted.
	 */
	public static function delete_attachments_for_project( string $project_slug ): int {
		$attachments = self::get_project_attachments( $project_slug, 'image' );
		$deleted = 0;

		Logger::info( 'Starting deletion of all Media Library attachments for project.', array(
			'project_slug' => $project_slug,
			'attachment_count' => count( $attachments ),
		) );

		foreach ( $attachments as $attachment_id ) {
			if ( $attachment_id > 0 ) {
				$result = wp_delete_attachment( $attachment_id, true ); // true = force delete, removes file
				if ( $result ) {
					$deleted++;
					Logger::info( 'Deleted Media Library attachment during cleanup.', array(
						'attachment_id' => $attachment_id,
						'project_slug' => $project_slug,
					) );
				} else {
					Logger::warning( 'Failed to delete Media Library attachment.', array(
						'attachment_id' => $attachment_id,
						'project_slug' => $project_slug,
					) );
				}
			}
		}

		Logger::info( 'Completed deletion of Media Library attachments for project.', array(
			'project_slug' => $project_slug,
			'deleted_count' => $deleted,
			'total_attachments' => count( $attachments ),
		) );

		return $deleted;
	}

	/**
	 * Delete only orphaned Media Library attachments for a project.
	 *
	 * @param string $project_slug Project slug.
	 * @return int Number of orphaned attachments deleted.
	 */
	public static function delete_orphaned_attachments_for_project( string $project_slug ): int {
		$attachments = self::get_project_attachments( $project_slug, 'image' );
		$deleted = 0;
		$orphaned_count = 0;

		Logger::info( 'Starting deletion of orphaned Media Library attachments for project.', array(
			'project_slug' => $project_slug,
			'attachment_count' => count( $attachments ),
		) );

		foreach ( $attachments as $attachment_id ) {
			if ( $attachment_id > 0 ) {
				$is_orphaned = self::is_attachment_orphaned( $attachment_id );
				if ( $is_orphaned ) {
					$orphaned_count++;
					$result = wp_delete_attachment( $attachment_id, true ); // true = force delete, removes file
					if ( $result ) {
						$deleted++;
						Logger::info( 'Deleted orphaned Media Library attachment during cleanup.', array(
							'attachment_id' => $attachment_id,
							'project_slug' => $project_slug,
						) );
					} else {
						Logger::warning( 'Failed to delete orphaned Media Library attachment.', array(
							'attachment_id' => $attachment_id,
							'project_slug' => $project_slug,
						) );
					}
				}
			}
		}

		Logger::info( 'Completed deletion of orphaned Media Library attachments for project.', array(
			'project_slug' => $project_slug,
			'deleted_count' => $deleted,
			'orphaned_count' => $orphaned_count,
			'total_attachments' => count( $attachments ),
		) );

		return $deleted;
	}

	/**
	 * Delete invalid attachments (non-image files) for a project.
	 *
	 * Finds all attachments with project slug meta that are NOT images
	 * (e.g., zip files that were incorrectly uploaded before validation was added).
	 *
	 * @param string $project_slug Project slug.
	 * @return int Number of invalid attachments deleted.
	 */
	public static function delete_invalid_attachments_for_project( string $project_slug ): int {
		$project_slug = sanitize_key( $project_slug );
		if ( $project_slug === '' ) {
			return 0;
		}

		// Get all attachments for project (not just images)
		$all_attachments = self::get_project_attachments( $project_slug, 'all' );
		
		if ( empty( $all_attachments ) ) {
			return 0;
		}

		$deleted = 0;
		$invalid_attachments = array();

		// Filter to find non-image attachments
		foreach ( $all_attachments as $attachment_id ) {
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$mime_type = get_post_mime_type( $attachment_id );
			if ( $mime_type === false || strpos( $mime_type, 'image/' ) !== 0 ) {
				// Not an image - this is an invalid attachment
				$invalid_attachments[] = $attachment_id;
			}
		}

		if ( empty( $invalid_attachments ) ) {
			Logger::info( 'No invalid attachments found for project.', array(
				'project_slug' => $project_slug,
				'total_attachments' => count( $all_attachments ),
			) );
			return 0;
		}

		Logger::info( 'Found invalid attachments for project.', array(
			'project_slug' => $project_slug,
			'invalid_count' => count( $invalid_attachments ),
			'invalid_attachment_ids' => $invalid_attachments,
			'total_attachments' => count( $all_attachments ),
		) );

		// Delete all invalid attachments
		foreach ( $invalid_attachments as $attachment_id ) {
			$mime_type = get_post_mime_type( $attachment_id );
			$filename = get_post_meta( $attachment_id, '_wp_attached_file', true );
			
			$result = wp_delete_attachment( $attachment_id, true ); // true = force delete, removes file
			if ( $result ) {
				$deleted++;
				Logger::info( 'Deleted invalid Media Library attachment.', array(
					'attachment_id' => $attachment_id,
					'project_slug' => $project_slug,
					'mime_type' => $mime_type !== false ? $mime_type : 'unknown',
					'filename' => is_string( $filename ) ? $filename : 'unknown',
				) );
			} else {
				Logger::warning( 'Failed to delete invalid attachment.', array(
					'attachment_id' => $attachment_id,
					'project_slug' => $project_slug,
					'mime_type' => $mime_type !== false ? $mime_type : 'unknown',
				) );
			}
		}

		Logger::info( 'Completed deletion of invalid attachments.', array(
			'project_slug' => $project_slug,
			'deleted_count' => $deleted,
			'total_invalid' => count( $invalid_attachments ),
		) );

		return $deleted;
	}

	/**
	 * Get attachment references (posts that use this attachment).
	 *
	 * @param int $attachment_id Attachment post ID.
	 * @return array Array of post IDs that reference this attachment.
	 */
	public static function get_attachment_references( int $attachment_id ): array {
		if ( $attachment_id <= 0 ) {
			return array();
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		if ( ! $attachment_url || ! is_string( $attachment_url ) ) {
			return array();
		}

		// Search all posts for attachment URL or ID
		global $wpdb;
		$url_pattern = '%' . $wpdb->esc_like( $attachment_url ) . '%';
		$id_pattern = '%"id":' . $attachment_id . '%';
		$id_pattern_alt = '%"id": ' . $attachment_id . '%';

		$post_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} 
			 WHERE (post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s)
			 AND post_status != 'trash'",
			$url_pattern,
			$id_pattern,
			$id_pattern_alt
		) );

		return is_array( $post_ids ) ? array_map( 'intval', $post_ids ) : array();
	}
}
