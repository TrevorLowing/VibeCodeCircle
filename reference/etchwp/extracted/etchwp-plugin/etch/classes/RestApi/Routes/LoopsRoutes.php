<?php
/**
 * LoopsRoutes.php
 *
 * This file contains the LoopsRoutes class which defines REST API routes for handling loops.
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

/**
 * LoopsRoutes
 *
 * This class defines REST API endpoints for retrieving and updating loop options.
 *
 * @package Etch\RestApi\Routes
 */
class LoopsRoutes extends BaseRoute {

	/**
	 * Returns the route definitions for loops endpoints.
	 *
	 * @return array<array{
	 *     route: string,
	 *     methods: string|array<string>,
	 *     callback: callable,
	 *     permission_callback?: callable,
	 *     args?: array<string, array{required?: bool, type?: string}>
	 * }>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/loops',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_loops' ),
			),
			array(
				'route'               => '/loops',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_loops' ),
			),
		);
	}

	/**
	 * Update Etch loops.
	 * This function updates the Etch loops in the database.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_loops( $request ): WP_REST_Response|WP_Error {
		$new_loops = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_loops ) ) {
			return new WP_Error( 'invalid_loops', 'Loops must be provided as an array', array( 'status' => 400 ) );
		}

		$option_name = 'etch_loops';

		$existing_loops = get_option( $option_name, array() );
		$update_result = true;
		if ( $new_loops !== $existing_loops ) {
			$update_result = update_option( $option_name, $new_loops );
		}

		if ( $update_result ) {
			return new WP_REST_Response( array( 'message' => 'Loops updated successfully' ), 200 );
		} else {
			return new WP_Error( 'update_failed', 'Failed to update loops', array( 'status' => 500 ) );
		}
	}

	/**
	 * Get Etch loops.
	 *
	 * @return WP_REST_Response Response object with loops.
	 */
	public function get_loops(): WP_REST_Response {
		$option_name = 'etch_loops';
		$loops = get_option( $option_name, array() );

		// Ensure object is returned even if no loops are set (avoid returning an empty array).
		return new WP_REST_Response( (object) $loops, 200 );
	}
}
