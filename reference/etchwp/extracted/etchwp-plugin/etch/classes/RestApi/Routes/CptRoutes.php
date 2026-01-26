<?php
/**
 * CptRoutes.php
 *
 * This file contains the CptRoutes class which defines REST API routes for handling Custom Post Types (CPTs).
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Services\CustomPostTypeService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * CptRoutes
 *
 * This class defines REST API endpoints for retrieving information about Custom Post Types.
 *
 * @package Etch\RestApi\Routes
 */
class CptRoutes extends BaseRoute {

	/**
	 * Custom post type service.
	 *
	 * @var CustomPostTypeService
	 */
	private CustomPostTypeService $cpt_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cpt_service = new CustomPostTypeService();
	}

	/**
	 * Returns the route definitions for custom post type endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/cms/post-type',
				'methods' => 'GET',
				'callback' => array( $this, 'get_all_cpts' ),
			),
			array(
				'route' => '/cms/post-type/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'GET',
				'callback' => array( $this, 'get_single_post_type' ),
			),
			array(
				'route' => '/cms/post-type',
				'methods' => 'POST',
				'callback' => array( $this, 'create_custom_post_type' ),
			),
			array(
				'route' => '/cms/post-type/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'PUT',
				'callback' => array( $this, 'update_custom_post_type' ),
			),
			array(
				'route' => '/cms/post-type/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'DELETE',
				'callback' => array( $this, 'delete_custom_post_type' ),
			),
		);
	}

	/**
	 * Get all custom post types.
	 *
	 * @return WP_REST_Response
	 */
	public function get_all_cpts(): WP_REST_Response {
		$cpts = $this->cpt_service->get_all_cpts();
		return new WP_REST_Response( (object) $cpts, 200 );
	}

	/**
	 * Get a single custom post type.
	 *
	 * @param WP_REST_Request $request  Request with JSON body containing `id`.
	 * @phpstan-param WP_REST_Request<array{id: string}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single_post_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request->get_param( 'id' );
		$cpts = $this->cpt_service->get_all_cpts();

		if ( ! isset( $cpts[ $id ] ) ) {
			return new WP_Error( 'cpt_not_found', 'Custom post type not found', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( (object) $cpts[ $id ], 200 );
	}

	/**
	 * Create a custom post type.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string, args: array<string, mixed>}> $request Request with JSON body containing `id` and `args`
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_custom_post_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];
		$args = (array) $request['args'];

		if ( empty( $id ) || empty( $args ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cpt_service->create( $id, $args );
	}

	/**
	 * Update a custom post type.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string, args: array<string, mixed>}> $request Request with JSON body containing `id` and `args`
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_custom_post_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];
		$args = (array) $request['args'];

		if ( empty( $id ) || empty( $args ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cpt_service->update( $id, $args );
	}

	/**
	 * Delete a custom post type.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_custom_post_type( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];

		if ( empty( $id ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cpt_service->delete( $id );
	}
}
