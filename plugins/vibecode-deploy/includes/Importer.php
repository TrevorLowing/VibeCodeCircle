<?php

namespace VibeCode\Deploy;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Services\DeployService;
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
				wp_enqueue_script( $handle, $base_url . ltrim( $src, '/' ), array(), $version, true );

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
		$settings = Settings::get_all();
		$default_project_slug = isset( $settings['project_slug'] ) && is_string( $settings['project_slug'] ) ? (string) $settings['project_slug'] : '';
		$default_project_slug = sanitize_key( $default_project_slug );
		if ( $default_project_slug === '' ) {
			$default_project_slug = 'default';
		}

		// Determine which fingerprint to use for global assets
		$active_fingerprint = '';
		if ( is_singular( 'page' ) ) {
			$post_id = (int) get_queried_object_id();
			if ( $post_id > 0 ) {
				$page_project_slug = (string) get_post_meta( $post_id, self::META_PROJECT_SLUG, true );
				$page_fingerprint = (string) get_post_meta( $post_id, self::META_FINGERPRINT, true );
				// Use page's fingerprint if it matches the default project slug
				if ( $page_project_slug === $default_project_slug && $page_fingerprint !== '' ) {
					$active_fingerprint = $page_fingerprint;
				}
			}
		}

		// Fall back to active fingerprint if page doesn't have one
		if ( $active_fingerprint === '' ) {
			$active_fingerprint = BuildService::get_active_fingerprint( $default_project_slug );
		}
		if ( $active_fingerprint === '' ) {
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

	private static function convert_dom_children( \DOMNode $parent ): string {
		$blocks = '';

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
				
				if ( in_array( $parent_tag, $inline_elements, true ) ) {
					// For inline elements, preserve text as-is (no <p> wrapper)
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

			$blocks .= self::convert_element( $child );
		}

		return $blocks;
	}

	private static function convert_element( \DOMElement $el ): string {
		$tag = strtolower( $el->tagName );
		$attrs = self::pick_attributes( $el );
		$attrs_for_json = empty( $attrs ) ? new \stdClass() : $attrs;

		// Special handling for void elements (img, br, hr, input, etc.)
		// These should be preserved as-is, not wrapped in divs
		$void_elements = array( 'img', 'br', 'hr', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr' );
		
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
			
			// Wrap in wp:html block to preserve the element
			return "<!-- wp:html -->\n" . $element_html . "\n<!-- /wp:html -->\n";
		}

		// Define block-level elements that should be converted to wp:group blocks
		$block_elements = array( 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'form', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'figure', 'figcaption', 'blockquote', 'pre', 'address' );
		
		// Define inline elements that should be preserved as-is (not converted to groups)
		$inline_elements = array( 'span', 'a', 'strong', 'em', 'b', 'i', 'u', 'small', 'sub', 'sup', 'code', 'kbd', 'samp', 'var', 'mark', 'time', 'abbr', 'cite', 'q', 'dfn', 'button', 'label', 'select', 'textarea', 'option', 'optgroup' );
		
		// Handle headings - convert to heading blocks, not groups
		if ( preg_match( '/^h[1-6]$/', $tag ) ) {
			$level = (int) substr( $tag, 1 );
			$inner = self::convert_dom_children( $el );
			$inner_text = trim( strip_tags( $inner ) );
			
			// Build heading attributes
			$heading_attrs = array( 'level' => $level );
			if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
				$heading_attrs['className'] = $attrs['class'];
			}
			
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
		
		// Handle inline elements - preserve as-is in wp:html blocks
		if ( in_array( $tag, $inline_elements, true ) ) {
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
				
				// Wrap in wp:html block to preserve the element
				return "<!-- wp:html -->\n" . $element_html . "\n<!-- /wp:html -->\n";
			}
		}
		
		// Handle block-level elements - convert to wp:group blocks
		if ( in_array( $tag, $block_elements, true ) ) {
			$styles = array();
			if ( ( $attrs['data-etch-element'] ?? '' ) === 'flex-div' || ( ( $attrs['class'] ?? '' ) !== '' && strpos( (string) $attrs['class'], 'flex' ) !== false ) ) {
				$styles[] = 'etch-flex-div-style';
			}

			$inner = self::convert_dom_children( $el );

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
			
			// Wrap in wp:html block to preserve the element
			return "<!-- wp:html -->\n" . $element_html . "\n<!-- /wp:html -->\n";
		}
		
		// Fallback: return empty if DOMDocument not available
		return '';
	}

	public static function html_to_etch_blocks( string $html ): string {
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

		$inner = self::convert_dom_children( $root );

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

	public static function run_import( string $project_slug, string $fingerprint, string $build_root, bool $set_front_page, bool $force_claim_unowned, bool $deploy_template_parts = true, bool $generate_404_template = true, bool $force_claim_templates = false, bool $validate_cpt_shortcodes = false, array $selected_pages = array(), array $selected_css = array(), array $selected_js = array(), array $selected_templates = array(), array $selected_template_parts = array(), array $selected_theme_files = array() ): array {
		return DeployService::run_import( $project_slug, $fingerprint, $build_root, $set_front_page, $force_claim_unowned, $deploy_template_parts, $generate_404_template, $force_claim_templates, $validate_cpt_shortcodes, $selected_pages, $selected_css, $selected_js, $selected_templates, $selected_template_parts, $selected_theme_files );
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
