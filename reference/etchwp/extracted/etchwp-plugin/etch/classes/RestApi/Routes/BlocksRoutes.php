<?php
/**
 * BlocksRoutes.php
 *
 * This file contains the BlocksRoutes class which defines REST API routes for handling blocks.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\Logger;
use Etch\Helpers\Flag;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use WP_Post;
use Etch\Traits\DynamicData;
use Etch\Helpers\SvgLoader;
use Etch\Helpers\WideEventLogger;

/**
 * BlocksRoutes
 *
 * This class defines REST API endpoints for retrieving and updating blocks of a WordPress post.
 *
 * @package Etch\RestApi\Routes
 */
class BlocksRoutes extends BaseRoute {
	use DynamicData;

	/**
	 * Returns the route definitions for blocks endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/post/(?P<post_id>\d+)',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_post' ),
			),
			array(
				'route'               => '/post/(?P<post_id>\d+)/blocks',
				'methods'             => 'POST',
				'callback'            => array( $this, 'set_post_blocks' ),
				'args'                => array(
					'post_id' => array(
						'validate_callback' => function ( $param, $request, $key ) {
							return is_numeric( $param );
						},
					),
				),
			),
		);
	}

	/**
	 * Get the post blocks.
	 *
	 * @param WP_REST_Request<array{post_id: string}>|WP_Post $requestOrPost The REST request object or WP_Post object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_post( $requestOrPost ) {
		/**
		 * The post object retrieved from the request.
		 *
		 * @var WP_Post|WP_Error $post
		 */
		$post = $this->get_post_from_request( $requestOrPost );

		// Check if get_post_from_request returned an error
		if ( is_wp_error( $post ) ) {
			WideEventLogger::failure( 'api.blocks.validation', $post->get_error_message(), array( 'error_code' => $post->get_error_code() ) );
			return $post;
		}

		$blocks = $this->get_blocks_from_post( $post );

		// If blocks is an error, return it directly
		if ( is_wp_error( $blocks ) ) {
			WideEventLogger::failure( 'api.blocks.validation', $blocks->get_error_message(), array( 'error_code' => $blocks->get_error_code() ) );
			return $blocks;
		}

		$metadata = $this->get_dynamic_data( $post );
		$template = $this->get_template_data( $post );

		// Only create WP_REST_Response for successful cases
		return new WP_REST_Response(
			array(
				'blocks'   => $blocks,
				'metadata' => $metadata,
				'template' => $template,
			),
			200
		);
	}

	/**
	 * Get the posts blocks.
	 *
	 * @param WP_REST_Request<array{post_id: string}>|WP_Post $requestOrPost The REST request object or WP_Post object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_posts( $requestOrPost ) {
		/**
		 * The post object retrieved from the request.
		 *
		 * @var WP_Post|WP_Error $post
		 */
		$post = $this->get_post_from_request( $requestOrPost );

		// Check if get_post_from_request returned an error
		if ( is_wp_error( $post ) ) {
			WideEventLogger::failure( 'api.blocks.validation', $post->get_error_message(), array( 'error_code' => $post->get_error_code() ) );
			return $post;
		}

		$blocks = $this->get_blocks_from_post( $post );

		// If blocks is an error, return it directly
		if ( is_wp_error( $blocks ) ) {
			WideEventLogger::failure( 'api.blocks.validation', $blocks->get_error_message(), array( 'error_code' => $blocks->get_error_code() ) );
			return $blocks;
		}

		$metadata = $this->get_dynamic_data( $post );
		$template = $this->get_template_data( $post );

		// Only create WP_REST_Response for successful cases
		return new WP_REST_Response(
			array(
				'blocks'   => $blocks,
				'metadata' => $metadata,
				'template' => $template,
			),
			200
		);
	}

	/**
	 * Get the post blocks by id.
	 *
	 * @param WP_REST_Request<array{post_id: string}>|WP_Post $requestOrPost The REST request object or WP_Post object.
	 * @return WP_Post|WP_Error
	 */
	private function get_post_from_request( $requestOrPost ) {
		$post_id = null;
		if ( is_a( $requestOrPost, 'WP_REST_Request' ) ) {
			$post_id = absint( $requestOrPost->get_param( 'post_id' ) );
		} else if ( is_a( $requestOrPost, 'WP_Post' ) ) {
			$post_id = $requestOrPost->ID;
		}

		if ( ! $post_id ) {
			return new WP_Error( 'no_post_id', 'No valid post id provided', array( 'status' => 412 ) );
		}

		$post = is_a( $requestOrPost, 'WP_REST_Request' ) ?
			get_post( $post_id ) :
			$requestOrPost;

		if ( ! $post ) {
			return new WP_Error( 'no_post', 'Post not found', array( 'status' => 404 ) );
		}

		return $post;
	}

	/**
	 * Get the post blocks.
	 *
	 * @param \WP_Post $post The WP_Post object.
	 * @return array<int, array<string, mixed>>|WP_Error Array of blocks or error
	 */
	private function get_blocks_from_post( \WP_Post $post ) {
		if ( ! $post instanceof \WP_Post ) {
			return new WP_Error( 'invalid_post', 'Invalid post object', array( 'status' => 404 ) );
		}

		if ( ! has_blocks( $post->post_content ) ) {
			return array();
		}

		$blocks = parse_blocks( $post->post_content );

		// Remove all blocks that have no blockName.
		$blocks = array_values(
			array_filter(
				$blocks,
				function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);

		return $blocks;
	}

	/**
	 * Sets the blocks for a given post.
	 *
	 * @param WP_REST_Request<array{post_id: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_post_blocks( $request ) {
		$post_id = $request->get_param( 'post_id' );

		if ( ! $post_id ) {
			WideEventLogger::failure( 'api.blocks.validation', 'No valid post id provided', array( 'error_code' => 'invalid_post_id' ) );
			return new WP_Error( 'no_post_id', 'No valid post id provided', array( 'status' => 412 ) );
		}

		$post_id = absint( $post_id );

		$body = json_decode( $request->get_body(), true );

		if ( Flag::is_on( 'ENABLE_WAF_BLOCK_REQUEST_WORKAROUND' ) && is_string( $body ) ) {
			$body = json_decode( $body, true );
		}

		if ( ! is_array( $body ) ) {
			WideEventLogger::failure( 'api.blocks.validation', 'Invalid block data provided', array( 'error_code' => 'invalid_block_data' ) );
			return new WP_Error( 'invalid_data', 'Invalid block data provided', array( 'status' => 400 ) );
		}

		$serialized_blocks = serialize_blocks( $body );
		$serialized_blocks = wp_slash( $serialized_blocks );
		$serialized_blocks = str_replace( '\u002d', '-', $serialized_blocks );

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $serialized_blocks,
			),
			true
		);

		if ( is_wp_error( $updated ) ) {
			WideEventLogger::failure( 'api.blocks.update', 'Failed to update the post', array( 'error_code' => 'update_failed' ) );
			return new WP_Error(
				'update_failed',
				'Failed to update the post: ' . $updated->get_error_message(),
				array( 'status' => 500 )
			);
		}

		// Reset remote SVG cache to ensure updated SVGs are fetched
		SvgLoader::bump_cache_version();

		WideEventLogger::set( 'api.queries.update', 'success' );
		return new WP_REST_Response(
			array(
				'message' => 'Post updated successfully!',
				'post_id' => $post_id,
			),
			200
		);
	}
}
