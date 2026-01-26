<?php
/**
 * ComponentFieldRoutes.php
 *
 * Defines REST API routes for manipulating component custom fields on posts.
 *
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * ComponentFieldRoutes
 *
 * Provides endpoints to update component-related custom fields for posts.
 *
 * @package Etch\RestApi\Routes
 */
class ComponentFieldRoutes extends BaseRoute {

	private const DEFAULT_COMPONENT_META_KEY = 'json';
	private const DEFAULT_COMPONENT_CPTS = array( 'pattern' );

	/**
	 * Returns the route definitions for component custom field endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/component-field/(?P<post_id>\d+)',
				'methods'             => array( 'POST', 'PUT' ),
				'callback'            => array( $this, 'update_component_field' ),
				'args'                => array(
					'post_id' => array(
						'required'          => true,
						'validate_callback' => static function ( $param ): bool {
							return is_numeric( $param ) && (int) $param > 0;
						},
					),
				),
			),
		);
	}

	/**
	 * Updates the component custom field for a post.
	 *
	 * @param WP_REST_Request<array{body?: mixed, value?: mixed, post_id?: int|string}> $request Request containing the post ID and payload.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_component_field( $request ): WP_REST_Response|WP_Error {
		$post_id_param = $request->get_param( 'post_id' );

		if ( ! is_numeric( $post_id_param ) ) {
			return new WP_Error( 'invalid_post_id', 'A valid post ID is required.', array( 'status' => 400 ) );
		}

		$post_id = (int) $post_id_param;

		if ( $post_id <= 0 ) {
			return new WP_Error( 'invalid_post_id', 'A valid post ID is required.', array( 'status' => 400 ) );
		}

		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'post_not_found', 'The specified post could not be found.', array( 'status' => 404 ) );
		}

		$allowed_post_types = $this->get_allowed_post_types();
		if ( ! in_array( $post->post_type, $allowed_post_types, true ) ) {
			return new WP_Error(
				'unsupported_post_type',
				'Component field updates are not permitted for this post type.',
				array( 'status' => 403 )
			);
		}

		$value_to_store = $this->extract_payload( $request );
		if ( null === $value_to_store ) {
			return new WP_Error( 'invalid_payload', 'Request body must contain data to store.', array( 'status' => 400 ) );
		}

		$meta_key = $this->get_component_meta_key( $post_id );

		$this->maybe_remove_default_meta_key( $post_id, $meta_key );

		$update_result = update_post_meta( $post_id, $meta_key, $value_to_store );

		if ( false === $update_result ) {
			$existing_value = get_post_meta( $post_id, $meta_key, true );
			if ( $existing_value !== $value_to_store ) {
				return new WP_Error(
					'component_field_update_failed',
					'Failed to update the component field.',
					array( 'status' => 500 )
				);
			}
		}

		return new WP_REST_Response(
			array(
				'message'  => 'Component field updated successfully.',
				'meta_key' => $meta_key,
			),
			200
		);
	}

	/**
	 * Determine the component meta key using a filter override.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function get_component_meta_key( int $post_id ): string {
		$default_key = self::DEFAULT_COMPONENT_META_KEY;

		$filtered_key = apply_filters( 'etch_define_copy_paste_custom_field', $default_key, $post_id );
		if ( ! is_string( $filtered_key ) ) {
			return $default_key;
		}

		$sanitized_key = trim( $filtered_key );

		return '' !== $sanitized_key ? $sanitized_key : $default_key;
	}

	/**
	 * Remove the legacy default meta key when a custom key is in use.
	 *
	 * @param int    $post_id  Post ID being updated.
	 * @param string $meta_key Meta key chosen for storage.
	 * @return void
	 */
	private function maybe_remove_default_meta_key( int $post_id, string $meta_key ): void {
		if ( self::DEFAULT_COMPONENT_META_KEY === $meta_key ) {
			return;
		}

		delete_post_meta( $post_id, self::DEFAULT_COMPONENT_META_KEY );
	}

	/**
	 * Retrieve the list of post types allowed to use the copy-paste component endpoint.
	 *
	 * @return array<int, string>
	 */
	private function get_allowed_post_types(): array {
		$default_post_types = self::DEFAULT_COMPONENT_CPTS;
		/**
		 * Filters the list of post types that may use the component field copy-paste endpoint.
		 *
		 * @param string[] $allowed_post_types Post type slugs that are permitted. Default contains only `page`.
		 */
		$filtered_post_types = apply_filters( 'etch_define_copy_paste_cpts', $default_post_types );

		if ( ! is_array( $filtered_post_types ) ) {
			return $default_post_types;
		}

		$sanitized_post_types = array();
		foreach ( $filtered_post_types as $post_type ) {
			if ( ! is_string( $post_type ) ) {
				continue;
			}

			$sanitized_post_type = sanitize_key( $post_type );

			if ( '' !== $sanitized_post_type ) {
				$sanitized_post_types[] = $sanitized_post_type;
			}
		}

		return ! empty( $sanitized_post_types ) ? $sanitized_post_types : $default_post_types;
	}

	/**
	 * Extract the payload from the REST request, normalising to a string.
	 *
	 * @param WP_REST_Request<array{body?: mixed, value?: mixed, post_id?: int|string}> $request REST request instance.
	 * @return string|null
	 */
	private function extract_payload( WP_REST_Request $request ): ?string {
		$json_params = $request->get_json_params();

		if ( is_array( $json_params ) ) {
			if ( array_key_exists( 'body', $json_params ) ) {
				$payload = $json_params['body'];

				if ( is_array( $payload ) && array_key_exists( 'body', $payload ) && 1 === count( $payload ) ) {
					$payload = $payload['body'];
				}

				return $this->normalise_payload_value( $payload );
			}

			// Allow clients to send value directly without wrapping in `body`.
			if ( array_key_exists( 'value', $json_params ) ) {
				return $this->normalise_payload_value( $json_params['value'] );
			}

			// If the payload is an object/array without explicit key, store it as JSON.
			if ( ! empty( $json_params ) ) {
				$encoded_payload = wp_json_encode( $json_params );

				return false === $encoded_payload ? null : $encoded_payload;
			}
		}

		$raw_body = $request->get_body();
		if ( '' === $raw_body ) {
			return null;
		}

		return $this->normalise_payload_value( $raw_body );
	}

	/**
	 * Normalises mixed payload values to strings for storage.
	 *
	 * @param mixed $value The value to normalise.
	 * @return string|null
	 */
	private function normalise_payload_value( $value ): ?string {
		if ( null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			return $value;
		}

		$encoded_value = wp_json_encode( $value );

		return false === $encoded_value ? null : $encoded_value;
	}
}
