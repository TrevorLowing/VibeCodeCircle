<?php
/**
 * PatternsRoutes.php
 *
 * This file contains the PatternsRoutes class which defines REST API routes for handling synced patterns (wp_block).
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\Flag;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * PatternsRoutes
 *
 * This class defines REST API endpoints for retrieving and managing synced patterns (wp_block CPT).
 *
 * @package Etch\RestApi\Routes
 */
class PatternsRoutes extends BaseRoute {

	/**
	 * Returns the route definitions for patterns endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/patterns',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_patterns' ),
			),
			array(
				'route'               => '/patterns/list',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_patterns_list' ),
			),
			array(
				'route'               => '/patterns',
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_pattern' ),
			),
			array(
				'route'               => '/patterns/(?P<id>\d+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_pattern' ),
			),
			array(
				'route'               => '/patterns/(?P<id>\d+)',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_pattern' ),
			),
			array(
				'route'               => '/patterns/(?P<id>\d+)',
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_pattern' ),
			),
		);
	}

	/**
	 * Get all patterns.
	 *
	 * @param WP_REST_Request<array{}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_patterns( $request ) {
		$args = array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'etch_component_html_key',
					'compare' => 'EXISTS',
				),
			),
		);

		$posts = get_posts( $args );

		$patterns = array_map(
			function ( $post ) {
				$properties = get_post_meta( $post->ID, 'etch_component_properties', true );
				if ( ! is_array( $properties ) ) {
					$properties = array();
				}

				return array(
					'id'          => $post->ID,
					'name'        => $post->post_title,
					'key'         => get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '',
					'blocks'      => parse_blocks( $post->post_content ),
					'properties'  => $properties,
					'description' => $post->post_excerpt,
					'legacyId'    => get_post_meta( $post->ID, 'etch_component_legacy_id', true ),
				);
			},
			$posts
		);

		return new WP_REST_Response( $patterns, 200 );
	}

	/**
	 * Get all patterns as a list of names and keys.
	 *
	 * @param WP_REST_Request<array{}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_patterns_list( $request ) {
		$args = array(
			'post_type'      => 'wp_block',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => 'etch_component_html_key',
					'compare' => 'EXISTS',
				),
			),
		);

		$posts = get_posts( $args );

		$patterns = array_map(
			function ( $post ) {
				return array(
					'id'   => $post->ID,
					'key'  => get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '',
					'name' => $post->post_title,
					'legacyId' => get_post_meta( $post->ID, 'etch_component_legacy_id', true ),
				);
			},
			$posts
		);

		return new WP_REST_Response( $patterns, 200 );
	}

	/**
	 * Get a single pattern.
	 *
	 * @param WP_REST_Request<array{id: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_pattern( $request ) {
		$id = $request->get_param( 'id' );
		$post = get_post( (int) $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		$properties = get_post_meta( $post->ID, 'etch_component_properties', true );
		if ( ! is_array( $properties ) ) {
			$properties = array();
		}

		$response_data = array(
			'id'          => $post->ID,
			'name'        => $post->post_title,
			'key'         => get_post_meta( $post->ID, 'etch_component_html_key', true ) ?? '',
			'blocks'      => parse_blocks( $post->post_content ),
			'properties'  => $properties,
			'description' => $post->post_excerpt,
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Create a pattern.
	 *
	 * @param WP_REST_Request<array{name?: string, blocks?: array<int|string, mixed>, description?: string, properties?: array<int|string, mixed>, key?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_pattern( $request ) {
		if ( Flag::is_on( 'ENABLE_WAF_BLOCK_REQUEST_WORKAROUND' ) ) {
			$body = json_decode( $request->get_body(), true );
			if ( is_string( $body ) ) {
				$body = json_decode( $body, true );
			}
			if ( ! is_array( $body ) ) {
				$body = $request->get_json_params();
			}
		} else {
			$body = $request->get_json_params();
		}

		$post_data = array(
			'post_type'    => 'wp_block',
			'post_title'   => sanitize_text_field( $body['name'] ?? 'New Pattern' ),
			'post_content' => wp_slash( serialize_blocks( $body['blocks'] ?? array() ) ),
			'post_excerpt' => sanitize_text_field( $body['description'] ?? '' ),
			'post_status'  => 'publish',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $body['properties'] ) ) {
			update_post_meta( $post_id, 'etch_component_properties', $body['properties'] );
		}

		if ( isset( $body['key'] ) ) {
			update_post_meta( $post_id, 'etch_component_html_key', $body['key'] );
		}

		/**
		 * Create a GET request for retrieving the pattern.
		 *
		 * @var WP_REST_Request<array{id: string}> $get_request
		 */
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_url_params( array( 'id' => (string) $post_id ) );

		return $this->get_pattern( $get_request );
	}

	/**
	 * Update a pattern.
	 *
	 * @param WP_REST_Request<array{id: string, name?: string, blocks?: array<string, mixed>, description?: string, properties?: array<string, mixed>, key?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_pattern( $request ) {
		$id = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		if ( Flag::is_on( 'ENABLE_WAF_BLOCK_REQUEST_WORKAROUND' ) ) {
			$body = json_decode( $request->get_body(), true );
			if ( is_string( $body ) ) {
				$body = json_decode( $body, true );
			}
			if ( ! is_array( $body ) ) {
				$body = $request->get_json_params();
			}
		} else {
			$body = $request->get_json_params();
		}

		$post_data = array(
			'ID' => $id,
		);

		if ( isset( $body['name'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $body['name'] );
		}

		if ( isset( $body['blocks'] ) ) {
			$post_data['post_content'] = wp_slash( serialize_blocks( $body['blocks'] ) );
		}

		if ( isset( $body['description'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $body['description'] );
		}

		if ( isset( $body['key'] ) ) {
			update_post_meta( $id, 'etch_component_html_key', $body['key'] );
		}

		$post_id = wp_update_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( isset( $body['properties'] ) ) {
			update_post_meta( $id, 'etch_component_properties', $body['properties'] );
		}

		/**
		 * Create a GET request for retrieving the pattern.
		 *
		 * @var WP_REST_Request<array{id: string}> $get_request
		 */
		$get_request = new WP_REST_Request( 'GET' );
		$get_request->set_url_params( array( 'id' => (string) $id ) );

		return $this->get_pattern( $get_request );
	}

	/**
	 * Delete a pattern.
	 *
	 * @param WP_REST_Request<array{id: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_pattern( $request ) {
		$id = (int) $request->get_param( 'id' );
		$post = get_post( $id );

		if ( null === $post || 'wp_block' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Pattern not found.', array( 'status' => 404 ) );
		}

		$deleted = wp_delete_post( $id, true );

		if ( false === $deleted ) {
			return new WP_Error( 'delete_failed', 'Failed to delete pattern.', array( 'status' => 500 ) );
		}

		return new WP_REST_Response( array( 'message' => 'Pattern deleted successfully.' ), 200 );
	}
}
