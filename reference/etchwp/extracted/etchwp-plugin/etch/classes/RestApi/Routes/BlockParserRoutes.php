<?php
/**
 * BlockParserRoutes.php
 *
 * This file contains the BlockParserRoutes class which defines REST API routes for parsing Gutenberg block HTML.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * BlockParserRoutes
 *
 * This class defines REST API endpoints for parsing Gutenberg block HTML strings into block objects.
 *
 * @package Etch\RestApi\Routes
 */
class BlockParserRoutes extends BaseRoute {

	/**
	 * Returns the route definitions for block parser endpoints.
	 *
	 * @return array<array{route: string, methods: string|array<string>, callback: callable, permission_callback?: callable, args?: array<string, mixed>}>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'    => '/blocks/parse',
				'methods'  => 'POST',
				'callback' => array( $this, 'parse_blocks' ),
			),
		);
	}

	/**
	 * Parse Gutenberg block HTML content into block objects.
	 *
	 * @param WP_REST_Request<array{content?: string}> $request The REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function parse_blocks( $request ) {
		$body    = $request->get_json_params();
		$content = $body['content'] ?? '';

		if ( ! is_string( $content ) ) {
			return new WP_Error(
				'invalid_content',
				'Content must be a string',
				array( 'status' => 400 )
			);
		}

		$blocks = parse_blocks( $content );

		// Remove all blocks that have no blockName (whitespace-only blocks).
		$blocks = array_values(
			array_filter(
				$blocks,
				function ( $block ) {
					return ! empty( $block['blockName'] );
				}
			)
		);

		return new WP_REST_Response( $blocks, 200 );
	}
}
