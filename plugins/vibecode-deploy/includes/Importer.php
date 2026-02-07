<?php

namespace VibeCode\Deploy;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Services\DeployService;
use VibeCode\Deploy\Services\MediaLibraryService;
use VibeCode\Deploy\Services\ShortcodePlaceholderService;
use VibeCode\Deploy\Settings;

defined( 'ABSPATH' ) || exit;

final class Importer {
	public const META_PROJECT_SLUG = '_vibecode_deploy_project_slug';
	public const META_SOURCE_PATH = '_vibecode_deploy_source_path';
	public const META_FINGERPRINT = '_vibecode_deploy_fingerprint';
	public const META_ASSET_CSS = '_vibecode_deploy_assets_css';
	public const META_ASSET_JS = '_vibecode_deploy_assets_js';
	public const META_ASSET_FONTS = '_vibecode_deploy_assets_fonts';
	public const META_ASSET_CDN_SCRIPTS = '_vibecode_deploy_assets_cdn_scripts';
	public const META_ASSET_CDN_CSS = '_vibecode_deploy_assets_cdn_css';
	public const META_BODY_CLASS = '_vibecode_deploy_body_class';

	public static function get_active_fingerprint( string $project_slug ): string {
		return BuildService::get_active_fingerprint( $project_slug );
	}

	public static function set_active_fingerprint( string $project_slug, string $fingerprint ): bool {
		return BuildService::set_active_fingerprint( $project_slug, $fingerprint );
	}

	public static function clear_active_fingerprint( string $project_slug ): bool {
		return BuildService::clear_active_fingerprint( $project_slug );
	}

	/**
	 * Enqueue Google Fonts at high priority (before WordPress core styles).
	 * 
	 * This ensures fonts load first, before resets and project CSS.
	 */
	public static function enqueue_fonts(): void {
		if ( is_admin() ) {
			return;
		}

		// Per-page fonts for Vibe Code Deploy-owned pages
		if ( is_singular( 'page' ) ) {
			$post_id = (int) get_queried_object_id();
			if ( $post_id > 0 ) {
				$project_slug = (string) get_post_meta( $post_id, self::META_PROJECT_SLUG, true );
				if ( $project_slug !== '' ) {
					$fonts = get_post_meta( $post_id, self::META_ASSET_FONTS, true );
					if ( is_array( $fonts ) && ! empty( $fonts ) ) {
						Logger::info( 'Enqueuing Google Fonts for page.', array(
							'post_id' => $post_id,
							'project_slug' => $project_slug,
							'font_count' => count( $fonts ),
							'font_urls' => $fonts,
						) );
						foreach ( $fonts as $font_url ) {
							if ( ! is_string( $font_url ) || $font_url === '' ) {
								continue;
							}
							$handle = 'vibecode-deploy-fonts-' . md5( $font_url );
							// Check if already enqueued to avoid duplicates
							if ( ! wp_style_is( $handle, 'enqueued' ) && ! wp_style_is( $handle, 'done' ) ) {
								wp_enqueue_style( $handle, $font_url, array(), null );
								Logger::info( 'Enqueued Google Font.', array(
									'handle' => $handle,
									'url' => $font_url,
								) );
							} else {
								Logger::info( 'Google Font already enqueued, skipping.', array(
									'handle' => $handle,
									'url' => $font_url,
								) );
							}
						}
					} else {
						Logger::info( 'No fonts found in post meta for page.', array(
							'post_id' => $post_id,
							'project_slug' => $project_slug,
							'fonts_meta' => $fonts,
						) );
					}
				}
			}
		}
	}

	public static function enqueue_assets_for_current_page(): void {
		if ( is_admin() ) {
			return;
		}

		$enqueued_css_paths = array();
		$enqueued_js_paths = array();
		$enqueued_font_urls = array();
		$script_attr_map = array();

		// 0.5) Enqueue generalized WordPress resets CSS (after fonts, before project CSS)
		$plugin_dir = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) : '';
		$resets_css_file = $plugin_dir . '/assets/css/wordpress-resets.css';
		if ( file_exists( $resets_css_file ) ) {
			$resets_handle = 'vibecode-deploy-wordpress-resets';
			$resets_url = plugins_url( 'assets/css/wordpress-resets.css', VIBECODE_DEPLOY_PLUGIN_FILE );
			$resets_version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION : '0.1.1';
			wp_enqueue_style( $resets_handle, $resets_url, array(), $resets_version );
		}

		// 1) Per-page assets for Vibe Code Deploy-owned pages (if present).
		if ( is_singular( 'page' ) ) {
			$post_id = (int) get_queried_object_id();
			if ( $post_id > 0 ) {
				$project_slug = (string) get_post_meta( $post_id, self::META_PROJECT_SLUG, true );
				$fingerprint = (string) get_post_meta( $post_id, self::META_FINGERPRINT, true );
				if ( $project_slug !== '' && $fingerprint !== '' ) {
					$uploads = wp_upload_dir();
					$base_url = rtrim( (string) $uploads['baseurl'], '/\\' ) . '/vibecode-deploy/staging/' . rawurlencode( $project_slug ) . '/' . rawurlencode( $fingerprint ) . '/';

					$css = get_post_meta( $post_id, self::META_ASSET_CSS, true );
					if ( is_array( $css ) && ! empty( $css ) ) {
						Logger::info( 'Enqueuing per-page CSS assets.', array( 
							'post_id' => $post_id, 
							'project_slug' => $project_slug, 
							'fingerprint' => $fingerprint,
							'css_files' => $css,
							'count' => count( $css )
						) );
						foreach ( $css as $i => $href ) {
							if ( ! is_string( $href ) || $href === '' ) {
								continue;
							}
							$enqueued_css_paths[] = $href;
							$handle = 'vibecode-deploy-css-' . md5( $project_slug . '|' . $fingerprint . '|' . $href . '|' . (string) $i );
							// Include file modification time for cache busting
							$css_path = rtrim( BuildService::build_root_path( $project_slug, $fingerprint ), '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $href );
							$file_mtime = is_file( $css_path ) ? (string) filemtime( $css_path ) : '';
							$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $fingerprint . ( $file_mtime !== '' ? '-' . $file_mtime : '' ) : $fingerprint . ( $file_mtime !== '' ? '-' . $file_mtime : '' );
							$css_url = $base_url . ltrim( $href, '/' );
							wp_enqueue_style( $handle, $css_url, array(), $version );
							Logger::info( 'Enqueued CSS file.', array( 
								'handle' => $handle, 
								'url' => $css_url, 
								'version' => $version,
								'file_exists' => is_file( $css_path )
							) );
						}
					} else {
						Logger::info( 'No per-page CSS assets found.', array( 
							'post_id' => $post_id, 
							'project_slug' => $project_slug,
							'css_meta' => $css
						) );
					}

					// Enqueue CDN CSS first (before local CSS)
					$cdn_css = get_post_meta( $post_id, self::META_ASSET_CDN_CSS, true );
					if ( is_array( $cdn_css ) && ! empty( $cdn_css ) ) {
						foreach ( $cdn_css as $cdn_css_item ) {
							if ( ! is_array( $cdn_css_item ) || ! isset( $cdn_css_item['url'] ) || ! isset( $cdn_css_item['handle'] ) ) {
								continue;
							}
							$cdn_css_handle = sanitize_key( $cdn_css_item['handle'] );
							$cdn_css_url = esc_url_raw( $cdn_css_item['url'] );
							if ( $cdn_css_handle !== '' && $cdn_css_url !== '' ) {
								wp_enqueue_style( $cdn_css_handle, $cdn_css_url, array(), null );
							}
						}
					}

					// Enqueue CDN scripts (before local scripts, respecting dependencies)
					$cdn_scripts = get_post_meta( $post_id, self::META_ASSET_CDN_SCRIPTS, true );
					if ( is_array( $cdn_scripts ) && ! empty( $cdn_scripts ) ) {
						// Sort by dependencies: scripts with no deps first, then by dependency chain
						usort( $cdn_scripts, function( $a, $b ) {
							$a_deps = isset( $a['deps'] ) && is_array( $a['deps'] ) ? $a['deps'] : array();
							$b_deps = isset( $b['deps'] ) && is_array( $b['deps'] ) ? $b['deps'] : array();
							// If a depends on b, a should come after b
							if ( in_array( $b['handle'] ?? '', $a_deps, true ) ) {
								return 1;
							}
							if ( in_array( $a['handle'] ?? '', $b_deps, true ) ) {
								return -1;
							}
							return 0;
						});
						
						foreach ( $cdn_scripts as $cdn_script ) {
							if ( ! is_array( $cdn_script ) || ! isset( $cdn_script['url'] ) || ! isset( $cdn_script['handle'] ) ) {
								continue;
							}
							$cdn_handle = sanitize_key( $cdn_script['handle'] );
							$cdn_url = esc_url_raw( $cdn_script['url'] );
							$cdn_deps = isset( $cdn_script['deps'] ) && is_array( $cdn_script['deps'] ) ? $cdn_script['deps'] : array();
							$cdn_version = isset( $cdn_script['version'] ) ? $cdn_script['version'] : null;
							
							if ( $cdn_handle !== '' && $cdn_url !== '' ) {
								// CDN scripts load in footer (false) to ensure dependencies are ready
								wp_enqueue_script( $cdn_handle, $cdn_url, $cdn_deps, $cdn_version, false );
							}
						}
					}

					$js = get_post_meta( $post_id, self::META_ASSET_JS, true );
					if ( is_array( $js ) ) {
						foreach ( $js as $i => $item ) {
							if ( ! is_array( $item ) ) {
								continue;
							}

							$src = isset( $item['src'] ) && is_string( $item['src'] ) ? (string) $item['src'] : '';
							if ( $src === '' ) {
								continue;
							}

							$enqueued_js_paths[] = $src;
				$handle = 'vibecode-deploy-js-' . md5( $project_slug . '|' . $fingerprint . '|' . $src . '|' . (string) $i );
				$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $fingerprint : $fingerprint;
				
				// Check if this script depends on any CDN scripts
				$script_deps = array();
				if ( strpos( $src, 'map.js' ) !== false ) {
					// map.js depends on Leaflet.js
					$script_deps[] = 'leaflet-js';
				}
				
				wp_enqueue_script( $handle, $base_url . ltrim( $src, '/' ), $script_deps, $version, true );

							$script_attr_map[ $handle ] = array(
								'defer' => ! empty( $item['defer'] ),
								'async' => ! empty( $item['async'] ),
								'type' => isset( $item['type'] ) && is_string( $item['type'] ) ? (string) $item['type'] : '',
							);
						}
					}
				}
			}
		}

		// 2) Global active-build assets (archives, non-owned pages like Secure Drop, etc.).
		// Always enqueue global assets to ensure styling works even if per-page assets are missing.
		// CRITICAL: Global CSS (styles.css, icons.css) MUST use the active fingerprint from
		// BuildService::get_active_fingerprint(), NOT the page's fingerprint from post meta.
		// This ensures CSS updates are immediately visible after deployment without needing
		// to re-import every page to update their fingerprint post meta.
		$settings = Settings::get_all();
		$default_project_slug = isset( $settings['project_slug'] ) && is_string( $settings['project_slug'] ) ? (string) $settings['project_slug'] : '';
		$default_project_slug = sanitize_key( $default_project_slug );
		if ( $default_project_slug === '' ) {
			$default_project_slug = 'default';
		}

		// ALWAYS use the active fingerprint for global CSS assets
		// This is the key fix: global CSS should load from the ACTIVE deployment,
		// not from stale fingerprints stored in page post meta.
		$active_fingerprint = BuildService::get_active_fingerprint( $default_project_slug );
		if ( $active_fingerprint === '' ) {
			// Fallback to most recent build if no active fingerprint set
			$fingerprints = BuildService::list_build_fingerprints( $default_project_slug );
			if ( ! empty( $fingerprints ) && is_string( $fingerprints[0] ?? null ) ) {
				$active_fingerprint = (string) $fingerprints[0];
			}
		}

		if ( $active_fingerprint !== '' ) {
			$uploads = wp_upload_dir();
			$base_url = rtrim( (string) $uploads['baseurl'], '/\\' ) . '/vibecode-deploy/staging/' . rawurlencode( $default_project_slug ) . '/' . rawurlencode( $active_fingerprint ) . '/';
			$base_dir = BuildService::build_root_path( $default_project_slug, $active_fingerprint );

			// Always enqueue global CSS (even if per-page assets exist, ensure base styles load)
			$global_css = array(
				'css/icons.css',
				'css/styles.css',
			);
			foreach ( $global_css as $href ) {
				// Check if already enqueued as per-page asset
				if ( in_array( $href, $enqueued_css_paths, true ) ) {
					continue;
				}
				$path = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $href );
				if ( ! is_file( $path ) ) {
					continue;
				}
				$handle = 'vibecode-deploy-global-css-' . md5( $default_project_slug . '|' . $active_fingerprint . '|' . $href );
				// Include file modification time for cache busting
				$file_mtime = (string) filemtime( $path );
				$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $active_fingerprint . '-' . $file_mtime : $active_fingerprint . '-' . $file_mtime;
				wp_enqueue_style( $handle, $base_url . ltrim( $href, '/' ), array(), $version );
			}

			// 3) Page-specific CSS detection (e.g., css/secure-drop.css for secure-drop page)
			if ( is_singular( 'page' ) ) {
				$post_id = (int) get_queried_object_id();
				if ( $post_id > 0 ) {
					$page_slug = get_post_field( 'post_name', $post_id );
					if ( is_string( $page_slug ) && $page_slug !== '' ) {
						$page_css = 'css/' . sanitize_file_name( $page_slug ) . '.css';
						// Check if already enqueued
						if ( ! in_array( $page_css, $enqueued_css_paths, true ) ) {
							$page_css_path = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $page_css );
							if ( is_file( $page_css_path ) ) {
								$enqueued_css_paths[] = $page_css;
								$handle = 'vibecode-deploy-page-css-' . md5( $default_project_slug . '|' . $active_fingerprint . '|' . $page_css );
								// Include file modification time for cache busting
								$file_mtime = (string) filemtime( $page_css_path );
								$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $active_fingerprint . '-' . $file_mtime : $active_fingerprint . '-' . $file_mtime;
								wp_enqueue_style( $handle, $base_url . ltrim( $page_css, '/' ), array(), $version );
								Logger::info( 'Enqueued page-specific CSS file.', array(
									'post_id' => $post_id,
									'page_slug' => $page_slug,
									'css_file' => $page_css,
									'handle' => $handle,
									'url' => $base_url . ltrim( $page_css, '/' ),
									'version' => $version,
									'file_exists' => true,
								) );
							} else {
								Logger::info( 'Page-specific CSS file not found (skipping).', array(
									'post_id' => $post_id,
									'page_slug' => $page_slug,
									'css_file' => $page_css,
									'expected_path' => $page_css_path,
									'file_exists' => false,
								) );
							}
						} else {
							Logger::info( 'Page-specific CSS already enqueued (skipping duplicate).', array(
								'post_id' => $post_id,
								'page_slug' => $page_slug,
								'css_file' => $page_css,
							) );
						}
					}
				}
			}

			// Always enqueue global JS (even if per-page assets exist, ensure base scripts load)
			$global_js = array(
				array( 'src' => 'js/route-adapter.js', 'defer' => true ),
				array( 'src' => 'js/icons.js', 'defer' => true ),
				array( 'src' => 'js/main.js', 'defer' => true ),
			);
			foreach ( $global_js as $it ) {
				$src = isset( $it['src'] ) && is_string( $it['src'] ) ? (string) $it['src'] : '';
				if ( $src === '' || in_array( $src, $enqueued_js_paths, true ) ) {
					continue;
				}
				$path = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $src );
				if ( ! is_file( $path ) ) {
					continue;
				}
				$handle = 'vibecode-deploy-global-js-' . md5( $default_project_slug . '|' . $active_fingerprint . '|' . $src );
				$version = defined( 'VIBECODE_DEPLOY_PLUGIN_VERSION' ) ? VIBECODE_DEPLOY_PLUGIN_VERSION . '-' . $active_fingerprint : $active_fingerprint;
				wp_enqueue_script( $handle, $base_url . ltrim( $src, '/' ), array(), $version, true );
				$script_attr_map[ $handle ] = array(
					'defer' => ! empty( $it['defer'] ),
					'async' => ! empty( $it['async'] ),
					'type' => isset( $it['type'] ) && is_string( $it['type'] ) ? (string) $it['type'] : '',
				);
			}
		}

		if ( ! empty( $script_attr_map ) ) {
			add_filter(
				'script_loader_tag',
				function ( $tag, $handle, $src_attr ) use ( $script_attr_map ) {
					if ( ! isset( $script_attr_map[ $handle ] ) ) {
						return $tag;
					}

					$attrs = $script_attr_map[ $handle ];
					$extra = '';

					if ( ! empty( $attrs['defer'] ) && strpos( $tag, ' defer' ) === false ) {
						$extra .= ' defer';
					}
					if ( ! empty( $attrs['async'] ) && strpos( $tag, ' async' ) === false ) {
						$extra .= ' async';
					}
					if ( ! empty( $attrs['type'] ) && strpos( $tag, ' type=' ) === false ) {
						$extra .= ' type="' . esc_attr( (string) $attrs['type'] ) . '"';
					}

					if ( $extra === '' ) {
						return $tag;
					}

					return str_replace( '<script ', '<script ' . trim( $extra ) . ' ', $tag );
				},
				10,
				3
			);
		}
	}

	public static function get_project_staging_dir( string $project_slug ): string {
		return BuildService::get_project_staging_dir( $project_slug );
	}

	public static function list_build_fingerprints( string $project_slug ): array {
		return BuildService::list_build_fingerprints( $project_slug );
	}

	public static function build_root_path( string $project_slug, string $fingerprint ): string {
		return BuildService::build_root_path( $project_slug, $fingerprint );
	}

	public static function pages_dir( string $build_root ): string {
		return rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'pages';
	}

	public static function list_page_files( string $build_root ): array {
		$pages = self::pages_dir( $build_root );
		if ( ! is_dir( $pages ) ) {
			return array();
		}

		$files = glob( $pages . DIRECTORY_SEPARATOR . '*.html' ) ?: array();
		$files = is_array( $files ) ? $files : array();
		sort( $files );
		return $files;
	}

	/**
	 * Build project-agnostic path variations for resolving image files in staging.
	 * Always tries standard locations (e.g. resources/images/) so any project finds the file.
	 *
	 * @param string $normalized_path Relative path (no leading ./ or /).
	 * @return array List of path strings to try under build_root.
	 */
	private static function get_image_path_variations( string $normalized_path ): array {
		$variations = array( $normalized_path );
		$variations[] = 'resources/images/' . basename( $normalized_path );
		if ( strpos( $normalized_path, 'resources/images/' ) !== 0 ) {
			$variations[] = 'resources/images/' . $normalized_path;
		}
		if ( strpos( $normalized_path, 'resources/' ) !== 0 ) {
			$variations[] = 'resources/' . $normalized_path;
		}
		if ( strpos( $normalized_path, 'images/' ) !== 0 ) {
			$variations[] = 'images/' . $normalized_path;
		}
		return array_values( array_unique( $variations ) );
	}

	private static function inner_html( \DOMDocument $dom, \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
	}

	private static function block_open( string $block_name, array $attrs ): string {
		if ( empty( $attrs ) ) {
			return '<!-- wp:' . $block_name . ' -->';
		}
		return '<!-- wp:' . $block_name . ' ' . wp_json_encode( $attrs ) . ' -->';
	}

	private static function block_close( string $block_name ): string {
		return '<!-- /wp:' . $block_name . ' -->';
	}

	private static function pick_attributes( \DOMElement $el, array $extra = array() ): array {
		$attrs = array();
		foreach ( $el->attributes as $attr ) {
			if ( ! ( $attr instanceof \DOMAttr ) ) {
				continue;
			}
			$attrs[ $attr->name ] = $attr->value;
		}
		foreach ( $extra as $k => $v ) {
			$attrs[ $k ] = $v;
		}
		return $attrs;
	}

	/**
	 * Convert DOM children to Gutenberg blocks.
	 *
	 * @param \DOMNode $parent Parent DOM node.
	 * @param string $build_root Optional build root path for image uploads during deployment.
	 * @return string Gutenberg block markup.
	 */
	/**
	 * Check if parent node is a list (ul/ol) or inside a list.
	 * 
	 * @param \DOMNode $parent Parent node to check.
	 * @return bool True if parent is a list or inside a list.
	 */
	private static function is_list_context( \DOMNode $parent ): bool {
		if ( $parent instanceof \DOMElement ) {
			$tag = strtolower( $parent->tagName );
			if ( $tag === 'ul' || $tag === 'ol' ) {
				return true;
			}
			// Check if parent is inside a list
			return self::is_inside_list_ancestor( $parent );
		}
		return false;
	}

	private static function convert_dom_children( \DOMNode $parent, string $build_root = '' ): string {
		$blocks = '';
		
		// Check if we're in a list context - if so, preserve HTML instead of converting to blocks
		$in_list_context = self::is_list_context( $parent );

		foreach ( $parent->childNodes as $child ) {
			if ( $child instanceof \DOMComment ) {
				$comment = trim( (string) ( $child->data ?? '' ) );
				if ( $comment === '' ) {
					continue;
				}
				$block = ShortcodePlaceholderService::comment_to_shortcode_block( $comment );
				if ( is_string( $block ) && $block !== '' ) {
					$blocks .= $block . "\n";
				}
				continue;
			}

			if ( $child instanceof \DOMText ) {
				$text = trim( (string) ( $child->textContent ?? '' ) );
				if ( $text === '' ) {
					continue;
				}
				
				// Check if parent is an inline element (like <a>, <span>, etc.)
				// If so, don't wrap text in <p> tags - preserve as plain text
				$parent_tag = '';
				if ( $parent instanceof \DOMElement ) {
					$parent_tag = strtolower( $parent->tagName );
				}
				$inline_elements = array( 'a', 'span', 'strong', 'em', 'b', 'i', 'u', 'small', 'sub', 'sup', 'code', 'kbd', 'samp', 'var', 'mark', 'time', 'abbr', 'cite', 'q', 'dfn', 'button', 'label' );
				
				if ( in_array( $parent_tag, $inline_elements, true ) || $in_list_context ) {
					// For inline elements or list items, preserve text as-is (no <p> wrapper)
					$blocks .= esc_html( $text );
					continue;
				}
				
				// For block-level parents, convert to paragraph block
				$blocks .= self::block_open(
					'paragraph',
					array(
						'metadata' => array(
							'name' => 'Text',
							'etchData' => array(
								'origin' => 'etch',
								'removeWrapper' => true,
								'block' => array(
									'type' => 'text',
									'tag' => 'span',
								),
							),
						),
					)
				) . "\n";
				$blocks .= '<p>' . esc_html( $text ) . '</p>' . "\n";
				$blocks .= self::block_close( 'paragraph' ) . "\n";
				continue;
			}

			if ( ! ( $child instanceof \DOMElement ) ) {
				continue;
			}

			// If in list context, preserve HTML for non-list elements to prevent block groups inside list items
			if ( $in_list_context ) {
				$child_tag = strtolower( $child->tagName );
				// List items (li) are handled by convert_element() which checks is_inside_list
				// Other elements inside lists should be preserved as HTML
				if ( $child_tag !== 'ul' && $child_tag !== 'ol' && $child_tag !== 'li' ) {
					// Preserve as HTML instead of converting to blocks
					$inner_html = '';
					foreach ( $child->childNodes as $grandchild ) {
						$inner_html .= $child->ownerDocument->saveHTML( $grandchild );
					}
					$attrs_html = '';
					$child_attrs = self::pick_attributes( $child );
					foreach ( $child_attrs as $key => $value ) {
						if ( is_string( $value ) && $value !== '' ) {
							$attrs_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
						}
					}
					$blocks .= '<' . $child_tag . $attrs_html . '>' . $inner_html . '</' . $child_tag . '>';
					continue;
				}
			}

			$blocks .= self::convert_element( $child, $build_root );
		}

		return $blocks;
	}

	/**
	 * Build etchData structure for a block.
	 *
	 * @param string $tag HTML tag name (e.g., 'p', 'ul', 'img').
	 * @param array  $attrs HTML attributes.
	 * @param string $block_type Block type ('html' for most, 'text' for text-only blocks).
	 * @return array etchData array.
	 */
	private static function build_etch_data( string $tag, array $attrs, string $block_type = 'html' ): array {
		$attrs_for_json = empty( $attrs ) ? new \stdClass() : $attrs;
		
		return array(
			'origin' => 'etch',
			'attributes' => $attrs_for_json,
			'block' => array(
				'type' => $block_type,
				'tag' => $tag,
			),
		);
	}

	/**
	 * Convert a DOM element to Gutenberg block markup.
	 *
	 * @param \DOMElement $el DOM element to convert.
	 * @param string $build_root Optional build root path for image uploads during deployment.
	 * @return string Gutenberg block markup.
	 */
	/**
	 * Check if an element is inside a list (ul/ol) by traversing ancestor chain.
	 * 
	 * @param \DOMElement $el Element to check.
	 * @return bool True if element is inside a list.
	 */
	private static function is_inside_list_ancestor( \DOMElement $el ): bool {
		$current = $el->parentNode;
		while ( $current instanceof \DOMElement ) {
			$tag = strtolower( $current->tagName );
			if ( $tag === 'ul' || $tag === 'ol' ) {
				return true;
			}
			$current = $current->parentNode;
		}
		return false;
	}

	private static function convert_element( \DOMElement $el, string $build_root = '' ): string {
		$tag = strtolower( $el->tagName );
		$attrs = self::pick_attributes( $el );
		$attrs_for_json = empty( $attrs ) ? new \stdClass() : $attrs;
		
		// Check if this element is inside a list (ul/ol) by traversing ancestor chain
		// If so, list items and their content should remain as raw HTML, not blocks
		$is_inside_list = self::is_inside_list_ancestor( $el );

		// Special handling for void elements (br, hr, input, etc.)
		// Note: 'img' is handled separately as a semantic block (wp:image)
		$void_elements = array( 'br', 'hr', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr' );
		
		// Elements that should be preserved as-is (non-void elements with special requirements)
		$preserve_as_is_elements = array( 'iframe', 'svg', 'script', 'style' );
		
		// Combine all elements that should be preserved
		$all_preserve_elements = array_merge( $void_elements, $preserve_as_is_elements );
		
		if ( in_array( $tag, $all_preserve_elements, true ) ) {
			// Build the opening tag with all attributes preserved
			$element_html = '<' . $tag;
			foreach ( $attrs as $key => $value ) {
				if ( is_string( $value ) && $value !== '' ) {
					$element_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			$element_html .= '>';
			
			// For non-void preserved elements, extract and preserve inner HTML
			if ( in_array( $tag, $preserve_as_is_elements, true ) ) {
				$dom = $el->ownerDocument;
				if ( $dom instanceof \DOMDocument ) {
					$inner_html = self::inner_html( $dom, $el );
					$element_html .= $inner_html;
				}
				$element_html .= '</' . $tag . '>';
			}
			
			// Add etchData to make wp:html blocks editable in EtchWP IDE
			$html_attrs = array(
				'metadata' => array(
					'name' => strtoupper( $tag ),
					'etchData' => self::build_etch_data( $tag, $attrs ),
				),
			);
			
			return self::block_open( 'html', $html_attrs ) . "\n" .
				$element_html . "\n" .
				self::block_close( 'html' ) . "\n";
		}

		// Define block-level elements that should be converted to wp:group blocks
		// Note: Semantic content elements (p, ul, ol, blockquote, pre, table, img) are handled separately
		// Keep only structural containers: div, section, article, main, header, footer, aside, nav, form, dl, dt, dd, figure, figcaption, address
		$block_elements = array( 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'form', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'address' );
		
		// Define inline elements that should be preserved as-is (not converted to groups)
		$inline_elements = array( 'span', 'a', 'strong', 'em', 'b', 'i', 'u', 'small', 'sub', 'sup', 'code', 'kbd', 'samp', 'var', 'mark', 'time', 'abbr', 'cite', 'q', 'dfn', 'button', 'label', 'select', 'textarea', 'option', 'optgroup' );
		
		// Handle headings - convert to heading blocks, not groups
		if ( preg_match( '/^h[1-6]$/', $tag ) ) {
			$level = (int) substr( $tag, 1 );
			$inner = self::convert_dom_children( $el, $build_root );
			$inner_text = trim( strip_tags( $inner ) );
			
			// Build heading attributes
			$heading_attrs = array( 'level' => $level );
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$heading_attrs['className'] = $attrs['class'];
			}
			
			// Add etchData for EtchWP IDE editability
			$heading_attrs['metadata'] = array(
				'name' => 'Heading',
				'etchData' => self::build_etch_data( $tag, $attrs ),
			);
			
			// Add other attributes (id, data-*, etc.)
			$element_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( $key === 'class' ) {
					continue; // Already handled in className
				}
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			return self::block_open( 'heading', $heading_attrs ) . "\n" .
				'<' . $tag . $element_attrs . '>' . $inner_text . '</' . $tag . '>' . "\n" .
				self::block_close( 'heading' ) . "\n";
		}
		
		// Handle paragraphs - convert to paragraph blocks WITH etchData
		if ( $tag === 'p' ) {
			$inner = self::convert_dom_children( $el, $build_root );
			
			$paragraph_attrs = array();
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$paragraph_attrs['className'] = $attrs['class'];
			}
			
			// Add etchData for EtchWP IDE editability
			$paragraph_attrs['metadata'] = array(
				'name' => 'Paragraph',
				'etchData' => self::build_etch_data( 'p', $attrs ),
			);
			
			// Build HTML attributes (id, data-*, etc.)
			$element_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( $key === 'class' ) {
					continue; // Already handled in className
				}
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			return self::block_open( 'paragraph', $paragraph_attrs ) . "\n" .
				'<p' . $element_attrs . '>' . $inner . '</p>' . "\n" .
				self::block_close( 'paragraph' ) . "\n";
		}
		
		// Handle list items
		// IMPORTANT: List items inside <ul>/<ol> should remain as raw HTML (not blocks)
		// Only standalone <li> elements (rare edge case) should be converted to blocks
		if ( $tag === 'li' ) {
			$inner = self::convert_dom_children( $el, $build_root );
			
			// Build HTML attributes
			$element_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			// If inside a list, return as raw HTML (will be wrapped by the list block)
			if ( $is_inside_list ) {
				return '<li' . $element_attrs . '>' . $inner . '</li>';
			}
			
			// Standalone <li> (edge case) - convert to list-item block with etchData
			$list_item_attrs = array();
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$list_item_attrs['className'] = $attrs['class'];
			}
			
			// Add etchData for EtchWP IDE editability
			$list_item_attrs['metadata'] = array(
				'name' => 'List Item',
				'etchData' => self::build_etch_data( 'li', $attrs ),
			);
			
			return self::block_open( 'list-item', $list_item_attrs ) . "\n" .
				'<li' . $element_attrs . '>' . $inner . '</li>' . "\n" .
				self::block_close( 'list-item' ) . "\n";
		}
		
		// Handle lists - convert to list blocks WITH etchData
		if ( $tag === 'ul' || $tag === 'ol' ) {
			$inner = self::convert_dom_children( $el, $build_root );
			
			$list_attrs = array();
			if ( $tag === 'ol' ) {
				$list_attrs['ordered'] = true;
				// Extract type attribute (1, a, A, i, I)
				if ( isset( $attrs['type'] ) && is_string( $attrs['type'] ) ) {
					$list_attrs['type'] = $attrs['type'];
				}
				// Extract start attribute
				if ( isset( $attrs['start'] ) && is_numeric( $attrs['start'] ) ) {
					$list_attrs['start'] = (int) $attrs['start'];
				}
			}
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$list_attrs['className'] = $attrs['class'];
			}
			
			// Add etchData for EtchWP IDE editability
			$list_attrs['metadata'] = array(
				'name' => 'List',
				'etchData' => self::build_etch_data( $tag, $attrs ),
			);
			
			// Build HTML attributes (id, data-*, etc.)
			$element_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( in_array( $key, array( 'class', 'type', 'start' ), true ) ) {
					continue; // Already handled
				}
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			return self::block_open( 'list', $list_attrs ) . "\n" .
				'<' . $tag . $element_attrs . '>' . $inner . '</' . $tag . '>' . "\n" .
				self::block_close( 'list' ) . "\n";
		}
		
		// Handle images - convert to image blocks WITH etchData
		if ( $tag === 'img' ) {
			$image_attrs = array();
			$image_url = '';
			$attachment_id = 0;
			
			// Check if image was already processed by process_images_in_html() (has data-attachment-id)
			if ( isset( $attrs['data-attachment-id'] ) && is_numeric( $attrs['data-attachment-id'] ) ) {
				$attachment_id = (int) $attrs['data-attachment-id'];
				if ( $attachment_id > 0 ) {
					$image_url = MediaLibraryService::get_attachment_url( $attachment_id );
					$image_attrs['id'] = $attachment_id;
					Logger::info( 'Using pre-processed image from HTML processing.', array(
						'attachment_id' => $attachment_id,
						'image_url' => $image_url,
					) );
				}
			}
			
			// Check plugin setting for image storage method
			$settings = Settings::get_all();
			$storage_method = isset( $settings['image_storage_method'] ) && is_string( $settings['image_storage_method'] ) ? (string) $settings['image_storage_method'] : 'media_library';
			
			if ( isset( $attrs['src'] ) && is_string( $attrs['src'] ) && $attrs['src'] !== '' ) {
				$source_path = $attrs['src'];
				
				// If already processed, use the Media Library URL from src
				if ( $attachment_id > 0 && $image_url !== '' ) {
					$image_attrs['url'] = $image_url;
				} else {
					// Log image processing start
					Logger::info( 'Processing image for Media Library upload.', array(
						'source_path' => $source_path,
						'storage_method' => $storage_method,
						'build_root' => $build_root,
						'build_root_is_dir' => $build_root !== '' ? @is_dir( $build_root ) : false,
					) );
					
					if ( $storage_method === 'media_library' ) {
					// Media Library mode: Upload image to Media Library (or reuse existing)
					
					// Check if source_path is already a plugin URL or absolute URL
					$is_plugin_url = false;
					$is_absolute_url = false;
					$original_path = $source_path;
					
					// Check if it's an absolute URL (http://, https://, //)
					if ( preg_match( '/^(https?:|\/\/)/i', $source_path ) ) {
						$is_absolute_url = true;
						Logger::info( 'Skipping Media Library upload - absolute URL detected.', array(
							'source_path' => $source_path,
						) );
					} else {
						// Check if it's a plugin URL (already converted by rewrite_asset_urls)
						$plugin_url_base = plugins_url( 'assets', VIBECODE_DEPLOY_PLUGIN_FILE );
						if ( strpos( $source_path, $plugin_url_base . '/' ) === 0 ) {
							$is_plugin_url = true;
							// Extract original path from plugin URL
							// Format: [plugin_url]/assets/resources/image.jpg -> resources/image.jpg
							$path_after_assets = substr( $source_path, strlen( $plugin_url_base . '/' ) );
							$original_path = $path_after_assets;
							Logger::info( 'Extracted original path from plugin URL.', array(
								'plugin_url' => $source_path,
								'original_path' => $original_path,
							) );
						}
					}
					
					// Use build_root if provided (during deployment), otherwise fall back to active fingerprint
					$use_build_root = false;
					if ( $build_root !== '' && is_string( $build_root ) && strlen( $build_root ) > 0 ) {
						// is_dir() doesn't throw exceptions - it returns false or generates warnings
						// Use @ to suppress warnings if path is invalid
						$use_build_root = @is_dir( $build_root );
						Logger::info( 'Build root check.', array(
							'build_root' => $build_root,
							'is_dir' => $use_build_root,
						) );
					}
					
					if ( ! $is_absolute_url && $use_build_root ) {
						// Use the original path (either from src or extracted from plugin URL)
						$path_to_resolve = $original_path;
						
						// Normalize source path (remove leading ./ or /)
						$normalized_path = (string) preg_replace( '/^\.\//', '', $path_to_resolve );
						$normalized_path = ltrim( $normalized_path, '/' );
						
						$path_variations = self::get_image_path_variations( $normalized_path );
						$file_path = '';
						$found_path = '';
						
						foreach ( $path_variations as $path_var ) {
							$test_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $path_var );
							Logger::info( 'Trying image path variation.', array(
								'variation' => $path_var,
								'full_path' => $test_path,
								'exists' => file_exists( $test_path ),
								'readable' => file_exists( $test_path ) ? is_readable( $test_path ) : false,
							) );
							
							if ( file_exists( $test_path ) && is_readable( $test_path ) ) {
								$file_path = $test_path;
								$found_path = $path_var;
								break;
							}
						}
						
						if ( $file_path !== '' ) {
							// Extract filename from found path
							$filename = basename( $found_path );
							Logger::info( 'Image file found, uploading to Media Library.', array(
								'file_path' => $file_path,
								'filename' => $filename,
								'original_source_path' => $source_path,
							) );
							
							// Upload to Media Library (or reuse existing)
							$metadata = array();
							if ( isset( $attrs['alt'] ) && is_string( $attrs['alt'] ) ) {
								$metadata['alt'] = $attrs['alt'];
							}
							$result = MediaLibraryService::upload_image_to_media_library( $file_path, $filename, $source_path, $metadata );
							
							if ( is_array( $result ) && isset( $result['attachment_id'], $result['url'] ) && $result['url'] !== '' ) {
								$image_url = $result['url'];
								$image_attrs['id'] = $result['attachment_id'];
								Logger::info( 'Image uploaded to Media Library successfully.', array(
									'attachment_id' => $result['attachment_id'],
									'image_url' => $image_url,
								) );
							} else {
								Logger::warning( 'Media Library upload returned false.', array(
									'file_path' => $file_path,
									'filename' => $filename,
								) );
							}
						} else {
							Logger::warning( 'Image file not found in build root.', array(
								'build_root' => $build_root,
								'original_path' => $original_path,
								'normalized_path' => $normalized_path,
								'path_variations_tried' => $path_variations,
							) );
						}
					} elseif ( ! $is_absolute_url ) {
						// Fallback: Try to get build root from active fingerprint (for runtime processing)
						$project_slug = isset( $settings['project_slug'] ) && is_string( $settings['project_slug'] ) ? (string) $settings['project_slug'] : '';
						if ( $project_slug !== '' ) {
							$active_fingerprint = self::get_active_fingerprint( $project_slug );
							if ( $active_fingerprint !== '' ) {
								$fallback_build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
								Logger::info( 'Using fallback build root from active fingerprint.', array(
									'fallback_build_root' => $fallback_build_root,
									'active_fingerprint' => $active_fingerprint,
								) );
								
								// Use the original path
								$path_to_resolve = $original_path;
								
								// Normalize source path (remove leading ./ or /)
								$normalized_path = (string) preg_replace( '/^\.\//', '', $path_to_resolve );
								$normalized_path = ltrim( $normalized_path, '/' );
								
								$path_variations = self::get_image_path_variations( $normalized_path );
								$file_path = '';
								$found_path = '';
								
								foreach ( $path_variations as $path_var ) {
									$test_path = rtrim( $fallback_build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $path_var );
									if ( file_exists( $test_path ) && is_readable( $test_path ) ) {
										$file_path = $test_path;
										$found_path = $path_var;
										break;
									}
								}
								
								if ( $file_path !== '' ) {
									// Extract filename from found path
									$filename = basename( $found_path );
									Logger::info( 'Image file found in fallback build root, uploading to Media Library.', array(
										'file_path' => $file_path,
										'filename' => $filename,
									) );
									
									// Upload to Media Library (or reuse existing)
									$metadata = array();
									if ( isset( $attrs['alt'] ) && is_string( $attrs['alt'] ) ) {
										$metadata['alt'] = $attrs['alt'];
									}
									$result = MediaLibraryService::upload_image_to_media_library( $file_path, $filename, $source_path, $metadata );
									
									if ( is_array( $result ) && isset( $result['attachment_id'], $result['url'] ) && $result['url'] !== '' ) {
										$image_url = $result['url'];
										$image_attrs['id'] = $result['attachment_id'];
										Logger::info( 'Image uploaded to Media Library successfully (fallback).', array(
											'attachment_id' => $result['attachment_id'],
										) );
									}
								} else {
									Logger::warning( 'Image file not found in fallback build root.', array(
										'fallback_build_root' => $fallback_build_root,
										'original_path' => $original_path,
									) );
								}
							}
						}
					}
					
					// Fallback to plugin assets if Media Library upload failed
					if ( $image_url === '' ) {
						Logger::info( 'Falling back to plugin assets URL conversion.', array(
							'source_path' => $source_path,
						) );
						$image_url = \VibeCode\Deploy\Services\AssetService::convert_asset_path_to_url( $source_path );
					}
				} else {
					// Plugin assets mode (fallback)
					Logger::info( 'Using plugin assets mode (Media Library disabled).', array(
						'source_path' => $source_path,
					) );
					$image_url = \VibeCode\Deploy\Services\AssetService::convert_asset_path_to_url( $source_path );
				}
				
					// Only set URL if not already set from pre-processed image
					if ( ! isset( $image_attrs['url'] ) ) {
						$image_attrs['url'] = $image_url;
					}
				}
			}
			
			if ( isset( $attrs['alt'] ) && is_string( $attrs['alt'] ) ) {
				$image_attrs['alt'] = $attrs['alt'];
			}
			if ( isset( $attrs['width'] ) && is_numeric( $attrs['width'] ) ) {
				$image_attrs['width'] = (int) $attrs['width'];
			}
			if ( isset( $attrs['height'] ) && is_numeric( $attrs['height'] ) ) {
				$image_attrs['height'] = (int) $attrs['height'];
			}
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$image_attrs['className'] = $attrs['class'];
			}
			
			// Add etchData for EtchWP IDE editability
			$image_attrs['metadata'] = array(
				'name' => 'Image',
				'etchData' => self::build_etch_data( 'img', $attrs ),
			);
			
			// Build HTML attributes (id, data-*, etc.)
			// Use converted URL for src attribute in HTML. Output class on inner img so project CSS applies (e.g. .cfa-hero__logo).
			$element_attrs = '';
			if ( $image_url !== '' ) {
				$element_attrs .= ' src="' . esc_attr( $image_url ) . '"';
			}
			$img_class = isset( $image_attrs['className'] ) && is_string( $image_attrs['className'] ) && $image_attrs['className'] !== ''
				? $image_attrs['className']
				: ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) ? $attrs['class'] : '' );
			if ( $img_class !== '' ) {
				$element_attrs .= ' class="' . esc_attr( $img_class ) . '"';
			}
			foreach ( $attrs as $key => $value ) {
				if ( in_array( $key, array( 'class', 'src', 'alt', 'width', 'height' ), true ) ) {
					continue; // Already handled (class output above; rest in block attrs)
				}
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			return self::block_open( 'image', $image_attrs ) . "\n" .
				'<img' . $element_attrs . ' />' . "\n" .
				self::block_close( 'image' ) . "\n";
		}
		
		// Handle blockquotes - convert to quote blocks WITH etchData
		if ( $tag === 'blockquote' ) {
			$inner = self::convert_dom_children( $el, $build_root );
			
			$quote_attrs = array();
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$quote_attrs['className'] = $attrs['class'];
			}
			
			// Check for citation (cite attribute or <cite> element)
			$dom = $el->ownerDocument;
			$citation = '';
			if ( $dom instanceof \DOMDocument ) {
				$xpath = new \DOMXPath( $dom );
				$cite_elements = $xpath->query( './/cite', $el );
				if ( $cite_elements && $cite_elements->length > 0 ) {
					$citation = trim( $cite_elements->item( 0 )->textContent ?? '' );
				}
				if ( $citation === '' && isset( $attrs['cite'] ) && is_string( $attrs['cite'] ) ) {
					$citation = $attrs['cite'];
				}
			}
			if ( $citation !== '' ) {
				$quote_attrs['citation'] = $citation;
			}
			
			// Add etchData for EtchWP IDE editability
			$quote_attrs['metadata'] = array(
				'name' => 'Quote',
				'etchData' => self::build_etch_data( 'blockquote', $attrs ),
			);
			
			// Build HTML attributes (id, data-*, etc.)
			$element_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( in_array( $key, array( 'class', 'cite' ), true ) ) {
					continue; // Already handled
				}
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			return self::block_open( 'quote', $quote_attrs ) . "\n" .
				'<blockquote' . $element_attrs . '>' . $inner . '</blockquote>' . "\n" .
				self::block_close( 'quote' ) . "\n";
		}
		
		// Handle preformatted text - convert to preformatted blocks WITH etchData
		if ( $tag === 'pre' ) {
			$dom = $el->ownerDocument;
			if ( $dom instanceof \DOMDocument ) {
				$inner_html = self::inner_html( $dom, $el );
				
				$pre_attrs = array();
				if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
					$pre_attrs['className'] = $attrs['class'];
				}
				
				// Add etchData for EtchWP IDE editability
				$pre_attrs['metadata'] = array(
					'name' => 'Preformatted',
					'etchData' => self::build_etch_data( 'pre', $attrs ),
				);
				
				// Build HTML attributes (id, data-*, etc.)
				$element_attrs = '';
				foreach ( $attrs as $key => $value ) {
					if ( $key === 'class' ) {
						continue; // Already handled
					}
					if ( is_string( $value ) && $value !== '' ) {
						$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
					}
				}
				
				return self::block_open( 'preformatted', $pre_attrs ) . "\n" .
					'<pre' . $element_attrs . '>' . $inner_html . '</pre>' . "\n" .
					self::block_close( 'preformatted' ) . "\n";
			}
		}
		
		// Handle tables - convert to table blocks WITH etchData
		if ( $tag === 'table' ) {
			$inner = self::convert_dom_children( $el, $build_root );
			
			$table_attrs = array();
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$table_attrs['className'] = $attrs['class'];
			}
			
			// Add etchData for EtchWP IDE editability
			$table_attrs['metadata'] = array(
				'name' => 'Table',
				'etchData' => self::build_etch_data( 'table', $attrs ),
			);
			
			// Build HTML attributes (id, data-*, etc.)
			$element_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( $key === 'class' ) {
					continue; // Already handled
				}
				if ( is_string( $value ) && $value !== '' ) {
					$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			
			return self::block_open( 'table', $table_attrs ) . "\n" .
				'<table' . $element_attrs . '>' . $inner . '</table>' . "\n" .
				self::block_close( 'table' ) . "\n";
		}
		
		// Handle inline elements - keep as raw HTML (not blocks) so they stay inline in parent blocks
		if ( in_array( $tag, $inline_elements, true ) ) {
			$dom = $el->ownerDocument;
			if ( $dom instanceof \DOMDocument ) {
				$inner_html = self::convert_dom_children( $el, $build_root );
				$element_html = '<' . $tag;
				foreach ( $attrs as $key => $value ) {
					if ( is_string( $value ) && $value !== '' ) {
						$element_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
					}
				}
				$element_html .= '>' . $inner_html . '</' . $tag . '>';
				
				// Return as raw HTML string (not a block) - will be included in parent block
				return $element_html;
			}
		}
		
		// Handle block-level elements - convert to wp:group blocks
		if ( in_array( $tag, $block_elements, true ) ) {
			$styles = array();
			if ( ( $attrs['data-etch-element'] ?? '' ) === 'flex-div' || ( ( $attrs['class'] ?? '' ) !== '' && strpos( (string) $attrs['class'], 'flex' ) !== false ) ) {
				$styles[] = 'etch-flex-div-style';
			}

			$inner = self::convert_dom_children( $el, $build_root );

			$etch_data = array(
				'origin' => 'etch',
				'attributes' => $attrs_for_json,
				'block' => array(
					'type' => 'html',
					'tag' => $tag,
				),
			);

			if ( ! empty( $styles ) ) {
				$etch_data['styles'] = $styles;
			}

			// Preserve original classes on the original element
			// CRITICAL: This ensures CSS classes are preserved during Gutenberg conversion
			// We add 'wp-block-group' to the original element (not a wrapper div) to maintain semantic HTML
			$class_attr = '';
			$original_classes = '';
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$original_classes = $attrs['class'];
				$class_attr = ' class="' . esc_attr( $original_classes ) . ' wp-block-group"';
				
				// Log class preservation for debugging (only for important classes like hero--compact)
				if ( strpos( $original_classes, 'hero--compact' ) !== false || strpos( $original_classes, 'secure-drop' ) !== false ) {
					Logger::info( 'Preserved CSS classes during conversion.', array(
						'element' => $tag,
						'original_classes' => $original_classes,
						'final_classes' => $original_classes . ' wp-block-group',
						'classes_preserved' => true,
					) );
				}
			} else {
				$class_attr = ' class="wp-block-group"';
			}

			// Build additional attributes string (id, data-*, etc.)
			// These will be added directly to the original element (not a wrapper div)
			$additional_attrs = '';
			foreach ( $attrs as $key => $value ) {
				if ( $key === 'class' ) {
					continue; // Already handled above in $class_attr
				}
				if ( is_string( $value ) && $value !== '' ) {
					$additional_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}

			// Use the original element's tag directly (not wrapped in a div)
			// This preserves semantic HTML (section, article, etc.) while adding Gutenberg classes
			return self::block_open(
				'group',
				array(
					'metadata' => array(
						'name' => strtoupper( $tag ),
						'etchData' => $etch_data,
					),
				)
			) . "\n" .
				'<' . $tag . $class_attr . $additional_attrs . '>' . "\n" .
				$inner .
				'</' . $tag . '>' . "\n" .
				self::block_close( 'group' ) . "\n";
		}
		
		// Default: For any other elements, preserve as-is in wp:html block
		$dom = $el->ownerDocument;
		if ( $dom instanceof \DOMDocument ) {
			$inner_html = self::convert_dom_children( $el );
			$element_html = '<' . $tag;
			foreach ( $attrs as $key => $value ) {
				if ( is_string( $value ) && $value !== '' ) {
					$element_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
				}
			}
			$element_html .= '>' . $inner_html . '</' . $tag . '>';
			
			// Add etchData to make wp:html blocks editable in EtchWP IDE
			$html_attrs = array(
				'metadata' => array(
					'name' => strtoupper( $tag ),
					'etchData' => self::build_etch_data( $tag, $attrs ),
				),
			);
			
			return self::block_open( 'html', $html_attrs ) . "\n" .
				$element_html . "\n" .
				self::block_close( 'html' ) . "\n";
		}
		
		// Fallback: return empty if DOMDocument not available
		return '';
	}

	/**
	 * Convert HTML to Gutenberg blocks with EtchWP metadata.
	 *
	 * @param string $html HTML content to convert.
	 * @param string $build_root Optional build root path for image uploads during deployment.
	 * @return string Gutenberg block markup.
	 */
	public static function html_to_etch_blocks( string $html, string $build_root = '' ): string {
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->encoding = 'UTF-8';
		// Add UTF-8 encoding declaration to prevent character corruption
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"><!doctype html><html><body><div id="vibecode-deploy-import-root">' . $html . '</div></body></html>' );
		libxml_clear_errors();

		if ( ! $loaded ) {
			return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
		}

		$xpath = new \DOMXPath( $dom );
		$root = $xpath->query( '//*[@id="vibecode-deploy-import-root"]' )->item( 0 );
		if ( ! $root ) {
			return "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->";
		}

		$inner = self::convert_dom_children( $root, $build_root );

		return self::block_open(
			'group',
			array(
				'metadata' => array(
					'name' => 'Page Content',
					'etchData' => array(
						'origin' => 'etch',
						'component' => 'flex-div',
						'styles' => array( 'etch-flex-div-style' ),
						'attributes' => array(
							'data-etch-element' => 'flex-div',
						),
						'block' => array(
							'type' => 'html',
							'tag' => 'div',
						),
					),
				),
			)
		) . "\n" .
			'<div class="wp-block-group">' . "\n" .
			$inner .
			'</div>' . "\n" .
			self::block_close( 'group' );
	}

	private static function rewrite_urls( string $html, array $slug_set, string $resources_base_url ): string {
		return (string) preg_replace_callback(
			'/\b(href|src)=("|\')([^"\']+)(\2)/i',
			function ( $m ) use ( $slug_set, $resources_base_url ) {
				$attr = strtolower( $m[1] );
				$q = $m[2];
				$url = $m[3];

				if ( $url === '' || $url[0] === '#' ) {
					return $m[0];
				}

				$lower = strtolower( $url );
				$schemes = array( 'http://', 'https://', 'mailto:', 'tel:', 'data:', 'javascript:' );
				foreach ( $schemes as $scheme ) {
					if ( strpos( $lower, $scheme ) === 0 ) {
						return $m[0];
					}
				}

				$clean = (string) preg_replace( '/^\.\//', '', $url );

				if ( strpos( $clean, 'resources/' ) === 0 ) {
					$rest = substr( $clean, strlen( 'resources/' ) );
					return $attr . '=' . $q . rtrim( $resources_base_url, '/' ) . '/' . $rest . $q;
				}

				$parts = preg_split( '/(#|\?)/', $clean, 2, PREG_SPLIT_DELIM_CAPTURE );
				$path = $parts[0] ?? '';
				$suffix = '';
				if ( is_array( $parts ) && count( $parts ) > 1 ) {
					$suffix = $parts[1] . $parts[2];
				}

				$path = (string) preg_replace( '/\.html$/', '', (string) $path );
				$path = trim( $path, '/' );

				if ( $path !== '' && isset( $slug_set[ $path ] ) ) {
					return $attr . '=' . $q . '/' . $path . '/' . $suffix . $q;
				}

				return $m[0];
			},
			$html
		);
	}

	private static function title_from_dom( \DOMDocument $dom, string $fallback ): string {
		$nodes = $dom->getElementsByTagName( 'title' );
		if ( $nodes->length > 0 ) {
			$val = trim( (string) $nodes->item( 0 )->textContent );
			if ( $val !== '' ) {
				return $val;
			}
		}
		return $fallback;
	}

	private static function title_from_slug( string $slug ): string {
		$slug = str_replace( array( '-', '_' ), ' ', $slug );
		$slug = preg_replace( '/\s+/', ' ', $slug );
		return ucwords( trim( (string) $slug ) );
	}

	private static function normalize_local_asset_path( string $path ): string {
		$path = trim( $path );
		if ( $path === '' ) {
			return '';
		}

		$path = (string) preg_replace( '/^\.\//', '', $path );
		$path = ltrim( $path, '/' );
		return $path;
	}

	private static function extract_head_assets( \DOMDocument $dom ): array {
		$css = array();
		$js = array();

		$xpath = new \DOMXPath( $dom );
		$links = $xpath->query( '//head//link[@rel="stylesheet"]' );
		if ( $links ) {
			foreach ( $links as $node ) {
				if ( ! ( $node instanceof \DOMElement ) ) {
					continue;
				}
				$href = (string) $node->getAttribute( 'href' );
				$href = self::normalize_local_asset_path( $href );
				if ( $href === '' ) {
					continue;
				}
				if ( strpos( $href, 'http://' ) === 0 || strpos( $href, 'https://' ) === 0 ) {
					continue;
				}
				if ( strpos( $href, 'css/' ) !== 0 ) {
					continue;
				}
				$css[] = $href;
			}
		}

		$scripts = $xpath->query( '//script[@src]' );
		if ( $scripts ) {
			foreach ( $scripts as $node ) {
				if ( ! ( $node instanceof \DOMElement ) ) {
					continue;
				}
				$src = (string) $node->getAttribute( 'src' );
				$src = self::normalize_local_asset_path( $src );
				if ( $src === '' ) {
					continue;
				}
				if ( strpos( $src, 'http://' ) === 0 || strpos( $src, 'https://' ) === 0 ) {
					continue;
				}
				if ( strpos( $src, 'js/' ) !== 0 ) {
					continue;
				}

				$js[] = array(
					'src' => $src,
					'defer' => $node->hasAttribute( 'defer' ),
					'async' => $node->hasAttribute( 'async' ),
					'type' => (string) $node->getAttribute( 'type' ),
				);
			}
		}

		return array(
			'css' => array_values( array_unique( $css ) ),
			'js' => $js,
		);
	}

	private static function copy_dir_recursive( string $src, string $dest ): bool {
		if ( ! is_dir( $src ) ) {
			return true;
		}
		if ( ! wp_mkdir_p( $dest ) ) {
			return false;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$src_path = (string) $item->getPathname();
			$rel = substr( $src_path, strlen( rtrim( $src, '/\\' ) ) + 1 );
			$dest_path = rtrim( $dest, '/\\' ) . DIRECTORY_SEPARATOR . $rel;

			if ( $item->isDir() ) {
				if ( ! wp_mkdir_p( $dest_path ) ) {
					return false;
				}
				continue;
			}

			$dest_dir = dirname( $dest_path );
			if ( ! wp_mkdir_p( $dest_dir ) ) {
				return false;
			}

			if ( ! copy( $src_path, $dest_path ) ) {
				return false;
			}
		}

		return true;
	}

	public static function preflight( string $project_slug, string $build_root ): array {
		return DeployService::preflight( $project_slug, $build_root );
	}

	/**
	 * Process images in raw HTML before URL rewriting.
	 * 
	 * Finds all <img> tags, uploads to Media Library if enabled, and updates src attributes.
	 * This must run BEFORE rewrite_asset_urls() so images still have relative paths.
	 *
	 * @param string $html Raw HTML content.
	 * @param string $build_root Build root path for finding image files.
	 * @return string Modified HTML with updated image src attributes.
	 */
	public static function process_images_in_html( string $html, string $build_root = '' ): string {
		if ( $html === '' ) {
			return $html;
		}
		
		$settings = Settings::get_all();
		$storage_method = isset( $settings['image_storage_method'] ) && is_string( $settings['image_storage_method'] ) ? (string) $settings['image_storage_method'] : 'media_library';
		
		// If not using Media Library, return HTML unchanged
		if ( $storage_method !== 'media_library' ) {
			return $html;
		}
		
		// Check if build_root is valid
		$use_build_root = false;
		if ( $build_root !== '' && is_string( $build_root ) && strlen( $build_root ) > 0 ) {
			$use_build_root = @is_dir( $build_root );
		}
		
		if ( ! $use_build_root ) {
			Logger::info( 'Skipping image processing - build_root not available.', array(
				'build_root' => $build_root,
			) );
			return $html;
		}
		
		// Parse HTML to find all <img> tags
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->encoding = 'UTF-8';
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"><!doctype html><html><body><div id="vibecode-deploy-image-process-root">' . $html . '</div></body></html>' );
		libxml_clear_errors();
		
		if ( ! $loaded ) {
			Logger::warning( 'Failed to parse HTML for image processing.', array() );
			return $html;
		}
		
		$xpath = new \DOMXPath( $dom );
		$root = $xpath->query( '//*[@id="vibecode-deploy-image-process-root"]' )->item( 0 );
		if ( ! $root ) {
			return $html;
		}
		
		// Find all <img> tags
		$images = $xpath->query( './/img', $root );
		if ( ! $images || $images->length === 0 ) {
			return $html;
		}
		
		Logger::info( 'Processing images in HTML before URL rewriting.', array(
			'image_count' => $images->length,
			'build_root' => $build_root,
		) );
		
		$images_processed = 0;
		$images_uploaded = 0;
		
		foreach ( $images as $img ) {
			if ( ! ( $img instanceof \DOMElement ) ) {
				continue;
			}
			
			$src = $img->getAttribute( 'src' );
			if ( $src === '' ) {
				continue;
			}
			
			$images_processed++;
			
			// Skip absolute URLs
			if ( preg_match( '/^(https?:|\/\/)/i', $src ) ) {
				Logger::info( 'Skipping absolute URL image.', array( 'src' => $src ) );
				continue;
			}
			
			// Normalize source path
			$normalized_path = (string) preg_replace( '/^\.\//', '', $src );
			$normalized_path = ltrim( $normalized_path, '/' );
			
			$path_variations = self::get_image_path_variations( $normalized_path );
			$file_path = '';
			$found_path = '';
			
			foreach ( $path_variations as $path_var ) {
				$test_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $path_var );
				if ( file_exists( $test_path ) && is_readable( $test_path ) ) {
					$file_path = $test_path;
					$found_path = $path_var;
					break;
				}
			}
			
			if ( $file_path !== '' ) {
				$filename = basename( $found_path );
				$alt = $img->getAttribute( 'alt' );
				
				$metadata = array();
				if ( $alt !== '' ) {
					$metadata['alt'] = $alt;
				}
				
				Logger::info( 'Uploading image to Media Library from HTML processing.', array(
					'file_path' => $file_path,
					'filename' => $filename,
					'original_src' => $src,
				) );
				
				$result = MediaLibraryService::upload_image_to_media_library( $file_path, $filename, $src, $metadata );
				
				if ( is_array( $result ) && isset( $result['attachment_id'], $result['url'] ) && $result['url'] !== '' ) {
					$img->setAttribute( 'src', $result['url'] );
					$img->setAttribute( 'data-attachment-id', (string) $result['attachment_id'] );
					$images_uploaded++;
					Logger::info( 'Image uploaded and src updated in HTML.', array(
						'attachment_id' => $result['attachment_id'],
						'new_src' => $result['url'],
					) );
				} else {
					Logger::warning( 'Failed to upload image to Media Library.', array(
						'file_path' => $file_path,
						'filename' => $filename,
					) );
				}
			} else {
				Logger::warning( 'Image file not found in build root during HTML processing.', array(
					'original_src' => $src,
					'normalized_path' => $normalized_path,
					'path_variations_tried' => $path_variations,
				) );
			}
		}
		
		// Extract modified HTML
		$inner_html = '';
		foreach ( $root->childNodes as $child ) {
			$inner_html .= $dom->saveHTML( $child );
		}
		
		Logger::info( 'Image processing in HTML complete.', array(
			'images_processed' => $images_processed,
			'images_uploaded' => $images_uploaded,
		) );
		
		return $inner_html;
	}

	public static function run_import( string $project_slug, string $fingerprint, string $build_root, bool $force_claim_unowned, bool $deploy_template_parts = true, bool $generate_404_template = true, bool $force_claim_templates = false, bool $validate_cpt_shortcodes = false, array $selected_pages = array(), array $selected_css = array(), array $selected_js = array(), array $selected_templates = array(), array $selected_template_parts = array(), array $selected_theme_files = array() ): array {
		return DeployService::run_import( $project_slug, $fingerprint, $build_root, $force_claim_unowned, $deploy_template_parts, $generate_404_template, $force_claim_templates, $validate_cpt_shortcodes, $selected_pages, $selected_css, $selected_js, $selected_templates, $selected_template_parts, $selected_theme_files );
	}

	/**
	 * Add body class from post meta to body_class filter.
	 *
	 * Extracts body class stored in post meta during deployment and adds it to WordPress body_class.
	 * This ensures page-specific body classes (e.g., cfa-secure-drop-page) are preserved.
	 *
	 * @param array $classes Existing body classes.
	 * @return array Modified body classes with custom class added.
	 */
	public static function add_body_class_from_meta( array $classes ): array {
		if ( is_admin() ) {
			return $classes;
		}

		// Only apply to singular pages
		if ( ! is_singular( 'page' ) ) {
			return $classes;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id <= 0 ) {
			return $classes;
		}

		// Check if this page is owned by Vibe Code Deploy
		$project_slug = (string) get_post_meta( $post_id, self::META_PROJECT_SLUG, true );
		if ( $project_slug === '' ) {
			return $classes;
		}

		// Get body class from post meta
		$body_class = (string) get_post_meta( $post_id, self::META_BODY_CLASS, true );
		if ( $body_class !== '' ) {
			// Split multiple classes and add each one
			$custom_classes = array_filter( array_map( 'trim', explode( ' ', $body_class ) ) );
			$added_classes = array();
			foreach ( $custom_classes as $class ) {
				if ( $class !== '' && ! in_array( $class, $classes, true ) ) {
					$classes[] = sanitize_html_class( $class );
					$added_classes[] = sanitize_html_class( $class );
				}
			}
			if ( ! empty( $added_classes ) ) {
				Logger::info( 'Added body class from post meta.', array(
					'post_id' => $post_id,
					'project_slug' => $project_slug,
					'body_class_meta' => $body_class,
					'added_classes' => $added_classes,
				) );
			}
		} else {
			Logger::info( 'No body class found in post meta.', array(
				'post_id' => $post_id,
				'project_slug' => $project_slug,
			) );
		}

		return $classes;
	}
}
