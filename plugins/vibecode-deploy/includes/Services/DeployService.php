<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Settings;

defined( 'ABSPATH' ) || exit;

final class DeployService {
	/**
	 * Snapshot post meta for a page before deployment.
	 * 
	 * Captures current meta values (project slug, source path, fingerprint, assets)
	 * to enable rollback functionality.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array Associative array of meta keys and values.
	 */
	private static function snapshot_post_meta( int $post_id ): array {
		$keys = array(
			Importer::META_PROJECT_SLUG,
			Importer::META_SOURCE_PATH,
			Importer::META_FINGERPRINT,
			Importer::META_ASSET_CSS,
			Importer::META_ASSET_JS,
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

	/**
	 * Normalize a local file path by removing leading dots and slashes.
	 * 
	 * Converts paths like './css/styles.css' or '/css/styles.css' to 'css/styles.css'.
	 *
	 * @param string $path Raw path string.
	 * @return string Normalized path.
	 */
	private static function normalize_local_path( string $path ): string {
		$path = trim( $path );
		if ( $path === '' ) {
			return '';
		}
		$path = (string) preg_replace( '/^\.\//', '', $path );
		$path = ltrim( $path, '/' );
		return $path;
	}

	/**
	 * Collect all resource paths (images, documents) referenced in HTML.
	 * 
	 * Scans DOM for src and href attributes, filters out external URLs,
	 * and returns only local resource paths (those starting with 'resources/').
	 *
	 * @param \DOMDocument $dom Parsed HTML document.
	 * @return array Array of unique resource paths.
	 */
	private static function collect_resource_paths( \DOMDocument $dom ): array {
		$resources = array();
		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//*[@src or @href]' );
		if ( ! $nodes ) {
			return array();
		}

		foreach ( $nodes as $node ) {
			if ( ! ( $node instanceof \DOMElement ) ) {
				continue;
			}

			$attrs = array( 'src', 'href' );
			foreach ( $attrs as $attr ) {
				if ( ! $node->hasAttribute( $attr ) ) {
					continue;
				}

				$val = (string) $node->getAttribute( $attr );
				$val = self::normalize_local_path( $val );
				if ( $val === '' ) {
					continue;
				}

				$lower = strtolower( $val );
				$schemes = array( 'http://', 'https://', 'mailto:', 'tel:', 'data:', 'javascript:' );
				foreach ( $schemes as $scheme ) {
					if ( strpos( $lower, $scheme ) === 0 ) {
						continue 2;
					}
				}

				if ( strpos( $val, 'resources/' ) === 0 ) {
					$resources[] = $val;
				}
			}
		}

		return array_values( array_unique( $resources ) );
	}

	private static function file_exists_in_build( string $build_root, string $relative ): bool {
		$relative = self::normalize_local_path( $relative );
		if ( $relative === '' ) {
			return false;
		}
		$path = rtrim( $build_root, '/\\' ) . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $relative );
		return is_file( $path );
	}

	/**
	 * Extract inner HTML content from a DOM node.
	 *
	 * @param \DOMDocument $dom Document containing the node.
	 * @param \DOMNode $node Node to extract inner HTML from.
	 * @return string Inner HTML content.
	 */
	private static function inner_html( \DOMDocument $dom, \DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
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

	/**
	 * Run preflight validation before deployment.
	 *
	 * Validates environment, checks pages, validates shortcode placeholders,
	 * and returns a report of what will be deployed.
	 *
	 * @param string $project_slug Project identifier.
	 * @param string $build_root   Path to build root directory.
	 * @return array Preflight results with 'pages_total', 'items', 'warnings', 'errors', etc.
	 */
	public static function preflight( string $project_slug, string $build_root ): array {
		$project_slug = sanitize_key( $project_slug );
		$pages = Importer::list_page_files( $build_root );
		$settings = Settings::get_all();
		$placeholder_config = ShortcodePlaceholderService::load_config( $build_root );
		
		// Check for critical environment errors first
		$env_errors = \VibeCode\Deploy\Services\EnvService::get_critical_errors();
		if ( ! empty( $env_errors ) ) {
			$settings = \VibeCode\Deploy\Settings::get_all();
			$env_errors_mode = isset( $settings['env_errors_mode'] ) && $settings['env_errors_mode'] === 'fail' ? 'fail' : 'warn';
			$total_warnings = count( $env_errors );
			$items = array();
			$slug_set = array();
			foreach ( $pages as $path ) {
				$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
				if ( $slug !== '' ) {
					$slug_set[ $slug ] = true;
					$items[] = array(
						'slug' => $slug,
						'path' => $path,
						'action' => 'create',
						'title' => self::title_from_slug( $slug ),
						'warnings' => $env_errors,
					);
				}
			}
			
			$errors = array();
			if ( $env_errors_mode === 'fail' ) {
				$errors = $env_errors;
			}
			
			return array(
				'pages_total' => count( $pages ),
				'items' => $items,
				'slug_set' => $slug_set,
				'total_warnings' => $total_warnings,
				'templates' => array(),
				'template_parts' => array(),
				'auto_template_parts' => array(),
				'errors' => $errors,
			);
		}
		$slug_set = array();
		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug !== '' ) {
				$slug_set[ $slug ] = true;
			}
		}

		$items = array();
		$total_warnings = 0;
		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug === '' ) {
				continue;
			}

			$warnings = array();
			$raw = file_get_contents( $path );
			if ( $raw === false ) {
				$warnings[] = 'Unable to read HTML file.';
			} else {
				libxml_use_internal_errors( true );
				$dom = new \DOMDocument();
				$loaded = $dom->loadHTML( $raw );
				libxml_clear_errors();
				if ( ! $loaded ) {
					$warnings[] = 'Unable to parse HTML.';
				} else {
					$xpath = new \DOMXPath( $dom );
					$main = $xpath->query( '//main' )->item( 0 );
					if ( ! $main ) {
						$warnings[] = 'Missing <main> element (required for import).';
					}

					if ( $main && is_array( $placeholder_config ) && ! empty( $placeholder_config ) && empty( $placeholder_config['_error'] ) ) {
						$placeholder_scan = ShortcodePlaceholderService::extract_placeholders_from_main( $dom, $main );
						$found = isset( $placeholder_scan['found'] ) && is_array( $placeholder_scan['found'] ) ? $placeholder_scan['found'] : array();
						$invalid = isset( $placeholder_scan['invalid'] ) && is_array( $placeholder_scan['invalid'] ) ? $placeholder_scan['invalid'] : array();

						$validation = ShortcodePlaceholderService::validate_page_slug( $slug, $found, $placeholder_config, $settings );
						$validation_warnings = isset( $validation['warnings'] ) && is_array( $validation['warnings'] ) ? $validation['warnings'] : array();
						$validation_errors = isset( $validation['errors'] ) && is_array( $validation['errors'] ) ? $validation['errors'] : array();
						foreach ( $validation_warnings as $m ) {
							if ( is_string( $m ) && $m !== '' ) {
								$warnings[] = $m;
							}
						}
						foreach ( $validation_errors as $m ) {
							if ( is_string( $m ) && $m !== '' ) {
								$warnings[] = 'ERROR: ' . $m;
							}
						}

						if ( ! empty( $invalid ) ) {
							$invalid_mode = ShortcodePlaceholderService::get_mode( $placeholder_config, $settings, 'on_unknown_placeholder', 'warn' );
							$prefix = ShortcodePlaceholderService::get_placeholder_prefix();
							foreach ( $invalid as $bad ) {
								if ( ! is_string( $bad ) || $bad === '' ) {
									continue;
								}
								$msg = 'Invalid ' . $prefix . ' placeholder for page "' . $slug . '": ' . $bad;
								$warnings[] = ( $invalid_mode === 'fail' ) ? ( 'ERROR: ' . $msg ) : $msg;
							}
						}
					}

					$assets = AssetService::extract_head_assets( $dom );
					$css = isset( $assets['css'] ) && is_array( $assets['css'] ) ? $assets['css'] : array();
					$js = isset( $assets['js'] ) && is_array( $assets['js'] ) ? $assets['js'] : array();

					foreach ( $css as $href ) {
						if ( ! is_string( $href ) ) {
							continue;
						}
						if ( ! self::file_exists_in_build( $build_root, $href ) ) {
							$warnings[] = 'Missing CSS asset: ' . $href;
						}
					}

					foreach ( $js as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$src = isset( $it['src'] ) && is_string( $it['src'] ) ? (string) $it['src'] : '';
						if ( $src === '' ) {
							continue;
						}
						if ( ! self::file_exists_in_build( $build_root, $src ) ) {
							$warnings[] = 'Missing JS asset: ' . $src;
						}
					}

					$resources = self::collect_resource_paths( $dom );
					foreach ( $resources as $res ) {
						$rel = substr( $res, strlen( 'resources/' ) );
						$rel = 'resources/' . ltrim( (string) $rel, '/' );
						if ( ! self::file_exists_in_build( $build_root, $rel ) ) {
							$warnings[] = 'Missing resource asset: ' . $rel;
						}
					}
				}
			}

			$existing = get_page_by_path( $slug );
			$action = 'create';
			if ( $existing && isset( $existing->ID ) ) {
				$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
				$action = ( $owner === $project_slug ) ? 'update' : 'skip';
			}

			$items[] = array(
				'slug' => $slug,
				'file' => $path,
				'action' => $action,
				'warnings_count' => count( $warnings ),
				'warnings' => $warnings,
			);

			$total_warnings += count( $warnings );
		}

        $templates = TemplateService::preflight_templates( $project_slug, $build_root );
		$template_parts = TemplateService::preflight_template_parts( $project_slug, $build_root );
		$auto_parts = TemplateService::preflight_auto_template_parts( $project_slug, $build_root );
		
		// Aggregate warnings from templates/parts
		foreach ( $templates['items'] as $tpl ) {
			if ( isset( $tpl['action'] ) && $tpl['action'] === 'skip' ) {
				$total_warnings++;
			}
		}
		foreach ( $template_parts['items'] as $part ) {
			if ( isset( $part['action'] ) && $part['action'] === 'skip' ) {
				$total_warnings++;
			}
		}
		foreach ( $auto_parts['items'] as $part ) {
			if ( isset( $part['action'] ) && $part['action'] === 'skip' ) {
				$total_warnings++;
			}
		}
		
		if ( isset( $placeholder_config['_error'] ) && is_string( $placeholder_config['_error'] ) && $placeholder_config['_error'] !== '' ) {
			$total_warnings++;
			$items[] = array(
				'slug' => '(build)',
				'file' => ShortcodePlaceholderService::CONFIG_FILENAME,
				'action' => 'check',
				'warnings_count' => 1,
				'warnings' => array( $placeholder_config['_error'] ),
			);
		}

		return array(
			'pages_total' => count( $items ),
			'items' => $items,
			'slug_set' => $slug_set,
			'total_warnings' => $total_warnings,
			'templates' => $templates,
			'template_parts' => $template_parts,
			'auto_template_parts' => $auto_parts,
		);
	}

	/**
	 * Run the deployment import process.
	 *
	 * Creates/updates pages, extracts templates, copies assets, and generates manifests.
	 *
	 * @param string $project_slug           Project identifier.
	 * @param string $fingerprint            Build fingerprint.
	 * @param string $build_root             Path to build root directory.
	 * @param bool   $set_front_page        Whether to set home.html as front page.
	 * @param bool   $force_claim_unowned   Whether to claim pages not owned by this project.
	 * @param bool   $deploy_template_parts Whether to extract header/footer from home.html.
	 * @param bool   $generate_404_template  Whether to generate 404 template.
	 * @param bool   $force_claim_templates Whether to claim templates not owned by this project.
	 * @param bool   $validate_cpt_shortcodes Whether to validate CPT shortcode coverage.
	 * @return array Deployment results with 'pages_created', 'pages_updated', 'templates_created', etc.
	 */
	public static function run_import( string $project_slug, string $fingerprint, string $build_root, bool $set_front_page, bool $force_claim_unowned, bool $deploy_template_parts = true, bool $generate_404_template = true, bool $force_claim_templates = false, bool $validate_cpt_shortcodes = false ): array {
		AssetService::copy_assets_to_plugin_folder( $build_root );
		$active_before = BuildService::get_active_fingerprint( $project_slug );
		$settings = Settings::get_all();
		$placeholder_config = ShortcodePlaceholderService::load_config( $build_root );
		$front_before = array(
			'show_on_front' => get_option( 'show_on_front' ),
			'page_on_front' => get_option( 'page_on_front' ),
		);
		$created_pages = array();
		$updated_pages = array();
		$created_template_parts = array();
		$updated_template_parts = array();
		$created_templates = array();
		$updated_templates = array();

		$pages = Importer::list_page_files( $build_root );
		$slug_set = array();
		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug !== '' ) {
				$slug_set[ $slug ] = true;
			}
		}

		$uploads = wp_upload_dir();
		$resources_base_url = rtrim( (string) $uploads['baseurl'], '/\\' ) . '/vibecode-deploy/staging/' . rawurlencode( $project_slug ) . '/' . rawurlencode( $fingerprint ) . '/resources';

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors = 0;
		$home_id = null;

		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug === '' ) {
				continue;
			}

			$raw = file_get_contents( $path );
			if ( $raw === false ) {
				$errors++;
				continue;
			}

			libxml_use_internal_errors( true );
			$dom = new \DOMDocument();
			$loaded = $dom->loadHTML( $raw );
			libxml_clear_errors();
			if ( ! $loaded ) {
				$errors++;
				continue;
			}

			$xpath = new \DOMXPath( $dom );
			$main = $xpath->query( '//main' )->item( 0 );
			if ( ! $main ) {
				$errors++;
				continue;
			}

			if ( is_array( $placeholder_config ) && ! empty( $placeholder_config ) && empty( $placeholder_config['_error'] ) ) {
				$placeholder_scan = ShortcodePlaceholderService::extract_placeholders_from_main( $dom, $main );
				$found = isset( $placeholder_scan['found'] ) && is_array( $placeholder_scan['found'] ) ? $placeholder_scan['found'] : array();
				$invalid = isset( $placeholder_scan['invalid'] ) && is_array( $placeholder_scan['invalid'] ) ? $placeholder_scan['invalid'] : array();
				\VibeCode\Deploy\Logger::info(
					'Placeholder scan complete.',
					array(
						'slug' => $slug,
						'found_count' => count( $found ),
						'found' => array_values( $found ),
						'invalid_count' => count( $invalid ),
					),
					$project_slug
				);
				$validation = ShortcodePlaceholderService::validate_page_slug( $slug, $found, $placeholder_config, $settings );
				$page_errors = isset( $validation['errors'] ) && is_array( $validation['errors'] ) ? $validation['errors'] : array();
				$page_warnings = isset( $validation['warnings'] ) && is_array( $validation['warnings'] ) ? $validation['warnings'] : array();

				if ( ! empty( $page_warnings ) ) {
					\VibeCode\Deploy\Logger::info( 'Placeholder validation warnings.', array( 'slug' => $slug, 'warnings' => $page_warnings ), $project_slug );
				}

				if ( ! empty( $invalid ) ) {
					$invalid_mode = ShortcodePlaceholderService::get_mode( $placeholder_config, $settings, 'on_unknown_placeholder', 'warn' );
					$prefix = ShortcodePlaceholderService::get_placeholder_prefix();
					foreach ( $invalid as $bad ) {
						if ( ! is_string( $bad ) || $bad === '' ) {
							continue;
						}
						$msg = 'Invalid ' . $prefix . ' placeholder for page "' . $slug . '": ' . $bad;
						if ( $invalid_mode === 'fail' ) {
							$page_errors[] = $msg;
						} else {
							\VibeCode\Deploy\Logger::info( 'Placeholder validation warning.', array( 'slug' => $slug, 'warning' => $msg ), $project_slug );
						}
					}
				}

				if ( ! empty( $page_errors ) ) {
					$errors += count( $page_errors );
					\VibeCode\Deploy\Logger::error( 'Placeholder validation failed for page.', array( 'slug' => $slug, 'errors' => $page_errors ), $project_slug );
					continue;
				}
			}

			$assets = AssetService::extract_head_assets( $dom );

			$content = self::inner_html( $dom, $main );
			$content = self::rewrite_urls( $content, $slug_set, $resources_base_url );
			$content = AssetService::rewrite_asset_urls( $content, $project_slug );
			$raw_content = $content;

			$content = HtmlToEtchConverter::convert( $raw_content );

			$title = self::title_from_dom( $dom, self::title_from_slug( $slug ) );

			$existing = get_page_by_path( $slug );
			$postarr = array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_name' => $slug,
				'post_content' => $content,
			);

			if ( $existing && isset( $existing->ID ) ) {
				$before = array(
					'post_content' => (string) ( $existing->post_content ?? '' ),
					'post_title' => (string) ( $existing->post_title ?? '' ),
					'post_status' => (string) ( $existing->post_status ?? '' ),
				);
				$before_meta = self::snapshot_post_meta( (int) $existing->ID );

				$owner = (string) get_post_meta( (int) $existing->ID, Importer::META_PROJECT_SLUG, true );
				if ( $owner !== $project_slug && ! $force_claim_unowned ) {
					$skipped++;
					continue;
				}

				$postarr['ID'] = (int) $existing->ID;
				$res = wp_update_post( $postarr, true );
				if ( is_wp_error( $res ) ) {
					$errors++;
					continue;
				}

				$updated++;
				$post_id = (int) $existing->ID;
				$updated_pages[] = array(
					'post_id' => $post_id,
					'slug' => $slug,
					'before' => $before,
					'before_meta' => $before_meta,
				);
			} else {
				$res = wp_insert_post( $postarr, true );
				if ( is_wp_error( $res ) ) {
					$errors++;
					continue;
				}

				$created++;
				$post_id = (int) $res;
				$created_pages[] = array(
					'post_id' => $post_id,
					'slug' => $slug,
				);
			}

			update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $project_slug );
			update_post_meta( $post_id, Importer::META_SOURCE_PATH, 'pages/' . basename( $path ) );
			update_post_meta( $post_id, Importer::META_FINGERPRINT, $fingerprint );
			update_post_meta( $post_id, Importer::META_ASSET_CSS, $assets['css'] ?? array() );
			update_post_meta( $post_id, Importer::META_ASSET_JS, $assets['js'] ?? array() );

			if ( $slug === 'home' ) {
				$home_id = $post_id;
			}
		}

		if ( $set_front_page && $home_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $home_id );
		}

		$auto_parts_result = TemplateService::auto_extract_template_parts_from_home(
			$project_slug,
			$fingerprint,
			$build_root,
			$slug_set,
			$resources_base_url,
			(bool) $force_claim_templates
		);
		if ( is_array( $auto_parts_result ) ) {
			$created += (int) ( $auto_parts_result['created'] ?? 0 );
			$updated += (int) ( $auto_parts_result['updated'] ?? 0 );
			$skipped += (int) ( $auto_parts_result['skipped'] ?? 0 );
			$errors += (int) ( $auto_parts_result['errors'] ?? 0 );
			$created_template_parts = array_merge( $created_template_parts, ( is_array( $auto_parts_result['created_parts'] ?? null ) ? $auto_parts_result['created_parts'] : array() ) );
			$updated_template_parts = array_merge( $updated_template_parts, ( is_array( $auto_parts_result['updated_parts'] ?? null ) ? $auto_parts_result['updated_parts'] : array() ) );
		}

		$template_result = TemplateService::deploy_template_parts_and_404_template(
			$project_slug,
			$fingerprint,
			$build_root,
			$slug_set,
			$resources_base_url,
			(bool) $deploy_template_parts,
			(bool) $generate_404_template,
			(bool) $force_claim_templates
		);
		if ( is_array( $template_result ) ) {
			$created += (int) ( $template_result['created'] ?? 0 );
			$updated += (int) ( $template_result['updated'] ?? 0 );
			$skipped += (int) ( $template_result['skipped'] ?? 0 );
			$errors += (int) ( $template_result['errors'] ?? 0 );
			$created_template_parts = isset( $template_result['created_parts'] ) && is_array( $template_result['created_parts'] ) ? $template_result['created_parts'] : array();
			$updated_template_parts = isset( $template_result['updated_parts'] ) && is_array( $template_result['updated_parts'] ) ? $template_result['updated_parts'] : array();
			$created_templates = isset( $template_result['created_templates'] ) && is_array( $template_result['created_templates'] ) ? $template_result['created_templates'] : array();
			$updated_templates = isset( $template_result['updated_templates'] ) && is_array( $template_result['updated_templates'] ) ? $template_result['updated_templates'] : array();
		}

		// Deploy theme files (functions.php, ACF JSON) from staging
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$theme_slug = $theme && method_exists( $theme, 'get_stylesheet' ) ? (string) $theme->get_stylesheet() : '';
		if ( $theme_slug !== '' ) {
			$theme_deploy = ThemeDeployService::deploy_theme_files( $build_root, $theme_slug );
			if ( ! empty( $theme_deploy['errors'] ) ) {
				foreach ( $theme_deploy['errors'] as $error ) {
					\VibeCode\Deploy\Logger::error( 'Theme deployment failed.', array( 'error' => $error ), $project_slug );
				}
			}
			if ( ! empty( $theme_deploy['created'] ) || ! empty( $theme_deploy['updated'] ) ) {
				\VibeCode\Deploy\Logger::info( 'Theme files deployed.', array(
					'created' => $theme_deploy['created'],
					'updated' => $theme_deploy['updated'],
				), $project_slug );
			}
		}

		$cpt_validation = array();
		if ( $validate_cpt_shortcodes && is_array( $placeholder_config ) && ! empty( $placeholder_config ) && empty( $placeholder_config['_error'] ) ) {
			$cpt_validation = ShortcodePlaceholderService::validate_post_types( $placeholder_config, $settings );
			$cpt_warnings = isset( $cpt_validation['warnings'] ) && is_array( $cpt_validation['warnings'] ) ? $cpt_validation['warnings'] : array();
			$cpt_errors = isset( $cpt_validation['errors'] ) && is_array( $cpt_validation['errors'] ) ? $cpt_validation['errors'] : array();
			if ( ! empty( $cpt_warnings ) ) {
				\VibeCode\Deploy\Logger::info( 'CPT shortcode validation warnings.', array( 'warnings' => $cpt_warnings ), $project_slug );
			}
			if ( ! empty( $cpt_errors ) ) {
				$errors += count( $cpt_errors );
				\VibeCode\Deploy\Logger::error( 'CPT shortcode validation failed.', array( 'errors' => $cpt_errors ), $project_slug );
			}
		}

		if ( $errors === 0 ) {
			$manifest = array(
				'version' => 1,
				'project_slug' => sanitize_key( $project_slug ),
				'fingerprint' => sanitize_text_field( $fingerprint ),
				'timestamp' => time(),
				'user_id' => (int) get_current_user_id(),
				'active_before' => $active_before,
				'active_after' => sanitize_text_field( $fingerprint ),
				'front_before' => $front_before,
				'front_after' => array(
					'show_on_front' => get_option( 'show_on_front' ),
					'page_on_front' => get_option( 'page_on_front' ),
				),
				'created_pages' => $created_pages,
				'updated_pages' => $updated_pages,
				'created_template_parts' => $created_template_parts,
				'updated_template_parts' => $updated_template_parts,
				'created_templates' => $created_templates,
				'updated_templates' => $updated_templates,
				'result' => array(
					'created' => $created,
					'updated' => $updated,
					'skipped' => $skipped,
					'errors' => $errors,
				),
				'build_stats' => BuildService::get_build_stats( $project_slug, $fingerprint ),
				'options' => array(
					'deploy_template_parts' => (bool) $deploy_template_parts,
					'generate_404_template' => (bool) $generate_404_template,
					'force_claim_templates' => (bool) $force_claim_templates,
					'validate_cpt_shortcodes' => (bool) $validate_cpt_shortcodes,
					'placeholder_config' => ShortcodePlaceholderService::CONFIG_FILENAME,
				),
				'cpt_shortcode_validation' => is_array( $cpt_validation ) ? $cpt_validation : array(),
			);

			ManifestService::write_manifest( $project_slug, $fingerprint, $manifest );
			ManifestService::set_last_deploy_fingerprint( $project_slug, $fingerprint );
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors' => $errors,
			'template_result' => is_array( $template_result ) ? $template_result : array(),
		);
	}
}
