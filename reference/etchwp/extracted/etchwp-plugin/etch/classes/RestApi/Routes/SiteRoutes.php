<?php
/**
 * Site Routes for the REST API.
 *
 * @package Etch
 * @gplv2
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;
use Etch\Traits\DynamicDataBases;

/**
 * SiteRoutes
 *
 * This class defines REST API endpoints for retrieving site information.
 *
 * @package Etch\RestApi\Routes
 */
class SiteRoutes extends BaseRoute {

	use DynamicDataBases;

	/**
	 * Get the routes for the site.
	 *
	 * @return array<array{
	 *     route: string,
	 *     methods: string,
	 *     callback: callable,
	 * }> The routes for the site.
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/site',
				'methods' => 'GET',
				'callback' => array( $this, 'get_site' ),
			),
		);
	}

	/**
	 * Get the site information.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error The site information response or an error.
	 */
	public function get_site( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$site_info = $this->get_dynamic_site_data();

		// Add additional API-specific date settings
		$site_info['dateSettings'] = array(
			'timezone'   => wp_timezone_string(),
			// 'dateFormat' => get_option( 'date_format' ),
			// 'timeFormat' => get_option( 'time_format' ),
		);

		$response = new WP_REST_Response( $site_info, 200 );
		return $response;
	}
}
