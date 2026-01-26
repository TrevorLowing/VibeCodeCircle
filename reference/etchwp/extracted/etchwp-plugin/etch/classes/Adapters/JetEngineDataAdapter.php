<?php
/**
 * JetEngineData Adapter.
 *
 * This adapter handles JetEngine Meta boxes data retrieval and formatting.
 *
 * @package Etch\Adapters
 */

declare(strict_types=1);

namespace Etch\Adapters;

use WP_Post;
use WP_Term;
use WP_User;
use Etch\Traits\DynamicDataBases;

/**
 * Class JetEngineDataAdapter
 *
 * Convert the meta boxes from JetEnginerAdapter to Etch Dynamic Data.
 */
class JetEngineDataAdapter {
	use DynamicDataBases;

	/**
	 * Cache all Dynamic Data
	 *
	 * @var array<string, mixed>
	 */
	protected $cached_data = array();

	/**
	 * Returns an array of all values and formatted fields available for the given object type.
	 *
	 * @param string   $object_type Object type that need to be checked (post, setting, term or user).
	 * @param null|int $object_id Object ID that need to be checked. Setting page don't need to pass the ID.
	 * @return array<string, mixed> Jet Engine field values with proper formatting.
	 */
	public function get_data( string $object_type, $object_id = null ): array {
		if ( ! function_exists( 'jet_engine' ) ) {
			// JetEngine is not active or different version; log for debugging
			error_log( 'JetEngine plugin is not installed or active.' );
			return array();
		}

		if ( 'option' === $object_type ) {
			$option_all_fields = $this->get_options_fields();

			$formatted_fields = array();

			foreach ( $option_all_fields as $option_page_name => $option_page_fields ) {
				if ( ! is_object( $option_page_fields ) || ! isset( $option_page_fields->meta_box ) || ! is_array( $option_page_fields->meta_box ) ) {
					continue;
				}

				foreach ( $option_page_fields->meta_box as $field ) {
					if ( ! is_array( $field )
					|| ! isset( $field['name'] )
					|| ! isset( $field['type'] )
					|| ! isset( jet_engine()->options_pages->registered_pages[ $option_page_name ] )
					|| ! is_object( jet_engine()->options_pages->registered_pages[ $option_page_name ] )
					|| ! method_exists( jet_engine()->options_pages->registered_pages[ $option_page_name ], 'get' )
					) {
						continue;
					}

					$key = $field['name'];
					$type = $field['type'];
					$jetengine_value = jet_engine()->options_pages->registered_pages[ $option_page_name ]->get( $key );
					$value = $this->format_field_by_type( $type, $jetengine_value, $field, 0 );

					if ( empty( $value ) ) {
						continue;
					}

					$formatted_fields[ $option_page_name ][ $key ] = $value;
				}
			}

			return $formatted_fields;
		} elseif ( 'term' === $object_type ) {
			$jetengine_fields_for_term = $this->get_terms_fields( $object_id );
			$formatted_fields = array();
			foreach ( $jetengine_fields_for_term as $field ) {
				if ( ! is_array( $field )
					|| ! isset( $field['name'] )
					|| ! isset( $field['type'] )
				) {
					continue;
				}

				$key = $field['name'];
				$type = $field['type'];
				$jetengine_value = get_term_meta( (int) $object_id, $key, false );

				if ( false === $jetengine_value || ! is_array( $jetengine_value ) || count( $jetengine_value ) == 0 ) {
					continue;
				}

				if ( count( $jetengine_value ) > 1 ) {
					$formatted_fields[ $key ] = $this->format_field_by_type( $type, $jetengine_value, $field, (int) $object_id );
				} else {
					$formatted_fields[ $key ] = $this->format_field_by_type( $type, $jetengine_value[0], $field, (int) $object_id );
				}
			}

			return $formatted_fields;
		} elseif ( 'user' === $object_type ) {
			$jetengine_fields = $this->get_users_fields( $object_id );

			$formatted_fields = array();

			foreach ( $jetengine_fields as $field ) {
				if ( ! is_array( $field )
					|| ! isset( $field['name'] )
					|| ! isset( $field['type'] )
				) {
					continue;
				}

				$key = $field['name'];
				$type = $field['type'];
				$jetengine_value = get_user_meta( (int) $object_id, $key, false );

				if ( false === $jetengine_value || ! is_array( $jetengine_value ) || count( $jetengine_value ) == 0 ) {
					continue;
				}

				if ( count( $jetengine_value ) > 1 ) {
					$formatted_fields[ $key ] = $this->format_field_by_type( $type, $jetengine_value, $field, (int) $object_id );
				} else {
					$formatted_fields[ $key ] = $this->format_field_by_type( $type, $jetengine_value[0], $field, (int) $object_id );
				}
			}

			return $formatted_fields;
		}

		return array();
	}

	/**
	 * Returns an array of all raw fields available for the given object type.
	 *
	 * @param string   $object_type Object type that need to be checked.
	 * @param null|int $object_id Object ID that need to be checked. Setting page don't need to pass the ID.
	 * @return array<string, mixed> Raw Jet Engine fields.
	 */
	public function get_raw_fields( string $object_type, $object_id = null ): array {
		if ( ! function_exists( 'jet_engine' ) ) {
			// JetEngine is not active or different version; log for debugging
			error_log( 'JetEngine plugin is not installed or active.' );
			return array();
		}

		if ( 'option' === $object_type ) {
			$option_all_fields = $this->get_options_fields();

			$formatted_fields = array();

			foreach ( $option_all_fields as $option_page_name => $option_page_fields ) {
				if ( ! is_object( $option_page_fields ) || empty( $option_page_fields->meta_box ) || ! is_array( $option_page_fields->meta_box ) ) {
					continue;
				}
				$formatted_fields[ $option_page_name ] = $option_page_fields->meta_box;
			}

			return $formatted_fields;
		} elseif ( 'term' === $object_type ) {
			$jetengine_fields_for_term = $this->get_terms_fields( $object_id );

			return $jetengine_fields_for_term;
		} elseif ( 'user' === $object_type ) {
			$jetengine_fields = $this->get_users_fields( $object_id );

			return $jetengine_fields;
		}

		return array();
	}

	/**
	 * Retrieves and formats JetEngine fields for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> JetEngine field values with proper formatting.
	 */
	public function get_data_for_post( WP_Post $post ): array {
		// STEP: Create a list with all JetEngine fields registered
		$jetengine_fields_type = $this->get_fields_for_post( $post );

		if ( count( $jetengine_fields_type ) == 0 ) {
			return array();
		}

		// STEP: Find all post meta for each field and format the value
		$formatted_fields = array();
		foreach ( $jetengine_fields_type as $key => $field ) {
			if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
				continue;
			}
			$type = $field['type'];
			$value = get_post_meta( $post->ID, $key, true );

			if ( ! $value ) {
				continue;
			}

			$formatted_fields[ $key ] = $this->format_field_by_type( $type, $value, $field, $post->ID );
		}

		return $formatted_fields;
	}

	/**
	 * Create a list with all fields
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> JetEngine field available for the post
	 */
	public function get_fields_for_post( WP_Post $post ): array {
		if ( ! function_exists( 'jet_engine' )
			|| ! isset( jet_engine()->meta_boxes )
			|| ! method_exists( jet_engine()->meta_boxes, 'get_registered_fields' )
			|| ! is_object( jet_engine()->meta_boxes )
		) {
			// JetEngine is not active or different version; log for debugging
			error_log( 'JetEngine plugin is not installed or active.' );
			return array();
		}

		$all_jet_engine_fields = jet_engine()->meta_boxes->get_registered_fields() ?? array();

		if ( ! $all_jet_engine_fields || ! is_array( $all_jet_engine_fields ) ) {
			return array();
		}

		$jetengine_fields_type = array();
		foreach ( $all_jet_engine_fields as $group_fields ) {
			foreach ( $group_fields as $fields ) {
				if ( isset( $fields['name'] ) ) {
					$jetengine_fields_type[ $fields['name'] ] = $fields;
				}
			}
		}

		$post_fields = $this->filter_existing_postmeta_fields_sql( $post->ID, $jetengine_fields_type );

		$jetengine_fields_type = array_filter(
			$jetengine_fields_type,
			function ( $item, $key ) use ( $post_fields ) {
				if ( ! in_array( $key, $post_fields ) ) {
					return false;
				}

				return true;
			},
			ARRAY_FILTER_USE_BOTH
		);

		return $jetengine_fields_type;
	}

	/**
	 * Receveive a list with fields keys and return a lista with only the meta that post have.
	 *
	 * @param int           $post_id Post ID to be checked.
	 * @param array<string> $fields List with meta keys to check.
	 * @return array<string> List with available keys for the post.
	 */
	private function filter_existing_postmeta_fields_sql( int $post_id, array $fields ): array {
		$cache_key = 'postmeta_fields_' . $post_id;
		if ( isset( $this->cached_data[ $cache_key ] ) && is_array( $this->cached_data[ $cache_key ] ) ) {
			return $this->cached_data[ $cache_key ];
		}
		global $wpdb;

		$fields_to_check = array();

		foreach ( $fields as $key => $value ) {
			$fields_to_check[] = $key;
		}

		if ( empty( $fields_to_check ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $fields_to_check ), '%s' ) );

		$result = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_key 
				FROM {$wpdb->postmeta} 
				WHERE post_id = %d 
				AND meta_key IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				array_merge( array( $post_id ), $fields_to_check )
			)
		);
		$this->cached_data[ $cache_key ] = $result;
		return $result;
	}

	/**
	 * Formats a field value based on its type using a centralized switch statement.
	 *
	 * @param string               $field_type The field type.
	 * @param mixed                $value The field value.
	 * @param array<string, mixed> $field_object The field object from JetEngine.
	 * @param int                  $post_id The post ID.
	 * @return mixed The formatted field value.
	 */
	protected function format_field_by_type( string $field_type, $value, array $field_object, int $post_id ) {
		switch ( $field_type ) {
			case 'media':
				return $this->format_image_field( $value );

			case 'repeater':
				return $this->format_repeater_field( $value, $field_object, $post_id );

			case 'gallery':
				if ( ! is_string( $value ) ) {
					return $value;
				}
				return $this->format_gallery_field( $value );

			default:
				return $value;
		}
	}

	/**
	 * Formats a gallery field to a list with images.
	 *
	 * @param string $value String with image IDs separated by comma.
	 * @return array<int, mixed> List with images already formated.
	 */
	protected function format_gallery_field( string $value ): array {
		if ( empty( $value ) ) {
			return array();
		}

		$image_list = array();
		$image_id_list = explode( ',', trim( $value ) );

		foreach ( $image_id_list as $image_id ) {
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
	 * @param array<string, mixed> $field_object The field object from JetEngine.
	 * @param int                  $post_id The post ID.
	 * @return array<int, array<string, mixed>> Formatted repeater data.
	 */
	protected function format_repeater_field( $value, array $field_object, int $post_id ): array {
		if ( ! is_array( $value ) || empty( $value ) ) {
			return array();
		}

		$formatted_rows = array();
		$index = 0;

		if ( isset( $value[0] ) ) {
			$value = $value[0];
		}

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
					$index
				);
			}

			$formatted_rows[] = $formatted_row;
			$index++;
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
		// Try to get the sub-field object from the parent repeater field
		$sub_field_object = null;
		if ( isset( $parent_field_object['repeater-fields'] ) && is_array( $parent_field_object['repeater-fields'] ) ) {
			foreach ( $parent_field_object['repeater-fields'] as $sub_field ) {
				if ( isset( $sub_field['name'] ) && $sub_field['name'] === $sub_field_key ) {
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

	/**
	 * Return all fields for a user based on the ID.
	 *
	 * @param mixed $object_id User ID that need to be checked.
	 * @return array<string, mixed> Array with all fields.
	 */
	private function get_users_fields( $object_id ): array {
		if ( ! is_numeric( $object_id ) ) {
			return array();
		}
		$object = get_user( (int) $object_id );

		if ( ! $object instanceof WP_User ) {
			return array();
		}

		if ( ! function_exists( 'jet_engine' )
		|| ! isset( jet_engine()->meta_boxes )
		|| ! is_object( jet_engine()->meta_boxes )
		|| ! isset( jet_engine()->meta_boxes->meta_fields )
		|| ! is_array( jet_engine()->meta_boxes->meta_fields )
		) {
			return array();
		}

		$jetengine_fields = jet_engine()->meta_boxes->meta_fields;

		unset( $jetengine_fields['Default user fields'] );

		$jetengine_fields = array_filter(
			$jetengine_fields,
			function ( $fields, $field_key ) {
				return strpos( strtolower( $field_key ), 'user fields' ) !== false;
			},
			ARRAY_FILTER_USE_BOTH
		);

		$formatted_fields = array();
		foreach ( $jetengine_fields as $fields ) {
			if ( is_array( $fields ) ) {
				$formatted_fields = array_merge( $formatted_fields, $fields );
			}
		}

		return $formatted_fields;
	}

	/**
	 * Return all fields for a taxonomy based on the ID.
	 *
	 * @param mixed $object_id Term ID that need to be checked.
	 * @return array<string, mixed> Array with all fields.
	 */
	private function get_terms_fields( $object_id ): array {
		if ( ! is_numeric( $object_id ) ) {
			return array();
		}
		$object = get_term( (int) $object_id );

		if ( ! $object instanceof WP_Term ) {
			return array();
		}

		if ( ! function_exists( 'jet_engine' )
		|| ! isset( jet_engine()->meta_boxes )
		|| ! is_object( jet_engine()->meta_boxes )
		|| ! isset( jet_engine()->meta_boxes->meta_fields )
		|| ! is_array( jet_engine()->meta_boxes->meta_fields )
		|| ! isset( jet_engine()->meta_boxes->meta_fields[ $object->taxonomy ] )
		|| ! is_array( jet_engine()->meta_boxes->meta_fields[ $object->taxonomy ] )
		) {
			return array();
		}

		return jet_engine()->meta_boxes->meta_fields[ $object->taxonomy ];
	}

	/**
	 * Return all fields for option pages.
	 *
	 * @return array<string, mixed> Array with all fields grouped by option page.
	 */
	private function get_options_fields(): array {
		if ( ! function_exists( 'jet_engine' )
		|| ! isset( jet_engine()->options_pages )
		|| ! is_object( jet_engine()->options_pages )
		|| ! isset( jet_engine()->options_pages->registered_pages )
		|| ! is_array( jet_engine()->options_pages->registered_pages )
		) {
			return array();
		}

		return jet_engine()->options_pages->registered_pages;
	}
}
