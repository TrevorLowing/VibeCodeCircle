<?php
/**
 * TaxonomiesRoutes.php
 *
 * REST API Routes for custom taxonomies stored in wp_options['etch_taxonomies'].
 *
 * @package Etch\RestApi\Routes
 */

declare(strict_types=1);

namespace Etch\RestApi\Routes;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Etch\Services\TaxonomiesService;

/**
 * TaxonomiesRoutes
 *
 * This class defines REST API endpoints for retrieving information about custom taxonomies.
 *
 * @package Etch\RestApi\Routes
 */
class TaxonomiesRoutes extends BaseRoute {

	/**
	 * Taxonomies service.
	 *
	 * @var TaxonomiesService
	 */
	private TaxonomiesService $taxonomies_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->taxonomies_service = new TaxonomiesService();
	}

	/**
	 * Returns the route definitions for custom taxonomies endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/taxonomies',
				'methods' => 'GET',
				'callback' => array( $this, 'get_all_taxonomies' ),
			),
			array(
				'route' => '/taxonomies/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'GET',
				'callback' => array( $this, 'get_taxonomy' ),
			),
			array(
				'route' => '/cms/taxonomy',
				'methods' => 'GET',
				'callback' => array( $this, 'get_etch_taxonomies' ),
			),
			array(
				'route' => '/cms/taxonomy/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'GET',
				'callback' => array( $this, 'get_etch_taxonomy' ),
			),
			array(
				'route' => '/cms/taxonomy',
				'methods' => 'POST',
				'callback' => array( $this, 'create_taxonomy' ),
			),
			array(
				'route' => '/cms/taxonomy/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'PUT',
				'callback' => array( $this, 'update_taxonomy' ),
			),
			array(
				'route' => '/cms/taxonomy/(?P<id>[a-zA-Z0-9_-]+)',
				'methods' => 'DELETE',
				'callback' => array( $this, 'delete_taxonomy' ),
			),
		);
	}

	/**
	 * Returns all public registered taxonomies in wordpress.
	 *
	 * @return WP_REST_Response
	 */
	public function get_all_taxonomies(): WP_REST_Response {
		$taxonomies = $this->taxonomies_service->get_all_taxonomies();
		return new WP_REST_Response( $taxonomies, 200 );
	}

	/**
	 * Returns all custom taxonomies.
	 *
	 * @return WP_REST_Response
	 */
	public function get_etch_taxonomies(): WP_REST_Response {
		$taxonomies = $this->taxonomies_service->get_all_etch_taxonomies();
		return new WP_REST_Response( $taxonomies, 200 );
	}

	/**
	 * Returns a single taxonomy.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_taxonomy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request->get_param( 'id' );
		$taxonomies = $this->taxonomies_service->get_taxonomy( $id );
		return new WP_REST_Response( $taxonomies, 200 );
	}

	/**
	 * Returns a single custom taxonomy.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_etch_taxonomy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request->get_param( 'id' );
		$taxonomies = $this->taxonomies_service->get_all_etch_taxonomies();

		if ( ! isset( $taxonomies[ $id ] ) ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found.', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( (object) $taxonomies[ $id ], 200 );
	}

	/**
	 * Creates a new custom taxonomy.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string, args: array<string, mixed>, objectTypes: string[]}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_taxonomy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];
		$args = (array) $request['args'];
		$object_types = (array) $request['objectTypes'];

		$error = $this->taxonomies_service->validate_taxonomy_input( $id, $args, $object_types );
		if ( $error ) {
			return $error;
		}

		return $this->taxonomies_service->create( $id, $args, $object_types );
	}

	/**
	 * Updates an existing custom taxonomy.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string, args: array<string, mixed>, objectTypes: string[]}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_taxonomy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];
		$args = (array) $request['args'];
		$object_types = (array) $request['objectTypes'];

		$error = $this->taxonomies_service->validate_taxonomy_input( $id, $args, $object_types );
		if ( $error ) {
			return $error;
		}

		return $this->taxonomies_service->update( $id, $args, $object_types );
	}

	/**
	 * Deletes an existing custom taxonomy.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @phpstan-param WP_REST_Request<array{id: string}> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_taxonomy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = (string) $request['id'];

		if ( empty( $id ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request.', array( 'status' => 400 ) );
		}

		return $this->taxonomies_service->delete( $id );
	}
}
