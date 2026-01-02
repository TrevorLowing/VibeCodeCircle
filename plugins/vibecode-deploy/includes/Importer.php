<?php

namespace VibeCode\Deploy;

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

	public static function get_active_fingerprint( string $project_slug ): string {
		return BuildService::get_active_fingerprint( $project_slug );
	}

	public static function set_active_fingerprint( string $project_slug, string $fingerprint ): bool {
		return BuildService::set_active_fingerprint( $project_slug, $fingerprint );
	}

	public static function clear_active_fingerprint( string $project_slug ): bool {
		return BuildService::clear_active_fingerprint( $project_slug );
	}

	public static function enqueue_assets_for_current_page(): void {
		if ( is_admin() ) {
			return;
		}

		$enqueued_css_paths = array();
		$enqueued_js_paths = array();
		$script_attr_map = array();

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
					if ( is_array( $css ) ) {
						foreach ( $css as $i => $href ) {
							if ( ! is_string( $href ) || $href === '' ) {
								continue;
							}
							$enqueued_css_paths[] = $href;
							$handle = 'vibecode-deploy-css-' . md5( $project_slug . '|' . $fingerprint . '|' . $href . '|' . (string) $i );
							wp_enqueue_style( $handle, $base_url . ltrim( $href, '/' ), array(), null );
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
							wp_enqueue_script( $handle, $base_url . ltrim( $src, '/' ), array(), null, true );

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
				wp_enqueue_style( $handle, $base_url . ltrim( $href, '/' ), array(), null );
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
				wp_enqueue_script( $handle, $base_url . ltrim( $src, '/' ), array(), null, true );
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

		// Preserve original classes in the wrapper div
		$class_attr = '';
		if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
			$class_attr = ' class="' . esc_attr( $attrs['class'] ) . ' wp-block-group"';
		} else {
			$class_attr = ' class="wp-block-group"';
		}

		// Build additional attributes string (id, data-*, etc.)
		$additional_attrs = '';
		foreach ( $attrs as $key => $value ) {
			if ( $key === 'class' ) {
				continue; // Already handled above
			}
			if ( is_string( $value ) && $value !== '' ) {
				$additional_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		return self::block_open(
			'group',
			array(
				'metadata' => array(
					'name' => strtoupper( $tag ),
					'etchData' => $etch_data,
				),
			)
		) . "\n" .
			'<div' . $class_attr . $additional_attrs . '>' . "\n" .
			$inner .
			'</div>' . "\n" .
			self::block_close( 'group' ) . "\n";
	}

	public static function html_to_etch_blocks( string $html ): string {
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( '<!doctype html><html><body><div id="vibecode-deploy-import-root">' . $html . '</div></body></html>' );
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

	public static function run_import( string $project_slug, string $fingerprint, string $build_root, bool $set_front_page, bool $force_claim_unowned, bool $deploy_template_parts = true, bool $generate_404_template = true, bool $force_claim_templates = false, bool $validate_cpt_shortcodes = false ): array {
		return DeployService::run_import( $project_slug, $fingerprint, $build_root, $set_front_page, $force_claim_unowned, $deploy_template_parts, $generate_404_template, $force_claim_templates, $validate_cpt_shortcodes );
	}
}
