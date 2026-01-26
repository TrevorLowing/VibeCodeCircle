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
 * OptionPagesRoutes
 *
 * This class defines REST API endpoints for option pages fields.
 *
 * @package Etch\RestApi\Routes
 */
class OptionPagesRoutes extends BaseRoute {

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
				'route' => '/options',
				'methods' => 'GET',
				'callback' => array( $this, 'get_options' ),
			),
		);
	}

	/**
	 * Get taxonomy information by slug.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error The taxonomy information response or an error.
	 */
	public function get_options( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$options = $this->get_dynamic_option_pages_data();
		$response = new WP_REST_Response( $options, 200 );
		return $response;
	}
}
