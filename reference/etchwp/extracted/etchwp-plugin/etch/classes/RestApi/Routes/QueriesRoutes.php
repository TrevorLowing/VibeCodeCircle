<?php
/**
 * QueriesRoutes.php
 *
 * This file contains the QueriesRoutes class which defines REST API routes for handling queries.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Routes
 */

declare(strict_types=1);
namespace Etch\RestApi\Routes;

use Etch\Helpers\WideEventLogger;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Etch\Traits\DynamicData;

/**
 * QueriesRoutes
 *
 * This class defines REST API endpoints for retrieving and updating query options.
 *
 * @package Etch\RestApi\Routes
 */
class QueriesRoutes extends BaseRoute {
	use DynamicData;

	/**
	 * Returns the route definitions for queries endpoints.
	 *
	 * @return array<array{
	 *     route: string,
	 *     methods: string|array<string>,
	 *     callback: callable,
	 *     permission_callback?: callable,
	 *     args?: array<string, array{required?: bool, type?: string}>
	 * }>
	 */
	protected function get_routes(): array {
		return array(
			array(
				'route'               => '/queries',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_queries' ),
			),
			array(
				'route'               => '/queries',
				'methods'             => 'PUT',
				'callback'            => array( $this, 'update_queries' ),
			),
			array(
				'route'               => '/queries/wp-query',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_wp_query_results' ),
				'args'                => array(
					'query_args' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			),
			array(
				'route'               => '/queries/wp-terms',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_wp_terms_results' ),
				'args'                => array(
					'query_args' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			),
			array(
				'route'               => '/queries/wp-users',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_wp_users_results' ),
				'args'                => array(
					'query_args' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			),
			array(
				'route'               => '/queries/main-query-preview',
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_main_query_preview' ),
				'args'                => array(
					'slug'       => array(
						'required' => true,
						'type'     => 'string',
					),
					'query_args' => array(
						'required' => false,
						'type'     => 'string',
					),
				),
			),
		);
	}

	/**
	 * Update Etch queries.
	 * This function updates the Etch queries in the database.
	 *
	 * @param WP_REST_Request<array{}> $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_queries( $request ): WP_REST_Response|WP_Error {
		$new_queries = json_decode( $request->get_body(), true );
		if ( ! is_array( $new_queries ) ) {
			WideEventLogger::failure( 'api.queries.validation', 'Queries must be provided as an array', array( 'error_code' => 'invalid_queries' ) );
			return new WP_Error( 'invalid_queries', 'Queries must be provided as an array', array( 'status' => 400 ) );
		}

		$option_name = 'etch_queries';

		$existing_queries = get_option( $option_name, array() );
		$update_result = true;
		if ( $new_queries !== $existing_queries ) {
			$update_result = update_option( $option_name, $new_queries );
		}

		if ( $update_result ) {
			WideEventLogger::set( 'api.queries.update', 'success' );
			return new WP_REST_Response( array( 'message' => 'Queries updated successfully' ), 200 );
		} else {
			WideEventLogger::failure( 'api.queries.update', 'Failed to update queries', array( 'error_code' => 'update_failed' ) );
			return new WP_Error( 'update_failed', 'Failed to update queries', array( 'status' => 500 ) );
		}
	}

	/**
	 * Get Etch queries.
	 *
	 * @return WP_REST_Response Response object with queries.
	 */
	public function get_queries(): WP_REST_Response {
		$option_name = 'etch_queries';
		$queries = get_option( $option_name, array() );

		// Ensure object is returned even if no queries are set (avoid returning an empty array).
		return new WP_REST_Response( (object) $queries, 200 );
	}

	/**
	 * Get WP query results based on provided arguments.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @phpstan-param WP_REST_Request<array<string,mixed>> $request
	 */
	public function get_wp_query_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$query_args = $request->get_param( 'query_args' );

		// Convert to array if it's a JSON string
		if ( is_string( $query_args ) ) {
			$query_args = json_decode( $query_args, true );
		}

		if ( empty( $query_args ) || ! is_array( $query_args ) ) {
			WideEventLogger::failure( 'api.queries.validation', 'Query arguments must be provided as an array', array( 'error_code' => 'invalid_query_args' ) );
			return new WP_Error(
				'invalid_query_args',
				'Query arguments must be provided as an array',
				array( 'status' => 400 )
			);
		}

		// Run the query
		$query = new \WP_Query( $query_args );

		/**
		 * Posts.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		$posts = array();

		// Build the response
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$posts[] = $this->get_dynamic_data( $post );
			}
			wp_reset_postdata();
		}

		$response = new WP_REST_Response(
			array(
				'data'  => $posts,
				'total' => $query->found_posts,
				'pages' => $query->max_num_pages,
			),
			200
		);

		// Set caching headers
		$this->set_caching_headers( 30, $response );

		return $response;
	}

	/**
	 * Get WP terms results based on provided arguments.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @phpstan-param WP_REST_Request<array<string,mixed>> $request
	 */
	public function get_wp_terms_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$query_args = $request->get_param( 'query_args' );

		// Convert to array if it's a JSON string
		if ( is_string( $query_args ) ) {
			$query_args = json_decode( $query_args, true );
		}

		if ( empty( $query_args ) || ! is_array( $query_args ) ) {
			WideEventLogger::failure( 'api.queries.validation', 'Query arguments must be provided as an array', array( 'error_code' => 'invalid_query_args' ) );
			return new WP_Error(
				'invalid_query_args',
				'Query arguments must be provided as an array',
				array( 'status' => 400 )
			);
		}

		$query = new \WP_Term_Query( $query_args );

		/**
		 * Terms.
		 *
		 * @var array<\WP_Term>
		 */
		$terms = $query->get_terms();

		foreach ( $terms as $index  => $term ) {
			$terms[ $index ] = $this->get_dynamic_term_data( $term );
		}

		$response = new WP_REST_Response(
			array(
				'data'  => $terms,
			),
			200
		);

		// Set caching headers
		$this->set_caching_headers( 30, $response );

		return $response;
	}

	/**
	 * Get WP users results based on provided arguments.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @phpstan-param WP_REST_Request<array<string,mixed>> $request
	 */
	public function get_wp_users_results( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$query_args = $request->get_param( 'query_args' );

		if ( is_string( $query_args ) ) {
			$query_args = json_decode( $query_args, true );
		}

		if ( empty( $query_args ) || ! is_array( $query_args ) ) {
			WideEventLogger::failure( 'api.queries.validation', 'Query arguments must be provided as an array', array( 'error_code' => 'invalid_query_args' ) );
			return new WP_Error(
				'invalid_query_args',
				'Query arguments must be provided as an array',
				array( 'status' => 400 )
			);
		}

		$query = new \WP_User_Query( $query_args );

		$users = $query->get_results();

		foreach ( $users as $index  => $user ) {
			$users[ $index ] = $this->get_dynamic_user_data( $user );
		}

		$response = new WP_REST_Response(
			array(
				'data'  => $users,
			),
			200
		);

		// Set caching headers
		$this->set_caching_headers( 30, $response );

		return $response;
	}

	/**
	 * Get main query preview results based on template slug.
	 * Simulates the main query for builder preview based on the template being edited.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 *
	 * @phpstan-param WP_REST_Request<array<string,mixed>> $request
	 */
	public function get_main_query_preview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug = $request->get_param( 'slug' );

		if ( empty( $slug ) || ! is_string( $slug ) ) {
			WideEventLogger::failure( 'api.queries.validation', 'Template slug must be provided as a string', array( 'error_code' => 'invalid_slug' ) );
			return new WP_Error(
				'invalid_slug',
				'Template slug must be provided as a string',
				array( 'status' => 400 )
			);
		}

		// Parse the template slug to determine what query to run
		$base_args = $this->parse_template_slug_for_query( $slug );

		// Get optional query args overrides
		$query_args = $request->get_param( 'query_args' );
		if ( is_string( $query_args ) ) {
			$query_args = json_decode( $query_args, true );
		}

		// Merge base args with overrides (overrides take precedence)
		if ( is_array( $query_args ) ) {
			$base_args = array_merge( $base_args, $query_args );
		}

		// Ensure we always have post_status
		if ( ! isset( $base_args['post_status'] ) ) {
			$base_args['post_status'] = 'publish';
		}

		// Run the query
		$query = new \WP_Query( $base_args );

		/**
		 * Posts.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		$posts = array();

		// Build the response
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post = get_post();

				if ( ! $post instanceof \WP_Post ) {
					continue;
				}

				$post_data = $this->get_dynamic_data( $post );
				// Add raw main query post data under 'main' key
				$post_data['main'] = $this->get_main_query_post_data( $post );
				$posts[] = $post_data;
			}
			wp_reset_postdata();
		}

		$response = new WP_REST_Response(
			array(
				'data'  => $posts,
				'total' => $query->found_posts,
				'pages' => $query->max_num_pages,
			),
			200
		);

		// Set caching headers
		$this->set_caching_headers( 30, $response );

		return $response;
	}

	/**
	 * Get normalized main query post data.
	 *
	 * Returns the raw WP_Post object properties as an array, preserving
	 * all default WordPress post fields from the main query.
	 *
	 * @param \WP_Post $post The post object from the main query.
	 * @return array<string, mixed> Normalized post data array.
	 */
	private function get_main_query_post_data( \WP_Post $post ): array {
		// Get all object properties as an array
		$post_data = get_object_vars( $post );

		// Ensure all values are arrays/scalars (no objects)
		// This recursively normalizes any nested objects
		$normalized_data = $this->normalize_data_recursively( $post_data );

		// Guard against filters/extensions returning non-arrays
		return is_array( $normalized_data ) ? $normalized_data : array();
	}

	/**
	 * Parse template slug to determine the appropriate WP_Query arguments.
	 *
	 * Template slugs follow WordPress conventions:
	 * - archive-{post_type} - post type archive
	 * - taxonomy-{taxonomy} - taxonomy archive
	 * - home - blog posts
	 * - search - search results
	 * - index - fallback
	 *
	 * @param string $slug The template slug.
	 * @return array<string, mixed> WP_Query arguments.
	 */
	private function parse_template_slug_for_query( string $slug ): array {
		// Default args
		$default_args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 10,
		);

		// archive-{post_type} - post type archive
		if ( preg_match( '/^archive-(.+)$/', $slug, $matches ) ) {
			$post_type = $matches[1];
			// Verify post type exists
			if ( post_type_exists( $post_type ) ) {
				return array_merge(
					$default_args,
					array(
						'post_type' => $post_type,
					)
				);
			}
		}

		// taxonomy-{taxonomy} - taxonomy archive
		if ( preg_match( '/^taxonomy-(.+)$/', $slug, $matches ) ) {
			$taxonomy = $matches[1];
			// Verify taxonomy exists
			if ( taxonomy_exists( $taxonomy ) ) {
				// Get first term from this taxonomy for preview
				$terms = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'number'     => 1,
						'hide_empty' => false,
					)
				);

				$tax_query = array(
					array(
						'taxonomy' => $taxonomy,
						'operator' => 'EXISTS',
					),
				);

				// If we have a term, use it for more accurate preview
				if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
					$tax_query = array(
						array(
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => array( $terms[0]->term_id ),
						),
					);
				}

				return array_merge(
					$default_args,
					array(
						'post_type' => 'any',
						'tax_query' => $tax_query,
					)
				);
			}
		}

		// home - blog posts
		if ( 'home' === $slug ) {
			return array_merge(
				$default_args,
				array(
					'post_type' => 'post',
				)
			);
		}

		// search - search results (sample search)
		if ( 'search' === $slug ) {
			return array_merge(
				$default_args,
				array(
					'post_type' => 'any',
					's'         => '', // Empty search returns all posts
				)
			);
		}

		// index or any other fallback - return posts
		return $default_args;
	}
}
