<?php
/**
 * ACFData trait.
 *
 * This trait handles Advanced Custom Fields (ACF) data retrieval and
 * formatting.
 *
 * @package Etch\Traits
 */

declare(strict_types=1);

namespace Etch\Traits;

use Etch\Helpers\Flag;
use WP_Post;
use WP_User;
use WP_Term;
trait ACFData {
	use DynamicDataBases;


	/**
	 * Retrieves and formats ACF fields for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> ACF field values with proper formatting.
	 */
	protected function get_acf_data( WP_Post $post ): array {
		if ( ! function_exists( '\get_fields' ) ) {
			// ACF is not active; log for debugging
			error_log( 'ACF plugin is not installed or active.' );
			return array();
		}

		$acf_fields = \get_fields( $post->ID );

		if ( ! $acf_fields || ! is_array( $acf_fields ) ) {
			return array();
		}

		$formatted_fields = array();
		foreach ( $acf_fields as $key => $value ) {
			$formatted_fields[ $key ] = $this->
				format_acf_field( $key, $value, $post->ID );
		}

		return $formatted_fields;
	}

	/**
	 * Formats an ACF field based on its type.
	 *
	 * @param string $key The field key.
	 * @param mixed  $value The field value.
	 * @param int    $post_id The post ID.
	 * @return mixed The formatted field value.
	 */
	protected function format_acf_field(
		string $key,
		$value,
		int $post_id
	) {
		if ( empty( $value ) ) {
			return $value;
		}

		if ( ! function_exists( '\get_field_object' ) ) {
			return $value;
		}

		$field_object = \get_field_object( $key, $post_id );

		if ( ! $field_object || ! isset( $field_object['type'] ) ) {
			return $value;
		}

		return $this->format_field_by_type( $field_object['type'], $value, $field_object, $post_id );
	}

	/**
	 * Formats a field value based on its type using a centralized switch statement.
	 *
	 * @param string               $field_type The field type.
	 * @param mixed                $value The field value.
	 * @param array<string, mixed> $field_object The field object from ACF.
	 * @param int                  $post_id The post ID.
	 * @return mixed The formatted field value.
	 */
	protected function format_field_by_type( string $field_type, $value, array $field_object, int $post_id ) {
		if ( ! Flag::is_on( 'RETURN_ACF_DYNAMIC_DATA' ) ) {
			switch ( $field_type ) {
				case 'image':
					return $this->format_image_field( $value );

				case 'link':
					return $this->format_link_field( $value );

				case 'flexible_content':
					return $this->format_flexible_content_field( $value, $field_object, $post_id );

				case 'repeater':
					return $this->format_repeater_field( $value, $field_object, $post_id );

				case 'gallery':
					return $this->format_gallery_field( $value );

				case 'post_object':
				case 'relationship':
					return $this->format_post_object_field( $value );

				case 'taxonomy':
					return $this->format_taxonomy_field( $value );

				case 'user':
					return $this->format_user_field( $value );

				// TODO: Probably some extra handling in the future
				case 'select':
				case 'checkbox':
					return $value;

				default:
					return $value;
			}
		}

		switch ( $field_type ) {
			case 'image':
				return $this->format_image_field( $value );

			case 'flexible_content':
				return $this->format_flexible_content_field( $value, $field_object, $post_id );

			case 'repeater':
				return $this->format_repeater_field( $value, $field_object, $post_id );

			case 'gallery':
				return $this->format_gallery_field( $value );

			default:
				return $value;
		}
	}

	/**
	 * Formats an image field to provide additional properties.
	 *
	 * @param mixed $value The image field value.
	 * @return array<string, mixed> Formatted image data.
	 */
	protected function format_image_field( $value ): array {
		// Handle ACF image array with ID
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
	 * Formats a link field.
	 *
	 * @param mixed $value The link field value.
	 * @return array<string, string> Formatted link data.
	 */
	protected function format_link_field( $value ): array {
		if ( empty( $value ) ) {
			return array(
				'url' => '',
				'title' => '',
				'target' => '',
			);
		}

		if ( is_string( $value ) ) {
			return array(
				'url' => $value,
				'title' => '',
				'target' => '',
			);
		}

		if ( is_array( $value ) ) {
			return array(
				'url' => isset( $value['url'] ) ? $value['url'] : '',
				'title' => isset( $value['title'] ) ? $value['title'] : '',
				'target' => isset( $value['target'] ) ? $value['target'] : '',
			);
		}

		return array(
			'url' => '',
			'title' => '',
			'target' => '',
		);
	}

	/**
	 * Formats a repeater field by processing each row and its sub-fields.
	 *
	 * @param mixed                $value The repeater field value.
	 * @param array<string, mixed> $field_object The field object from ACF.
	 * @param int                  $post_id The post ID.
	 * @return array<int, array<string, mixed>> Formatted repeater data.
	 */
	protected function format_repeater_field( $value, array $field_object, int $post_id ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$formatted_rows = array();

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

		return $formatted_rows;
	}

	/**
	 * Formats a flexible_content field by processing each row and its sub-fields.
	 *
	 * @param mixed                $value The flexible_content field value.
	 * @param array<string, mixed> $field_object The field object from ACF.
	 * @param int                  $post_id The post ID.
	 * @return array<int, array<string, mixed>> Formatted flexible_content data.
	 */
	protected function format_flexible_content_field( $value, array $field_object, int $post_id ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$formatted_rows = array();

		foreach ( $value as $row_index => $row_data ) {
			if ( ! is_array( $row_data ) || ! isset( $row_data['acf_fc_layout'] ) ) {
				continue;
			}

			$formatted_row = array();

			foreach ( $row_data as $sub_field_key => $sub_field_value ) {
				// Format each sub-field recursively
				$formatted_row[ $sub_field_key ] = $this->format_flexible_content_sub_field(
					$row_data['acf_fc_layout'],
					$sub_field_key,
					$sub_field_value,
					$field_object,
					$post_id,
					$row_index
				);
			}

			$formatted_rows[] = $formatted_row;
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
		int $row_index
	) {
		if ( empty( $sub_field_value ) ) {
			return $sub_field_value;
		}

		if ( ! function_exists( '\get_field_object' ) ) {
			return $sub_field_value;
		}

		// Try to get the sub-field object from the parent repeater field
		$sub_field_object = null;
		if ( isset( $parent_field_object['sub_fields'] ) && is_array( $parent_field_object['sub_fields'] ) ) {
			foreach ( $parent_field_object['sub_fields'] as $sub_field ) {
				if ( isset( $sub_field['name'] ) && $sub_field['name'] === $sub_field_key ) {
					$sub_field_object = $sub_field;
					break;
				}
			}
		}

		// Fallback: try to get field object directly (this might work in some cases)
		if ( ! $sub_field_object ) {
			$sub_field_object = \get_field_object( $sub_field_key, $post_id );
		}

		if ( ! $sub_field_object || ! isset( $sub_field_object['type'] ) ) {
			return $sub_field_value;
		}

		// Format based on sub-field type using the centralized helper
		return $this->format_field_by_type( $sub_field_object['type'], $sub_field_value, $sub_field_object, $post_id );
	}

	/**
	 * Formats a sub-field within a flexible content field.
	 *
	 * @param string               $parent_layout The name of the layout for the current item.
	 * @param string               $sub_field_key The sub-field key.
	 * @param mixed                $sub_field_value The sub-field value.
	 * @param array<string, mixed> $parent_field_object The parent flexible content field object.
	 * @param int                  $post_id The post ID.
	 * @param int                  $row_index The row index within the flexible content.
	 * @return mixed The formatted sub-field value.
	 */
	protected function format_flexible_content_sub_field(
		string $parent_layout,
		string $sub_field_key,
		$sub_field_value,
		array $parent_field_object,
		int $post_id,
		int $row_index
	) {
		if ( empty( $sub_field_value ) ) {
			return $sub_field_value;
		}

		if ( ! function_exists( '\get_field_object' ) ) {
			return $sub_field_value;
		}

		if ( ! isset( $parent_field_object['layouts'] ) || ! is_array( $parent_field_object['layouts'] ) ) {
			return $sub_field_value;
		}

		$sub_field_object = null;
		foreach ( $parent_field_object['layouts'] as $layout ) {
			if ( ! isset( $layout['name'] ) || $layout['name'] != $parent_layout || ! isset( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
				continue;
			}
			foreach ( $layout['sub_fields'] as $sub_field ) {
				if ( isset( $sub_field['name'] ) && $sub_field['name'] === $sub_field_key ) {
					$sub_field_object = $sub_field;
					break;
				}
			}

			if ( $sub_field_object ) {
				break;
			}
		}

		if ( ! $sub_field_object || ! isset( $sub_field_object['type'] ) ) {
			return $sub_field_value;
		}

		// Format based on sub-field type using the centralized helper
		return $this->format_field_by_type( $sub_field_object['type'], $sub_field_value, $sub_field_object, $post_id );
	}

	/**
	 * Retrieves ACF field types for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, string> ACF field names and their types.
	 */
	protected function get_acf_field_types( WP_Post $post ): array {
		if ( ! function_exists( '\get_fields' ) ) {
			// ACF is not active
			return array();
		}

		$acf_fields = \get_fields( $post->ID );

		if ( ! $acf_fields || ! is_array( $acf_fields ) ) {
			return array();
		}

		if ( ! function_exists( '\get_field_object' ) ) {
			return array();
		}

		$field_types = array();
		foreach ( array_keys( $acf_fields ) as $key ) {
			$field_object = \get_field_object( $key, $post->ID );
			if ( $field_object && isset( $field_object['type'] ) ) {
				$field_types[ $key ] = (string) $field_object['type'];
			}
		}

		return $field_types;
	}

	/**
	 * Format a gallery field to return an image array with all metadata
	 *
	 * @param mixed $value ACF Gallery value.
	 * @return mixed Formatted gallery field with image metadata.
	 */
	protected function format_gallery_field( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		return array_values(
			array_filter(
				array_map(
					function ( $item ) {
						$image = $this->format_image_field( $item );
						return ! empty( $image ) ? $image : false;
					},
					$value
				)
			)
		);
	}

	/**
	 * Format a post list and return an array with all metadata
	 *
	 * @param mixed $value ACF Post Object | Relationship value.
	 * @return array<int, array<string, mixed>>|array<string, mixed> Formatted post object field with metadata.
	 */
	protected function format_post_object_field( $value ): array {
		if ( is_int( $value ) ) {
			$post = get_post( $value );

			return $post instanceof WP_Post ? $this->get_base_post_data( $post ) : array();
		}

		if ( $value instanceof WP_Post ) {
			return $this->get_base_post_data( $value );
		}

		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					function ( $item ) {
						$post = get_post( $item );
						return $post instanceof WP_Post ? $this->get_base_post_data( $post ) : false;
					},
					$value
				)
			)
		);
	}

	/**
	 * Format a taxonomy list and return an array with all metadata
	 *
	 * @param mixed $value ACF Taxonomy value.
	 * @return array<int, array<string, mixed>> Formatted taxonomy field with metadata.
	 */
	protected function format_taxonomy_field( $value ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_map(
					function ( $item ) {
						$term = get_term( $item );
						return $term instanceof WP_Term ? $this->get_base_term_data( $term ) : false;
					},
					$value
				)
			)
		);
	}

	/**
	 * Format a user list and return an array with all metadata
	 *
	 * @param mixed $value ACF user value.
	 * @return array<string, mixed>|array<int, array<string, mixed>> Formatted user field with metadata.
	 */
	protected function format_user_field( $value ): array {
		// Check if it is a multiple selection
		if ( is_array( $value ) && ! isset( $value['id'] ) && ! isset( $value['ID'] ) ) {
			return array_values(
				array_filter(
					array_map(
						function ( $item ) {
							$user = $this->get_user_from_value( $item );
							return $user ? $this->get_base_user_data( $user ) : false;
						},
						$value
					)
				)
			);
		}

		// It is a single selection
		$user = $this->get_user_from_value( $value );
		return $user ? $this->get_base_user_data( $user ) : array();
	}

	/**
	 * Normalizes a user field value (ID, object, or array) into a WP_User object.
	 *
	 * @param mixed $value Possible user value.
	 * @return WP_User|false
	 */
	private function get_user_from_value( $value ): WP_User|false {
		if ( $value instanceof WP_User ) {
			return $value;
		}

		// Single User ID
		if ( is_int( $value ) ) {
			return get_user( $value );
		}

		// Single User array
		if ( is_array( $value ) && isset( $value['ID'] ) ) {
			return get_user( $value['ID'] );
		}

		// Just to prevent issues with lowercase
		if ( is_array( $value ) && isset( $value['id'] ) ) {
			return get_user( $value['id'] );
		}

		return false;
	}
}
