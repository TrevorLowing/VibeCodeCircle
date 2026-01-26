<?php
/**
 * BaseRoute.php
 *
 * This file contains the BaseRoute abstract class, which provides methods for REST API routes
 * registration, permission checks, and caching headers.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

/**
 * BaseRoute
 *
 * The BaseRoute abstract class outlines the methods and properties required for any REST API route.
 * Child classes should implement the register_routes() method.
 *
 * @package Etch\RestApi\Routes
 */
abstract class BaseRoute {

	/**
	 * API namespace used for the routes.
	 *
	 * @var string
	 */
	protected $api_namespace = 'etch-api';

	/**
	 * Returns an array of route definitions.
	 *
	 * Each route definition is an associative array that should include:
	 * - route: string endpoint (e.g., '/blocks/(?P<post_id>\d+)')
	 * - methods: HTTP method(s) (e.g., 'GET', 'POST', etc.)
	 * - callback: callback function for the route
	 * - permission_callback: (optional) callback function for permission check
	 * - args: (optional) additional arguments for the route.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	abstract protected function get_routes(): array;

	/**
	 * Register the REST API routes.
	 *
	 * This method automatically registers all routes defined in get_routes().
	 *
	 * @return void
	 */
	public function register_routes() {
		add_action(
			'rest_api_init',
			function () {
				foreach ( $this->get_routes() as $route ) {
					$route_slug = $route['route'];
					$args       = $route;

					// If not explicitly set, add a default permission callback
					if ( ! isset( $args['permission_callback'] ) ) {
						$args['permission_callback'] = fn() => $this->has_etch_api_access();
					}

					unset( $args['route'] );
					register_rest_route( $this->api_namespace, $route_slug, $args );
				}
			}
		);
	}

	/**
	 * Check if the current request has access to the Etch API.
	 *
	 * @return bool True if access is granted, false otherwise.
	 */
	public function has_etch_api_access(): bool {
		return current_user_can( 'edit_posts' ) && current_user_can( 'manage_options' );
	}

	/**
	 * Sets caching headers on the REST response.
	 *
	 * @param int               $cache_time Time in seconds for max-age.
	 * @param \WP_REST_Response $response Response object to set headers on.
	 * @return void
	 */
	protected function set_caching_headers( int $cache_time, \WP_REST_Response $response ): void {
		$e_tag = '"' . hash( 'sha256', (string) json_encode( $response->get_data() ) ) . '"';

		// Add Cache-Control and ETag headers
		$response->header( 'Cache-Control', 'public, max-age=' . $cache_time );
		$response->header( 'ETag', $e_tag );

		// Handle conditional requests
		if ( ! empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) && $_SERVER['HTTP_IF_NONE_MATCH'] === $e_tag ) {
			$response->set_status( 304 ); // Not Modified
			$response->set_data( null );  // Remove body for 304 responses
		}
	}
}
