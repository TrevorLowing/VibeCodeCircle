<?php
/**
 * Meta Box Data Adapter.
 *
 * This adapter handles Meta Box fields data retrieval and formatting.
 *
 * @package Etch\Adapters
 */

declare(strict_types=1);

namespace Etch\Adapters;

use WP_Post;
use Etch\Traits\DynamicDataBases;

/**
 * Class MetaBoxDataAdapter
 *
 * Convert the custom fields from Meta Box to Etch Dynamic Data.
 */
class MetaBoxDataAdapter {
	use DynamicDataBases;

	/**
	 * Check if the object type is allowed for Meta Box.
	 *
	 * @param string $object_type Object type name that need to be checked.
	 * @return bool True if it's allowed and false if doesn't
	 */
	private function is_allowed_type( string $object_type ): bool {
		$allowed_types = array( 'setting', 'term', 'user', 'post' );

		return in_array( $object_type, $allowed_types );
	}

	/**
	 * Returns an array of all values and formatted fields available for the given object type.
	 *
	 * @param string   $object_type Object type that need to be checked (post, setting, term or user).
	 * @param null|int $object_id Object ID that need to be checked. Setting page don't need to pass the ID.
	 * @return array<string, mixed> Meta Box field values with proper formatting.
	 */
	public function get_data( string $object_type, $object_id = null ): array {
		if ( ! function_exists( 'rwmb_get_registry' ) || ! function_exists( 'rwmb_get_value' ) ) {
			return array();
		}

		if ( ! $this->is_allowed_type( $object_type ) ) {
			return array();
		}

		$field_registry = rwmb_get_registry( 'field' );

		if ( ! is_object( $field_registry ) || ! method_exists( $field_registry, 'get_by_object_type' ) ) {
			return array();
		}

		$fields_for_type = $field_registry->get_by_object_type( $object_type );

		if ( ! $fields_for_type || ! is_array( $fields_for_type ) ) {
			return array();
		}

		$formatted_fields = array();

		if ( 'setting' === $object_type ) {

			foreach ( $fields_for_type as $setting_page_name => $setting_page_fields ) {
				if ( ! is_array( $setting_page_fields ) ) {
					continue;
				}
				// TODO: remove the use for option name once everyone migrate to setting page ID OR we create a migration for that.
				$field_value = $this->format_field_values( $object_type, $setting_page_name, $setting_page_fields );
				$formatted_fields[ $setting_page_name ] = $field_value;

				// Metabox store the fields by option name, but option name could be a string with whitespace and case sensitive.
				// For that reason since alpha-11 we start to use the Setting Page ID instead.
				$option_id = $this->get_option_page_id_by_option_name( $setting_page_name );
				if ( $option_id ) {
					$formatted_fields[ $option_id ] = $field_value;
				}
			}
		} else {
			if ( ! is_int( $object_id ) ) {
				return array();
			}
			$formatted_fields = $this->format_field_values( $object_type, $object_id, $fields_for_type[ array_key_first( $fields_for_type ) ] );
		}

		return $formatted_fields;
	}

	/**
	 * Get the Setting Page ID of option page from the option name.
	 *
	 * @param string $option_name Meta Box option name.
	 * @return string|null Return the ID if founded or null.
	 */
	private function get_option_page_id_by_option_name( string $option_name ): string|null {
		global $wpdb;

		$row = $wpdb->get_var(
			$wpdb->prepare(
				"
			SELECT meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'settings_page'
			AND meta_value LIKE %s
			LIMIT 1
		",
				'%"option_name";s:%:"' . $option_name . '"%'
			)
		);

		if ( $row ) {
			$option_data = maybe_unserialize( $row );
			return is_array( $option_data ) && isset( $option_data['id'] ) && is_string( $option_data['id'] ) ? $option_data['id'] : null;
		}

		return null;
	}

	/**
	 * Format and filter all fields values.
	 *
	 * @param string               $object_type Object type that need to be checked (post, setting, term or user).
	 * @param string|int           $object_id Object ID that need to be checked. Setting page use name as ID.
	 * @param array<string, mixed> $fields All Meta Box fields that need to be formated.
	 * @return array<string, mixed> Meta Box field values with proper formatting.
	 */
	private function format_field_values( string $object_type, string|int $object_id, array $fields ): array {
		if ( ! function_exists( 'rwmb_get_value' ) ) {
			return array();
		}

		$formatted_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
				continue;
			}

			$mb_value = rwmb_get_value( $key, array( 'object_type' => $object_type ), $object_id );

			$value = $this->format_field_by_type( $field['type'], $mb_value, $field );

			if ( empty( $value ) ) {
				continue;
			}

			$formatted_fields[ $key ] = $value;
		}

		return $formatted_fields;
	}

	/**
	 * Returns an array of all raw fields available for the given object type.
	 *
	 * @param string $object_type Object type that need to be checked.
	 * @return array<string, mixed> Raw Meta Box fields.
	 */
	public function get_raw_fields( string $object_type ): array {
		if ( ! function_exists( 'rwmb_get_registry' ) ) {
			return array();
		}

		if ( ! $this->is_allowed_type( $object_type ) ) {
			return array();
		}

		$field_registry = rwmb_get_registry( 'field' );

		if ( ! is_object( $field_registry ) || ! method_exists( $field_registry, 'get_by_object_type' ) ) {
			return array();
		}

		$fields_for_type = $field_registry->get_by_object_type( $object_type );

		if ( ! $fields_for_type || ! is_array( $fields_for_type ) ) {
			return array();
		}

		if ( 'setting' === $object_type ) {
			return $fields_for_type;
		} else {
			$fields_for_type = $fields_for_type[ array_key_first( $fields_for_type ) ];
			return is_array( $fields_for_type ) ? $fields_for_type : array();
		}
	}

	/**
	 * Retrieves and formats Meta Box fields for a post with your values.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> Meta Box field values with proper formatting.
	 */
	public function get_data_for_post( WP_Post $post ): array {
		// STEP: Create a list with all Meta Box fields registered
		$mb_fields_type = $this->get_fields_for_post( $post );

		if ( count( $mb_fields_type ) == 0 ) {
			return array();
		}

		// STEP: Find all post meta for each field and format the value
		$formatted_fields = array();
		foreach ( $mb_fields_type as $key => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
				continue;
			}
			$type = $field['type'];
			if ( function_exists( 'rwmb_get_value' ) && ! empty( $field['storage']->table ) ) {
				$value = rwmb_get_value(
					$key,
					array(
						'storage_type' => 'custom_table',
						'table' => $field['storage']->table,
					),
					$post->ID
				);
				if ( ! is_array( $value ) ) {
					$value = array( $value );
				}
			} else {
				$value = get_post_meta( $post->ID, $key, false );
			}

			if ( false === $value || ! is_array( $value ) ) {
				continue;
			}

			if ( count( $value ) == 0 ) {
				$formatted_fields[ $key ] = array();
				continue;
			}

			if ( count( $value ) > 1 ) {
				$formatted_fields[ $key ] = $this->format_field_by_type( $type, $value, $field, $post->ID );
			} else {
				$formatted_fields[ $key ] = $this->format_field_by_type( $type, $value[0], $field, $post->ID );
			}
		}

		return $formatted_fields;
	}

	/**
	 * Create a list with all fields for a post with all settings to know the type and subfields
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> Meta Box field available for the post
	 */
	public function get_fields_for_post( WP_Post $post ): array {
		if ( ! function_exists( 'rwmb_get_object_fields' )
		) {
			// Meta Box is not active or different version; log for debugging
			error_log( 'Meta Box plugin is not installed or active.' );
			return array();
		}

		$all_mb_fields = rwmb_get_object_fields( $post->ID ) ?? array();

		if ( ! $all_mb_fields || ! is_array( $all_mb_fields ) ) {
			return array();
		}

		$mb_fields_type = array();
		foreach ( $all_mb_fields as $fields ) {
			if ( isset( $fields['id'] ) ) {
				$mb_fields_type[ $fields['id'] ] = $fields;
			}
		}

		return $mb_fields_type;
	}

	/**
	 * Formats a field value based on its type using a centralized switch statement.
	 *
	 * @param string               $field_type The field type.
	 * @param mixed                $value The field value.
	 * @param array<string, mixed> $field_object The field object from Meta box.
	 * @param int                  $post_id The post ID.
	 * @return mixed The formatted field value.
	 */
	protected function format_field_by_type( string $field_type, $value, array $field_object, int $post_id = 0 ) {
		switch ( $field_type ) {
			case 'image':
			case 'image_advanced':
			case 'image_upload':
			case 'single_image':
				if ( ! is_array( $value ) ) {
					return $this->format_image_field( $value );
				}

				if ( is_array( $value ) && ( isset( $value['ID'] ) || isset( $value['id'] ) ) ) {
					return $this->format_image_field( $value );
				}

				return $this->format_gallery_field( $value );

			case 'group':
				return $this->format_repeater_field( $value, $field_object, $post_id );

			default:
				return $value;
		}
	}

	/**
	 * Formats a gallery field to a list with images.
	 *
	 * @param array<int, mixed> $value Array with image IDs, could be another array or just a list with IDs.
	 * @return array<int, mixed> List with images already formated.
	 */
	protected function format_gallery_field( array $value ): array {
		if ( empty( $value ) ) {
			return array();
		}

		$image_list = array();

		// Check if is a repeater field
		if ( array_key_exists( 0, $value ) && is_array( $value[0] ) ) {
			foreach ( $value as $sub_gallery ) {
				if ( ! is_array( $sub_gallery ) ) {
					continue;
				}
				$image_list[] = $this->format_gallery_field( $sub_gallery );
			}
		} else {
			$image_list = $this->format_array_to_gallery( $value );
		}

		return $image_list;
	}

	/**
	 * Format all images IDs to images.
	 *
	 * @param array<int, mixed> $value Array with image IDs.
	 * @return array<int, mixed> List with images already formated.
	 */
	private function format_array_to_gallery( array $value ): array {
		if ( empty( $value ) ) {
			return array();
		}

		$image_list = array();

		foreach ( $value as $image_id ) {
			$image_list[] = $this->format_image_field( $image_id );
		}

		return $image_list;
	}

	/**
	 * Formats an image field to provide additional properties.
	 *
	 * @param mixed $value The image field value.
	 * @return array<string, mixed> Formatted image data.
	 */
	protected function format_image_field( $value ): array {
		// Handle image array with ID
		if ( is_array( $value ) && isset( $value['id'] ) ) {
			return $this->get_base_image_data( $value['id'] );
		}

		if ( is_array( $value ) && isset( $value['ID'] ) ) {
			return $this->get_base_image_data( $value['ID'] );
		}

		// Handle attachment ID
		if ( is_numeric( $value ) ) {
			return $this->get_base_image_data( (int) $value );
		}

		// Handle image URL
		if ( is_string( $value ) && ! empty( $value ) ) {
			return $this->get_base_image_data( $value );
		}

		// Return empty array for invalid/empty values
		return array();
	}

	/**
	 * Formats a repeater field by processing each row and its sub-fields.
	 *
	 * @param mixed                $value The repeater field value.
	 * @param array<string, mixed> $field_object The field object from Meta Box.
	 * @param int                  $post_id The post ID.
	 * @return array<int, array<string, mixed>> Formatted repeater data.
	 */
	protected function format_repeater_field( $value, array $field_object, int $post_id ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$formatted_rows = array();

		// Check if is cloneable / repeater or not.
		if ( isset( $value[0] ) ) {
			// Handle repeater rows
			foreach ( $value as $row_index => $row_data ) {
				if ( ! is_array( $row_data ) ) {
					continue;
				}

				$formatted_row = array();

				foreach ( $row_data as $sub_field_key => $sub_field_value ) {
					// Format each sub-field recursively
					$formatted_row[ $sub_field_key ] = $this->format_repeater_sub_field(
						$sub_field_key,
						$sub_field_value,
						$field_object,
						$post_id,
						$row_index
					);
				}

				$formatted_rows[] = $formatted_row;
			}
		} else {
			// Handle single set of subfields
			foreach ( $value as $sub_field_key => $sub_field_value ) {
				// Format each sub-field recursively
				$formatted_rows[ $sub_field_key ] = $this->format_repeater_sub_field(
					$sub_field_key,
					$sub_field_value,
					$field_object,
					$post_id
				);
			}
		}

		return $formatted_rows;
	}

	/**
	 * Formats a sub-field within a repeater field.
	 *
	 * @param string               $sub_field_key The sub-field key.
	 * @param mixed                $sub_field_value The sub-field value.
	 * @param array<string, mixed> $parent_field_object The parent repeater field object.
	 * @param int                  $post_id The post ID.
	 * @param int                  $row_index The row index within the repeater.
	 * @return mixed The formatted sub-field value.
	 */
	protected function format_repeater_sub_field(
		string $sub_field_key,
		$sub_field_value,
		array $parent_field_object,
		int $post_id,
		int $row_index = 0
	) {
		if ( empty( $sub_field_value ) ) {
			return $sub_field_value;
		}

		// Try to get the sub-field object from the parent repeater field
		$sub_field_object = null;
		if ( isset( $parent_field_object['fields'] ) && is_array( $parent_field_object['fields'] ) ) {
			foreach ( $parent_field_object['fields'] as $sub_field ) {
				if ( isset( $sub_field['id'] ) && $sub_field['id'] === $sub_field_key ) {
					$sub_field_object = $sub_field;
					break;
				}
			}
		}

		if ( ! $sub_field_object || ! isset( $sub_field_object['type'] ) ) {
			return $sub_field_value;
		}

		// Format based on sub-field type using the centralized helper
		return $this->format_field_by_type( $sub_field_object['type'], $sub_field_value, $sub_field_object, $post_id );
	}
}
