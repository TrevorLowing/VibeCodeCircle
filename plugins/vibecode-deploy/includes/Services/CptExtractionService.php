<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts CPT posts from static HTML in the staging build when configured.
 *
 * When vibecode-deploy-shortcodes.json contains extract_cpt_from_static: true
 * and extract_cpt_pages with per-page rules, this service parses the HTML
 * files in the build root and creates (or skips duplicates) CPT posts.
 * If the flag is missing or false, extraction is never run (safe for all projects).
 *
 * @package VibeCode\Deploy\Services
 */
final class CptExtractionService {

	/**
	 * Run CPT extraction from static HTML in the build root.
	 *
	 * @param string $build_root Path to extracted staging (build root).
	 * @param array  $config     Full shortcodes config (must contain extract_cpt_from_static and optionally extract_cpt_pages).
	 * @param string $project_slug Project slug for logging.
	 * @return array{created: int, updated: int, errors: array<int, string>}
	 */
	public static function extract_from_build( string $build_root, array $config, string $project_slug ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'errors'  => array(),
		);

		if ( empty( $config['extract_cpt_from_static'] ) ) {
			return $result;
		}

		$pages_config = isset( $config['extract_cpt_pages'] ) && is_array( $config['extract_cpt_pages'] )
			? $config['extract_cpt_pages']
			: array();

		if ( empty( $pages_config ) ) {
			Logger::info( 'CPT extraction: extract_cpt_pages empty, skipping.', array(), $project_slug );
			return $result;
		}

		$build_root = rtrim( $build_root, '/\\' );
		$pages_dir  = $build_root . DIRECTORY_SEPARATOR . 'pages';
		if ( ! is_dir( $pages_dir ) ) {
			Logger::warning( 'CPT extraction: pages directory not found.', array( 'path' => $pages_dir ), $project_slug );
			return $result;
		}

		foreach ( $pages_config as $page_slug => $page_config ) {
			if ( ! is_array( $page_config ) || empty( $page_config['sections'] ) || ! is_array( $page_config['sections'] ) ) {
				continue;
			}

			$html_path = $pages_dir . DIRECTORY_SEPARATOR . sanitize_file_name( $page_slug ) . '.html';
			if ( ! is_file( $html_path ) || ! is_readable( $html_path ) ) {
				continue;
			}

			$raw = file_get_contents( $html_path );
			if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
				continue;
			}

			libxml_use_internal_errors( true );
			$dom = new \DOMDocument();
			$dom->encoding = 'UTF-8';
			$loaded       = $dom->loadHTML( '<?xml encoding="UTF-8">' . $raw );
			libxml_clear_errors();
			if ( ! $loaded ) {
				$result['errors'][] = sprintf( 'Failed to parse HTML: %s', $page_slug );
				continue;
			}

			$xpath = new \DOMXPath( $dom );
			$main  = $xpath->query( '//main' )->item( 0 );
			if ( ! $main instanceof \DOMNode ) {
				continue;
			}

			foreach ( $page_config['sections'] as $section ) {
				if ( ! is_array( $section ) || empty( $section['item_selector'] ) || empty( $section['cpt'] ) ) {
					continue;
				}

				$scope = $main;
				if ( ! empty( $section['parent_selector'] ) && is_string( $section['parent_selector'] ) ) {
					$parent_nodes = self::query_selector( $xpath, $main, $section['parent_selector'] );
					if ( $parent_nodes->length === 0 ) {
						continue;
					}
					$scope = $parent_nodes->item( 0 );
				}

				$item_nodes = self::query_selector( $xpath, $scope, $section['item_selector'] );
				$taxonomy   = isset( $section['taxonomy'] ) && is_string( $section['taxonomy'] ) ? $section['taxonomy'] : '';
				$term_slug  = isset( $section['term'] ) && is_string( $section['term'] ) ? $section['term'] : '';
				$fields     = isset( $section['fields'] ) && is_array( $section['fields'] ) ? $section['fields'] : array();
				$cpt        = sanitize_key( $section['cpt'] );

				for ( $i = 0; $i < $item_nodes->length; $i++ ) {
					$item_node = $item_nodes->item( $i );
					if ( ! $item_node instanceof \DOMNode ) {
						continue;
					}

					$post_title   = '';
					$post_content = '';
					$meta         = array();

					foreach ( $fields as $field_key => $selector ) {
						if ( ! is_string( $selector ) ) {
							continue;
						}

						$nodes = self::query_selector( $xpath, $item_node, $selector );
						$value = '';
						if ( $nodes->length > 0 ) {
							$first = $nodes->item( 0 );
							if ( $field_key === 'post_content' ) {
								$value = self::get_inner_html( $dom, $first );
							} else {
								$value = trim( (string) $first->textContent );
							}
						}

						if ( $field_key === 'post_title' ) {
							$post_title = $value;
						} elseif ( $field_key === 'post_content' ) {
							$post_content = $value;
						} elseif ( strpos( $field_key, '_meta' ) !== false ) {
							$meta_key = trim( str_replace( '_meta', '', $field_key ) );
							if ( $meta_key !== '' ) {
								$meta[ $meta_key ] = $value;
							}
						}
					}

					$post_title = trim( $post_title );
					if ( $post_title === '' ) {
						continue;
					}

					$existing = self::find_existing_post( $cpt, $post_title, $taxonomy, $term_slug );
					if ( $existing ) {
						$post_id = wp_update_post(
							array(
								'ID'           => $existing,
								'post_content' => $post_content,
								'post_type'    => $cpt,
							),
							true
						);
						if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
							foreach ( $meta as $meta_key => $meta_value ) {
								update_post_meta( $post_id, $meta_key, $meta_value );
							}
							$result['updated']++;
						} else {
							$result['errors'][] = sprintf( '%s: update failed for "%s"', $cpt, substr( $post_title, 0, 50 ) );
						}
					} else {
						$post_id = wp_insert_post(
							array(
								'post_title'   => $post_title,
								'post_content' => $post_content,
								'post_status'  => 'publish',
								'post_type'    => $cpt,
							),
							true
						);
						if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
							foreach ( $meta as $meta_key => $meta_value ) {
								update_post_meta( $post_id, $meta_key, $meta_value );
							}
							if ( $taxonomy !== '' && $term_slug !== '' ) {
								wp_set_object_terms( $post_id, $term_slug, $taxonomy );
							}
							$result['created']++;
						} else {
							$result['errors'][] = sprintf( '%s: insert failed for "%s"', $cpt, substr( $post_title, 0, 50 ) );
						}
					}
				}
			}
		}

		if ( $result['created'] > 0 || $result['updated'] > 0 ) {
			Logger::info( 'CPT extraction completed.', array(
				'created' => $result['created'],
				'updated' => $result['updated'],
				'errors'  => count( $result['errors'] ),
			), $project_slug );
		}
		if ( ! empty( $result['errors'] ) ) {
			Logger::warning( 'CPT extraction had errors.', array( 'errors' => $result['errors'] ), $project_slug );
		}

		return $result;
	}

	/**
	 * Query nodes by a simple CSS-like selector (single class or id).
	 *
	 * @param \DOMXPath $xpath    XPath instance.
	 * @param \DOMNode  $context Context node.
	 * @param string    $selector Selector e.g. .bgp-accessory-card or #id.
	 * @return \DOMNodeList
	 */
	private static function query_selector( \DOMXPath $xpath, \DOMNode $context, string $selector ): \DOMNodeList {
		$selector = trim( $selector );
		if ( $selector === '' ) {
			return new \DOMNodeList();
		}

		if ( strpos( $selector, '#' ) === 0 ) {
			$id = substr( $selector, 1 );
			$id = preg_replace( '/[^a-zA-Z0-9_-]/', '', $id );
			if ( $id !== '' ) {
				return $xpath->query( './/*[@id="' . $id . '"]', $context );
			}
		}

		if ( strpos( $selector, '.' ) === 0 ) {
			$class = trim( substr( $selector, 1 ) );
			$class = preg_replace( '/\s+/', ' ', $class );
			if ( $class !== '' ) {
				$parts = explode( ' ', $class );
				$expr  = './/*';
				foreach ( $parts as $c ) {
					$c   = preg_replace( '/[^a-zA-Z0-9_-]/', '', $c );
					if ( $c !== '' ) {
						$expr .= '[contains(concat(" ", normalize-space(@class), " "), " ' . $c . ' ")]';
					}
				}
				return $xpath->query( $expr, $context );
			}
		}

		return new \DOMNodeList();
	}

	/**
	 * Get inner HTML of a node.
	 *
	 * @param \DOMDocument $doc  Document.
	 * @param \DOMNode     $node Node.
	 * @return string
	 */
	private static function get_inner_html( \DOMDocument $doc, \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $doc->saveHTML( $child );
		}
		return $html;
	}

	/**
	 * Find existing post by title, CPT, and optional taxonomy term.
	 *
	 * @param string $cpt    Post type.
	 * @param string $title  Post title.
	 * @param string $taxonomy Taxonomy slug (optional).
	 * @param string $term_slug Term slug (optional).
	 * @return int Post ID or 0 if not found.
	 */
	private static function find_existing_post( string $cpt, string $title, string $taxonomy, string $term_slug ): int {
		$query = array(
			'post_type'      => $cpt,
			'post_status'    => 'any',
			'title'          => $title,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		);

		if ( $taxonomy !== '' && $term_slug !== '' && taxonomy_exists( $taxonomy ) ) {
			$query['tax_query'] = array(
				array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => $term_slug,
				),
			);
		}

		$posts = get_posts( $query );
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}
}
