<?php
/**
 * DynamicData trait.
 *
 * This trait centralizes all dynamic data related functions.
 *
 * @package Etch\Traits
 */

declare(strict_types=1);

namespace Etch\Traits;

use Etch\Helpers\Logger;
use Etch\CustomFields\CustomFieldService;
use WP_Post;
use WP_Query;
use WP_Term;
use WP_User;
use Etch\Adapters\JetEngineDataAdapter;
use Etch\Adapters\MetaBoxDataAdapter;

trait DynamicData {
	use ACFData;
	use DynamicDataBases;

	/**
	 * Cache all Dynamic Data
	 *
	 * @var array<string, mixed>
	 */
	protected $cached_data = array();

	/**
	 * Returns an instance of the CustomFieldService.
	 *
	 * @return CustomFieldService The CustomFieldService instance.
	 */
	protected function get_cf_service(): CustomFieldService {
		return CustomFieldService::get_instance();
	}

	/**
	 * Returns the enhanced user data (includes ACF if available).
	 *
	 * @param WP_User $user The user object.
	 * @return array<string, mixed> The enhanced user data.
	 */
	protected function get_user_data( WP_User $user ): array {
		$cache_key = 'user_' . $user->ID;
		if ( isset( $this->cached_data[ $cache_key ] ) && is_array( $this->cached_data[ $cache_key ] ) ) {
			return $this->cached_data[ $cache_key ];
		}

		$data = $this->get_base_user_data( $user );
		$data = $this->add_acf_data( 'user', $user->ID, $data );
		$data = $this->add_metabox_data( 'user', $user->ID, $data );
		$data = $this->add_jetengine_data( 'user', $user->ID, $data );

		$data_filtered = apply_filters( 'etch/dynamic_data/user', $data, $user->ID );

		if ( ! is_array( $data_filtered ) ) {
			trigger_error( 'etch/dynamic_data/user filter must return an array', E_USER_WARNING );

			$this->cached_data[ $cache_key ] = $data;
		} else {
			$this->cached_data[ $cache_key ] = $data_filtered;
		}

		return $this->cached_data[ $cache_key ];
	}

	/**
	 * Returns the enhanced term data (includes ACF if available).
	 *
	 * @param WP_Term $term The term object.
	 * @return array<string, mixed> The enhanced term data.
	 */
	protected function get_term_data( WP_Term $term ): array {
		$cache_key = 'term_' . $term->term_id;
		if ( isset( $this->cached_data[ $cache_key ] ) && is_array( $this->cached_data[ $cache_key ] ) ) {
			return $this->cached_data[ $cache_key ];
		}
		$data = $this->get_base_term_data( $term );

		// Add category-specific properties if it's a category
		if ( 'category' === $term->taxonomy ) {
			$data['cat_ID'] = $term->cat_ID ?? $term->term_id;
			$data['category_count'] = $term->category_count ?? $term->count;
			$data['category_description'] = $term->category_description ?? $term->description;
			$data['cat_name'] = $term->cat_name ?? $term->name;
			$data['category_nicename'] = $term->category_nicename ?? $term->slug;
			$data['category_parent'] = $term->category_parent ?? $term->parent;
		}

		// Add Etch custom fields
		$data['etch'] = $this->get_etch_custom_fields_term( $term );
		$data = $this->add_acf_data( 'term', $term->term_id, $data, $term->taxonomy );
		$data = $this->add_metabox_data( 'term', $term->term_id, $data, $term->taxonomy );
		$data = $this->add_jetengine_data( 'term', $term->term_id, $data, $term->taxonomy );

		$data_filtered = apply_filters( 'etch/dynamic_data/term', $data, $term->term_id, $term->taxonomy );

		if ( ! is_array( $data_filtered ) ) {
			trigger_error( 'etch/dynamic_data/term filter must return an array', E_USER_WARNING );

			$this->cached_data[ $cache_key ] = $data;
		} else {
			$this->cached_data[ $cache_key ] = $data_filtered;
		}

		return $this->cached_data[ $cache_key ];
	}

	/**
	 * Get terms for a post by taxonomy and normalize them.
	 *
	 * @param WP_Post $post The post object.
	 * @param string  $taxonomy The taxonomy name.
	 * @return array<array<string, mixed>> Array of normalized term data.
	 */
	protected function get_post_terms( WP_Post $post, string $taxonomy ): array {
		$terms = get_the_terms( $post, $taxonomy );
		$normalized_terms = array();

		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term ) {
					$normalized_terms[] = $this->get_term_data( $term );
				}
			}
		}

		return $normalized_terms;
	}

	/**
	 * Returns the enhanced post data (includes relationships, ACF, etc.).
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> The enhanced post data.
	 */
	protected function get_post_data( WP_Post $post ): array {
		$post_data = $this->get_base_post_data( $post );

		// Get author and normalize using user data method
		$author = get_user_by( 'id', (int) $post->post_author );
		$post_data['author'] = $author ? $this->get_base_user_data( $author ) : array(
			'id'   => (int) $post->post_author,
			'name' => get_the_author_meta( 'display_name', (int) $post->post_author ),
			'avatar' => get_avatar_url( $post->post_author ),
		);

		// Get categories and tags using the term collection method
		$post_data['categories'] = $this->get_post_terms( $post, 'category' );
		$post_data['tags'] = $this->get_post_terms( $post, 'post_tag' );

		// Get all other taxonomies for this post type
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
		foreach ( $taxonomies as $tax ) {
			if ( ! isset( $post_data[ $tax->name ] ) ) {
				$post_data[ $tax->name ] = $this->get_post_terms( $post, $tax->name );
			}
		}

		if ( 'wp_template' === $post->post_type ) {
			$block_templates = get_block_templates( array( 'slug__in' => array( $post->post_name ) ), 'wp_template' );
			$has_theme_file = false;
			foreach ( $block_templates as $template ) {
				if ( $template->has_theme_file ) {
					$has_theme_file = true;
					break;
				}
			}
			$post_data['has_theme_file'] = $has_theme_file;
		}

		return $post_data;
	}

	/**
	 * Returns an array with all fields for option pages.
	 *
	 * @return array<string, mixed> The enhanced data with ACF fields.
	 */
	protected function get_dynamic_option_pages_data(): array {
		$cache_key = 'option_page';
		if ( isset( $this->cached_data[ $cache_key ] ) && is_array( $this->cached_data[ $cache_key ] ) ) {
			return $this->cached_data[ $cache_key ];
		}
		$data = $this->get_base_option_pages_data();
		$data = $this->add_acf_data( 'option', 0, $data );
		$data = $this->add_metabox_data( 'setting', 0, $data );
		$data = $this->add_jetengine_data( 'option', 0, $data );

		$data_filtered = apply_filters( 'etch/dynamic_data/option', $data );

		if ( ! is_array( $data_filtered ) ) {
			trigger_error( 'etch/dynamic_data/option filter must return an array', E_USER_WARNING );

			$this->cached_data[ $cache_key ] = $data;
		} else {
			$this->cached_data[ $cache_key ] = $data_filtered;
		}

		return $this->cached_data[ $cache_key ];
	}

	/**
	 * Adds ACF data to base data if ACF is available.
	 *
	 * @param string               $object_type The object type ('post', 'user', 'term', 'option').
	 * @param int                  $object_id   The object ID.
	 * @param array<string, mixed> $base_data   The base data to enhance.
	 * @param string|null          $taxonomy    The taxonomy name (for terms only).
	 * @return array<string, mixed> The enhanced data with ACF fields.
	 */
	protected function add_acf_data( string $object_type, int $object_id, array $base_data, ?string $taxonomy = null ): array {
		if ( ! function_exists( '\get_fields' ) ) {
			return $base_data;
		}

		// Build the ACF object identifier
		$acf_id = match ( $object_type ) {
			'user' => 'user_' . $object_id,
			'term' => $taxonomy . '_' . $object_id,
			'post' => $object_id,
			'option' => 'option',
			default => $object_id,
		};

		// Get formatted ACF data using the ACF trait's methods
		$acf_data = array();
		$acf_meta = array();

		if ( 'post' === $object_type ) {
			// For posts, use the ACF trait's formatting methods
			$post = get_post( $object_id );
			if ( $post ) {
				$acf_data = $this->get_acf_data( $post );
				$acf_meta_raw = $this->get_acf_field_types( $post );

				// Convert field types to the expected format
				foreach ( $acf_meta_raw as $field_key => $field_type ) {
					$acf_meta[ $field_key ] = array(
						'type' => $field_type,
						'label' => '', // We could enhance this later if needed
					);
				}
			}
		} else {
			// For users and terms, get raw data (we can enhance this later if needed)
			$raw_acf_data = \get_fields( $acf_id );

			if ( function_exists( '\get_field_objects' ) ) {
				$field_objects = \get_field_objects( $acf_id );
				if ( is_array( $field_objects ) ) {
					foreach ( $field_objects as $field_key => $field_object ) {
						if ( is_array( $field_object ) && isset( $field_object['type'] ) ) {
							$acf_meta[ $field_key ] = array(
								'type' => $field_object['type'],
								'label' => $field_object['label'] ?? '',
							);
						}
					}
				}
			}

			if ( is_array( $raw_acf_data ) ) {
				$acf_data = $raw_acf_data;
			}
		}

		// Add ACF data to base data
		if ( ! empty( $acf_data ) ) {
			$base_data['acf'] = $this->normalize_data_recursively( $acf_data );
		}

		if ( ! empty( $acf_meta ) ) {
			$base_data['acf_meta'] = $this->normalize_data_recursively( $acf_meta );
		}

		return $base_data;
	}

	/**
	 * Adds JetEngine data to base data if JetEngine is available.
	 *
	 * @param string               $object_type The object type ('post', 'user', 'term').
	 * @param int                  $object_id   The object ID.
	 * @param array<string, mixed> $base_data   The base data to enhance.
	 * @param string|null          $taxonomy    The taxonomy name (for terms only).
	 * @return array<string, mixed> The enhanced data with JetEngine fields.
	 */
	protected function add_jetengine_data( string $object_type, int $object_id, array $base_data, ?string $taxonomy = null ): array {
		if ( ! function_exists( 'jet_engine' ) ) {
			return $base_data;
		}

		$jetengine_data = array();
		$jetengine_meta = array();
		$jetengine_adapter = new JetEngineDataAdapter();

		if ( 'post' === $object_type ) {
			$post = get_post( $object_id );
			if ( $post ) {
				$jetengine_data = $jetengine_adapter->get_data_for_post( $post );
				$jetengine_meta_raw = $jetengine_adapter->get_fields_for_post( $post );

				// Convert field types to the expected format
				foreach ( $jetengine_meta_raw as $field_key => $field ) {
					if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
						continue;
					}
					$jetengine_meta[ $field_key ] = array(
						'type' => $field['type'],
						'label' => '', // We could enhance this later if needed
					);
				}
			}
		} else if ( 'option' === $object_type ) {
			$jetengine_data = $jetengine_adapter->get_data( $object_type );
			$jetengine_meta_raw = $jetengine_adapter->get_raw_fields( $object_type );

			// Convert field types to the expected format
			foreach ( $jetengine_meta_raw as $option_page_name => $option_page_fields ) {
				$jetengine_meta[ $option_page_name ] = array();
				if ( ! is_array( $option_page_fields ) ) {
					continue;
				}
				foreach ( $option_page_fields as $field ) {
					if ( ! is_array( $field ) || ! isset( $field['type'] ) || ! isset( $field['name'] ) ) {
						continue;
					}
					$field_key = $field['name'];
					$jetengine_meta[ $option_page_name ][ $field_key ] = array(
						'type' => $field['type'],
						'label' => '', // We could enhance this later if needed
					);
				}
			}
		} else {
			$jetengine_data = $jetengine_adapter->get_data( $object_type, $object_id );
			$jetengine_meta_raw = $jetengine_adapter->get_raw_fields( $object_type, $object_id );

			// Convert field types to the expected format
			foreach ( $jetengine_meta_raw as $field ) {
				if ( ! is_array( $field ) || ! isset( $field['type'] ) || ! isset( $field['name'] ) ) {
					continue;
				}
				$field_key = $field['name'];
				$jetengine_meta[ $field_key ] = array(
					'type' => $field['type'],
					'label' => '', // We could enhance this later if needed
				);
			}
		}

		// Add Jet Engine data to base data
		if ( ! empty( $jetengine_data ) ) {
			$base_data['jetengine'] = $this->normalize_data_recursively( $jetengine_data );
		}

		if ( ! empty( $jetengine_meta ) ) {
			$base_data['jetengine_meta'] = $this->normalize_data_recursively( $jetengine_meta );
		}

		return $base_data;
	}

	/**
	 * Adds Meta Box data to base data if Meta Box is available.
	 *
	 * @param string               $object_type The object type ('post', 'user', 'term').
	 * @param int                  $object_id   The object ID.
	 * @param array<string, mixed> $base_data   The base data to enhance.
	 * @param string|null          $taxonomy    The taxonomy name (for terms only).
	 * @return array<string, mixed> The enhanced data with Meta Box fields.
	 */
	protected function add_metabox_data( string $object_type, int $object_id, array $base_data, ?string $taxonomy = null ): array {
		if ( ! function_exists( 'rwmb_the_value' ) || ! function_exists( 'rwmb_get_object_fields' ) ) {
			return $base_data;
		}

		$mb_data = array();
		$mb_meta = array();
		$mb_adapter = new MetaBoxDataAdapter();

		if ( 'post' === $object_type ) {
			$post = get_post( $object_id );
			if ( $post ) {
				$mb_data = $mb_adapter->get_data_for_post( $post );
				$mb_meta_raw = $mb_adapter->get_fields_for_post( $post );

				// Convert field types to the expected format
				foreach ( $mb_meta_raw as $field_key => $field ) {
					if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
						continue;
					}
					$mb_meta[ $field_key ] = array(
						'type' => $field['type'],
						'label' => '', // We could enhance this later if needed
					);
				}
			}
		} else if ( 'setting' === $object_type ) {
			$mb_data = $mb_adapter->get_data( $object_type );
			$mb_meta_raw = $mb_adapter->get_raw_fields( $object_type );

			// Convert field types to the expected format
			foreach ( $mb_meta_raw as $option_page_name => $option_page_fields ) {
				$mb_meta[ $option_page_name ] = array();
				if ( ! is_array( $option_page_fields ) ) {
					continue;
				}
				foreach ( $option_page_fields as $field_key => $field ) {
					if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
						continue;
					}
					$mb_meta[ $option_page_name ][ $field_key ] = array(
						'type' => $field['type'],
						'label' => '', // We could enhance this later if needed
					);
				}
			}
		} else {
			$mb_data = $mb_adapter->get_data( $object_type, $object_id );
			$mb_meta_raw = $mb_adapter->get_raw_fields( $object_type );

			// Convert field types to the expected format
			foreach ( $mb_meta_raw as $field_key => $field ) {
				if ( ! is_array( $field ) || ! isset( $field['type'] ) ) {
					continue;
				}
				$mb_meta[ $field_key ] = array(
					'type' => $field['type'],
					'label' => '', // We could enhance this later if needed
				);
			}
		}

		// Add Meta Box data to base data
		if ( ! empty( $mb_data ) ) {
			$base_data['metabox'] = $this->normalize_data_recursively( $mb_data );
		}

		if ( ! empty( $mb_meta ) ) {
			$base_data['metabox_meta'] = $this->normalize_data_recursively( $mb_meta );
		}

		return $base_data;
	}

	/**
	 * Normalize WordPress objects to associative arrays.
	 *
	 * @param mixed $item The item to normalize (WP_Term, WP_Post, WP_User, etc.).
	 * @return array<string, mixed> The normalized associative array.
	 */
	protected function normalize_wp_object( $item ): array {
		// If it's already an array, return it
		if ( is_array( $item ) ) {
			return $item;
		}

		// Handle WordPress Term objects - delegate to base method
		if ( $item instanceof WP_Term ) {
			return $this->get_base_term_data( $item );
		}

		// Handle WordPress Post objects - delegate to base method
		if ( $item instanceof WP_Post ) {
			$base_data = $this->get_base_post_data( $item );
			// Add author for post normalization
			$author = get_user_by( 'id', (int) $item->post_author );
			$base_data['author'] = $author ? array(
				'id'   => $author->ID,
				'name' => $author->display_name,
			) : array(
				'id'   => (int) $item->post_author,
				'name' => get_the_author_meta( 'display_name', (int) $item->post_author ),
			);
			return $base_data;
		}

		// Handle WordPress User objects - delegate to base method
		if ( $item instanceof WP_User ) {
			return $this->get_base_user_data( $item );
		}

		// For other objects, try to convert to array
		if ( is_object( $item ) ) {
			$array_item = get_object_vars( $item );
			return $array_item;
		}

		// For scalar values, wrap in an array with a 'value' key
		if ( is_scalar( $item ) ) {
			return array( 'value' => $item );
		}

		// Fallback to empty array
		return array();
	}

	/**
	 * Recursively normalize all WordPress objects in an array structure.
	 *
	 * @param mixed $data The data to normalize.
	 * @return mixed The normalized data.
	 */
	protected function normalize_data_recursively( $data ) {
		if ( is_array( $data ) ) {
			$normalized = array();
			foreach ( $data as $key => $value ) {
				$normalized[ $key ] = $this->normalize_data_recursively( $value );
			}
			return $normalized;
		}

		// Check if it's a WordPress object that needs normalization
		if ( $data instanceof WP_Term || $data instanceof WP_Post || $data instanceof WP_User ) {
			return $this->normalize_wp_object( $data );
		}

		// For other objects, arrays, or scalars, return as-is
		return $data;
	}

	/**
	 * Returns the template data (slug and post ID) for a given post.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, int|string>|null Template data array or null if no specific template is found.
	 */
	protected function get_template_data( WP_Post $post ): ?array {
		// Get post type
		$post_type = get_post_type( $post );

		// If we're looking at a template itself, return null
		if ( 'wp_template' === $post_type ) {
			return null;
		}

		// Check if a custom template is assigned
		$template_slug = get_page_template_slug( $post->ID );

		// If we have a custom template, use that
		if ( ! empty( $template_slug ) ) {
			// Strip .php extension if present (not needed but still a good idea)
			$template_slug = str_replace( '.php', '', $template_slug );

			$template_data = $this->get_template_data_by_slug( $template_slug );
			// If no or multiple templates are found, return null
			if ( ! empty( $template_data ) ) {
				return $template_data;
			}
		}

		// Build the template hierarchy as WordPress would
		$templates = array();

		 // For singular posts
		if ( 'page' === $post_type ) {
			// handle privacy policy
			if ( 'privacy-policy' === $post->post_name ) {
				$templates[] = 'privacy-policy';
			}

			if ( $post->post_name ) {
				$templates[] = "page-{$post->post_name}";
			}
			$templates[] = "page-{$post->ID}";
			$templates[] = 'page';
			$templates[] = 'index';
		} else {
			if ( $post->post_name ) {
				$templates[] = "single-{$post_type}-{$post->post_name}";
			}
			$templates[] = "single-{$post_type}";
			$templates[] = 'single';
			$templates[] = 'singular';
			$templates[] = 'index';
		}

		 // Now check each template in the hierarchy
		foreach ( $templates as $template_slug ) {
			$template_data = $this->get_template_data_by_slug( $template_slug );

			if ( ! empty( $template_data ) ) {
				return $template_data;
			}
		}

		return $this->get_template_data_by_slug( 'index' );
	}

	/**
	 * Retrieves template data by its slug.
	 *
	 * @param string $template_slug The slug of the template.
	 * @return array<string, int|string>|null The template data array or null if not found.
	 */
	protected function get_template_data_by_slug( string $template_slug ): ?array {
		$args = array(
			'post_type' => 'wp_template',
			'name' => $template_slug,
			'post_status' => 'publish',
			'posts_per_page' => -1,
		);

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return null;
		}

		$template_post = $query->posts[0];
		wp_reset_postdata();

		if ( ! $template_post instanceof WP_Post ) {
			return null;
		}

		return array(
			'slug'  => $template_post->post_name,
			'id'    => $template_post->ID,
			'title' => $template_post->post_title,
		);
	}

	/**
	 * Returns the post meta data in a flat format.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, string|array<int, mixed>> The post meta data.
	 */
	protected function get_post_meta( WP_Post $post ): array {
		$meta = get_post_meta( $post->ID );
		$flat = array();

		if ( ! is_array( $meta ) ) {
			return $flat;
		}

		foreach ( $meta as $key => $value ) {
			if ( is_array( $value ) ) {
				$flat[ $key ] = count( $value ) === 1 ? (string) $value[0] : $value;
			} elseif ( is_string( $value ) ) {
				$flat[ $key ] = $value;
			} else {
				$flat[ $key ] = '';
			}
		}
		return $flat;
	}

	/**
	 * Resolves a meta value to a string if possible.
	 *
	 * @param mixed $value The meta value to resolve.
	 * @return string|null The resolved string value or null if not resolvable.
	 */
	protected function resolve_meta_value( mixed $value ): ?string {
		if ( is_array( $value ) && count( $value ) === 1 ) {
			return (string) $value[0];
		} elseif ( is_string( $value ) ) {
			return $value;
		}

		return null;
	}

	/**
	 * Returns the etch custom field data in a flat format.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> The etch custom field data.
	 */
	protected function get_etch_custom_fields( WP_Post $post ): array {
		$meta = get_post_meta( $post->ID );
		$collection = array();

		if ( ! is_array( $meta ) ) {
			return $collection;
		}

		foreach ( $meta as $key => $value ) {
			$field_value = $this->resolve_meta_value( $value );

			if ( ! is_string( $field_value ) ) {
				// Skip non-string values
				continue;
			}

			if ( str_starts_with( $field_value, 'etch_field_group_' ) ) {
				// The group ID of the custom field group that this field belongs to
				$group_id = str_replace( 'etch_field_group_', '', $field_value );
				// strip the beginning '_' to get the actual field key
				$field_key = substr( $key, 1 );

				$collection[ $field_key ] = $this->get_cf_service()->get_field_value( $group_id, $field_key, $post->ID );
			}
		}

		return $collection;
	}

	/**
	 * Combines post data and dynamic date data.
	 *
	 * This method merges the post-related dynamic data (base & meta data)
	 * with the dynamic date data.
	 *
	 * @param WP_Post $post The post object.
	 * @return array<string, mixed> Combined dynamic data.
	 */
	protected function get_dynamic_data( WP_Post $post ): array {
		$cache_key = 'post_' . $post->ID;
		if ( isset( $this->cached_data[ $cache_key ] ) && is_array( $this->cached_data[ $cache_key ] ) ) {
			return $this->cached_data[ $cache_key ];
		}

		$data = array_merge(
			$this->get_post_data( $post ),
			array( 'meta' => $this->get_post_meta( $post ) ),
			array( 'template' => $this->get_template_data( $post ) ),
			array( 'etch' => $this->get_etch_custom_fields( $post ) ),
		);

		// Add ACF data using the unified method
		$data = $this->add_acf_data( 'post', $post->ID, $data );
		$data = $this->add_jetengine_data( 'post', $post->ID, $data );
		$data = $this->add_metabox_data( 'post', $post->ID, $data );

		$data_filtered = apply_filters( 'etch/dynamic_data/post', $data, $post->ID );

		if ( ! is_array( $data_filtered ) ) {
			trigger_error( 'etch/dynamic_data/post filter must return an array', E_USER_WARNING );
			$this->cached_data[ $cache_key ] = $data;
			return $data;
		}
		$this->cached_data[ $cache_key ] = $data_filtered;
		return $data_filtered;
	}

	/**
	 * Returns the post excerpt.
	 *
	 * @param WP_Post $post The post object.
	 * @return string The post excerpt.
	 */
	private function get_post_excerpt( WP_Post $post ): string {
		$excerpt = get_the_excerpt( $post );
		return $excerpt ? $excerpt : $post->post_excerpt;
	}

	/**
	 * Retrieves a value from a nested array or object using a dot-notation path.
	 *
	 * @param array<string, mixed>|object $data_source The array or object to search.
	 * @param string                      $path The dot-notation path (e.g., "user.address.street").
	 * @return mixed The value found at the path, or null if the path is invalid or not found.
	 */
	protected function get_data_value_by_path( $data_source, string $path ) {
		$keys = explode( '.', $path );
		$value = $data_source;

		foreach ( $keys as $key ) {
			if ( is_array( $value ) && array_key_exists( $key, $value ) ) {
				$value = $value[ $key ];
			} elseif ( is_object( $value ) && property_exists( $value, $key ) ) {
				$value = $value->{$key};
			} else {
				// Path not found or invalid structure at this key
				return null;
			}
		}
		return $value;
	}

	/**
	 * Combines user data with ACF fields.
	 *
	 * @param WP_User $user The user object.
	 * @return array<string, mixed> Combined dynamic data.
	 */
	protected function get_dynamic_user_data( WP_User $user ): array {
		return $this->get_user_data( $user );
	}

	/**
	 * Get ACF fields for a user.
	 *
	 * @param WP_User $user The user object.
	 * @return array<string, mixed> ACF field data.
	 */
	protected function get_acf_user_data( WP_User $user ): array {
		if ( ! function_exists( '\get_fields' ) ) {
			return array();
		}

		$fields = \get_fields( 'user_' . $user->ID );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Get ACF field types for a user.
	 *
	 * @param WP_User $user The user object.
	 * @return array<string, mixed> ACF field types.
	 */
	protected function get_acf_user_field_types( WP_User $user ): array {
		if ( ! function_exists( '\get_field_objects' ) ) {
			return array();
		}

		$field_objects = \get_field_objects( 'user_' . $user->ID );
		if ( ! is_array( $field_objects ) ) {
			return array();
		}

		$field_types = array();
		foreach ( $field_objects as $field_key => $field_object ) {
			if ( is_array( $field_object ) && isset( $field_object['type'] ) ) {
				$field_types[ $field_key ] = array(
					'type' => $field_object['type'],
					'label' => $field_object['label'] ?? '',
				);
			}
		}

		return $field_types;
	}

	/**
	 * Combines term data with acf fields.
	 *
	 * @param WP_Term $term The term object.
	 * @return array<string, mixed> Combined dynamic data.
	 */
	protected function get_dynamic_term_data( WP_Term $term ): array {
		return $this->get_term_data( $term );
	}

	/**
	 * Get ACF fields for a term.
	 *
	 * @param WP_term $term The term object.
	 * @return array<string, mixed> ACF field data
	 */
	protected function get_acf_term_data( WP_term $term ): array {
		if ( ! function_exists( '\get_fields' ) ) {
			return array();
		}

		$fields = \get_fields( $term->taxonomy . '_' . $term->term_id );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Get ACF field types for a term.
	 *
	 * @param WP_term $term The term object.
	 * @return array<string, mixed> ACF field types
	 */
	protected function get_acf_term_field_types( WP_term $term ): array {
		if ( ! function_exists( '\get_field_objects' ) ) {
			return array();
		}

		$field_objects = \get_field_objects( $term->taxonomy . '_' . $term->term_id );
		if ( ! is_array( $field_objects ) ) {
			return array();
		}

		$field_types = array();
		foreach ( $field_objects as $field_key => $field_object ) {
			if ( is_array( $field_object ) && isset( $field_object['type'] ) ) {
				$field_types[ $field_key ] = array(
					'type' => $field_object['type'],
					'label' => $field_object['label'] ?? '',
				);
			}
		}

		return $field_types;
	}

	/**
	 * Get comprehensive taxonomy data.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return array<string, mixed> The taxonomy data.
	 */
	protected function get_dynamic_tax_data( string $taxonomy ): array {
		$tax_object = get_taxonomy( $taxonomy );

		if ( ! $tax_object instanceof \WP_Taxonomy ) {
			return array();
		}

		$data = array(
			'name' => $taxonomy,
			'label' => $tax_object->label,
			'labels' => $tax_object->labels,
			'description' => $tax_object->description,
			'public' => $tax_object->public,
			'hierarchical' => $tax_object->hierarchical,
			'show_ui' => $tax_object->show_ui,
			'show_in_menu' => $tax_object->show_in_menu,
			'show_in_nav_menus' => $tax_object->show_in_nav_menus,
			'show_tagcloud' => $tax_object->show_tagcloud,
			'show_in_rest' => $tax_object->show_in_rest,
			'rest_base' => $tax_object->rest_base,
			'object_type' => $tax_object->object_type,
			'capabilities' => $tax_object->cap,
			'rewrite' => $tax_object->rewrite,
			'query_var' => $tax_object->query_var,
		);

		// Get archive URL if available
		$archive_link = get_term_link( '', $taxonomy );
		if ( ! is_wp_error( $archive_link ) ) {
			$data['archive_url'] = $archive_link;
		}

		// Get all terms for this taxonomy
		$terms = get_terms(
			array(
				'taxonomy' => $taxonomy,
				'hide_empty' => false,
				'orderby' => 'term_order',
			)
		);

		$terms_data = array();
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( $term instanceof WP_Term ) {
					$terms_data[] = $this->get_dynamic_term_data( $term );
				}
			}
			$data['term_count'] = count( $terms );
		} else {
			$data['term_count'] = 0;
		}

		// Add the terms array to taxonomy data
		$data['terms'] = $terms_data;

		// Add Etch custom fields for the taxonomy
		$data['etch'] = $this->get_etch_custom_fields_taxonomy( $taxonomy );

		return $data;
	}

	/**
	 * Returns the etch custom field data for a term in a flat format.
	 *
	 * @param WP_Term $term The term object.
	 * @return array<string, mixed> The etch custom field data.
	 */
	protected function get_etch_custom_fields_term( WP_Term $term ): array {
		// TODO: Implement term custom field support when CustomFieldService supports terms
		// For now, return empty array as term custom fields are not implemented yet
		return array();
	}

	/**
	 * Returns the etch custom field data for a taxonomy in a flat format.
	 *
	 * @param string $taxonomy The taxonomy slug.
	 * @return array<string, mixed> The etch custom field data.
	 */
	protected function get_etch_custom_fields_taxonomy( string $taxonomy ): array {
		// Note: WordPress doesn't have built-in taxonomy meta like term meta
		// This would be used for taxonomy-level custom fields if implemented
		// For now, return empty array as taxonomies don't have meta in core WordPress
		return array();
	}
}
