<?php
/**
 * Taxonomy Routes for the REST API.
 *
 * @package Etch
 * @gplv2
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use WP_REST_Response;
use WP_Error;
use WP_REST_Request;
use Etch\Traits\DynamicData;
use WP_Term;

/**
 * TaxonomyRoutes
 *
 * This class defines REST API endpoints for taxonomy and term information.
 *
 * @package Etch\RestApi\Routes
 */
class TaxonomyRoutes extends BaseRoute {

	use DynamicData;

	/**
	 * Get the routes for taxonomies.
	 *
	 * @return array<array{
	 *     route: string,
	 *     methods: string,
	 *     callback: callable,
	 * }> The routes for taxonomies.
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route' => '/taxonomy',
				'methods' => 'GET',
				'callback' => array( $this, 'get_taxonomy' ),
				'args' => array(
					'taxonomy_slug' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Taxonomy slug to fetch',
					),
				),
			),
			array(
				'route' => '/term',
				'methods' => 'GET',
				'callback' => array( $this, 'get_term' ),
				'args' => array(
					'taxonomy_slug' => array(
						'required' => true,
						'type' => 'string',
						'description' => 'Taxonomy slug to get term from',
					),
					'term_slug' => array(
						'required' => false,
						'type' => 'string',
						'description' => 'Specific term slug to fetch (optional - gets first if not provided)',
					),
				),
			),
		);
	}

	/**
	 * Get taxonomy information by slug.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error The taxonomy information response or an error.
	 */
	public function get_taxonomy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$taxonomy_slug = $request->get_param( 'taxonomy_slug' );

		if ( ! is_string( $taxonomy_slug ) ) {
			return new WP_Error( 'invalid_taxonomy_slug', 'Invalid taxonomy slug.', array( 'status' => 400 ) );
		}

		if ( ! taxonomy_exists( $taxonomy_slug ) ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found.', array( 'status' => 404 ) );
		}

		$taxonomy_info = $this->get_dynamic_tax_data( $taxonomy_slug );
		$response = new WP_REST_Response( $taxonomy_info, 200 );
		return $response;
	}

	/**
	 * Get term information by taxonomy and optional term slug.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error The term information response or an error.
	 */
	public function get_term( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$taxonomy_slug = $request->get_param( 'taxonomy_slug' );
		$term_slug = $request->get_param( 'term_slug' );

		if ( ! is_string( $taxonomy_slug ) ) {
			return new WP_Error( 'invalid_taxonomy_slug', 'Invalid taxonomy slug.', array( 'status' => 400 ) );
		}

		if ( ! taxonomy_exists( $taxonomy_slug ) ) {
			return new WP_Error( 'taxonomy_not_found', 'Taxonomy not found.', array( 'status' => 404 ) );
		}

		// Specific term requested
		if ( $term_slug && is_string( $term_slug ) ) {
			$term = get_term_by( 'slug', $term_slug, $taxonomy_slug );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'term_not_found', 'Term not found.', array( 'status' => 404 ) );
			}

			$term_info = $this->get_dynamic_term_data( $term );
			$response = new WP_REST_Response( $term_info, 200 );
			return $response;
		}

		// Get first term from taxonomy
		$terms = get_terms(
			array(
				'taxonomy' => $taxonomy_slug,
				'hide_empty' => false,
				'number' => 1,
				'orderby' => 'term_order',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$response = new WP_REST_Response( array(), 200 );
			return $response;
		}

		$first_term = $terms[0];
		$term_info = $this->get_dynamic_term_data( $first_term );
		$response = new WP_REST_Response( $term_info, 200 );
		return $response;
	}
}
