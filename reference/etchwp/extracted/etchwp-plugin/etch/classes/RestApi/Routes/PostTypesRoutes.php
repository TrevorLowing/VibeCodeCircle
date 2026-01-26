<?php
/**
 * PostTypesRoutes.php
 *
 * This file contains the PostTypesRoutes class which defines REST API routes for handling Post Types infos.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post_Type;
use WP_Post;

/**
 * PostTypesRoutes
 *
 * This class defines REST API endpoints for retrieving information about Custom Post Types.
 *
 * @package Etch\RestApi\Routes
 */
class PostTypesRoutes extends BaseRoute {

	/**
	 * Returns the route definitions for post type endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/post-types',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_all_post_types' ),
			),
			// Route to get details for a specific post type
			array(
				'route'               => '/post-types/(?P<post_type_name>[a-zA-Z0-9_-]+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_post_type_details' ),
				'args'                => array(
					'post_type_name' => array(
						'validate_callback' => array( $this, 'validate_post_type_exists' ),
						'required'          => true,
					),
				),
			),
		);
	}

	/**
	 * Get all registered public post types with relevant metadata.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_post_types() {
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		$post_type_data   = array();

		foreach ( $post_types as $post_type ) {
			if ( $post_type instanceof WP_Post_Type ) {
				$post_type_data[ $post_type->name ] = $post_type;
			}
		}

		// Exclude built-in types if desired (e.g., post, page, attachment)
		unset( $post_type_data['attachment'] );
		// You might want to keep 'post' and 'page' depending on your needs

		if ( empty( $post_type_data ) ) {
			return new WP_Error( 'no_post_types_found', 'No public post types found.', array( 'status' => 404 ) );
		}

		$response = new WP_REST_Response( $post_type_data, 200 );
		// Optional: Add caching headers if appropriate
		// $this->set_caching_headers( 3600, $response ); // Cache for 1 hour

		return $response;
	}

	/**
	 * Validate if a post type exists and is public.
	 *
	 * @param string $param The parameter value.
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_post_type_exists( $param ) {
		$post_type = get_post_type_object( $param );
		if ( ! $post_type || ! $post_type->public ) {
			return new WP_Error( 'invalid_post_type', 'Invalid or non-public post type specified.', array( 'status' => 404 ) );
		}
		return true;
	}

	/**
	 * Get details for a specific post.
	 *
	 * @param WP_REST_Request<array{post_type_name: string|null}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_post_type_details( $request ) {
		$post_type_name  = (string) $request->get_param( 'post_type_name' );
		$post_type = get_post_type_object( $post_type_name );

		// Validation should already be handled by args validation_callback, but double-check
		if ( ! $post_type || ! $post_type->public ) {
			return new WP_Error( 'invalid_post_type', 'Invalid or non-public post type specified.', array( 'status' => 404 ) );
		}

		$post_type_data = array(
			'name'      => $post_type->name,
			'label'    => $post_type->label,
			'singular_label' => $post_type->labels->singular_name,
			'slug' => $post_type->rewrite['slug'] ?? $post_type->name,
			'rest_base' => $post_type->rest_base ? $post_type->rest_base : $post_type->name,
			'publicly_queryable' => $post_type->publicly_queryable,
			'has_archive' => $post_type->has_archive,
			'rewrite' => $post_type->rewrite,
			'public' => $post_type->public,
			'hierarchical' => $post_type->hierarchical,
			'supports'  => get_all_post_type_supports( $post_type->name ),
			// Add more details as needed
		);

		$response = new WP_REST_Response( $post_type_data, 200 );
		// $this->set_caching_headers( 3600, $response ); // Optional caching
		return $response;
	}
}
