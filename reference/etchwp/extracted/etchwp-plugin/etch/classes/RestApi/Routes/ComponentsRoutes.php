<?php
/**
 * ComponentsRoutes.php
 *
 * This file contains the ComponentsRoutes class which defines REST API routes for handling components.

 * @package Etch
 * @gplv2
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\Logger;
use Etch\Helpers\WideEventLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Etch\Services\ComponentsService;

/**
 * ComponentsRoutes
 *
 * This class defines REST API endpoints for retrieving and updating components.
 *
 * @package Etch\RestApi\Routes
 */
class ComponentsRoutes extends BaseRoute {

	/**
	 * Components service instance.
	 *
	 * @var ComponentsService
	 */
	private ComponentsService $components_service;

	/**
	 * Constructor to initialize the components service.
	 */
	public function __construct() {
		$this->components_service = new ComponentsService();
	}

	/**
	 * Returns the route definitions for components endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/components',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_components' ),
			),
			array(
				'route'               => '/components',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_components' ),
			),
			array(
				'route'               => '/components/(?P<component_id>[a-zA-Z0-9_-]+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_specific_component' ),
			),
			array(
				'route'               => '/components/(?P<component_id>[a-zA-Z0-9_-]+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_specific_component' ),
			),
			array(
				'route'               => '/components/delete-all',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_all_components' ),
			),
		);
	}

	/**
	 * Update Etch components.
	 * This function updates the Etch components in the database.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_components( $request ) {

		$new_components = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_components ) ) {
			WideEventLogger::failure( 'api.components.validation', 'Components must be provided as an array', array( 'error_code' => 'invalid_components' ) );
			return new WP_Error( 'invalid_components', 'Components must be provided as an array', array( 'status' => 400 ) );
		}

		$option_name = 'etch_components';

		$existing_components = get_option( $option_name, array() );
		$update_result = true;
		if ( $new_components !== $existing_components ) {
			$update_result = update_option( $option_name, $new_components );
		}

		if ( $update_result ) {
			WideEventLogger::set( 'api.components.update', 'success' );
			return new WP_REST_Response( array( 'message' => 'Components updated successfully' ), 200 );
		} else {
			WideEventLogger::failure( 'api.components.update', 'Failed to update components', array( 'error_code' => 'update_failed' ) );
			return new WP_Error( 'update_failed', 'Failed to update components', array( 'status' => 500 ) );
		}
	}

	/**
	 * Get Etch components.
	 *
	 * @return WP_REST_Response Response object with components.
	 */
	public function get_components() {
		$option_name = 'etch_components';
		$components = get_option( $option_name, array() );

		// Ensure object is returned even if no components are set (avoid returning an empty array).
		// TODO - maybe discuss this stuff with Matteo if there is a better way to handle this. And if there may be any issues when handling it this way.
		return new WP_REST_Response( (object) $components, 200 );
	}
	/**
	 * Update Specific Etch component.
	 * This function updates a specific Etch component in the database.
	 *
	 * @param WP_REST_Request<array{component_id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_specific_component( $request ) {

		$component_id = (string) $request->get_param( 'component_id' );

		if ( ! is_string( $component_id ) || empty( $component_id ) ) {
			WideEventLogger::failure( 'api.components.validation', 'No valid component id provided', array( 'error_code' => 'no_component_id' ) );
			return new WP_Error( 'no_component_id', 'No valid component id provided', array( 'status' => 412 ) );
		}

		$option_name = 'etch_components';
		$existing_components = get_option( $option_name, array() );

		$component_def = json_decode( $request->get_body(), true );
		if ( ! is_array( $component_def ) ) {
			WideEventLogger::failure(
				'api.components.validation',
				'Component definition must be provided as an array',
				array(
					'error_code' => 'invalid_component',
					'component_id' => $component_id,
				)
			);
			return new WP_Error( 'invalid_component', 'Component definition must be provided as an array', array( 'status' => 400 ) );
		}

		$new_components = $existing_components;
		if ( is_array( $new_components ) ) {
			$new_components[ $component_id ] = $component_def;
		}

		$update_result = true;
		if ( $new_components !== $existing_components ) {
			$update_result = update_option( $option_name, $new_components );
		}

		if ( $update_result ) {
			WideEventLogger::set( 'api.components.update', 'success' );
			return new WP_REST_Response( array( 'message' => 'Components updated successfully' ), 200 );
		} else {
			WideEventLogger::failure(
				'api.components.update',
				'Failed to update component',
				array(
					'error_code' => 'update_failed',
					'component_id' => $component_id,
				)
			);
			return new WP_Error( 'update_failed', 'Failed to update components', array( 'status' => 500 ) );
		}
	}


	/**
	 * Delete Specific Etch component.
	 * This function deletes a specific Etch component in the database.
	 *
	 * @param WP_REST_Request<array{component_id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_specific_component( $request ) {
		$component_id = (string) $request->get_param( 'component_id' );

		if ( ! is_string( $component_id ) || empty( $component_id ) ) {
			WideEventLogger::failure( 'api.components.validation', 'No valid component id provided', array( 'error_code' => 'no_component_id' ) );
			return new WP_Error( 'no_component_id', 'No valid component id provided', array( 'status' => 412 ) );
		}

		$option_name = 'etch_components';
		$existing_components = get_option( $option_name, array() );

		$new_components = $existing_components;
		if ( is_array( $new_components ) ) {
			unset( $new_components[ $component_id ] );
		}

		$update_result = true;
		if ( $new_components !== $existing_components ) {
			$update_result = update_option( $option_name, $new_components );
		}

		if ( $update_result ) {
			WideEventLogger::set( 'api.components.delete', 'success' );
			return new WP_REST_Response( array( 'message' => 'Components updated successfully' ), 200 );
		} else {
			WideEventLogger::failure(
				'api.components.delete',
				'Failed to delete component',
				array(
					'error_code' => 'update_failed',
					'component_id' => $component_id,
				)
			);
			return new WP_Error( 'update_failed', 'Failed to update components', array( 'status' => 500 ) );
		}
	}

	/**
	 * Delete all components.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_all_components() {
		$result = $this->components_service->delete_all_components();

		$status_code = match ( $result['status'] ) {
			'success' => 200,
			'warning' => 200,
			'error' => 500,
			default => 500,
		};

		return new WP_REST_Response( $result, $status_code );
	}
}
