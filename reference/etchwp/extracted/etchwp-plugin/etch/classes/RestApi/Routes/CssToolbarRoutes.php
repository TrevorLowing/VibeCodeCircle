<?php
/**
 * CssToolbarRoutes.php
 *
 * This file contains the CssToolbarRoutes class which defines REST API routes for handling snippets.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Services\CssToolbarService;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * CssToolbarRoutes
 *
 * This class defines REST API endpoints for retrieving and updating snippets.
 *
 * @package Etch\RestApi\Routes
 */
class CssToolbarRoutes extends BaseRoute {

	/**
	 * Instance of the CssToolbarService.
	 *
	 * @var CssToolbarService
	 */
	private $css_toolbar_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->css_toolbar_service = CssToolbarService::get_instance();
	}

	/**
	 * Returns the route definitions for snippets endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/css-toolbar/definitions',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_snippets' ),
			),
			array(
				'route'               => '/css-toolbar/definitions/(?P<id>[\w-]+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_snippet' ),
			),
			array(
				'route'               => '/css-toolbar/definitions',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_snippets' ),
			),
			array(
				'route'               => '/css-toolbar/definitions/(?P<id>[\w-]+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_snippet' ),
			),
			array(
				'route'               => '/css-toolbar/definitions/(?P<id>[\w-]+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_snippet' ),
			),
		);
	}

	/**
	 * Get Etch snippets.
	 *
	 * @return WP_REST_Response Response object with snippets.
	 */
	public function get_snippets() {
		$snippets = $this->css_toolbar_service->get_snippets();

		return new WP_REST_Response( (object) $snippets, 200 );
	}

	/**
	 * Get specific Etch snippet.
	 *
	 * @param WP_REST_Request<array{id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_snippet( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id || ! is_string( $id ) ) {
			return new WP_Error( 'invalid_id', 'No valid snippet id provided', array( 'status' => 400 ) );
		}

		$snippet = $this->css_toolbar_service->get_snippet( $id );

		if ( is_wp_error( $snippet ) ) {
			return $snippet; // Return the WP_Error directly if creation failed.
		}

		return new WP_REST_Response( $snippet, 200 );
	}

	/**
	 * Update multiple global snippets.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_snippets( $request ) {
		$new_snippets = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_snippets ) ) {
			return new WP_Error( 'invalid_snippets', 'Snippets must be provided as an array', array( 'status' => 400 ) );
		}

		$this->css_toolbar_service->update_snippets( $new_snippets );
		return new WP_REST_Response( array( 'message' => 'Snippets updated successfully' ), 200 );
	}

	/**
	 * Update a specific snippet by ID.
	 *
	 * @param WP_REST_Request<array{id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_snippet( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id || ! is_string( $id ) ) {
			return new WP_Error( 'invalid_id', 'No valid snippet id provided', array( 'status' => 400 ) );
		}

		$body = json_decode( $request->get_body(), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data provided', array( 'status' => 400 ) );
		}

		$result = $this->css_toolbar_service->update_snippet( $id, $body );

		if ( is_wp_error( $result ) ) {
			return $result; // Return the WP_Error directly if update failed.
		}

		return new WP_REST_Response( array( 'message' => 'Snippet updated successfully' ), 200 );
	}

	/**
	 * Delete a specific snippet by ID.
	 *
	 * @param WP_REST_Request<array{id: string}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_snippet( $request ) {
		$id = $request->get_param( 'id' );

		if ( ! $id || ! is_string( $id ) ) {
			return new WP_Error( 'invalid_id', 'No valid snippet id provided', array( 'status' => 400 ) );
		}

		$result = $this->css_toolbar_service->delete_snippet( $id );

		if ( is_wp_error( $result ) ) {
			return $result; // Return the WP_Error directly if deletion failed.
		}

		return new WP_REST_Response( array( 'message' => 'Snippet deleted successfully' ), 200 );
	}
}
