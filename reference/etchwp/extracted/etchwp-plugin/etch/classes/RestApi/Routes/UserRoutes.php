<?php
/**
 * User Routes for the REST API.
 *
 * @package Etch
 * @gplv2
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Traits\DynamicDataBases;
use WP_REST_Response;
use WP_Error;
use WP_REST_Request;

/**
 * UserRoutes
 *
 * This class defines REST API endpoints for retrieving user information.
 *
 * @package Etch\RestApi\Routes
 */
class UserRoutes extends BaseRoute {

	use DynamicDataBases;

	/**
	 * Get the routes for the user.
	 *
	 * @return array<array{
	 *     route: string,
	 *     methods: string,
	 *     callback: callable,
	 * }> The routes for the user.
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/user',
				'methods' => 'GET',
				'callback' => array( $this, 'get_user' ),
			),
		);
	}

	/**
	 * Get the current user information.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error The user information response or an error.
	 */
	public function get_user( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$current_user = wp_get_current_user();

		$user_info = $this->get_base_user_data( $current_user );

		$response = new WP_REST_Response( $user_info, 200 );
		return $response;
	}
}
