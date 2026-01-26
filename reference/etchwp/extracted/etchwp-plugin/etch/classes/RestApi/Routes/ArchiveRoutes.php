<?php
/**
 * Option Pages Routes for the REST API.
 *
 * @package Etch
 * @gplv2
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;
use Etch\Traits\DynamicData;

/**
 * ArchiveRoutes
 *
 * This class defines REST API endpoints for archive data.
 *
 * @package Etch\RestApi\Routes
 */
class ArchiveRoutes extends BaseRoute {

	use DynamicData;

	/**
	 * Get the routes for taxonomies.
	 *
	 * @return array<array{
	 *     route: string,
	 *     methods: string,
	 *     callback: callable,
	 * }> The routes for taxonomies.
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/archive',
				'methods' => 'GET',
				'callback' => array( $this, 'get_archive_data' ),
			),
		);
	}

	/**
	 * Get archive dynamic data.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error The archive data or an error.
	 */
	public function get_archive_data( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$archive_data = $this->get_dynamic_archive_data();
		$response = new WP_REST_Response( $archive_data, 200 );
		return $response;
	}
}
