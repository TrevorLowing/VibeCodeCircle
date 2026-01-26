<?php
/**
 * TaxonomiesService.php
 *
 * Service class for managing custom taxonomies stored in wp_options['etch_taxonomies'].
 *
 * @package Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

use WP_Error;
use WP_REST_Response;
use Etch\Helpers\Logger;

/**
 * TaxonomiesService class.
 *
 * @package Etch\Services
 */
class TaxonomiesService {


	/**
	 * The option key for the taxonomies.
	 *
	 * @var string
	 */
	private string $option_key = 'etch_taxonomies';

	/**
	 * Get all wordpress registered taxonomies.
	 *
	 * @return array<string, \WP_Taxonomy>
	 */
	public function get_all_taxonomies(): array {
		return get_taxonomies( array( 'public' => true ), 'objects' );
	}

	/**
	 * Get a single wordpress registered taxonomy.
	 *
	 * @param string $id The taxonomy ID.
	 * @return array<string, mixed>
	 */
	public function get_taxonomy( string $id ): array {
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		if ( ! isset( $taxonomies[ $id ] ) ) {
			return array();
		}

		$taxonomy = $taxonomies[ $id ];

		return array(
			'name' => $taxonomy->name,
			'label' => $taxonomy->label,
			'labels' => $taxonomy->labels,
			'description' => $taxonomy->description,
			'public' => $taxonomy->public,
			'publicly_queryable' => $taxonomy->publicly_queryable,
			'hierarchical' => $taxonomy->hierarchical,
			'show_ui' => $taxonomy->show_ui,
			'show_in_menu' => $taxonomy->show_in_menu,
			'show_in_nav_menus' => $taxonomy->show_in_nav_menus,
			'show_tagcloud' => $taxonomy->show_tagcloud,
			'show_in_quick_edit' => $taxonomy->show_in_quick_edit,
			'show_admin_column' => $taxonomy->show_admin_column,
			'meta_box_cb' => $taxonomy->meta_box_cb,
			'meta_box_sanitize_cb' => $taxonomy->meta_box_sanitize_cb,
			'object_type' => $taxonomy->object_type,
			'cap' => $taxonomy->cap,
			'rewrite' => $taxonomy->rewrite,
			'query_var' => $taxonomy->query_var,
			'update_count_callback' => $taxonomy->update_count_callback,
			'show_in_rest' => $taxonomy->show_in_rest,
			'rest_base' => $taxonomy->rest_base,
			'rest_namespace' => $taxonomy->rest_namespace,
			'rest_controller_class' => $taxonomy->rest_controller_class,
			'rest_controller' => $taxonomy->rest_controller,
			'default_term' => $taxonomy->default_term,
			'sort' => $taxonomy->sort,
			'args' => $taxonomy->args,
			'_builtin' => $taxonomy->_builtin,
		);
	}

	/**
	 * Get all etch registered taxonomies.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_all_etch_taxonomies(): array {
		$taxonomies = get_option( $this->option_key, array() );
		return is_array( $taxonomies ) ? $taxonomies : array();
	}

	/**
	 * Create a new etch taxonomy.
	 *
	 * @param string               $id The taxonomy ID.
	 * @param array<string, mixed> $args The taxonomy arguments.
	 * @param array<string>        $object_types The object types the taxonomy is attached to.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create( string $id, array $args, array $object_types ): WP_REST_Response|WP_Error {
		$taxonomies = $this->get_all_etch_taxonomies();

		if ( isset( $taxonomies[ $id ] ) ) {
			return new WP_Error( 'taxonomy_exists', 'Taxonomy already exists.', array( 'status' => 409 ) );
		}

		$taxonomies[ $id ] = array(
			'args' => $args,
			'object_types' => $object_types,
		);
		update_option( $this->option_key, $taxonomies );

		return new WP_REST_Response( array( 'message' => 'Taxonomy created' ), 201 );
	}

	/**
	 * Update a etch taxonomy.
	 *
	 * @param string               $id The taxonomy ID.
	 * @param array<string, mixed> $args The taxonomy arguments.
	 * @param array<string>        $object_types The object types the taxonomy is attached to.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update( string $id, array $args, array $object_types ): WP_REST_Response|WP_Error {
		$taxonomies = $this->get_all_etch_taxonomies();

		if ( ! isset( $taxonomies[ $id ] ) ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found.', array( 'status' => 404 ) );
		}

		$taxonomies[ $id ] = array(
			'args' => $args,
			'object_types' => $object_types,
		);

		update_option( $this->option_key, $taxonomies );

		return new WP_REST_Response( array( 'message' => 'Taxonomy updated' ), 200 );
	}

	/**
	 * Delete a etch taxonomy.
	 *
	 * @param string $id The taxonomy ID.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete( string $id ): WP_REST_Response|WP_Error {
		$taxonomies = $this->get_all_etch_taxonomies();

		if ( ! isset( $taxonomies[ $id ] ) ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found.', array( 'status' => 404 ) );
		}

		unset( $taxonomies[ $id ] );
		$success = update_option( $this->option_key, $taxonomies );

		if ( ! $success ) {
			return new WP_Error( 'taxonomy_delete_failed', 'Failed to delete taxonomy.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'message' => 'Taxonomy deleted' ), 200 );
	}

	/**
	 * Map the arguments to the WordPress arguments.
	 *
	 * @param string               $id The taxonomy ID.
	 * @param array<string, mixed> $args The taxonomy arguments.
	 * @return array<string, mixed>
	 * @phpstan-return array{
	 *     labels?: array<string>,
	 *     description?: string,
	 *     public?: bool,
	 *     hierarchical?: bool,
	 *     show_ui?: bool,
	 *     show_in_menu?: bool,
	 *     show_in_nav_menus?: bool,
	 *     show_in_rest?: bool,
	 *     show_in_quick_edit?: bool,
	 *     show_in_admin_column?: bool,
	 *     capabilities?: array<string>
	 * }
	 */
	public function map_args_to_wp_args( string $id, array $args ): array {

		// todo: is not the complete set of args, we need to add the rest of the args.
		return array(
			'labels' => $this->map_labels( isset( $args['labels'] ) && is_array( $args['labels'] ) ? $args['labels'] : array() ),
			'description' => is_string( $args['description'] ?? null ) ? $args['description'] : '',
			'public' => is_bool( $args['public'] ?? null ) ? $args['public'] : true,
			'hierarchical' => is_bool( $args['hierarchical'] ?? null ) ? $args['hierarchical'] : false,
			'show_ui' => is_bool( $args['showUi'] ?? null ) ? $args['showUi'] : true,
			'show_in_menu' => is_bool( $args['showInMenu'] ?? null ) ? $args['showInMenu'] : true,
			'show_in_nav_menus' => is_bool( $args['showInNavMenus'] ?? null ) ? $args['showInNavMenus'] : true,
			'show_in_rest' => is_bool( $args['showInRest'] ?? null ) ? $args['showInRest'] : true,
			'show_in_quick_edit' => is_bool( $args['showInQuickEdit'] ?? null ) ? $args['showInQuickEdit'] : false,
			'show_in_admin_column' => is_bool( $args['showInAdminColumn'] ?? null ) ? $args['showInAdminColumn'] : false,
			'capabilities' => $this->map_capabilities( is_array( $args['capabilities'] ?? null ) ? $args['capabilities'] : array() ),
		);
	}

	/**
	 * Map the labels to the WordPress labels.
	 *
	 * @param array<string, mixed> $labels The taxonomy labels.
	 * @return array<string>
	 */
	private function map_labels( array $labels ): array {
		$label_map = array(
			'pluralName' => array( 'name', 'menu_name' ),
			'menuName' => array( 'menu_name' ),
			'singularName' => array( 'singular_name' ),
			'allItems' => array( 'all_items' ),
			'editItem' => array( 'edit_item' ),
			'viewItem' => array( 'view_item' ),
			'updateItem' => array( 'update_item' ),
			'addNewItem' => array( 'add_new_item' ),
		);

		$mapped = array();
		foreach ( $label_map as $key => $values ) {
			if ( ! empty( $labels[ $key ] ) && is_scalar( $labels[ $key ] ) ) {
				foreach ( $values as $value ) {
					$mapped[ $value ] = (string) $labels[ $key ];
				}
			}
		}

		return $mapped;
	}

	/**
	 * Map the capabilities to the WordPress capabilities.
	 *
	 * @param array<string, mixed> $capabilities The taxonomy capabilities.
	 * @return array<string>
	 */
	private function map_capabilities( array $capabilities ): array {
		$capabilities_map = array(
			'manageTerms' => array( 'manage_terms' ),
			'editTerms' => array( 'edit_terms' ),
			'deleteTerms' => array( 'delete_terms' ),
			'assignTerms' => array( 'assign_terms' ),
		);

		$mapped = array();
		foreach ( $capabilities_map as $key => $values ) {
			if ( ! empty( $capabilities[ $key ] ) && is_scalar( $capabilities[ $key ] ) ) {
				foreach ( $values as $value ) {
					$mapped[ $value ] = (string) $capabilities[ $key ];
				}
			}
		}

		return $mapped;
	}

	/**
	 * Validate the input parameters for creating/updating a etch taxonomy.
	 *
	 * @param mixed $id The taxonomy ID.
	 * @param mixed $args The taxonomy arguments.
	 * @param mixed $object_types The object types array.
	 * @return WP_Error|null
	 */
	public function validate_taxonomy_input( mixed $id, mixed $args, mixed $object_types ): ?WP_Error {
		if ( ! is_string( $id ) || trim( $id ) === '' ) {
			return new WP_Error(
				'invalid_request',
				'Taxonomy ID must be a non-empty string.',
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $args ) || empty( $args ) ) {
			return new WP_Error(
				'invalid_request',
				'Taxonomy arguments must be a valid array.',
				array( 'status' => 400 )
			);
		}

		if ( ! is_array( $object_types ) || empty( $object_types ) ) {
			return new WP_Error(
				'invalid_request',
				'Object types must be a non-empty array.',
				array( 'status' => 400 )
			);
		}

		foreach ( $object_types as $object_type ) {
			if ( ! is_string( $object_type ) || trim( $object_type ) === '' || str_contains( $object_type, ',' ) ) {
				return new WP_Error(
					'invalid_object_type',
					'Each object type must be a non-empty string without commas.',
					array( 'status' => 400 )
				);
			}
		}

		return null;
	}
}
