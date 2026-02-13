<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts CPT posts from static HTML in the staging build when configured.
 *
 * Supports two modes:
 * 1. Hint-based: HTML blocks wrapped in <!-- VIBECODE_CPT_BLOCK cpt="..." taxonomy="..." term="..." -->
 *    ... <!-- /VIBECODE_CPT_BLOCK --> are extracted using per-CPT field maps (cpt_field_maps or
 *    inferred from extract_cpt_pages). Runs for any page that contains the marker when
 *    extract_cpt_from_static is true.
 * 2. Selector-based: When extract_cpt_pages is set, pages that do NOT contain VIBECODE_CPT_BLOCK
 *    are processed with the legacy section/item_selector/fields config.
 *
 * @package VibeCode\Deploy\Services
 */
final class CptExtractionService {

	private const CPT_BLOCK_OPEN  = 'VIBECODE_CPT_BLOCK';
	private const CPT_BLOCK_CLOSE = '/VIBECODE_CPT_BLOCK';

	/**
	 * Run CPT extraction from static HTML in the build root.
	 *
	 * First runs hint-based extraction for every page that contains VIBECODE_CPT_BLOCK.
	 * Then runs selector-based extraction for pages in extract_cpt_pages that had no hints.
	 *
	 * @param string $build_root Path to extracted staging (build root).
	 * @param array  $config     Full shortcodes config (must contain extract_cpt_from_static and optionally extract_cpt_pages, cpt_field_maps).
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

		$build_root  = rtrim( $build_root, '/\\' );
		$pages_dir   = $build_root . DIRECTORY_SEPARATOR . 'pages';
		$pages_config = isset( $config['extract_cpt_pages'] ) && is_array( $config['extract_cpt_pages'] )
			? $config['extract_cpt_pages']
			: array();

		if ( ! is_dir( $pages_dir ) ) {
			Logger::warning( 'CPT extraction: pages directory not found.', array( 'path' => $pages_dir ), $project_slug );
			return $result;
		}

		// Phase 1: Hint-based extraction for any page that contains VIBECODE_CPT_BLOCK.
		$pages_with_hints = array();
		$html_files       = glob( $pages_dir . DIRECTORY_SEPARATOR . '*.html' );
		if ( is_array( $html_files ) ) {
			foreach ( $html_files as $html_path ) {
				$page_slug = basename( $html_path, '.html' );
				$raw       = is_readable( $html_path ) ? file_get_contents( $html_path ) : null;
				if ( ! is_string( $raw ) || strpos( $raw, self::CPT_BLOCK_OPEN ) === false ) {
					continue;
				}
				$hint_result = self::extract_hinted_blocks_from_page( $raw, $page_slug, $config, $project_slug );
				$result['created'] += $hint_result['created'];
				$result['updated'] += $hint_result['updated'];
				$result['errors']   = array_merge( $result['errors'], $hint_result['errors'] );
				$pages_with_hints[ $page_slug ] = true;
			}
		}

		// Phase 2: Selector-based extraction only for pages in config that did not use hints.
		if ( empty( $pages_config ) ) {
			if ( $result['created'] > 0 || $result['updated'] > 0 ) {
				Logger::info( 'CPT extraction (hint-based) completed.', array(
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

		foreach ( $pages_config as $page_slug => $page_config ) {
			if ( ! empty( $pages_with_hints[ $page_slug ] ) ) {
				continue;
			}
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
				if ( ! post_type_exists( $cpt ) ) {
					Logger::warning( 'CPT extraction: post type not registered, skipping section.', array(
						'cpt'   => $cpt,
						'page'  => $page_slug,
					), $project_slug );
					continue;
				}

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
	 * Extract CPT posts from a single page's HTML using VIBECODE_CPT_BLOCK / /VIBECODE_CPT_BLOCK markers.
	 *
	 * @param string $html         Full page HTML.
	 * @param string $page_slug    Page slug for logging.
	 * @param array  $config      Full shortcodes config (cpt_field_maps or extract_cpt_pages for field inference).
	 * @param string $project_slug Project slug for logging.
	 * @return array{created: int, updated: int, errors: array<int, string>}
	 */
	public static function extract_hinted_blocks_from_page( string $html, string $page_slug, array $config, string $project_slug ): array {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'errors'  => array(),
		);

		// Match <!-- VIBECODE_CPT_BLOCK attr="value" ... --> content <!-- /VIBECODE_CPT_BLOCK --> (s = dotall).
		$pattern = '#<!--\s*' . preg_quote( self::CPT_BLOCK_OPEN, '#' ) . '\s+([^>]+)\s*-->\s*(.*?)\s*<!--\s*' . preg_quote( self::CPT_BLOCK_CLOSE, '#' ) . '\s*-->#s';
		if ( ! preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			return $result;
		}

		foreach ( $matches as $block_match ) {
			$attr_string = trim( $block_match[1] );
			$block_html  = trim( $block_match[2] );
			if ( $block_html === '' ) {
				continue;
			}

			$cpt       = self::parse_block_attr( $attr_string, 'cpt' );
			$taxonomy  = self::parse_block_attr( $attr_string, 'taxonomy' );
			$term_slug = self::parse_block_attr( $attr_string, 'term' );
			if ( $cpt === '' ) {
				$result['errors'][] = sprintf( '%s: VIBECODE_CPT_BLOCK missing cpt attribute', $page_slug );
				continue;
			}

			$cpt = sanitize_key( $cpt );
			if ( ! post_type_exists( $cpt ) ) {
				Logger::warning( 'CPT hint extraction: post type not registered, skipping block.', array( 'cpt' => $cpt, 'page' => $page_slug ), $project_slug );
				continue;
			}

			$fields = self::get_field_map_for_cpt( $cpt, $config );
			if ( empty( $fields ) ) {
				Logger::warning( 'CPT hint extraction: no field map for CPT, skipping block.', array( 'cpt' => $cpt, 'page' => $page_slug ), $project_slug );
				continue;
			}

			libxml_use_internal_errors( true );
			$dom = new \DOMDocument();
			$dom->encoding = 'UTF-8';
			$loaded       = $dom->loadHTML( '<?xml encoding="UTF-8"><root>' . $block_html . '</root>' );
			libxml_clear_errors();
			if ( ! $loaded ) {
				$result['errors'][] = sprintf( '%s: failed to parse block HTML for %s', $page_slug, $cpt );
				continue;
			}

			$xpath = new \DOMXPath( $dom );
			$root  = $xpath->query( '//root' )->item( 0 );
			if ( ! $root instanceof \DOMNode ) {
				continue;
			}

			$post_title   = '';
			$post_content = '';
			$meta         = array();

			foreach ( $fields as $field_key => $selector ) {
				if ( ! is_string( $selector ) ) {
					continue;
				}
				$nodes = self::query_selector( $xpath, $root, $selector );
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

		return $result;
	}

	/**
	 * Parse a single attribute from VIBECODE_CPT_BLOCK opening comment (e.g. cpt="bgp_product").
	 *
	 * @param string $attr_string Full attribute string.
	 * @param string $key         Attribute name.
	 * @return string Value or empty string.
	 */
	private static function parse_block_attr( string $attr_string, string $key ): string {
		if ( preg_match( '/\b' . preg_quote( $key, '/' ) . '\s*=\s*"([^"]*)"/', $attr_string, $m ) ) {
			return trim( $m[1] );
		}
		return '';
	}

	/**
	 * Get field map (selectors) for a CPT: from cpt_field_maps config or first matching section in extract_cpt_pages.
	 *
	 * @param string $cpt    Post type.
	 * @param array  $config Full shortcodes config.
	 * @return array<string, string> Map of field_key => selector (e.g. post_title => .title).
	 */
	private static function get_field_map_for_cpt( string $cpt, array $config ): array {
		if ( ! empty( $config['cpt_field_maps'][ $cpt ] ) && is_array( $config['cpt_field_maps'][ $cpt ] ) ) {
			return $config['cpt_field_maps'][ $cpt ];
		}
		$pages_config = isset( $config['extract_cpt_pages'] ) && is_array( $config['extract_cpt_pages'] ) ? $config['extract_cpt_pages'] : array();
		foreach ( $pages_config as $page_config ) {
			if ( ! is_array( $page_config ) || empty( $page_config['sections'] ) ) {
				continue;
			}
			foreach ( $page_config['sections'] as $section ) {
				if ( ! is_array( $section ) || empty( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
					continue;
				}
				if ( isset( $section['cpt'] ) && sanitize_key( $section['cpt'] ) === $cpt ) {
					return $section['fields'];
				}
			}
		}
		return array();
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
