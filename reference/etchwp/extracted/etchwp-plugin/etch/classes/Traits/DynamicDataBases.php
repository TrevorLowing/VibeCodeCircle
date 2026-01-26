<?php
/**
 * DynamicDataBases trait.
 *
 * This trait contains the base data retrieval methods for WordPress objects.
 * These methods provide core properties without relationships or ACF data.
 *
 * @package Etch\Traits
 */

declare(strict_types=1);

namespace Etch\Traits;

use DateTime;
use Etch\Helpers\Flag;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

trait DynamicDataBases {

	/**
	 * Returns the base dynamic data for a user (core properties only).
	 *
	 * @param WP_User $user The user object.
	 * @return array<string, mixed> The base dynamic data.
	 */
	protected function get_base_user_data( WP_User $user ): array {
		$roles = $user->roles;
		$primary_role = ! empty( $roles ) ? $roles[0] : '';

		return array(
			'id'               => $user->ID,
			'login'            => $user->user_login,
			'email'            => $user->user_email,
			'displayName'     => $user->display_name,
			'firstName'       => $user->first_name,
			'lastName'        => $user->last_name,
			'nickname'         => $user->nickname,
			'description'      => $user->description,
			'userUrl'         => $user->user_url,
			'registered'       => $user->user_registered,
			'userRoles'      => $user->roles,
			'userRole'       => $primary_role,
			'capabilities'     => $user->allcaps,
			'fullName'        => trim( $user->first_name . ' ' . $user->last_name ),
			'avatar'           => get_avatar_url( $user->ID ),
			'loggedIn'         => $user->exists(),
		);
	}

	/**
	 * Returns the dynamic site data.
	 *
	 * @return array<string, mixed> The site data.
	 */
	protected function get_dynamic_site_data(): array {
		return array(
			'name'        => get_bloginfo( 'name' ),
			'description' => get_bloginfo( 'description' ),
			'home_url'    => home_url(),
			'url'         => site_url(),
			'version'     => get_bloginfo( 'version' ),
			'language'         => get_bloginfo( 'language' ),
			'locale'         => get_locale(),
			'isMultisite'     => is_multisite(),
			'currentDate' => time(),
		);
	}

	/**
	 * Returns the base dynamic data for a term (core properties only).
	 *
	 * @param WP_Term $term The term object.
	 * @return array<string, mixed> The base dynamic data.
	 */
	protected function get_base_term_data( WP_Term $term ): array {
		return array(
			'id'            => $term->term_id,
			'term_id'       => $term->term_id,
			'name'          => $term->name,
			'slug'          => $term->slug,
			'description'   => $term->description,
			'parent'        => $term->parent,
			'count'         => $term->count,
			'taxonomy'      => $term->taxonomy,
			'permalink'     => get_term_link( $term ),
		);
	}

	/**
	 * Returns the base dynamic data for a post (core properties only, no relationships).
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> The base dynamic data.
	 */
	protected function get_base_post_data( WP_Post $post ): array {
		$post_data = get_object_vars( $post );

		$data = array(
			'id'            => $post->ID,
			'title'         => html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ),
			'slug'          => $post->post_name,
			'excerpt'       => $this->get_the_post_excerpt( $post ),
			'content'       => $post->post_content ? $post->post_content : '',
			'permalink'     => array(
				'relative' => wp_make_link_relative( get_permalink( $post ) ) ? wp_make_link_relative( get_permalink( $post ) ) : '',
				'full'     => get_permalink( $post ) ? get_permalink( $post ) : '',
			),
			'featuredImage' => get_the_post_thumbnail_url( $post, 'full' ) ? get_the_post_thumbnail_url( $post, 'full' ) : '',
			'date'          => get_the_date( 'c', $post ),
			'modified'      => get_the_modified_date( 'c', $post ),
			'status'        => $post->post_status,
			'type'          => $post->post_type,
			'thumbnail'     => has_post_thumbnail( $post->ID ) ? get_the_post_thumbnail_url( $post->ID, 'full' ) : '',
			'image'         => $this->get_base_image_data( get_post_thumbnail_id( $post ) ),
			'readingTime'   => (string) $this->calculate_reading_time( $post ),
		);

		$post_data['post_excerpt'] = $data['excerpt'];

		return wp_parse_args( $data, $post_data );
	}

	/**
	 * Returns the options page data.
	 *
	 * @return array<string, mixed> The base dynamic data.
	 */
	protected function get_base_option_pages_data(): array {
		$data = array();

		return $data;
	}

	/**
	 * Get comprehensive image data from URL or attachment ID.
	 *
	 * @param string|int|false $image_input Image URL, attachment ID, or false.
	 * @return array<string, mixed> Comprehensive image data array.
	 */
	protected function get_base_image_data( $image_input ): array {
		// Handle empty input
		if ( empty( $image_input ) ) {
			return array();
		}

		$attachment_id = null;

		// Determine if input is URL or ID
		if ( is_numeric( $image_input ) ) {
			$attachment_id = (int) $image_input;
		} elseif ( is_string( $image_input ) ) {
			// Try to get attachment ID from URL
			$attachment_id = attachment_url_to_postid( $image_input );

			// If we can't find the attachment ID, return basic data
			if ( ! $attachment_id ) {
				return array(
					'url' => $image_input,
					'alt' => '',
					'title' => '',
					'caption' => '',
					'description' => '',
					'sizes' => array(),
					'srcset' => '',
				);
			}
		}

		// If we still don't have a valid attachment ID, return empty array
		if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
			return array();
		}

		// Get attachment post object for meta data
		$attachment = get_post( $attachment_id );
		if ( ! $attachment ) {
			return array();
		}

		// Get image metadata
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

		// Build sizes array
		$sizes = array();
		$image_sizes = get_intermediate_image_sizes();
		$image_sizes[] = 'full'; // Add full size

		foreach ( $image_sizes as $size ) {
			$image_src = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $image_src ) {
				$sizes[ $size ] = array(
					'url' => $image_src[0],
					'width' => $image_src[1],
					'height' => $image_src[2],
				);
			}
		}

		// Get full size image data
		$full_image = wp_get_attachment_image_src( $attachment_id, 'full' );
		$main_url = $full_image ? $full_image[0] : '';

		// Generate srcset string
		$srcset = wp_get_attachment_image_srcset( $attachment_id );

		return array(
			'id' => $attachment_id,
			'url' => $main_url,
			'alt' => $alt_text ? $alt_text : '',
			'title' => $attachment->post_title ? $attachment->post_title : '',
			'caption' => $attachment->post_excerpt ? $attachment->post_excerpt : '',
			'description' => $attachment->post_content ? $attachment->post_content : '',
			'filename' => basename( $main_url ),
			'sizes' => $sizes,
			'srcset' => $srcset ? $srcset : '',
			'width' => $metadata['width'] ?? 0,
			'height' => $metadata['height'] ?? 0,
			'filesize' => $metadata['filesize'] ?? 0,
			'mime_type' => get_post_mime_type( $attachment_id ) ? get_post_mime_type( $attachment_id ) : '',
		);
	}


	/**
	 * Returns the dynamic URL data.
	 *
	 * @return array<string, mixed> The URL data.
	 */
	protected function get_dynamic_url_data(): array {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$current_url = home_url( $request_uri );
		$parsed_url = parse_url( $current_url );

		$params = array_map(
			function ( $param ) {
				if ( is_string( $param ) ) {
					return sanitize_text_field( $param );
				} elseif ( is_array( $param ) ) {
					return array_map( 'sanitize_text_field', $param );
				}
				return $param;
			},
			wp_unslash( $_GET )
		);

		return array(
			'full'   => $current_url,
			'relative'   => $parsed_url['path'] ?? '',
			'parameter' => $params,
		);
	}

	/**
	 * Calculate reading time for a post.
	 *
	 * @param WP_Post $post The post object.
	 * @return int Reading time in minutes.
	 */
	protected function calculate_reading_time( WP_Post $post ): int {
		$content = $post->post_content;
		$word_count = str_word_count( strip_tags( $content ) );
		return (int) ceil( $word_count / 200 );
	}

	/**
	 * Format the post excerpt that should be returned.
	 *
	 * @param WP_Post $post Post that is requiring the excerpt.
	 * @return string Post excerpt.
	 */
	private function get_the_post_excerpt( WP_Post $post ): string {
		$excerpt = $post->post_excerpt;

		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words( (string) preg_replace( '/<\/?[^>]+>/', ' ', $post->post_content ), apply_filters( 'excerpt_length', 55 ) );
		}

		// If still empty we just return nothing to avoid infinite loop.
		if ( '' === $excerpt ) {
			return '';
		}

		return apply_filters( 'get_the_excerpt', $excerpt, $post );
	}

	/**
	 * Returns an array with Archive data based on the current context.
	 *
	 * @return array<string, mixed> Archive data with title, description and url.
	 */
	public function get_dynamic_archive_data(): array {
		$archive_data = array(
			'title'         => get_the_archive_title(),
			'description'   => get_the_archive_description(),
			'url'           => '',
		);

		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
			if ( $term instanceof WP_Term ) {
				$archive_data['url'] = get_term_link( $term );
				$archive_data['title'] = $term->name;
			}
		} elseif ( is_post_type_archive() || is_singular() ) {
			$post = get_post();

			if ( $post ) {
				$post_type_obj = get_post_type_object( $post->post_type );
			} else {
				$queried_object = get_queried_object();
				$post_type_obj = $queried_object instanceof WP_Post_Type ? $queried_object : null;
			}

			if ( $post_type_obj ) {
				$archive_data['url'] = get_post_type_archive_link( $post_type_obj->name );
				$archive_data['title'] = apply_filters(
					'post_type_archive_title',
					$post_type_obj->labels->name,
					$post_type_obj->name
				);
			}
		}

		return $archive_data;
	}
}
