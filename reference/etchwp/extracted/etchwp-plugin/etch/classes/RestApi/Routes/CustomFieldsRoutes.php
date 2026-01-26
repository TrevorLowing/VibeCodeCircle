<?php
/**
 * CustomFieldsRoutes.php
 *
 * This file contains the CustomFieldsRoutes class which defines REST API routes for handling Custom Fields.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\CustomFields\CustomFieldService;
use Etch\CustomFields\CustomFieldTypes;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * CustomFieldsRoutes
 *
 * This class defines REST API endpoints for retrieving information about Custom Fields and their groups.
 *
 * @package Etch\RestApi\Routes
 */
class CustomFieldsRoutes extends BaseRoute {

	/**
	 * Custom field service.
	 *
	 * @var CustomFieldService
	 */
	private CustomFieldService $cf_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->cf_service = CustomFieldService::get_instance();
	}

	/**
	 * Returns the route definitions for custom field endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/cms/field-group/',
				'methods' => 'GET',
				'callback' => array( $this, 'get_all_custom_field_groups' ),
			),
			array(
				'route' => '/cms/field-group/(?P<group_id>[a-zA-Z0-9_-]+)',
				'methods' => 'GET',
				'callback' => array( $this, 'get_specific_custom_field_group' ),
			),
			array(
				'route' => '/cms/field-group/',
				'methods' => 'POST',
				'callback' => array( $this, 'create_custom_field_group' ),
			),
			array(
				'route' => '/cms/field-group/(?P<group_id>[a-zA-Z0-9_-]+)',
				'methods' => 'PUT',
				'callback' => array( $this, 'update_custom_field_group' ),
			),
			array(
				'route' => '/cms/field-group/(?P<group_id>[a-zA-Z0-9_-]+)',
				'methods' => 'DELETE',
				'callback' => array( $this, 'delete_custom_field_group' ),
			),
			array(
				'route' => '/cms/field-group/(?P<group_id>[a-zA-Z0-9_-]+)/field',
				'methods' => 'POST',
				'callback' => array( $this, 'add_custom_field_to_group' ),
			),
			array(
				'route' => '/cms/field-group/(?P<group_id>[a-zA-Z0-9_-]+)/field/(?P<field_key>[a-zA-Z0-9_-]+)',
				'methods' => 'PUT',
				'callback' => array( $this, 'update_custom_field_in_group' ),
			),
			array(
				'route' => '/cms/field-group/(?P<group_id>[a-zA-Z0-9_-]+)/field/(?P<field_key>[a-zA-Z0-9_-]+)',
				'methods' => 'DELETE',
				'callback' => array( $this, 'delete_custom_field_in_group' ),
			),
		);
	}

	/**
	 * Get all custom field groups.
	 *
	 * @return WP_REST_Response
	 */
	public function get_all_custom_field_groups(): WP_REST_Response {
		$custom_fields = $this->cf_service->get_all_custom_field_groups();
		return new WP_REST_Response( (object) $custom_fields, 200 );
	}

	/**
	 * Get a specific custom field group.
	 *
	 * @param WP_REST_Request<array{group_id: string}> $request  Request with url containing `id`.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_specific_custom_field_group( $request ): WP_REST_Response|WP_Error {
		$id = (string) $request->get_param( 'group_id' );
		$custom_fields = $this->cf_service->get_all_custom_field_groups();

		if ( ! isset( $custom_fields[ $id ] ) ) {
			return new WP_Error( 'custom_field_group_not_found', 'Custom field group not found', array( 'status' => 404 ) );
		}

		return new WP_REST_Response( (object) $custom_fields[ $id ], 200 );
	}

	/**
	 * Create a custom field group.
	 *
	 * @param WP_REST_Request<array{}> $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_custom_field_group( $request ): WP_REST_Response|WP_Error {
		$definition = json_decode( $request->get_body(), true );

		if ( ! ( is_array( $definition ) && ! empty( $definition ) && isset( $definition['label'], $definition['assigned_to'] ) ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		if ( ! isset( $definition['fields'] ) || ! is_array( $definition['fields'] ) ) {
			$definition['fields'] = array();
		}

		return $this->cf_service->create_group( $definition );
	}

	/**
	 * Update a custom field group.
	 *
	 * @param WP_REST_Request<array{group_id: string}> $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_custom_field_group( $request ): WP_REST_Response|WP_Error {
		$id = (string) $request->get_param( 'group_id' );
		$definition = json_decode( $request->get_body(), true );

		if ( ! ( is_array( $definition ) && ! empty( $definition ) && isset( $definition['label'], $definition['assigned_to'] ) ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		if ( ! isset( $definition['fields'] ) || ! is_array( $definition['fields'] ) ) {
			$definition['fields'] = array();
		}

		return $this->cf_service->update_group( $id, $definition );
	}

	/**
	 * Delete a custom field group.
	 *
	 * @param WP_REST_Request<array{group_id: string}> $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_custom_field_group( $request ): WP_REST_Response|WP_Error {
		$id = (string) $request->get_param( 'group_id' );

		if ( empty( $id ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cf_service->delete_group( $id );
	}

	/**
	 * Add a custom field to a group.
	 *
	 * @param WP_REST_Request<array{group_id: string}> $request The request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function add_custom_field_to_group( $request ): WP_REST_Response|WP_Error {
		$group_id = (string) $request->get_param( 'group_id' );
		$definition = json_decode( $request->get_body(), true );

		if ( ! ( is_array( $definition ) && ! empty( $definition ) && isset( $definition['key'], $definition['label'], $definition['type'] ) ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cf_service->add_field_to_group( $group_id, $definition );
	}

	 /**
	  * Update a custom field in a group.
	  *
	  * @param WP_REST_Request<array{group_id: string, field_key: string}> $request The request object.
	  * @return WP_REST_Response|WP_Error
	  */
	public function update_custom_field_in_group( $request ): WP_REST_Response|WP_Error {
		$group_id = (string) $request->get_param( 'group_id' );
		$field_key = (string) $request->get_param( 'field_key' );
		$definition = json_decode( $request->get_body(), true );

		if ( ! ( is_array( $definition ) && ! empty( $definition ) && isset( $definition['key'], $definition['label'], $definition['type'] ) ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cf_service->update_field_in_group( $group_id, $field_key, $definition );
	}


	 /**
	  * Delete a custom field in a group.
	  *
	  * @param WP_REST_Request<array{group_id: string, field_key: string}> $request The request object.
	  * @return WP_REST_Response|WP_Error
	  */
	public function delete_custom_field_in_group( $request ): WP_REST_Response|WP_Error {
		$group_id = (string) $request->get_param( 'group_id' );
		 $field_key = (string) $request->get_param( 'field_key' );

		if ( empty( $group_id ) || empty( $field_key ) ) {
			return new WP_Error( 'invalid_request', 'Invalid request', array( 'status' => 400 ) );
		}

		return $this->cf_service->remove_field_from_group( $group_id, $field_key );
	}
}
