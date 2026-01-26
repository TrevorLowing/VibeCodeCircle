<?php
/**
 * UiRoutes.php
 *
 * This file contains the UiRoutes class which defines REST API routes for handling UI state.
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
 * UiRoutes
 *
 * This class defines REST API endpoints for retrieving and updating UI state.
 * UI state is stored per-user in user meta, with lazy migration from the legacy
 * global wp_option for backwards compatibility.
 *
 * @package Etch\RestApi\Routes
 */
class UiRoutes extends BaseRoute {

	/**
	 * User meta key for storing UI state (underscore prefix hides from WP admin).
	 *
	 * @var string
	 */
	private const USER_META_KEY = '_etch_ui_state';

	/**
	 * Legacy option key for backwards compatibility during migration.
	 *
	 * @var string
	 */
	private const LEGACY_OPTION_KEY = 'etch_ui_state';

	/**
	 * Returns the route definitions for UI state endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/ui/state',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_ui_state' ),
			),
			array(
				'route'               => '/ui/state',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_ui_state' ),
			),
		);
	}

	/**
	 * Get Etch UI State for the current user.
	 *
	 * Retrieves UI state from user meta, with lazy migration from the legacy
	 * global wp_option for backwards compatibility.
	 *
	 * @return WP_REST_Response Response object with ui state.
	 */
	public function get_ui_state() {
		$user_id = get_current_user_id();

		// Try user-specific meta first
		$ui_state = get_user_meta( $user_id, self::USER_META_KEY, true );

		// Lazy migration: if no user meta, check legacy global option
		if ( empty( $ui_state ) ) {
			$legacy_state = get_option( self::LEGACY_OPTION_KEY, array() );

			if ( ! empty( $legacy_state ) && is_array( $legacy_state ) ) {
				// Migrate to user meta on first access
				update_user_meta( $user_id, self::USER_META_KEY, $legacy_state );
				$ui_state = $legacy_state;
			}
		}

		return new WP_REST_Response( (object) ( ! empty( $ui_state ) ? $ui_state : array() ), 200 );
	}

	/**
	 * Update Etch UI state for the current user.
	 *
	 * Saves UI state to user meta (per-user storage).
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_ui_state( $request ) {
		$user_id = get_current_user_id();

		$new_state = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_state ) ) {
			return new WP_Error( 'invalid_ui_state', 'UI State must be provided as an object', array( 'status' => 400 ) );
		}

		$existing_state = get_user_meta( $user_id, self::USER_META_KEY, true );

		if ( $new_state === $existing_state ) {
			return new WP_REST_Response( array( 'message' => 'UI State updated successfully' ), 200 );
		}

		// Save to user meta
		$update_result = update_user_meta( $user_id, self::USER_META_KEY, $new_state );

		if ( false !== $update_result ) {
			return new WP_REST_Response( array( 'message' => 'UI State updated successfully' ), 200 );
		} else {
			return new WP_Error( 'update_failed', 'Failed to update UI State', array( 'status' => 500 ) );
		}
	}
}
