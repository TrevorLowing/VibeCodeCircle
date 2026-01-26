<?php
/**
 * TemplatesRoutes.php
 *
 * This file contains the TemplatesRoutes class which defines REST API routes for handling WordPress Templates (wp_template CPT).
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\WideEventLogger;
use Etch\Services\TemplatesService;
use Etch\Services\TemplatesServiceInterface;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * TemplatesRoutes
 *
 * This class defines REST API endpoints for managing WordPress Templates (wp_template CPT).
 *
 * @package Etch\RestApi\Routes
 */
class TemplatesRoutes extends BaseRoute {

	/**
	 * The templates service instance.
	 *
	 * @var TemplatesServiceInterface
	 */
	private TemplatesServiceInterface $templates_service;

	/**
	 * Constructor.
	 *
	 * @param TemplatesServiceInterface|null $templates_service Optional service instance for dependency injection.
	 */
	public function __construct( ?TemplatesServiceInterface $templates_service = null ) {
		$this->templates_service = $templates_service ?? new TemplatesService();
	}

	/**
	 * Returns the route definitions for template endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			// List Templates
			array(
				'route'               => '/templates',
				'methods'             => 'GET',
				'callback'            => array( $this, 'list_templates' ),
			),
			// Create Template
			array(
				'route'               => '/templates',
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_template' ),
				'args'                => $this->get_template_args_schema(),
			),
			// Get Single Template
			array(
				'route'               => '/templates/(?P<id>\d+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_template' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => fn( $param ) => $this->validate_template_id( $param ),
						'required'          => true,
					),
				),
			),
			// Update Template
			array(
				'route'               => '/templates/(?P<id>\d+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_template' ),
				'args'                => array_merge(
					$this->get_template_args_schema(),
					array(
						'id' => array(
							'validate_callback' => fn( $param ) => $this->validate_template_id( $param ),
							'required'          => true,
						),
					)
				),
			),
			// Delete Template
			array(
				'route'               => '/templates/(?P<id>\d+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_template' ),
				'args'                => array(
					'id' => array(
						'validate_callback' => fn( $param ) => $this->validate_template_id( $param ),
						'required'          => true,
					),
				),
			),
		);
	}

	/**
	 * Get the argument schema for template properties.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_template_args_schema(): array {
		return array(
			'post_title'   => array(
				'description'       => __( 'Template title.' ),
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => fn( $value ) => ! empty( $value ),
			),
			'post_content' => array(
				'description'       => __( 'Template content (block markup). Optional, defaults to empty.' ),
				'type'              => 'string',
				'required'          => false,
				'default'           => '',
				// No direct sanitize/validate here, handled by wp_insert/update_post
			),
			'post_name'    => array(
				'description'       => __( 'Template slug.' ),
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_key',
			),
			// Add other relevant fields like 'meta_input' if needed
		);
	}

	/**
	 * Validate if a template ID exists and matches the post type.
	 *
	 * @param mixed $param The parameter value (template ID).
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_template_id( $param ) {
		if ( ! is_numeric( $param ) || $param <= 0 ) {
			WideEventLogger::failure(
				'api.templates.validation',
				'Invalid template ID',
				array(
					'error_code' => 'invalid_id',
					'template_id' => $param,
				)
			);
			return new WP_Error( 'invalid_id', 'Invalid template ID.', array( 'status' => 400 ) );
		}
		$post = $this->templates_service->get_template( (int) $param );
		if ( ! $post ) {
			WideEventLogger::failure(
				'api.templates.validation',
				'Template not found',
				array(
					'error_code' => 'not_found',
					'template_id' => $param,
				)
			);
			return new WP_Error( 'not_found', 'Template not found.', array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * Ensure all plugin and theme templates are saved to the database.
	 *
	 * This checks if block templates have a wp_id set; if not (or if it's 0),
	 * it forces a save to create the template in the wp_template post type.
	 *
	 * @return void
	 */
	private function ensure_templates_saved(): void {
		$block_templates = $this->templates_service->get_block_templates();

		foreach ( $block_templates as $template ) {
			// Check if the template is from theme or plugin and needs to be saved to DB
			if ( ( 'theme' === $template->source && $template->has_theme_file ) || 'plugin' === $template->source ) {
				// Check if the template needs to be saved to DB (wp_id is not set or is 0)
				if ( ! isset( $template->wp_id ) || 0 === $template->wp_id ) {
					// Create the template as a wp_template post type
					$template_data = array(
						'post_title'   => ucwords( str_replace( '-', ' ', $template->slug ) ),
						'post_name'    => $template->slug,
						'post_content' => $template->content,
					);

					// Insert the post and get the ID
					$post_id = $this->templates_service->create_template( $template_data );

					// Add theme association
					if ( is_int( $post_id ) && $post_id > 0 ) {
						$theme_slug = $this->templates_service->get_current_theme_slug();
						$this->templates_service->set_template_theme( $post_id, $theme_slug );
					}
				}
			}
		}
	}

	/**
	 * Lists all templates.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_templates() {
		// Ensure all templates are saved to the database before listing them
		$this->ensure_templates_saved();

		$block_templates = $this->templates_service->get_block_templates();
		$theme_templates_map = array();
		foreach ( $block_templates as $template ) {
			if ( $template->has_theme_file ) {
				$theme_templates_map[ $template->slug ] = true;
			}
		}

		$active_theme = $this->templates_service->get_current_theme_slug();
		$posts = $this->templates_service->query_templates();
		$templates = array();

		foreach ( $posts as $post ) {
			$slug = $post->post_name;
			$has_theme_file = isset( $theme_templates_map[ $slug ] );

			// Get the theme associated with this template
			$theme = $this->templates_service->get_template_theme( $post->ID );

			// Skip templates that don't belong to the active theme
			if ( $theme !== $active_theme ) {
				continue;
			}

			// Return only essential fields for the list view
			$templates[] = array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'slug'           => $post->post_name,
				'has_theme_file' => $has_theme_file,
				'theme'          => $theme,
			);
		}

		return new WP_REST_Response( $templates, 200 );
	}

	/**
	 * Creates a new template.
	 *
	 * @param WP_REST_Request<array{post_title: string, post_content?: string, post_name?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_template( $request ): WP_REST_Response|WP_Error {
		$params = $request->get_params();

		$post_data = array(
			'post_title'   => $params['post_title'],
			'post_content' => $params['post_content'] ?? '',
		);

		if ( ! empty( $params['post_name'] ) ) {
			$post_data['post_name'] = $params['post_name'];
		}

		$post_id = $this->templates_service->create_template( $post_data );

		if ( is_wp_error( $post_id ) ) {
			WideEventLogger::failure( 'api.templates.create', $post_id->get_error_message(), array( 'error_code' => $post_id->get_error_code() ) );
			return $post_id; // Return WP_Error directly
		}

		// Associate the new template with the current theme
		$theme_slug = $this->templates_service->get_current_theme_slug();
		$this->templates_service->set_template_theme( $post_id, $theme_slug );

		$new_post = $this->templates_service->get_template( $post_id );
		if ( ! $new_post ) {
			WideEventLogger::failure(
				'api.templates.create',
				'Failed to retrieve the created template after theme association',
				array(
					'error_code' => 'creation_failed',
					'template_id' => $post_id,
				)
			);
			return new WP_Error( 'creation_failed', 'Failed to retrieve the created template after theme association.', array( 'status' => 500 ) );
		}

		WideEventLogger::set( 'api.templates.create', 'success' );
		$response_data = $this->prepare_template_for_response( $new_post );
		$response      = new WP_REST_Response( $response_data, 201 ); // 201 Created

		return $response;
	}

	/**
	 * Retrieves a specific template.
	 *
	 * @param WP_REST_Request<array{id: int}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_template( $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$post    = $this->templates_service->get_template( $post_id );

		// Validation already handled by args validate_callback, but add extra check
		if ( ! $post instanceof WP_Post ) {
			// This shouldn't happen due to validate_callback, but safety first
			WideEventLogger::failure(
				'api.templates.validation',
				'Template not found (post-validation)',
				array(
					'error_code' => 'not_found',
					'template_id' => $post_id,
				)
			);
			return new WP_Error( 'not_found', 'Template not found (post-validation).', array( 'status' => 404 ) );
		}

		$response_data = $this->prepare_template_for_response( $post );
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates a specific template.
	 *
	 * @param WP_REST_Request<array{id: int, post_title?: string, post_content?: string, post_name?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_template( $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'id' );
		$params  = $request->get_params();

		$post_data = array(
			'ID' => $post_id, // Must include ID for update
		);

		// Include fields only if they are present in the request
		if ( isset( $params['post_title'] ) ) {
			$post_data['post_title'] = $params['post_title'];
		}
		if ( isset( $params['post_content'] ) ) {
			$post_data['post_content'] = $params['post_content'];
		}
		if ( isset( $params['post_name'] ) ) {
			$post_data['post_name'] = $params['post_name'];
		}
		// Add meta_input handling if needed

		if ( count( $post_data ) === 1 ) { // Only ID was provided
			WideEventLogger::failure(
				'api.templates.validation',
				'No data provided for update',
				array(
					'error_code' => 'no_update_data',
					'template_id' => $post_id,
				)
			);
			return new WP_Error( 'no_update_data', 'No data provided for update.', array( 'status' => 400 ) );
		}

		$updated_post_id = $this->templates_service->update_template( $post_data );

		if ( is_wp_error( $updated_post_id ) ) {
			WideEventLogger::failure(
				'api.templates.update',
				$updated_post_id->get_error_message(),
				array(
					'error_code' => $updated_post_id->get_error_code(),
					'template_id' => $post_id,
				)
			);
			return $updated_post_id; // Return WP_Error directly
		}

		$updated_post = $this->templates_service->get_template( $updated_post_id );
		if ( ! $updated_post ) {
			WideEventLogger::failure(
				'api.templates.update',
				'Failed to retrieve the updated template',
				array(
					'error_code' => 'update_failed',
					'template_id' => $post_id,
				)
			);
			return new WP_Error( 'update_failed', 'Failed to retrieve the updated template.', array( 'status' => 500 ) );
		}

		WideEventLogger::set( 'api.templates.update', 'success' );
		$response_data = $this->prepare_template_for_response( $updated_post );
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Deletes a specific template.
	 *
	 * @param WP_REST_Request<array{id: int}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_template( $request ): WP_REST_Response|WP_Error {
		$post_id = (int) $request->get_param( 'id' );

		// Validation already handled by args validate_callback
		$post = $this->templates_service->get_template( $post_id );

		// Prepare previous data only if post exists
		$previous_data = null;
		if ( $post instanceof WP_Post ) {
			$previous_data = $this->prepare_template_for_response( $post );
		}

		$deleted = $this->templates_service->delete_template( $post_id );

		if ( ! $deleted ) {
			WideEventLogger::failure(
				'api.templates.delete',
				'Failed to delete the template',
				array(
					'error_code' => 'delete_failed',
					'template_id' => $post_id,
				)
			);
			return new WP_Error( 'delete_failed', 'Failed to delete the template.', array( 'status' => 500 ) );
		}

		WideEventLogger::set( 'api.templates.delete', 'success' );
		return new WP_REST_Response(
			array(
				'deleted' => true,
				'previous' => $previous_data,
			),
			200
		);
	}

	/**
	 * Prepares a template post object for the REST API response.
	 *
	 * @param WP_Post $post The template post object.
	 * @return array<string, mixed> The formatted template data.
	 */
	private function prepare_template_for_response( WP_Post $post ): array {
		return array(
			'id'           => $post->ID,
			'title'        => $post->post_title,
			'slug'         => $post->post_name,
		);
	}
}
