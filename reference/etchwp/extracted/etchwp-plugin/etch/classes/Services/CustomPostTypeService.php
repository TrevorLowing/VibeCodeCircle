<?php
/**
 * CustomPostTypeService.php
 *
 * This file contains the CustomPostTypeService class which defines the service for handling custom post types.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use WP_Error;
use WP_REST_Response;

/**
 * CustomPostTypeService
 */
class CustomPostTypeService {

	/**
	 * The name of the option that stores the custom post types.
	 *
	 * @var string
	 */
	private string $cpt_option_name = 'etch_cpts';

	/**
	 * Get all custom post types.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_cpts(): array {
		$cpts = get_option( $this->cpt_option_name, array() );

		if ( ! is_array( $cpts ) ) {
			return array();
		}

		return $cpts;
	}

	/**
	 * Create a new custom post type.
	 *
	 * @param string               $id   The unique identifier for the custom post type.
	 * @param array<string, mixed> $args The arguments for the custom post type.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create( string $id, array $args ): WP_Rest_Response|WP_Error {
		$cpts_options = get_option( $this->cpt_option_name, array() );
		$cpts = is_array( $cpts_options ) ? $cpts_options : array();

		if ( isset( $cpts[ $id ] ) ) {
			return new WP_Error( 'cpt_exists', 'Custom post type already exists', array( 'status' => 409 ) );
		}

		$cpts[ $id ] = $args;
		update_option( $this->cpt_option_name, $cpts );
		$this->set_etch_transient();

		return new WP_REST_Response( array( 'message' => 'Custom post type created' ), 201 );
	}

	/**
	 * Update a custom post type.
	 *
	 * @param string               $id   The unique identifier for the custom post type.
	 * @param array<string, mixed> $args The arguments for the custom post type.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update( string $id, array $args ): WP_REST_Response|WP_Error {
		$cpts_options = get_option( $this->cpt_option_name, array() );
		$cpts = is_array( $cpts_options ) ? $cpts_options : array();

		if ( ! isset( $cpts[ $id ] ) ) {
			return new WP_Error( 'cpt_not_found', 'Custom post type not found', array( 'status' => 404 ) );
		}

		$cpts[ $id ] = $args;
		update_option( $this->cpt_option_name, $cpts );
		$this->set_etch_transient();

		return new WP_REST_Response( array( 'message' => 'Custom post type updated' ), 200 );
	}

	/**
	 * Delete a custom post type.
	 *
	 * @param string $id The unique identifier for the custom post type.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete( string $id ): WP_REST_Response|WP_Error {
		$cpts_options = get_option( $this->cpt_option_name, array() );
		$cpts = is_array( $cpts_options ) ? $cpts_options : array();

		if ( ! isset( $cpts[ $id ] ) ) {
			return new WP_Error( 'cpt_not_found', 'Custom post type not found', array( 'status' => 404 ) );
		}

		unset( $cpts[ $id ] );

		$success = update_option( $this->cpt_option_name, $cpts );
		$this->set_etch_transient();

		if ( ! $success ) {
			return new WP_Error( 'cpt_delete_failed', 'Failed to delete custom post type', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'message' => 'Custom post type deleted' ), 200 );
	}

	/**
	 * Set the etch transient.
	 * This is used for us to know that we need to flush rewrite rules ( permalinks ).
	 * This transient will expire in 60 ( when wordpress removes it ) or when we remove it in ContentTypeService maybe_flush_rewrite_rules().
	 *
	 * @return void
	 */
	public function set_etch_transient(): void {
		set_transient( 'etch_flush_rewrite_rules', true, 300 );
	}
}
