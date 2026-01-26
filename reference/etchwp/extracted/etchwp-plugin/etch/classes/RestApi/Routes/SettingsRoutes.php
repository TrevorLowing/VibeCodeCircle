<?php
/**
 * SettingsRoutes.php
 *
 * This file contains the SettingsRoutes class which defines REST API routes for handling Etch settings.
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
 * SettingsRoutes
 *
 * This class defines REST API endpoints for retrieving and updating Etch settings.
 *
 * @package Etch\RestApi\Routes
 */
class SettingsRoutes extends BaseRoute {

	/**
	 * Returns the route definitions for settings endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/settings',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_settings' ),
			),
			array(
				'route'               => '/settings',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_settings' ),
			),
		);
	}

	/**
	 * Get Etch Settings.
	 *
	 * @return WP_REST_Response Response object with settings.
	 */
	public function get_settings() {
		$option_name = 'etch_settings';
		$settings = get_option( $option_name, array() );

		// Ensure default values
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		// Set default for custom_block_migration_completed if not present
		if ( ! isset( $settings['custom_block_migration_completed'] ) ) {
			$settings['custom_block_migration_completed'] = false;
		}

		return new WP_REST_Response( (object) $settings, 200 );
	}

	/**
	 * Update Etch Settings.
	 * This function updates the Etch settings in the database.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_settings( $request ) {

		$new_settings = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_settings ) ) {
			return new WP_Error( 'invalid_settings', 'Settings must be provided as an object', array( 'status' => 400 ) );
		}

		$option_name = 'etch_settings';

		$existing_settings = get_option( $option_name, array() );
		if ( ! is_array( $existing_settings ) ) {
			$existing_settings = array();
		}

		// Merge new settings with existing ones
		$merged_settings = array_merge( $existing_settings, $new_settings );

		$update_result = update_option( $option_name, $merged_settings );

		if ( $update_result ) {
			return new WP_REST_Response( array( 'message' => 'Settings updated successfully' ), 200 );
		} else {
			return new WP_Error( 'update_failed', 'Failed to update settings', array( 'status' => 500 ) );
		}
	}
}
