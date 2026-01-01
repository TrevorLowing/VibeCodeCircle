<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

final class TemplateService {
	private static function block_templates_supported(): bool {
		return post_type_exists( 'wp_template' ) && post_type_exists( 'wp_template_part' );
	}

	private static function current_theme_slug(): string {
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

	private static function get_template_part_by_slug( string $slug ) {
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

	private static function get_template_by_slug( string $slug ) {
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
		$loaded = $dom->loadHTML( $raw );
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
		$loaded = $dom->loadHTML( $raw );
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

	private static function upsert_template( string $project_slug, string $fingerprint, string $slug, string $content_blocks, bool $force_claim_unowned ): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		$slug = sanitize_key( $slug );

		if ( $project_slug === '' || $fingerprint === '' || $slug === '' ) {
			return array( 'ok' => false, 'error' => 'Missing required inputs.' );
		}
		if ( ! post_type_exists( 'wp_template' ) ) {
			return array( 'ok' => false, 'error' => 'wp_template post type not registered (block templates unsupported).' );
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
			wp_set_object_terms( $post_id, $theme, 'wp_theme', false );
		}
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $project_slug );
		update_post_meta( $post_id, Importer::META_SOURCE_PATH, 'templates/' . $slug . '.html' );
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

	public static function deploy_template_parts_and_404_template( string $project_slug, string $fingerprint, string $build_root, array $slug_set, string $resources_base_url, bool $deploy_template_parts, bool $generate_404_template, bool $force_claim_templates ): array {
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

		if ( $deploy_template_parts ) {
			$files = self::list_template_part_files( $build_root );
			foreach ( $files as $path ) {
				$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
				$slug = sanitize_key( $slug );
				if ( ! self::is_allowed_template_part_slug( $slug ) ) {
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

				$raw = file_get_contents( $path );
				if ( $raw === false ) {
					$errors++;
					continue;
				}

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
