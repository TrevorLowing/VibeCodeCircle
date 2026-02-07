<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

	final class TemplateService {
	public static function block_templates_supported(): bool {
		return post_type_exists( 'wp_template' ) && post_type_exists( 'wp_template_part' );
	}

	public static function current_theme_slug(): string {
		return sanitize_key( (string) get_stylesheet() );
	}

	private static function theme_post_name( string $slug ): string {
		$slug = sanitize_key( $slug );
		return $slug;
	}

	private static function legacy_theme_post_name( string $slug ): string {
		$theme = self::current_theme_slug();
		$slug = sanitize_key( $slug );
		if ( $theme === '' || $slug === '' ) {
			return '';
		}
		return sanitize_title( $theme . '//' . $slug );
	}

	private static function get_post_by_name_with_suffix( string $post_type, string $name ) {
		$name = sanitize_title( $name );
		if ( $name === '' ) {
			return null;
		}

		$exact = get_page_by_path( $name, OBJECT, $post_type );
		if ( $exact && isset( $exact->ID ) ) {
			return $exact;
		}

		global $wpdb;
		if ( ! isset( $wpdb ) || ! ( $wpdb instanceof \wpdb ) ) {
			return null;
		}

		$like = $wpdb->esc_like( $name ) . '-%';
		$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash' AND post_name LIKE %s ORDER BY ID DESC LIMIT 1",
			$post_type,
			$like
		);
		$post_id = (int) $wpdb->get_var( $sql );
		if ( $post_id > 0 ) {
			return get_post( $post_id );
		}

		return null;
	}

	public static function get_template_part_by_slug( string $slug ) {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return null;
		}

		$theme = self::current_theme_slug();
		if ( $theme !== '' && function_exists( 'get_block_template' ) ) {
			$tpl = get_block_template( $theme . '//' . $slug, 'wp_template_part' );
			if ( $tpl && isset( $tpl->wp_id ) && (int) $tpl->wp_id > 0 ) {
				return get_post( (int) $tpl->wp_id );
			}
		}

		// Core stores post_name as the plain slug and scopes it to a theme via the wp_theme taxonomy.
		$existing = get_page_by_path( $slug, OBJECT, 'wp_template_part' );
		if ( $existing && isset( $existing->ID ) ) {
			return $existing;
		}

		$legacy_name = self::legacy_theme_post_name( $slug );
		if ( $legacy_name !== '' ) {
			$legacy = self::get_post_by_name_with_suffix( 'wp_template_part', $legacy_name );
			if ( $legacy && isset( $legacy->ID ) ) {
				return $legacy;
			}
		}

		return get_page_by_path( $slug, OBJECT, 'wp_template_part' );
	}

	public static function get_template_by_slug( string $slug ) {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return null;
		}

		$theme = self::current_theme_slug();
		if ( $theme !== '' && function_exists( 'get_block_template' ) ) {
			$tpl = get_block_template( $theme . '//' . $slug, 'wp_template' );
			if ( $tpl && isset( $tpl->wp_id ) && (int) $tpl->wp_id > 0 ) {
				return get_post( (int) $tpl->wp_id );
			}
		}

		// Core stores post_name as the plain slug and scopes it to a theme via the wp_theme taxonomy.
		$existing = get_page_by_path( $slug, OBJECT, 'wp_template' );
		if ( $existing && isset( $existing->ID ) ) {
			return $existing;
		}

		$legacy_name = self::legacy_theme_post_name( $slug );
		if ( $legacy_name !== '' ) {
			$legacy = self::get_post_by_name_with_suffix( 'wp_template', $legacy_name );
			if ( $legacy && isset( $legacy->ID ) ) {
				return $legacy;
			}
		}

		return get_page_by_path( $slug, OBJECT, 'wp_template' );
	}

	private static function template_parts_dir( string $build_root ): string {
		return rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'template-parts';
	}

	private static function templates_dir( string $build_root ): string {
		return rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'templates';
	}

	public static function list_template_part_files( string $build_root ): array {
		$dir = self::template_parts_dir( $build_root );
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$files = glob( $dir . DIRECTORY_SEPARATOR . '*.html' ) ?: array();
		$files = is_array( $files ) ? $files : array();
		sort( $files );
		return $files;
	}

	public static function list_template_files( string $build_root ): array {
		$dir = self::templates_dir( $build_root );
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$files = glob( $dir . DIRECTORY_SEPARATOR . '*.html' ) ?: array();
		$files = is_array( $files ) ? $files : array();
		sort( $files );
		return $files;
	}

	private static function is_allowed_template_part_slug( string $slug ): bool {
		$slug = sanitize_key( $slug );
		if ( $slug === '' ) {
			return false;
		}
		return (bool) preg_match( '/^(header|footer)(-[a-z0-9-]+)?$/', $slug );
	}

	private static function title_from_slug( string $slug ): string {
		$slug = str_replace( array( '-', '_' ), ' ', $slug );
		$slug = preg_replace( '/\s+/', ' ', $slug );
		return ucwords( trim( (string) $slug ) );
	}

	private static function snapshot_meta( int $post_id ): array {
		$keys = array(
			Importer::META_PROJECT_SLUG,
			Importer::META_SOURCE_PATH,
			Importer::META_FINGERPRINT,
		);

		$out = array();
		foreach ( $keys as $key ) {
			if ( metadata_exists( 'post', $post_id, $key ) ) {
				$out[ $key ] = get_post_meta( $post_id, $key, true );
			} else {
				$out[ $key ] = null;
			}
		}

		return $out;
	}

	private static function normalize_local_path( string $path ): string {
		$path = trim( $path );
		if ( $path === '' ) {
			return '';
		}
		$path = (string) preg_replace( '/^\.\//', '', $path );
		$path = ltrim( $path, '/' );
		return $path;
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
				$clean = self::normalize_local_path( $clean );

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

	private static function inner_html( \DOMDocument $dom, \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
	}

	public static function auto_extract_template_parts_from_home( string $project_slug, string $fingerprint, string $build_root, array $slug_set, string $resources_base_url, bool $force_claim_templates ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$build_root = rtrim( $build_root, '/\\' );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors = 0;
		$error_messages = array();
		$created_parts = array();
		$updated_parts = array();

		if ( $project_slug === '' || $fingerprint === '' || $build_root === '' ) {
			return array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'created_parts' => array(), 'updated_parts' => array(), 'error_messages' => array() );
		}
		if ( ! self::block_templates_supported() ) {
			return array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'created_parts' => array(), 'updated_parts' => array(), 'error_messages' => array() );
		}

		$header_file = self::template_parts_dir( $build_root ) . DIRECTORY_SEPARATOR . 'header.html';
		$footer_file = self::template_parts_dir( $build_root ) . DIRECTORY_SEPARATOR . 'footer.html';
		$home_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home.html';

		if ( ! is_file( $home_path ) ) {
			return array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'created_parts' => array(), 'updated_parts' => array(), 'error_messages' => array() );
		}

		$raw = file_get_contents( $home_path );
		if ( $raw === false ) {
			return array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1, 'created_parts' => array(), 'updated_parts' => array(), 'error_messages' => array( 'Unable to read pages/home.html for template part extraction.' ) );
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->encoding = 'UTF-8';
		// Add UTF-8 encoding declaration to prevent character corruption
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $raw );
		libxml_clear_errors();
		if ( ! $loaded ) {
			return array( 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 1, 'created_parts' => array(), 'updated_parts' => array(), 'error_messages' => array( 'Unable to parse pages/home.html for template part extraction.' ) );
		}

		$xpath = new \DOMXPath( $dom );
		$header_node = $xpath->query( '//header' )->item( 0 );
		$footer_node = $xpath->query( '//footer' )->item( 0 );

		$targets = array(
			'header' => array(
				'node' => $header_node,
				'exists_in_bundle' => is_file( $header_file ),
			),
			'footer' => array(
				'node' => $footer_node,
				'exists_in_bundle' => is_file( $footer_file ),
			),
		);

		foreach ( $targets as $slug => $cfg ) {
			$node = $cfg['node'] ?? null;
			if ( ! ( $node instanceof \DOMNode ) ) {
				continue;
			}

			$part_html = self::inner_html( $dom, $node );
			$part_html = self::rewrite_urls( $part_html, $slug_set, $resources_base_url );
			$content_blocks = "<!-- wp:html -->\n" . $part_html . "\n<!-- /wp:html -->";
			$source_path = 'pages/home.html#' . $slug;

			$res = self::upsert_template_part( $project_slug, $fingerprint, $slug, $source_path, $content_blocks, $force_claim_templates );
			if ( empty( $res['ok'] ) ) {
				$errors++;
				$error_messages[] = isset( $res['error'] ) && is_string( $res['error'] ) ? $res['error'] : ( 'Template part upsert failed: ' . $slug );
				continue;
			}
			if ( ! empty( $res['skipped'] ) ) {
				$skipped++;
				continue;
			}
			if ( ! empty( $res['created'] ) ) {
				$created++;
				$created_parts[] = array( 'post_id' => (int) $res['post_id'], 'slug' => (string) $res['slug'], 'post_name' => (string) ( $res['post_name'] ?? '' ) );
			} else {
				$updated++;
				$updated_parts[] = array(
					'post_id' => (int) $res['post_id'],
					'slug' => (string) $res['slug'],
					'post_name' => (string) ( $res['post_name'] ?? '' ),
					'before' => is_array( $res['before'] ?? null ) ? $res['before'] : array(),
					'before_meta' => is_array( $res['before_meta'] ?? null ) ? $res['before_meta'] : array(),
				);
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors' => $errors,
			'created_parts' => $created_parts,
			'updated_parts' => $updated_parts,
			'error_messages' => $error_messages,
		);
	}

	public static function preflight_template_parts( string $project_slug, string $build_root ): array {
		$project_slug = sanitize_key( $project_slug );
		$items = array();
		$files = self::list_template_part_files( $build_root );

		foreach ( $files as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			$slug = sanitize_key( $slug );
			if ( ! self::is_allowed_template_part_slug( $slug ) ) {
				continue;
			}

			$existing = self::get_template_part_by_slug( $slug );
			$action = 'create';
			if ( $existing && isset( $existing->ID ) ) {
				$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
				$action = ( $owner === $project_slug ) ? 'update' : 'skip';
			}

			$items[] = array(
				'slug' => $slug,
				'file' => $path,
				'action' => $action,
			);
		}

		return array(
			'total' => count( $items ),
			'items' => $items,
		);
	}

	public static function preflight_auto_template_parts( string $project_slug, string $build_root ): array {
		$project_slug = sanitize_key( $project_slug );
		$items = array();

		// Auto-extracted header/footer from home.html
		$home_path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . 'home.html';
		if ( ! is_file( $home_path ) ) {
			return array( 'total' => 0, 'items' => array() );
		}

		$raw = file_get_contents( $home_path );
		if ( $raw === false ) {
			return array( 'total' => 0, 'items' => array() );
		}

		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->encoding = 'UTF-8';
		// Add UTF-8 encoding declaration to prevent character corruption
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $raw );
		libxml_clear_errors();
		if ( ! $loaded ) {
			return array( 'total' => 0, 'items' => array() );
		}

		$xpath = new \DOMXPath( $dom );
		$header_node = $xpath->query( '//header' )->item( 0 );
		$footer_node = $xpath->query( '//footer' )->item( 0 );

		$slugs = array( 'header', 'footer' );
		$nodes = array( 'header' => $header_node, 'footer' => $footer_node );

		foreach ( $slugs as $slug ) {
			$node = $nodes[ $slug ] ?? null;
			if ( ! ( $node instanceof \DOMNode ) ) {
				continue;
			}

			$existing = self::get_template_part_by_slug( $slug );
			$action = 'create';
			if ( $existing && isset( $existing->ID ) ) {
				$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
				$action = ( $owner === $project_slug ) ? 'update' : 'skip';
			}

			$items[] = array(
				'slug' => $slug,
				'file' => 'pages/home.html#' . $slug,
				'action' => $action,
			);
		}

		return array(
			'total' => count( $items ),
			'items' => $items,
		);
	}

	public static function preflight_templates( string $project_slug, string $build_root ): array {
		$project_slug = sanitize_key( $project_slug );
		$items = array();
		$files = self::list_template_files( $build_root );

		foreach ( $files as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			$slug = sanitize_key( $slug );
			if ( $slug === '' ) {
				continue;
			}

			$existing = self::get_template_by_slug( $slug );
			$action = 'create';
			if ( $existing && isset( $existing->ID ) ) {
				$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
				$action = ( $owner === $project_slug ) ? 'update' : 'skip';
			}

			$items[] = array(
				'slug' => $slug,
				'file' => $path,
				'action' => $action,
			);
		}

		return array(
			'total' => count( $items ),
			'items' => $items,
		);
	}

	private static function upsert_template_part( string $project_slug, string $fingerprint, string $slug, string $source_path, string $content_blocks, bool $force_claim_unowned ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$slug = sanitize_key( $slug );

		if ( $project_slug === '' || $fingerprint === '' || $slug === '' ) {
			return array( 'ok' => false, 'error' => 'Missing required inputs.' );
		}
		if ( ! post_type_exists( 'wp_template_part' ) ) {
			return array( 'ok' => false, 'error' => 'wp_template_part post type not registered (block templates unsupported).' );
		}

		$theme = self::current_theme_slug();
		$post_name = self::theme_post_name( $slug );
		$existing = self::get_template_part_by_slug( $slug );
		$postarr = array(
			'post_type' => 'wp_template_part',
			'post_status' => 'publish',
			'post_title' => self::title_from_slug( $slug ),
			'post_name' => $post_name,
			'post_content' => $content_blocks,
		);

		$created = false;
		$before = null;
		$before_meta = null;

		if ( $existing && isset( $existing->ID ) ) {
			$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
			if ( $owner !== $project_slug && ! $force_claim_unowned ) {
				return array( 'ok' => true, 'skipped' => true );
			}

			$before = array(
				'post_content' => (string) ( $existing->post_content ?? '' ),
				'post_title' => (string) ( $existing->post_title ?? '' ),
				'post_status' => (string) ( $existing->post_status ?? '' ),
			);
			$before_meta = self::snapshot_meta( (int) $existing->ID );

			$postarr['ID'] = (int) $existing->ID;
			$res = wp_update_post( $postarr, true );
			if ( is_wp_error( $res ) ) {
				return array( 'ok' => false, 'error' => $res->get_error_message() );
			}
			$post_id = (int) $existing->ID;
		} else {
			$res = wp_insert_post( $postarr, true );
			if ( is_wp_error( $res ) ) {
				return array( 'ok' => false, 'error' => $res->get_error_message() );
			}
			$post_id = (int) $res;
			$created = true;
		}

		$themes = array();
		if ( $theme !== '' ) {
			$themes[] = $theme;
		}
		$parent_theme = sanitize_key( (string) get_template() );
		if ( $parent_theme !== '' && $parent_theme !== $theme ) {
			$themes[] = $parent_theme;
		}
		if ( ! empty( $themes ) ) {
			wp_set_object_terms( $post_id, array_values( array_unique( $themes ) ), 'wp_theme', false );
		}
		$area = '';
		if ( strpos( $slug, 'header' ) === 0 ) {
			$area = 'header';
		} elseif ( strpos( $slug, 'footer' ) === 0 ) {
			$area = 'footer';
		}
		if ( $area !== '' ) {
			wp_set_object_terms( $post_id, $area, 'wp_template_part_area', false );
		}
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $project_slug );
		update_post_meta( $post_id, Importer::META_SOURCE_PATH, $source_path );
		update_post_meta( $post_id, Importer::META_FINGERPRINT, $fingerprint );

		return array(
			'ok' => true,
			'post_id' => $post_id,
			'slug' => $slug,
			'created' => $created,
			'post_name' => (string) ( get_post( $post_id )->post_name ?? '' ),
			'before' => $before,
			'before_meta' => $before_meta,
		);
	}

	private static function build_page_template_blocks( string $header_slug, string $footer_slug ): string {
		$header_slug = sanitize_key( $header_slug );
		$footer_slug = sanitize_key( $footer_slug );

		$out = '';
		if ( $header_slug !== '' ) {
			$attrs = array( 'slug' => $header_slug, 'tagName' => 'header' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n\n";
		}

		$out .= '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->' . "\n";
		$out .= '<main class="wp-block-group">' . "\n";
		$out .= '<!-- wp:post-content {"layout":{"type":"constrained"}} /-->' . "\n";
		$out .= '</main>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n\n";

		if ( $footer_slug !== '' ) {
			$attrs = array( 'slug' => $footer_slug, 'tagName' => 'footer' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
		}

		return $out;
	}

	private static function build_404_template_blocks( string $header_slug, string $footer_slug ): string {
		$header_slug = sanitize_key( $header_slug );
		$footer_slug = sanitize_key( $footer_slug );

		$out = '';
		if ( $header_slug !== '' ) {
			$attrs = array( 'slug' => $header_slug, 'tagName' => 'header' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n\n";
		}

		$out .= '<!-- wp:group {"tagName":"main","layout":{"type":"constrained"}} -->' . "\n";
		$out .= '<main class="wp-block-group">' . "\n";
		$out .= '<!-- wp:heading {"level":1} -->' . "\n";
		$out .= '<h1>404</h1>' . "\n";
		$out .= '<!-- /wp:heading -->' . "\n";
		$out .= '<!-- wp:paragraph -->' . "\n";
		$out .= '<p>Page not found.</p>' . "\n";
		$out .= '<!-- /wp:paragraph -->' . "\n";
		$out .= '</main>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n\n";

		if ( $footer_slug !== '' ) {
			$attrs = array( 'slug' => $footer_slug, 'tagName' => 'footer' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
		}

		return $out;
	}

	/**
	 * Ensure block templates exist for all registered public post types (including built-in 'post')
	 * Creates default single-{post_type}.html templates if missing
	 */
	public static function ensure_post_type_templates( string $project_slug, string $fingerprint ): array {
		$results = array(
			'created' => array(),
			'existing' => array(),
			'errors' => array(),
			'skipped' => array(),
		);

		if ( ! self::block_templates_supported() ) {
			\VibeCode\Deploy\Logger::warning( 'Block templates not supported, skipping CPT template creation.', array(), $project_slug );
			return $results;
		}

		// Get only custom post types (exclude built-in types like 'post', 'page', 'attachment')
		$public_cpts = get_post_types( array(
			'public' => true,
			'_builtin' => false,
		), 'names' );

		// Also include non-public CPTs that have show_ui enabled (they may still need templates)
		$non_public_cpts = get_post_types( array(
			'public' => false,
			'show_ui' => true,
			'_builtin' => false,
		), 'names' );

		$post_types = array_merge( $public_cpts, $non_public_cpts );
		$post_types = array_unique( $post_types );

		if ( empty( $post_types ) ) {
			\VibeCode\Deploy\Logger::info( 'No custom post types found, skipping CPT template creation.', array(), $project_slug );
			return $results;
		}

		$theme_dir = get_stylesheet_directory();
		$theme_slug = self::current_theme_slug();
		$header_slug = 'header';
		$footer_slug = 'footer';

		// Check if header/footer template parts exist
		$header_part = self::get_template_part_by_slug( $header_slug );
		$footer_part = self::get_template_part_by_slug( $footer_slug );

		if ( ! $header_part || ! isset( $header_part->ID ) || ! $footer_part || ! isset( $footer_part->ID ) ) {
			\VibeCode\Deploy\Logger::warning( 'Cannot create CPT templates: header/footer template parts missing.', array(
				'header_exists' => $header_part && isset( $header_part->ID ),
				'footer_exists' => $footer_part && isset( $footer_part->ID ),
			), $project_slug );
			return $results;
		}

		\VibeCode\Deploy\Logger::info( 'Ensuring CPT single templates exist.', array(
			'post_types' => $post_types,
			'header_part_id' => $header_part->ID,
			'footer_part_id' => $footer_part->ID,
			'theme' => $theme_slug,
		), $project_slug );

		foreach ( $post_types as $post_type ) {
			// Skip WordPress internal block types
			$internal_types = array( 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_block', 'wp_navigation' );
			if ( in_array( $post_type, $internal_types, true ) ) {
				$results['skipped'][] = array(
					'post_type' => $post_type,
					'reason' => 'WordPress internal type',
				);
				continue;
			}

			$slug = 'single-' . $post_type;
			$existing = self::get_template_by_slug( $slug );

			// Only create if template doesn't exist
			if ( $existing && isset( $existing->ID ) ) {
				$results['existing'][] = array(
					'post_type' => $post_type,
					'slug' => $slug,
					'template_id' => $existing->ID,
				);
				\VibeCode\Deploy\Logger::info( 'CPT single template already exists, skipping.', array(
					'post_type' => $post_type,
					'slug' => $slug,
					'template_id' => $existing->ID,
				), $project_slug );
				continue;
			}

			// Build default template content
			$content_blocks = self::build_single_template_blocks( $header_slug, $footer_slug, $post_type );

			// Create the template
			$res = self::upsert_template( $project_slug, $fingerprint, $slug, $content_blocks, false );

			if ( ! empty( $res['ok'] ) && ! empty( $res['created'] ) ) {
				$results['created'][] = array(
					'post_type' => $post_type,
					'slug' => $slug,
					'template_id' => $res['post_id'],
				);
				\VibeCode\Deploy\Logger::info( 'Created default post type single template.', array(
					'post_type' => $post_type,
					'slug' => $slug,
					'template_id' => $res['post_id'],
				), $project_slug );
			} elseif ( ! empty( $res['ok'] ) && empty( $res['created'] ) ) {
				$results['existing'][] = array(
					'post_type' => $post_type,
					'slug' => $slug,
					'template_id' => $res['post_id'] ?? 0,
				);
			} else {
				$results['errors'][] = array(
					'post_type' => $post_type,
					'slug' => $slug,
					'error' => $res['error'] ?? 'Unknown error',
				);
				\VibeCode\Deploy\Logger::error( 'Failed to create CPT single template.', array(
					'post_type' => $post_type,
					'slug' => $slug,
					'error' => $res['error'] ?? 'Unknown error',
				), $project_slug );
			}
		}

		// Verify all created templates are queryable
		foreach ( $results['created'] as $created ) {
			$verify = self::get_template_by_slug( $created['slug'] );
			if ( ! $verify || ! isset( $verify->ID ) || (int) $verify->ID !== (int) $created['template_id'] ) {
				\VibeCode\Deploy\Logger::warning( 'Template created but not immediately queryable.', array(
					'post_type' => $created['post_type'],
					'slug' => $created['slug'],
					'expected_id' => $created['template_id'],
					'found_id' => $verify && isset( $verify->ID ) ? $verify->ID : 0,
				), $project_slug );
			}
		}

		return $results;
	}

	/**
	 * Ensure block templates exist for default WordPress post type archives
	 * Creates home.html (blog posts index) and archive.html (category/tag/date archives) if missing
	 */
	public static function ensure_default_post_templates( string $project_slug, string $fingerprint ): void {
		if ( ! self::block_templates_supported() ) {
			return;
		}

		$header_slug = 'header';
		$footer_slug = 'footer';

		// Check if header/footer template parts exist
		$header_part = self::get_template_part_by_slug( $header_slug );
		$footer_part = self::get_template_part_by_slug( $footer_slug );

		if ( ! $header_part || ! isset( $header_part->ID ) || ! $footer_part || ! isset( $footer_part->ID ) ) {
			// Can't create templates without header/footer parts
			return;
		}

		// Create home.html (blog posts index)
		$home_slug = 'home';
		$existing_home = self::get_template_by_slug( $home_slug );
		if ( ! $existing_home || ! isset( $existing_home->ID ) ) {
			$home_blocks = self::build_home_template_blocks( $header_slug, $footer_slug );
			$res = self::upsert_template( $project_slug, $fingerprint, $home_slug, $home_blocks, false );
			if ( ! empty( $res['ok'] ) && ! empty( $res['created'] ) ) {
				\VibeCode\Deploy\Logger::info( 'Created default home template.', array( 'slug' => $home_slug ), $project_slug );
			}
		}

		// Create archive.html (category/tag/date archives)
		$archive_slug = 'archive';
		$existing_archive = self::get_template_by_slug( $archive_slug );
		if ( ! $existing_archive || ! isset( $existing_archive->ID ) ) {
			$archive_blocks = self::build_archive_template_blocks( $header_slug, $footer_slug );
			$res = self::upsert_template( $project_slug, $fingerprint, $archive_slug, $archive_blocks, false );
			if ( ! empty( $res['ok'] ) && ! empty( $res['created'] ) ) {
				\VibeCode\Deploy\Logger::info( 'Created default archive template.', array( 'slug' => $archive_slug ), $project_slug );
			}
		}
	}

	/**
	 * Build default block template content for single posts (all post types)
	 */
	private static function build_single_template_blocks( string $header_slug, string $footer_slug, string $post_type ): string {
		$header_slug = sanitize_key( $header_slug );
		$footer_slug = sanitize_key( $footer_slug );
		$post_type = sanitize_key( $post_type );

		// Get class prefix from settings
		$settings = \VibeCode\Deploy\Settings::get_all();
		$class_prefix = isset( $settings['class_prefix'] ) && is_string( $settings['class_prefix'] ) ? trim( (string) $settings['class_prefix'] ) : '';
		
		// Get theme slug for template part references
		$theme_slug = self::current_theme_slug();
		
		// Build class names with prefix
		$main_class = $class_prefix !== '' ? $class_prefix . 'main' : 'main';
		$hero_class = $class_prefix !== '' ? $class_prefix . 'hero' : 'hero';
		$hero_compact_class = $class_prefix !== '' ? $class_prefix . 'hero--compact' : 'hero--compact';
		$hero_gold_class = $class_prefix !== '' ? $class_prefix . 'hero--gold' : 'hero--gold';
		$hero_container_class = $class_prefix !== '' ? $class_prefix . 'hero__container' : 'hero__container';
		$hero_content_class = $class_prefix !== '' ? $class_prefix . 'hero__content' : 'hero__content';
		$hero_title_class = $class_prefix !== '' ? $class_prefix . 'hero__title' : 'hero__title';
		$page_section_class = $class_prefix !== '' ? $class_prefix . 'page-section' : 'page-section';
		$container_class = $class_prefix !== '' ? $class_prefix . 'container' : 'container';
		$page_card_class = $class_prefix !== '' ? $class_prefix . 'page-card' : 'page-card';
		$page_card_text_class = $class_prefix !== '' ? $class_prefix . 'page-card__text' : 'page-card__text';

		$out = '';
		if ( $header_slug !== '' ) {
			$attrs = array( 'slug' => $header_slug, 'tagName' => 'header' );
			if ( $theme_slug !== '' ) {
				$attrs['theme'] = $theme_slug;
			}
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n\n";
		}

		$out .= '<!-- wp:group {"tagName":"main","className":"' . esc_attr( $main_class ) . '"} -->' . "\n";
		$out .= '<main id="main" class="wp-block-group ' . esc_attr( $main_class ) . '" role="main">' . "\n";
		$out .= '<!-- wp:html -->' . "\n";
		$out .= '<section class="' . esc_attr( $hero_class . ' ' . $hero_compact_class . ' ' . $hero_gold_class ) . '">' . "\n";
		$out .= '<div class="' . esc_attr( $hero_container_class ) . '">' . "\n";
		$out .= '<div class="' . esc_attr( $hero_content_class ) . '">' . "\n";
		$out .= '<!-- wp:post-title {"level":1,"className":"' . esc_attr( $hero_title_class ) . '"} /-->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '</div>' . "\n";
		$out .= '</section>' . "\n";
		$out .= '<!-- /wp:html -->' . "\n\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_section_class ) . '"} -->' . "\n";
		$out .= '<section class="wp-block-group ' . esc_attr( $page_section_class ) . '">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $container_class ) . '"} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $container_class ) . '">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_card_class ) . '"} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $page_card_class ) . '">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_card_text_class ) . '"} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $page_card_text_class ) . '">' . "\n";
		$out .= '<!-- wp:post-content {"layout":{"type":"constrained"}} /-->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</section>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</main>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n\n";

		if ( $footer_slug !== '' ) {
			$attrs = array( 'slug' => $footer_slug, 'tagName' => 'footer' );
			if ( $theme_slug !== '' ) {
				$attrs['theme'] = $theme_slug;
			}
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
		}

		return $out;
	}

	/**
	 * Build default block template content for home.html (blog posts index)
	 */
	private static function build_home_template_blocks( string $header_slug, string $footer_slug ): string {
		$header_slug = sanitize_key( $header_slug );
		$footer_slug = sanitize_key( $footer_slug );

		// Get class prefix from settings
		$settings = \VibeCode\Deploy\Settings::get_all();
		$class_prefix = isset( $settings['class_prefix'] ) && is_string( $settings['class_prefix'] ) ? trim( (string) $settings['class_prefix'] ) : '';
		
		// Build class names with prefix
		$main_class = $class_prefix !== '' ? $class_prefix . 'main' : 'main';
		$page_section_class = $class_prefix !== '' ? $class_prefix . 'page-section' : 'page-section';
		$container_class = $class_prefix !== '' ? $class_prefix . 'container' : 'container';
		$page_card_class = $class_prefix !== '' ? $class_prefix . 'page-card' : 'page-card';
		$page_card_title_class = $class_prefix !== '' ? $class_prefix . 'page-card__title' : 'page-card__title';
		$page_card_meta_class = $class_prefix !== '' ? $class_prefix . 'page-card__meta' : 'page-card__meta';
		$page_card_text_class = $class_prefix !== '' ? $class_prefix . 'page-card__text' : 'page-card__text';

		$out = '';
		if ( $header_slug !== '' ) {
			$attrs = array( 'slug' => $header_slug, 'tagName' => 'header' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n\n";
		}

		$out .= '<!-- wp:group {"tagName":"main","className":"' . esc_attr( $main_class ) . '"} -->' . "\n";
		$out .= '<main id="main" class="wp-block-group ' . esc_attr( $main_class ) . '" role="main">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_section_class ) . '"} -->' . "\n";
		$out .= '<section class="wp-block-group ' . esc_attr( $page_section_class ) . '">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $container_class ) . '"} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $container_class ) . '">' . "\n";
		$out .= '<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->' . "\n";
		$out .= '<div class="wp-block-query">' . "\n";
		$out .= '<!-- wp:post-template -->' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_card_class ) . '","layout":{"type":"constrained"}} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $page_card_class ) . '">' . "\n";
		$out .= '<!-- wp:post-title {"level":2,"isLink":true,"className":"' . esc_attr( $page_card_title_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:post-date {"className":"' . esc_attr( $page_card_meta_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:post-excerpt {"className":"' . esc_attr( $page_card_text_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:post-terms {"term":"category","className":"' . esc_attr( $page_card_meta_class ) . '"} /-->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '<!-- /wp:post-template -->' . "\n";
		$out .= '<!-- wp:query-pagination -->' . "\n";
		$out .= '<div class="wp-block-query-pagination">' . "\n";
		$out .= '<!-- wp:query-pagination-previous /-->' . "\n";
		$out .= '<!-- wp:query-pagination-numbers /-->' . "\n";
		$out .= '<!-- wp:query-pagination-next /-->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:query-pagination -->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:query -->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</section>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</main>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n\n";

		if ( $footer_slug !== '' ) {
			$attrs = array( 'slug' => $footer_slug, 'tagName' => 'footer' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
		}

		return $out;
	}

	/**
	 * Build default block template content for archive.html (category/tag/date archives)
	 */
	private static function build_archive_template_blocks( string $header_slug, string $footer_slug ): string {
		$header_slug = sanitize_key( $header_slug );
		$footer_slug = sanitize_key( $footer_slug );

		// Get class prefix from settings
		$settings = \VibeCode\Deploy\Settings::get_all();
		$class_prefix = isset( $settings['class_prefix'] ) && is_string( $settings['class_prefix'] ) ? trim( (string) $settings['class_prefix'] ) : '';
		
		// Build class names with prefix
		$main_class = $class_prefix !== '' ? $class_prefix . 'main' : 'main';
		$page_section_class = $class_prefix !== '' ? $class_prefix . 'page-section' : 'page-section';
		$container_class = $class_prefix !== '' ? $class_prefix . 'container' : 'container';
		$page_card_class = $class_prefix !== '' ? $class_prefix . 'page-card' : 'page-card';
		$page_card_title_class = $class_prefix !== '' ? $class_prefix . 'page-card__title' : 'page-card__title';
		$page_card_meta_class = $class_prefix !== '' ? $class_prefix . 'page-card__meta' : 'page-card__meta';
		$page_card_text_class = $class_prefix !== '' ? $class_prefix . 'page-card__text' : 'page-card__text';

		$out = '';
		if ( $header_slug !== '' ) {
			$attrs = array( 'slug' => $header_slug, 'tagName' => 'header' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n\n";
		}

		$out .= '<!-- wp:group {"tagName":"main","className":"' . esc_attr( $main_class ) . '"} -->' . "\n";
		$out .= '<main id="main" class="wp-block-group ' . esc_attr( $main_class ) . '" role="main">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_section_class ) . '"} -->' . "\n";
		$out .= '<section class="wp-block-group ' . esc_attr( $page_section_class ) . '">' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $container_class ) . '"} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $container_class ) . '">' . "\n";
		$out .= '<!-- wp:query-title {"type":"archive","className":"' . esc_attr( $page_card_title_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:term-description /-->' . "\n";
		$out .= '<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true}} -->' . "\n";
		$out .= '<div class="wp-block-query">' . "\n";
		$out .= '<!-- wp:post-template -->' . "\n";
		$out .= '<!-- wp:group {"className":"' . esc_attr( $page_card_class ) . '","layout":{"type":"constrained"}} -->' . "\n";
		$out .= '<div class="wp-block-group ' . esc_attr( $page_card_class ) . '">' . "\n";
		$out .= '<!-- wp:post-title {"level":2,"isLink":true,"className":"' . esc_attr( $page_card_title_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:post-date {"className":"' . esc_attr( $page_card_meta_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:post-excerpt {"className":"' . esc_attr( $page_card_text_class ) . '"} /-->' . "\n";
		$out .= '<!-- wp:post-terms {"term":"category","className":"' . esc_attr( $page_card_meta_class ) . '"} /-->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '<!-- /wp:post-template -->' . "\n";
		$out .= '<!-- wp:query-pagination -->' . "\n";
		$out .= '<div class="wp-block-query-pagination">' . "\n";
		$out .= '<!-- wp:query-pagination-previous /-->' . "\n";
		$out .= '<!-- wp:query-pagination-numbers /-->' . "\n";
		$out .= '<!-- wp:query-pagination-next /-->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:query-pagination -->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:query -->' . "\n";
		$out .= '</div>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</section>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n";
		$out .= '</main>' . "\n";
		$out .= '<!-- /wp:group -->' . "\n\n";

		if ( $footer_slug !== '' ) {
			$attrs = array( 'slug' => $footer_slug, 'tagName' => 'footer' );
			$out .= '<!-- wp:template-part ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
		}

		return $out;
	}

	/**
	 * Validate template content for page templates.
	 * 
	 * Page templates should NOT include wp:post-content blocks because they define
	 * the full page structure. If wp:post-content is present, it would render
	 * the page's post_content, which we want to avoid for custom templates.
	 *
	 * @param string $slug Template slug (e.g., 'page-home').
	 * @param string $content Template content to validate.
	 * @return array Array with 'valid' (bool) and 'error' (string) keys.
	 */
	public static function validate_page_template( string $slug, string $content ): array {
		// Only validate page templates (templates that start with 'page-')
		if ( strpos( $slug, 'page-' ) !== 0 ) {
			return array( 'valid' => true, 'error' => '' );
		}
		
		// Check for wp:post-content blocks
		if ( preg_match( '/<!--\s*wp:post-content/i', $content ) ) {
			return array(
				'valid' => false,
				'error' => 'Page templates should not include wp:post-content blocks. Page templates define the full page structure, and wp:post-content would render the page editor content, which conflicts with the template design.',
			);
		}
		
		return array( 'valid' => true, 'error' => '' );
	}

	public static function upsert_template( string $project_slug, string $fingerprint, string $slug, string $content_blocks, bool $force_claim_unowned ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$slug = sanitize_key( $slug );

		if ( $project_slug === '' || $fingerprint === '' || $slug === '' ) {
			return array( 'ok' => false, 'error' => 'Missing required inputs.' );
		}
		if ( ! post_type_exists( 'wp_template' ) ) {
			return array( 'ok' => false, 'error' => 'wp_template post type not registered (block templates unsupported).' );
		}
		
		// Validate page templates don't include wp:post-content
		$validation = self::validate_page_template( $slug, $content_blocks );
		if ( ! $validation['valid'] ) {
			return array( 'ok' => false, 'error' => $validation['error'] );
		}

		$theme = self::current_theme_slug();
		$post_name = self::theme_post_name( $slug );
		$existing = self::get_template_by_slug( $slug );
		$postarr = array(
			'post_type' => 'wp_template',
			'post_status' => 'publish',
			'post_title' => self::title_from_slug( $slug ),
			'post_name' => $post_name,
			'post_content' => $content_blocks,
		);

		$created = false;
		$before = null;
		$before_meta = null;

		if ( $existing && isset( $existing->ID ) ) {
			$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
			if ( $owner !== $project_slug && ! $force_claim_unowned ) {
				return array( 'ok' => true, 'skipped' => true );
			}

			$before = array(
				'post_content' => (string) ( $existing->post_content ?? '' ),
				'post_title' => (string) ( $existing->post_title ?? '' ),
				'post_status' => (string) ( $existing->post_status ?? '' ),
			);
			$before_meta = self::snapshot_meta( (int) $existing->ID );

			$postarr['ID'] = (int) $existing->ID;
			$res = wp_update_post( $postarr, true );
			if ( is_wp_error( $res ) ) {
				return array( 'ok' => false, 'error' => $res->get_error_message() );
			}
			$post_id = (int) $existing->ID;
		} else {
			$res = wp_insert_post( $postarr, true );
			if ( is_wp_error( $res ) ) {
				return array( 'ok' => false, 'error' => $res->get_error_message() );
			}
			$post_id = (int) $res;
			$created = true;
		}

		if ( $theme !== '' ) {
			$term_set = wp_set_object_terms( $post_id, $theme, 'wp_theme', false );
			if ( is_wp_error( $term_set ) ) {
				\VibeCode\Deploy\Logger::error( 'Failed to set wp_theme taxonomy for template.', array(
					'template_slug' => $slug,
					'post_id' => $post_id,
					'theme' => $theme,
					'error' => $term_set->get_error_message(),
				), $project_slug );
			}
		}
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $project_slug );
		update_post_meta( $post_id, Importer::META_SOURCE_PATH, 'templates/' . $slug . '.html' );
		update_post_meta( $post_id, Importer::META_FINGERPRINT, $fingerprint );

		// Verify template is queryable after creation
		$verify_template = self::get_template_by_slug( $slug );
		if ( ! $verify_template || ! isset( $verify_template->ID ) || (int) $verify_template->ID !== $post_id ) {
			\VibeCode\Deploy\Logger::warning( 'Template created but not immediately queryable.', array(
				'template_slug' => $slug,
				'post_id' => $post_id,
				'theme' => $theme,
			), $project_slug );
		}

		// Log template creation/update
		if ( $created ) {
			\VibeCode\Deploy\Logger::info( 'Template created successfully.', array(
				'template_slug' => $slug,
				'post_id' => $post_id,
				'theme' => $theme,
				'post_name' => (string) ( get_post( $post_id )->post_name ?? '' ),
			), $project_slug );
		} else {
			\VibeCode\Deploy\Logger::info( 'Template updated successfully.', array(
				'template_slug' => $slug,
				'post_id' => $post_id,
				'theme' => $theme,
			), $project_slug );
		}

		return array(
			'ok' => true,
			'post_id' => $post_id,
			'slug' => $slug,
			'created' => $created,
			'post_name' => (string) ( get_post( $post_id )->post_name ?? '' ),
			'before' => $before,
			'before_meta' => $before_meta,
		);
	}

	public static function deploy_template_parts_and_404_template( string $project_slug, string $fingerprint, string $build_root, array $slug_set, string $resources_base_url, bool $deploy_template_parts, bool $generate_404_template, bool $force_claim_templates, array $selected_templates = array(), array $selected_template_parts = array() ): array {
		$created_parts = array();
		$updated_parts = array();
		$created_templates = array();
		$updated_templates = array();
		$part_diagnostics = array();
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors = 0;
		$error_messages = array();
		$theme = self::current_theme_slug();
		$parent_theme = sanitize_key( (string) get_template() );
		$supported = self::block_templates_supported();
		if ( ! $supported ) {
			return array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors' => 0,
				'created_parts' => array(),
				'updated_parts' => array(),
				'created_templates' => array(),
				'updated_templates' => array(),
				'debug' => array(
					'supported' => false,
					'theme' => $theme,
					'parent_theme' => $parent_theme,
					'post_type_wp_template' => post_type_exists( 'wp_template' ),
					'post_type_wp_template_part' => post_type_exists( 'wp_template_part' ),
				),
			);
		}

		// Normalize selected filters
		$selected_templates = array_map( 'sanitize_key', $selected_templates );
		$selected_templates = array_filter( $selected_templates );
		$selected_template_parts = array_map( 'sanitize_key', $selected_template_parts );
		$selected_template_parts = array_filter( $selected_template_parts );

		if ( $deploy_template_parts ) {
			$files = self::list_template_part_files( $build_root );
			foreach ( $files as $path ) {
				$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
				$slug = sanitize_key( $slug );
				if ( ! self::is_allowed_template_part_slug( $slug ) ) {
					continue;
				}

				// Filter by selected template parts if specified
				if ( ! empty( $selected_template_parts ) && ! in_array( $slug, $selected_template_parts, true ) ) {
					continue;
				}

				$existing = self::get_template_part_by_slug( $slug );
				$legacy_name = self::legacy_theme_post_name( $slug );
				$legacy = $legacy_name !== '' ? self::get_post_by_name_with_suffix( 'wp_template_part', $legacy_name ) : null;
				$part_diagnostics[] = array(
					'slug' => $slug,
					'pre_existing_id' => (int) ( $existing->ID ?? 0 ),
					'pre_existing_post_name' => (string) ( $existing->post_name ?? '' ),
					'legacy_name' => (string) $legacy_name,
					'legacy_id' => (int) ( $legacy->ID ?? 0 ),
					'theme' => $theme,
					'parent_theme' => $parent_theme,
				);

				$raw = file_get_contents( $path );
				if ( $raw === false ) {
					$errors++;
					continue;
				}

				$raw = self::rewrite_urls( $raw, $slug_set, $resources_base_url );
				$content_blocks = "<!-- wp:html -->\n" . $raw . "\n<!-- /wp:html -->";
				$source_path = 'template-parts/' . basename( $path );

				$res = self::upsert_template_part( $project_slug, $fingerprint, $slug, $source_path, $content_blocks, $force_claim_templates );
				if ( empty( $res['ok'] ) ) {
					$errors++;
					$error_messages[] = isset( $res['error'] ) && is_string( $res['error'] ) ? $res['error'] : 'Template part upsert failed.';
					continue;
				}

				if ( ! empty( $res['skipped'] ) ) {
					$skipped++;
					continue;
				}

				if ( ! empty( $res['created'] ) ) {
					$created++;
					$created_parts[] = array( 'post_id' => (int) $res['post_id'], 'slug' => (string) $res['slug'], 'post_name' => (string) ( $res['post_name'] ?? '' ) );
				} else {
					$updated++;
					$updated_parts[] = array(
						'post_id' => (int) $res['post_id'],
						'slug' => (string) $res['slug'],
						'post_name' => (string) ( $res['post_name'] ?? '' ),
						'before' => is_array( $res['before'] ?? null ) ? $res['before'] : array(),
						'before_meta' => is_array( $res['before_meta'] ?? null ) ? $res['before_meta'] : array(),
					);
				}
			}

			$tpl_files = self::list_template_files( $build_root );
			foreach ( $tpl_files as $path ) {
				$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
				$slug = sanitize_key( $slug );
				if ( $slug === '' ) {
					continue;
				}

				// Filter by selected templates if specified
				if ( ! empty( $selected_templates ) && ! in_array( $slug, $selected_templates, true ) ) {
					continue;
				}

				$raw = file_get_contents( $path );
				if ( $raw === false ) {
					$errors++;
					continue;
				}

				// Process images (upload to Media Library when enabled) before rewrite_urls
				$raw = Importer::process_images_in_html( $raw, $build_root );
				$content_blocks = self::rewrite_urls( $raw, $slug_set, $resources_base_url );
				$res = self::upsert_template( $project_slug, $fingerprint, $slug, $content_blocks, $force_claim_templates );
				if ( empty( $res['ok'] ) ) {
					$errors++;
					$error_messages[] = isset( $res['error'] ) && is_string( $res['error'] ) ? $res['error'] : 'Template upsert failed.';
					continue;
				}

				if ( ! empty( $res['skipped'] ) ) {
					$skipped++;
					continue;
				}

				if ( ! empty( $res['created'] ) ) {
					$created++;
					$created_templates[] = array( 'post_id' => (int) $res['post_id'], 'slug' => (string) $res['slug'], 'post_name' => (string) ( $res['post_name'] ?? '' ) );
				} else {
					$updated++;
					$updated_templates[] = array(
						'post_id' => (int) $res['post_id'],
						'slug' => (string) $res['slug'],
						'post_name' => (string) ( $res['post_name'] ?? '' ),
						'before' => is_array( $res['before'] ?? null ) ? $res['before'] : array(),
						'before_meta' => is_array( $res['before_meta'] ?? null ) ? $res['before_meta'] : array(),
					);
				}
			}
		}

		// Create/update page.html template to include header/footer template parts
		$header_slug = 'header';
		$footer_slug = 'footer';
		$header_part = self::get_template_part_by_slug( 'header' );
		$footer_part = self::get_template_part_by_slug( 'footer' );
		
		// Only create page template if header/footer template parts exist and are owned by this project
		if ( $header_part && isset( $header_part->ID ) && $footer_part && isset( $footer_part->ID ) ) {
			$header_owner = (string) get_post_meta( (int) $header_part->ID, Importer::META_PROJECT_SLUG, true );
			$footer_owner = (string) get_post_meta( (int) $footer_part->ID, Importer::META_PROJECT_SLUG, true );
			
			if ( $header_owner === $project_slug && $footer_owner === $project_slug ) {
				$content = self::build_page_template_blocks( $header_slug, $footer_slug );
				$res = self::upsert_template( $project_slug, $fingerprint, 'page', $content, $force_claim_templates );
				if ( empty( $res['ok'] ) ) {
					$errors++;
					$error_messages[] = isset( $res['error'] ) && is_string( $res['error'] ) ? $res['error'] : 'Page template upsert failed.';
				} elseif ( ! empty( $res['skipped'] ) ) {
					$skipped++;
				} elseif ( ! empty( $res['created'] ) ) {
					$created++;
					$created_templates[] = array( 'post_id' => (int) $res['post_id'], 'slug' => (string) $res['slug'], 'post_name' => (string) ( $res['post_name'] ?? '' ) );
				} else {
					$updated++;
					$updated_templates[] = array(
						'post_id' => (int) $res['post_id'],
						'slug' => (string) $res['slug'],
						'post_name' => (string) ( $res['post_name'] ?? '' ),
						'before' => is_array( $res['before'] ?? null ) ? $res['before'] : array(),
						'before_meta' => is_array( $res['before_meta'] ?? null ) ? $res['before_meta'] : array(),
					);
				}
			}
		}

		if ( $generate_404_template ) {
			$header_slug = 'header';
			$footer_slug = 'footer';

			$header_404 = self::get_template_part_by_slug( 'header-404' );
			if ( $header_404 && isset( $header_404->ID ) ) {
				$owner = (string) get_post_meta( (int) $header_404->ID, Importer::META_PROJECT_SLUG, true );
				if ( $owner === $project_slug ) {
					$header_slug = 'header-404';
				}
			}
			$footer_404 = self::get_template_part_by_slug( 'footer-404' );
			if ( $footer_404 && isset( $footer_404->ID ) ) {
				$owner = (string) get_post_meta( (int) $footer_404->ID, Importer::META_PROJECT_SLUG, true );
				if ( $owner === $project_slug ) {
					$footer_slug = 'footer-404';
				}
			}

			$content = self::build_404_template_blocks( $header_slug, $footer_slug );
			$res = self::upsert_template( $project_slug, $fingerprint, '404', $content, $force_claim_templates );
			if ( empty( $res['ok'] ) ) {
				$errors++;
				$error_messages[] = isset( $res['error'] ) && is_string( $res['error'] ) ? $res['error'] : 'Template upsert failed.';
			} elseif ( ! empty( $res['skipped'] ) ) {
				$skipped++;
			} elseif ( ! empty( $res['created'] ) ) {
				$created++;
				$created_templates[] = array( 'post_id' => (int) $res['post_id'], 'slug' => (string) $res['slug'], 'post_name' => (string) ( $res['post_name'] ?? '' ) );
			} else {
				$updated++;
				$updated_templates[] = array(
					'post_id' => (int) $res['post_id'],
					'slug' => (string) $res['slug'],
					'post_name' => (string) ( $res['post_name'] ?? '' ),
					'before' => is_array( $res['before'] ?? null ) ? $res['before'] : array(),
					'before_meta' => is_array( $res['before_meta'] ?? null ) ? $res['before_meta'] : array(),
				);
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors' => $errors,
			'created_parts' => $created_parts,
			'updated_parts' => $updated_parts,
			'created_templates' => $created_templates,
			'updated_templates' => $updated_templates,
			'error_messages' => $error_messages,
			'debug' => array(
				'supported' => true,
				'theme' => $theme,
				'parent_theme' => $parent_theme,
				'post_type_wp_template' => post_type_exists( 'wp_template' ),
				'post_type_wp_template_part' => post_type_exists( 'wp_template_part' ),
				'get_block_template_available' => function_exists( 'get_block_template' ),
				'parts' => $part_diagnostics,
			),
		);
	}
}
