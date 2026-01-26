<?php
/**
 * StylesRoutes.php
 *
 * This file contains the StylesRoutes class which defines REST API routes for handling styles.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\WideEventLogger;
use Etch\Services\StylesheetService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
/**
 * StylesRoutes
 *
 * This class defines REST API endpoints for retrieving and updating styles.
 *
 * @package Etch\RestApi\Routes
 */
class StylesRoutes extends BaseRoute {

	/**
	 * Option name where styles are stored.
	 *
	 * @var string
	 */
	private $styles_option_name = 'etch_styles';


	/**
	 * Instance of the StylesheetService.
	 *
	 * @var StylesheetService
	 */
	private $stylesheet_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->stylesheet_service = StylesheetService::get_instance();
	}


	/**
	 * Returns the route definitions for styles endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/styles',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_styles' ),
			),
			array(
				'route'               => '/styles',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_styles' ),
			),
			// Stylesheet routes.
			array(
				'route'               => '/stylesheets',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_stylesheets' ),
			),
			array(
				'route'               => '/stylesheets',
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_stylesheet' ),
			),
			array(
				'route'               => '/stylesheets',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_stylesheets' ),
			),
			array(
				'route'               => '/stylesheets/(?P<id>[\w-]+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_stylesheet' ),
			),
			array(
				'route'               => '/stylesheets/(?P<id>[\w-]+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_stylesheet' ),
			),
		);
	}

	/**
	 * Update Etch styles.
	 * This function updates the Etch styles in the database.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_styles( $request ) {

		$new_styles = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_styles ) ) {
			WideEventLogger::failure( 'api.styles.validation', 'Styles must be provided as an array', array( 'error_code' => 'invalid_styles' ) );
			return new WP_Error( 'invalid_styles', 'Styles must be provided as an array', array( 'status' => 400 ) );
		}
		$existing_styles = get_option( $this->styles_option_name, array() );
		$update_result = true;
		if ( $new_styles !== $existing_styles ) {
			$update_result = update_option( $this->styles_option_name, $new_styles );
		}

		if ( $update_result ) {
			WideEventLogger::set( 'api.styles.update', 'success' );
			return new WP_REST_Response( array( 'message' => 'Styles updated successfully' ), 200 );
		} else {
			WideEventLogger::failure( 'api.styles.update', 'Failed to update styles', array( 'error_code' => 'update_failed' ) );
			return new WP_Error( 'update_failed', 'Failed to update styles', array( 'status' => 500 ) );
		}
	}

	/**
	 * Get Etch styles.
	 *
	 * @return WP_REST_Response Response object with styles.
	 */
	public function get_styles() {
		$styles = get_option( $this->styles_option_name, array() );

		// Ensure object is returned even if no styles are set (avoid returning an empty array).
		// TODO - maybe discuss this stuff with Matteo if there is a better way to handle this. And if there may be any issues when handling it this way.
		return new WP_REST_Response( (object) $styles, 200 );
	}


	// Global Stylesheet Routes

	/**
	 * Get all global style sheets.
	 *
	 * @return WP_REST_Response Response object with global style sheets.
	 */
	public function get_stylesheets() {
		$stylesheets = $this->stylesheet_service->get_stylesheets();
		return new WP_REST_Response( (object) $stylesheets, 200 );
	}

	/**
	 * Update multiple global style sheets.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_stylesheets( $request ) {
		$new_styles = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_styles ) ) {
			WideEventLogger::failure( 'api.stylesheets.validation', 'Styles must be provided as an array', array( 'error_code' => 'invalid_styles' ) );
			return new WP_Error( 'invalid_styles', 'Styles must be provided as an array', array( 'status' => 400 ) );
		}

		$this->stylesheet_service->update_stylesheets( $new_styles );
		WideEventLogger::set( 'api.stylesheets.update', 'success' );
		return new WP_REST_Response( array( 'message' => 'Stylesheets updated successfully' ), 200 );
	}

	/**
	 * Create a new global style sheet.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_stylesheet( $request ) {
		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) || ! is_string( $body['name'] ) || ! is_string( $body['css'] ) ) {
			WideEventLogger::failure( 'api.stylesheets.validation', 'Invalid data provided', array( 'error_code' => 'invalid_data' ) );
			return new WP_Error( 'invalid_data', 'Invalid data provided', array( 'status' => 400 ) );
		}

		$id = $this->stylesheet_service->create_stylesheet( $body );

		if ( is_wp_error( $id ) ) {
			WideEventLogger::failure( 'api.stylesheets.create', $id->get_error_message(), array( 'error_code' => $id->get_error_code() ) );
			return $id; // Return the WP_Error directly if creation failed.
		}

		WideEventLogger::set( 'api.stylesheets.create', 'success' );
		return new WP_REST_Response(
			array(
				'id' => $id,
				'message' => 'Stylesheet created successfully',
			),
			201
		);
	}

	/**
	 * Update a specific global style sheet by ID.
	 *
	 * @param WP_REST_Request<array{id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_stylesheet( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id || ! is_string( $id ) ) {
			WideEventLogger::failure( 'api.stylesheets.validation', 'No valid stylesheet id provided', array( 'error_code' => 'invalid_id' ) );
			return new WP_Error( 'invalid_id', 'No valid stylesheet id provided', array( 'status' => 400 ) );
		}

		$body = json_decode( $request->get_body(), true );

		if ( ! is_array( $body ) || ! is_string( $body['name'] ) || ! is_string( $body['css'] ) ) {
			WideEventLogger::failure(
				'api.stylesheets.validation',
				'Invalid data provided',
				array(
					'error_code' => 'invalid_data',
					'stylesheet_id' => $id,
				)
			);
			return new WP_Error( 'invalid_data', 'Invalid data provided', array( 'status' => 400 ) );
		}

		$result = $this->stylesheet_service->update_stylesheet( $id, $body );
		if ( is_wp_error( $result ) ) {
			WideEventLogger::failure(
				'api.stylesheets.update',
				$result->get_error_message(),
				array(
					'error_code' => $result->get_error_code(),
					'stylesheet_id' => $id,
				)
			);
			return $result; // Return the WP_Error directly if update failed.
		}

		WideEventLogger::set( 'api.stylesheets.update_single', 'success' );
		return new WP_REST_Response( array( 'message' => 'Stylesheet updated successfully' ), 200 );
	}

	/**
	 * Delete a specific global style sheet by ID.
	 *
	 * @param WP_REST_Request<array{id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_stylesheet( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id || ! is_string( $id ) ) {
			WideEventLogger::failure( 'api.stylesheets.validation', 'No valid stylesheet id provided', array( 'error_code' => 'invalid_id' ) );
			return new WP_Error( 'invalid_id', 'No valid stylesheet id provided', array( 'status' => 400 ) );
		}

		$result = $this->stylesheet_service->delete_stylesheet( $id );

		if ( is_wp_error( $result ) ) {
			WideEventLogger::failure(
				'api.stylesheets.delete',
				$result->get_error_message(),
				array(
					'error_code' => $result->get_error_code(),
					'stylesheet_id' => $id,
				)
			);
			return $result; // Return the WP_Error directly if deletion failed.
		}

		WideEventLogger::set( 'api.stylesheets.delete', 'success' );
		return new WP_REST_Response( array( 'message' => 'Stylesheet deleted successfully' ), 200 );
	}
}
