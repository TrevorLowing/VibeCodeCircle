<?php
/**
 * CustomFieldService.php
 *
 * This file contains the CustomFieldService class which defines the service for handling custom fields.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\CustomFields
 */

namespace Etch\CustomFields;

use Etch\CustomFields\Fields\BaseField;
use Etch\Helpers\Logger;
use WP_Error;
use WP_REST_Response;
use Etch\Traits\Singleton;

/**
 * CustomFieldService
 *
 * This class is responsible for registering custom fields.
 *
 * @phpstan-import-type CustomFieldType from CustomFieldTypes
 * @phpstan-import-type CustomField from CustomFieldTypes
 * @phpstan-import-type CustomFieldGroup from CustomFieldTypes
 * @phpstan-import-type CustomFieldAssignment from CustomFieldTypes
 *
 * @package Etch\CustomFields
 */
class CustomFieldService {

	use Singleton;

	/**
	 * The name of the option that stores the custom fields.
	 *
	 * @var string
	 */
	private string $option_name = 'etch_cfs';


	/**
	 * Initialize the Content Type Service.
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_custom_fields' ) );
		add_action( 'save_post', array( $this, 'save_custom_fields' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_custom_field_styles' ) );
	}

	/**
	 * Enqueue admin CSS for custom fields
	 *
	 * @return void
	 */
	public function enqueue_custom_field_styles() {
		$file_path = ETCH_PLUGIN_URL . 'assets/css/etch-custom-fields.css';
		$version = file_exists( $file_path ) ? (string) filemtime( $file_path ) : null;

		// Enqueue custom CSS
		wp_enqueue_style(
			'etch-custom-fields',
			$file_path,
			array(),
			$version
		);
	}

	/**
	 * Registers custom fields from wp_options as meta boxes
	 *
	 * @return void
	 */
	public function register_custom_fields() {
		global $post;

		$cf_groups = get_option( $this->option_name, array() );

		if ( ! is_array( $cf_groups ) ) {
			return;
		}

		foreach ( $cf_groups as $group_id => $group_definition ) {
			// If the assignment does not match the current post type, skip this group
			if ( ! isset( $group_definition['assigned_to'] ) || ! is_array( $group_definition['assigned_to'] ) ) {
				continue;
			}

			$assigned_to = $group_definition['assigned_to'];

			if ( isset( $assigned_to['post_types'] ) && is_array( $assigned_to['post_types'] ) ) {
				if ( ! in_array( $post->post_type, $assigned_to['post_types'], true ) ) {
					continue; // Skip if the post type is not in the assigned post types
				}
			} elseif ( isset( $assigned_to['post_ids'] ) && is_array( $assigned_to['post_ids'] ) ) {
				if ( ! in_array( $post->ID, $assigned_to['post_ids'], true ) ) {
					continue; // Skip if the post ID is not in the assigned post IDs
				}
			}
			// TODO
			// elseif ( isset( $assigned_to['taxonomies'] ) && is_array( $assigned_to['taxonomies'] ) ) {}

			add_meta_box(
				'etch_cf_' . $group_id,
				$group_definition['label'],
				array( $this, 'render_custom_field_meta_box' ),
				null,
				'normal',
				'high',
				array(
					'group_id' => $group_id,
					'group_definition' => $group_definition,
				)
			);
		}
	}

	/**
	 * Renders the custom field meta box in the post editor.
	 *
	 * @param \WP_Post                                                                 $post    The current post object.
	 * @param  array{args: array{group_id: string, group_definition: CustomFieldGroup}} $metabox The meta box arguments.
	 *
	 * @return void
	 */
	public function render_custom_field_meta_box( $post, $metabox ) {
		wp_nonce_field( 'etch_cf_nonce', 'etch_cf_nonce_field' );

		// Get the field definitions passed from add_meta_box
		$group = $metabox['args'];
		$group_definition = $group['group_definition'];
		$group_id = $group['group_id'];

		echo '<div class="etch-cf-metabox">';

		$field_keys = array();
		foreach ( $group_definition['fields'] as $field ) {
			$field_keys[] = $field['key'];
			$value = get_post_meta( $post->ID, $field['key'], true );

			echo '<div class="etch-cf-field-wrapper">';
			$field = BaseField::create( $field, $value );
			$field->render();
			echo '</div>';
		}

		echo '</div>';

		// Store field definitions in a hidden input for the save callback
		$json_encoded = wp_json_encode( $field_keys );
		if ( false === $json_encoded ) {
			$json_encoded = '[]'; // Fallback to an empty array if encoding fails
		}

		echo '<input type="hidden" id="_etch_cf_' . esc_attr( $group_id ) . '" name="_etch_cf_' . esc_attr( $group_id ) . '" value="' . esc_attr( $json_encoded ) . '">';
	}

	/**
	 * Saves custom field values when a post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 *
	 * @return void
	 */
	public function save_custom_fields( $post_id ) {
		// Security checks
		if ( ! isset( $_POST['etch_cf_nonce_field'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['etch_cf_nonce_field'] ) ), 'etch_cf_nonce' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		foreach ( $_POST as $key => $value ) {
			if ( str_starts_with( $key, '_etch_cf_' ) ) {
				// The value is the actual field key
				$field_keys = json_decode( sanitize_text_field( wp_unslash( $value ) ), true );

				// Ensure we have a valid array of field keys
				if ( ! is_array( $field_keys ) ) {
					continue;
				}

				$group_id = str_replace( '_etch_cf_', '', $key );
				$field_map = $this->get_custom_field_map( $group_id );

				foreach ( $field_keys as $field_key ) {
					if ( isset( $field_map[ $field_key ] ) ) {
						$field_definition = $field_map[ $field_key ];

						// Sanitize and validate the field value
						if ( ! isset( $_POST[ $field_key ] ) ) {
							$field_value = '';
						} else {
							$field_value = sanitize_text_field( wp_unslash( $_POST[ $field_key ] ) );
						}

						$field = BaseField::create( $field_definition, $field_value );
						$field_value = $field->sanitize_value( $field_value );

						update_post_meta( $post_id, $field_key, $field_value );

						// For good measure also add a reference to the id in the options
						update_post_meta( $post_id, '_' . $field_key, 'etch_field_group_' . $group_id );
					}
				}
			}
		}
	}

	/**
	 * Get all custom field groups.
	 *
	 * @return array<string, CustomFieldGroup>
	 */
	public function get_all_custom_field_groups() {
		$cf_groups = get_option( $this->option_name, array() );

		if ( ! is_array( $cf_groups ) ) {
			return array();
		}

		return $cf_groups;
	}

	/**
	 * Get a custom field group by its ID.
	 *
	 * @param string $id The unique identifier for the custom field group.
	 * @return CustomFieldGroup|null The custom field group definition or null if not found.
	 */
	public function get_custom_field_group( string $id ) {
		$cf_groups = $this->get_all_custom_field_groups();

		if ( isset( $cf_groups[ $id ] ) ) {
			return $cf_groups[ $id ];
		}

		return null;
	}


	/**
	 * Get a custom field by its group ID and field key.
	 *
	 * @param string $group_id The unique identifier for the custom field group.
	 * @param string $field_key The key of the custom field to retrieve.
	 * @return CustomField|null The custom field definition or null if not found.
	 */
	public function get_custom_field( string $group_id, string $field_key ) {
		$group = $this->get_custom_field_group( $group_id );

		if ( ! $group ) {
			return null;
		}

		foreach ( $group['fields'] as $field ) {
			if ( $field['key'] === $field_key ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Get all custom fields of a specific group.
	 *
	 * @param string $group_id The unique identifier for the custom field group.
	 * @return array<string, CustomField> An associative array of field keys to field definitions.
	 */
	public function get_custom_field_map( string $group_id ): array {
		$group = $this->get_custom_field_group( $group_id );

		if ( ! $group ) {
			return array();
		}

		$indexed_fields = array();
		foreach ( $group['fields'] as $field ) {
			$indexed_fields[ $field['key'] ] = $field;
		}
		return $indexed_fields;
	}

	/**
	 * Get the value of a custom field for a specific post.
	 *
	 * @param string $group_id The unique identifier for the custom field group.
	 * @param string $field_key The key of the custom field to retrieve.
	 * @param int    $post_id  The ID of the post to get the field value for.
	 * @return mixed The type resolved value of the custom field or null if not found.
	 */
	public function get_field_value( string $group_id, string $field_key, int $post_id ): mixed {
		$field_definition = $this->get_custom_field( $group_id, $field_key );

		if ( ! $field_definition ) {
			return null;
		}

		$field_value = get_post_meta( $post_id, $field_key, true );

		$field = BaseField::create( $field_definition, $field_value );
		return $field->sanitize_value( $field_value );
	}

	/**
	 * Create a new custom field group.
	 *
	 * @param CustomFieldGroup $definition The definition for the custom field.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_group( $definition ): WP_Rest_Response|WP_Error {

		$cf_groups = get_option( $this->option_name, array() );
		$cf_groups = is_array( $cf_groups ) ? $cf_groups : array();

		$uniqueId = substr( uniqid(), -7 );

		$cf_groups[ $uniqueId ] = $definition;
		update_option( $this->option_name, $cf_groups );

		return new WP_REST_Response(
			array(
				'message' => 'Custom field group created',
				'id' => $uniqueId,
			),
			201
		);
	}

	/**
	 * Update a custom field group.
	 *
	 * @param string           $id   The unique identifier for the custom field type.
	 * @param CustomFieldGroup $definition The definition for the custom field.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_group( $id, $definition ): WP_REST_Response|WP_Error {
		$cf_groups = get_option( $this->option_name, array() );
		$cf_groups = is_array( $cf_groups ) ? $cf_groups : array();

		if ( ! isset( $cf_groups[ $id ] ) ) {
			return new WP_Error( 'cf_not_found', 'Custom field group not found', array( 'status' => 404 ) );
		}

		$cf_groups[ $id ] = $definition;
		update_option( $this->option_name, $cf_groups );

		return new WP_REST_Response( array( 'message' => 'Custom field group updated' ), 200 );
	}

	/**
	 * Delete a custom field group.
	 *
	 * @param string $id The unique identifier for the field group.
	 * @return WP_Error|WP_REST_Response
	 */
	public function delete_group( string $id ): WP_REST_Response|WP_Error {
		$cf_groups = get_option( $this->option_name, array() );
		$cf_groups = is_array( $cf_groups ) ? $cf_groups : array();

		if ( ! isset( $cf_groups[ $id ] ) ) {
			return new WP_Error( 'cf_not_found', 'Custom field not found', array( 'status' => 404 ) );
		}

		unset( $cf_groups[ $id ] );

		$success = update_option( $this->option_name, $cf_groups );

		if ( ! $success ) {
			return new WP_Error( 'cf_delete_failed', 'Failed to delete custom field', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'message' => 'Custom field group deleted' ), 200 );
	}

	/**
	 * Add a custom field to group type.
	 *
	 * @param string      $group_id The unique identifier for the field group.
	 * @param CustomField $definition The definition for the custom field.
	 * @return WP_Error|WP_REST_Response
	 */
	public function add_field_to_group( $group_id, $definition ): WP_REST_Response|WP_Error {
		$cf_groups = get_option( $this->option_name, array() );
		$cf_groups = is_array( $cf_groups ) ? $cf_groups : array();

		if ( ! isset( $cf_groups[ $group_id ] ) ) {
			return new WP_Error( 'cf_group_not_found', 'Custom field group not found', array( 'status' => 404 ) );
		}

		// Ensure the fields array exists
		if ( ! isset( $cf_groups[ $group_id ]['fields'] ) || ! is_array( $cf_groups[ $group_id ]['fields'] ) ) {
			$cf_groups[ $group_id ]['fields'] = array();
		}

		// Check if a field with the same key already exists
		foreach ( $cf_groups[ $group_id ]['fields'] as $existing_field ) {
			if ( $existing_field['key'] === $definition['key'] ) {
				return new WP_Error( 'cf_field_exists', 'Custom field with this key already exists in the group', array( 'status' => 400 ) );
			}
		}

		// Add the new field
		$cf_groups[ $group_id ]['fields'][] = $definition;

		update_option( $this->option_name, $cf_groups );

		return new WP_REST_Response( array( 'message' => 'Custom field added to group' ), 201 );
	}

	/**
	 * Update a custom field in a group.
	 *
	 * @param string      $group_id The unique identifier for the custom field group.
	 * @param string      $field_key The key of the custom field to update.
	 * @param CustomField $definition The definition for the custom field.
	 * @return WP_Error|WP_REST_Response
	 */
	public function update_field_in_group( $group_id, $field_key, $definition ): WP_REST_Response|WP_Error {
		$cf_groups = get_option( $this->option_name, array() );
		$cf_groups = is_array( $cf_groups ) ? $cf_groups : array();

		if ( ! isset( $cf_groups[ $group_id ] ) ) {
			return new WP_Error( 'cf_group_not_found', 'Custom field group not found', array( 'status' => 404 ) );
		}

		  // Ensure the fields array exists
		if ( ! isset( $cf_groups[ $group_id ]['fields'] ) || ! is_array( $cf_groups[ $group_id ]['fields'] ) ) {
			$cf_groups[ $group_id ]['fields'] = array();
		}

		$original_count = count( $cf_groups[ $group_id ]['fields'] );
		$cf_groups[ $group_id ]['fields'] = array_filter(
			$cf_groups[ $group_id ]['fields'],
			fn( $field ) => $field['key'] !== $field_key
		);
		$field_found = count( $cf_groups[ $group_id ]['fields'] ) < $original_count;

		if ( ! $field_found ) {
			return new WP_Error( 'cf_field_not_found', 'Custom field with this key not found in the group', array( 'status' => 404 ) );
		}

		// if the field key is not the same as the definition key we need to check if the definition key already exists
		if ( $field_key !== $definition['key'] ) {
			foreach ( $cf_groups[ $group_id ]['fields'] as $existing_field ) {
				if ( $existing_field['key'] === $definition['key'] ) {
					return new WP_Error( 'cf_field_exists', 'Custom field with this key already exists in the group', array( 'status' => 400 ) );
				}
			}
		}

		// Add the updated field definition
		$cf_groups[ $group_id ]['fields'][] = $definition;

		// reindex the fields array to ensure it is sequential
		$cf_groups[ $group_id ]['fields'] = array_values( $cf_groups[ $group_id ]['fields'] );

		update_option( $this->option_name, $cf_groups );

		return new WP_REST_Response( array( 'message' => 'Custom field updated in group' ), 200 );
	}

	/**
	 * Remove a custom field from a group.
	 *
	 * @param string $group_id The unique identifier for the custom field group.
	 * @param string $field_key The key of the custom field to remove.
	 * @return WP_Error|WP_REST_Response
	 */
	public function remove_field_from_group( $group_id, $field_key ): WP_REST_Response|WP_Error {
		$cf_groups = get_option( $this->option_name, array() );
		$cf_groups = is_array( $cf_groups ) ? $cf_groups : array();

		if ( ! isset( $cf_groups[ $group_id ] ) ) {
			return new WP_Error( 'cf_group_not_found', 'Custom field group not found', array( 'status' => 404 ) );
		}

		// Ensure the fields array exists
		if ( ! isset( $cf_groups[ $group_id ]['fields'] ) || ! is_array( $cf_groups[ $group_id ]['fields'] ) ) {
			$cf_groups[ $group_id ]['fields'] = array();
		}

		$cf_groups[ $group_id ]['fields'] = array_filter(
			$cf_groups[ $group_id ]['fields'],
			fn( $field ) => $field['key'] !== $field_key
		);

		// reindex the fields array to ensure it is sequential
		$cf_groups[ $group_id ]['fields'] = array_values( $cf_groups[ $group_id ]['fields'] );

		update_option( $this->option_name, $cf_groups );

		return new WP_REST_Response( array( 'message' => 'Custom field removed from group' ), 201 );
	}
}
