<?php
/**
 * TemplatesServiceInterface.php
 *
 * Interface for the TemplatesService to enable mocking in tests.
 *
 * @package Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use WP_Post;

/**
 * TemplatesServiceInterface
 *
 * @package Etch\Services
 */
interface TemplatesServiceInterface {

	/**
	 * Query all published templates.
	 *
	 * @return array<WP_Post> Array of WP_Post objects.
	 */
	public function query_templates(): array;

	/**
	 * Get block templates from WordPress.
	 *
	 * @return array<\WP_Block_Template> Array of block template objects.
	 */
	public function get_block_templates(): array;

	/**
	 * Get a single template by ID.
	 *
	 * @param int $id The template post ID.
	 * @return WP_Post|null The post object or null if not found.
	 */
	public function get_template( int $id ): ?WP_Post;

	/**
	 * Create a new template.
	 *
	 * @param array<string, mixed> $data The template data.
	 * @return int|\WP_Error The post ID on success, WP_Error on failure.
	 */
	public function create_template( array $data );

	/**
	 * Update an existing template.
	 *
	 * @param array<string, mixed> $data The template data including 'ID'.
	 * @return int|\WP_Error The post ID on success, WP_Error on failure.
	 */
	public function update_template( array $data );

	/**
	 * Delete a template.
	 *
	 * @param int $id The template post ID.
	 * @return WP_Post|false|null The deleted post object on success, false or null on failure.
	 */
	public function delete_template( int $id );

	/**
	 * Get the theme associated with a template.
	 *
	 * @param int $id The template post ID.
	 * @return string|null The theme slug or null if not found.
	 */
	public function get_template_theme( int $id ): ?string;

	/**
	 * Set the theme for a template.
	 *
	 * @param int    $id    The template post ID.
	 * @param string $theme The theme slug.
	 * @return void
	 */
	public function set_template_theme( int $id, string $theme ): void;

	/**
	 * Get the current active theme slug.
	 *
	 * @return string The theme slug.
	 */
	public function get_current_theme_slug(): string;
}
