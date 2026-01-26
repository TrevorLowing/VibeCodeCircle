<?php
/**
 * TemplatesService.php
 *
 * Service class for managing WordPress Templates (wp_template CPT).
 * This service wraps WordPress functions to enable dependency injection and testing.
 *
 * @package Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use WP_Error;
use WP_Post;
use WP_Query;

/**
 * TemplatesService class.
 *
 * @package Etch\Services
 */
class TemplatesService implements TemplatesServiceInterface {

	private const POST_TYPE = 'wp_template';

	/**
	 * Query all published templates.
	 *
	 * @return array<WP_Post> Array of WP_Post objects.
	 */
	public function query_templates(): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		);

		$query = new WP_Query( $args );

		return array_filter(
			$query->posts,
			fn( $post ) => $post instanceof WP_Post
		);
	}

	/**
	 * Get block templates from WordPress.
	 *
	 * @return array<\WP_Block_Template> Array of block template objects.
	 */
	public function get_block_templates(): array {
		return get_block_templates( array(), 'wp_template' );
	}

	/**
	 * Get a single template by ID.
	 *
	 * @param int $id The template post ID.
	 * @return WP_Post|null The post object or null if not found.
	 */
	public function get_template( int $id ): ?WP_Post {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return $post;
	}

	/**
	 * Create a new template.
	 *
	 * @param array{post_title?: string, post_content?: string, post_name?: string} $data The template data.
	 * @return int|WP_Error The post ID on success, WP_Error on failure.
	 */
	public function create_template( array $data ): int|WP_Error {
		$post_data = array_merge(
			$data,
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
			)
		);

		return wp_insert_post( $post_data, true );
	}

	/**
	 * Update an existing template.
	 *
	 * @param array{ID: int, post_title?: string, post_content?: string, post_name?: string} $data The template data including 'ID'.
	 * @return int|WP_Error The post ID on success, WP_Error on failure.
	 */
	public function update_template( array $data ): int|WP_Error {
		return wp_update_post( $data, true );
	}

	/**
	 * Delete a template.
	 *
	 * @param int $id The template post ID.
	 * @return WP_Post|false|null The deleted post object on success, false or null on failure.
	 */
	public function delete_template( int $id ): WP_Post|false|null {
		return wp_delete_post( $id, true );
	}

	/**
	 * Get the theme associated with a template.
	 *
	 * @param int $id The template post ID.
	 * @return string|null The theme slug or null if not found.
	 */
	public function get_template_theme( int $id ): ?string {
		$theme_terms = wp_get_object_terms( $id, 'wp_theme', array( 'fields' => 'names' ) );

		if ( is_wp_error( $theme_terms ) || empty( $theme_terms ) ) {
			return null;
		}

		return $theme_terms[0];
	}

	/**
	 * Set the theme for a template.
	 *
	 * @param int    $id    The template post ID.
	 * @param string $theme The theme slug.
	 * @return void
	 */
	public function set_template_theme( int $id, string $theme ): void {
		wp_set_object_terms( $id, $theme, 'wp_theme', false );
	}

	/**
	 * Get the current active theme slug.
	 *
	 * @return string The theme slug.
	 */
	public function get_current_theme_slug(): string {
		return get_stylesheet();
	}
}
