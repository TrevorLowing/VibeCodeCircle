<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Logger;
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

				// Skip resources/ paths - they should already be converted to plugin URLs by AssetService::rewrite_asset_urls()
				// Check if URL is already a plugin asset URL (already converted)
				$plugin_url_base = plugins_url( 'assets', VIBECODE_DEPLOY_PLUGIN_FILE );
				if ( strpos( $url, $plugin_url_base . '/resources/' ) !== false ) {
					// Already converted to plugin URL, skip
					return $m[0];
				}

				if ( strpos( $clean, 'resources/' ) === 0 ) {
					// Resources not yet converted - this shouldn't happen if rewrite_asset_urls() ran first
					// But handle it anyway for safety
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
	 * Check for missing CPT single templates and return warnings.
	 *
	 * @return array Array of warning messages for missing templates.
	 */
	private static function check_missing_cpt_templates(): array {
		$warnings = array();
		
		if ( ! TemplateService::block_templates_supported() ) {
			return $warnings;
		}

		// Get only custom post types (exclude built-in types like 'post', 'page', 'attachment')
		$post_types = get_post_types( array(
			'public' => true,
			'_builtin' => false,
		), 'names' );

		if ( empty( $post_types ) ) {
			return $warnings;
		}

		foreach ( $post_types as $post_type ) {
			// Skip WordPress internal block types
			$internal_types = array( 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_block', 'wp_navigation' );
			if ( in_array( $post_type, $internal_types, true ) ) {
				continue;
			}

			$slug = 'single-' . $post_type;
			$existing = TemplateService::get_template_by_slug( $slug );

			if ( ! $existing || ! isset( $existing->ID ) ) {
				$warnings[] = sprintf(
					'Missing template for CPT "%s" (single-%s.html). The plugin will auto-create this during deployment, but you may need to flush rewrite rules after deployment.',
					$post_type,
					$post_type
				);
			}
		}

		return $warnings;
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

		// Check for missing CPT single templates
		$cpt_warnings = self::check_missing_cpt_templates();
		$total_warnings = count( $cpt_warnings );

		$items = array();
		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug === '' ) {
				continue;
			}

			$warnings = array();
			// Add CPT template warnings to first page item
			if ( ! empty( $cpt_warnings ) && empty( $items ) ) {
				$warnings = array_merge( $warnings, $cpt_warnings );
			}
			$raw = file_get_contents( $path );
			if ( $raw === false ) {
				$warnings[] = 'Unable to read HTML file.';
			} else {
				libxml_use_internal_errors( true );
				$dom = new \DOMDocument();
				$dom->encoding = 'UTF-8';
				// Add UTF-8 encoding declaration to prevent character corruption
				$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $raw );
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

					$assets = AssetService::extract_head_assets( $dom, $project_slug );
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

		// Collect CSS/JS file lists from staging
		$css_files = array();
		$js_files = array();
		$css_dir = $build_root . '/css';
		$js_dir = $build_root . '/js';
		if ( is_dir( $css_dir ) ) {
			$css_glob = glob( $css_dir . '/*.css' ) ?: array();
			foreach ( $css_glob as $css_file ) {
				$css_files[] = 'css/' . basename( $css_file );
			}
		}
		if ( is_dir( $js_dir ) ) {
			$js_glob = glob( $js_dir . '/*.js' ) ?: array();
			foreach ( $js_glob as $js_file ) {
				$js_files[] = 'js/' . basename( $js_file );
			}
		}

		// Collect theme file lists from staging
		$theme_files = array();
		$staging_theme_dir = $build_root . '/theme';
		if ( is_dir( $staging_theme_dir ) ) {
			if ( file_exists( $staging_theme_dir . '/functions.php' ) ) {
				$theme_files[] = 'functions.php';
			}
			$acf_dir = $staging_theme_dir . '/acf-json';
			if ( is_dir( $acf_dir ) ) {
				$acf_glob = glob( $acf_dir . '/*.json' ) ?: array();
				foreach ( $acf_glob as $acf_file ) {
					$theme_files[] = 'acf-json/' . basename( $acf_file );
				}
			}
		}

		return array(
			'pages_total' => count( $items ),
			'items' => $items,
			'slug_set' => $slug_set,
			'total_warnings' => $total_warnings,
			'templates' => $templates,
			'template_parts' => $template_parts,
			'auto_template_parts' => $auto_parts,
			'css_files' => $css_files, // CSS file list for selection UI
			'js_files' => $js_files, // JS file list for selection UI
			'theme_files' => $theme_files, // Theme file list for selection UI
		);
	}

	/**
	 * Create page templates from pages/*.html files.
	 *
	 * Automatically creates page-{slug}.html templates from pages directory,
	 * ensuring templates always match source HTML files and include hero sections.
	 *
	 * @param string $project_slug         Project identifier.
	 * @param string $fingerprint          Build fingerprint.
	 * @param array  $pages                Array of page file paths.
	 * @param array  $slug_set             Set of valid page slugs.
	 * @param string $resources_base_url   Base URL for resources.
	 * @param bool   $force_claim_templates Whether to force claim unowned templates.
	 * @return array Results with 'created', 'updated', 'skipped', 'errors', 'created_templates', 'updated_templates'.
	 */
	private static function create_page_templates_from_pages(
		string $project_slug,
		string $fingerprint,
		array $pages,
		array $slug_set,
		string $resources_base_url,
		bool $force_claim_templates
	): array {
		$project_slug = sanitize_key( $project_slug );
		$fingerprint = sanitize_text_field( $fingerprint );
		
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors = 0;
		$created_templates = array();
		$updated_templates = array();

		if ( ! TemplateService::block_templates_supported() ) {
			return array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors' => 0,
				'created_templates' => array(),
				'updated_templates' => array(),
			);
		}

		// Get header/footer template parts
		$header_part = TemplateService::get_template_part_by_slug( 'header' );
		$footer_part = TemplateService::get_template_part_by_slug( 'footer' );
		
		// Only create templates if header/footer exist
		if ( ! $header_part || ! isset( $header_part->ID ) || ! $footer_part || ! isset( $footer_part->ID ) ) {
			Logger::info( 'Skipping page template creation: header/footer template parts not found.', array(), $project_slug );
			return array(
				'created' => 0,
				'updated' => 0,
				'skipped' => count( $pages ),
				'errors' => 0,
				'created_templates' => array(),
				'updated_templates' => array(),
			);
		}

		// Get class prefix from settings for main class
		$settings = Settings::get_all();
		$class_prefix = isset( $settings['class_prefix'] ) && is_string( $settings['class_prefix'] ) ? trim( (string) $settings['class_prefix'] ) : '';
		$main_class = $class_prefix !== '' ? $class_prefix . 'main' : 'main';

		// Get build root from first page path (assumes all pages are in same pages/ directory)
		$build_root = '';
		if ( ! empty( $pages ) ) {
			$first_page = reset( $pages );
			$build_root = dirname( dirname( $first_page ) ); // Go up from pages/{file}.html to build root
		}

		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug === '' ) {
				continue;
			}

			// Skip if template already exists in templates/ directory (manual templates take precedence)
			if ( $build_root !== '' ) {
				$templates_dir = $build_root . DIRECTORY_SEPARATOR . 'templates';
				$template_file = $templates_dir . DIRECTORY_SEPARATOR . 'page-' . $slug . '.html';
				if ( is_file( $template_file ) ) {
					// Manual template exists, skip auto-generation
					Logger::info( 'Skipping auto-template creation: manual template exists.', array(
						'page_slug' => $slug,
						'template_file' => $template_file,
					), $project_slug );
					$skipped++;
					continue;
				}
			}

			$raw = file_get_contents( $path );
			if ( $raw === false ) {
				$errors++;
				Logger::error( 'Failed to read page file for template creation.', array(
					'page_slug' => $slug,
					'path' => $path,
				), $project_slug );
				continue;
			}

			libxml_use_internal_errors( true );
			$dom = new \DOMDocument();
			$dom->encoding = 'UTF-8';
			// Add UTF-8 encoding declaration to prevent character corruption
			$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $raw );
			libxml_clear_errors();
			if ( ! $loaded ) {
				$errors++;
				Logger::error( 'Failed to parse page file for template creation.', array(
					'page_slug' => $slug,
					'path' => $path,
				), $project_slug );
				continue;
			}

			$xpath = new \DOMXPath( $dom );
			$main = $xpath->query( '//main' )->item( 0 );
			if ( ! $main ) {
				$errors++;
				Logger::error( 'Page file missing <main> element for template creation.', array(
					'page_slug' => $slug,
					'path' => $path,
				), $project_slug );
				continue;
			}

			// Extract main content (same as page content extraction)
			$content = self::inner_html( $dom, $main );
			// Rewrite asset URLs FIRST (resources/ -> plugin URL) before rewrite_urls processes them
			$content = AssetService::rewrite_asset_urls( $content, $project_slug );
			// Then rewrite page URLs (skip resources already converted to plugin URLs)
			$content = self::rewrite_urls( $content, $slug_set, $resources_base_url );
			$raw_content = $content;

			// Convert to block markup
			$content_blocks = HtmlToEtchConverter::convert( $raw_content );
			
			// Verify converted content doesn't include wp:post-content blocks
			// (should not happen, but validate to be safe)
			if ( preg_match( '/<!--\s*wp:post-content/i', $content_blocks ) ) {
				$errors++;
				Logger::error( 'Converted page content includes wp:post-content block, which should not be in page templates.', array(
					'page_slug' => $slug,
					'template_slug' => 'page-' . $slug,
				), $project_slug );
				continue;
			}

			// Wrap with header/footer template parts
			$template_slug = 'page-' . $slug;
			$template_content = '';
			
			// Header template part
			$header_attrs = array( 'slug' => 'header', 'tagName' => 'header' );
			$theme_slug = TemplateService::current_theme_slug();
			if ( $theme_slug !== '' ) {
				$header_attrs['theme'] = $theme_slug;
			}
			$template_content .= '<!-- wp:template-part ' . wp_json_encode( $header_attrs ) . ' /-->' . "\n\n";
			
			// Main content wrapper
			$template_content .= '<!-- wp:group {"tagName":"main","className":"' . esc_attr( $main_class ) . '"} -->' . "\n";
			$template_content .= '<main id="main" class="wp-block-group ' . esc_attr( $main_class ) . '" role="main">' . "\n";
			$template_content .= $content_blocks;
			$template_content .= '</main>' . "\n";
			$template_content .= '<!-- /wp:group -->' . "\n\n";
			
			// Footer template part
			$footer_attrs = array( 'slug' => 'footer', 'tagName' => 'footer' );
			if ( $theme_slug !== '' ) {
				$footer_attrs['theme'] = $theme_slug;
			}
			$template_content .= '<!-- wp:template-part ' . wp_json_encode( $footer_attrs ) . ' /-->' . "\n";
			
			// Final validation: ensure template doesn't include wp:post-content
			$validation = TemplateService::validate_page_template( $template_slug, $template_content );
			if ( ! $validation['valid'] ) {
				$errors++;
				Logger::error( 'Template validation failed.', array(
					'page_slug' => $slug,
					'template_slug' => $template_slug,
					'error' => $validation['error'],
				), $project_slug );
				continue;
			}

			// Create/update template using TemplateService
			$res = TemplateService::upsert_template( $project_slug, $fingerprint, $template_slug, $template_content, $force_claim_templates );
			
			if ( empty( $res['ok'] ) ) {
				$errors++;
				Logger::error( 'Failed to create page template.', array(
					'page_slug' => $slug,
					'template_slug' => $template_slug,
					'error' => isset( $res['error'] ) ? $res['error'] : 'Unknown error',
				), $project_slug );
				continue;
			}

			// Update source path to reflect that template comes from pages/ not templates/
			if ( ! empty( $res['post_id'] ) ) {
				update_post_meta( (int) $res['post_id'], Importer::META_SOURCE_PATH, 'pages/' . $slug . '.html' );
			}

			if ( ! empty( $res['skipped'] ) ) {
				$skipped++;
				continue;
			}

			if ( ! empty( $res['created'] ) ) {
				$created++;
				$created_templates[] = array(
					'post_id' => (int) $res['post_id'],
					'slug' => (string) $res['slug'],
					'post_name' => (string) ( $res['post_name'] ?? '' ),
				);
				Logger::info( 'Created page template from page file.', array(
					'page_slug' => $slug,
					'template_slug' => $template_slug,
					'post_id' => (int) $res['post_id'],
				), $project_slug );
			} else {
				$updated++;
				$updated_templates[] = array(
					'post_id' => (int) $res['post_id'],
					'slug' => (string) $res['slug'],
					'post_name' => (string) ( $res['post_name'] ?? '' ),
					'before' => is_array( $res['before'] ?? null ) ? $res['before'] : array(),
					'before_meta' => is_array( $res['before_meta'] ?? null ) ? $res['before_meta'] : array(),
				);
				Logger::info( 'Updated page template from page file.', array(
					'page_slug' => $slug,
					'template_slug' => $template_slug,
					'post_id' => (int) $res['post_id'],
				), $project_slug );
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors' => $errors,
			'created_templates' => $created_templates,
			'updated_templates' => $updated_templates,
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
	 * @param array  $selected_pages        Optional array of page slugs to deploy (empty = all).
	 * @param array  $selected_css          Optional array of CSS file paths to deploy (empty = all).
	 * @param array  $selected_js           Optional array of JS file paths to deploy (empty = all).
	 * @param array  $selected_templates    Optional array of template slugs to deploy (empty = all).
	 * @param array  $selected_template_parts Optional array of template part slugs to deploy (empty = all).
	 * @param array  $selected_theme_files  Optional array of theme file names to deploy (empty = all).
	 * @return array Deployment results with 'pages_created', 'pages_updated', 'templates_created', etc.
	 */
	public static function run_import( string $project_slug, string $fingerprint, string $build_root, bool $set_front_page, bool $force_claim_unowned, bool $deploy_template_parts = true, bool $generate_404_template = true, bool $force_claim_templates = false, bool $validate_cpt_shortcodes = false, array $selected_pages = array(), array $selected_css = array(), array $selected_js = array(), array $selected_templates = array(), array $selected_template_parts = array(), array $selected_theme_files = array() ): array {
		// Optional: Flush caches before import for fresh deployment
		// This ensures no stale data interferes with new deployment
		if ( $force_claim_templates ) {
			CleanupService::flush_all_caches( $project_slug );
			Logger::info( 'Flushed all caches before import for fresh deployment.', array(
				'project_slug' => $project_slug,
				'fingerprint' => $fingerprint,
				'force_claim_templates' => $force_claim_templates,
			), $project_slug );
		}
		
		// Copy assets (filtering will be handled by AssetService if needed, or we can filter here)
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

		// Normalize selected filters
		$selected_pages = array_map( 'sanitize_key', $selected_pages );
		$selected_pages = array_filter( $selected_pages );
		$selected_css = array_map( 'sanitize_file_name', $selected_css );
		$selected_css = array_filter( $selected_css );
		$selected_js = array_map( 'sanitize_file_name', $selected_js );
		$selected_js = array_filter( $selected_js );
		$selected_templates = array_map( 'sanitize_key', $selected_templates );
		$selected_templates = array_filter( $selected_templates );
		$selected_template_parts = array_map( 'sanitize_key', $selected_template_parts );
		$selected_template_parts = array_filter( $selected_template_parts );
		$selected_theme_files = array_map( 'sanitize_file_name', $selected_theme_files );
		$selected_theme_files = array_filter( $selected_theme_files );

		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug === '' ) {
				continue;
			}

			// Filter by selected pages if specified
			if ( ! empty( $selected_pages ) && ! in_array( $slug, $selected_pages, true ) ) {
				continue;
			}

			$raw = file_get_contents( $path );
			if ( $raw === false ) {
				$errors++;
				continue;
			}

			libxml_use_internal_errors( true );
			$dom = new \DOMDocument();
			$dom->encoding = 'UTF-8';
			// Add UTF-8 encoding declaration to prevent character corruption
			$loaded = $dom->loadHTML( '<?xml encoding="UTF-8">' . $raw );
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

			// Extract body class from source HTML
			$body_class = '';
			$body = $xpath->query( '//body' )->item( 0 );
			if ( $body instanceof \DOMElement ) {
				$body_class_attr = $body->getAttribute( 'class' );
				if ( is_string( $body_class_attr ) && $body_class_attr !== '' ) {
					$body_class = sanitize_text_field( trim( $body_class_attr ) );
				}
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

			$assets = AssetService::extract_head_assets( $dom, $project_slug );

			$content = self::inner_html( $dom, $main );
			// CRITICAL: URL rewriting order matters!
			// 1. Rewrite asset URLs FIRST (css/, js/, resources/ -> plugin URL)
			//    This ensures resources/ paths are converted before page URL rewriting
			// 2. Then rewrite page URLs (extensionless links -> WordPress permalinks)
			//    rewrite_urls() skips already-converted plugin asset URLs
			// 
			// @see VibeCodeCircle/plugins/vibecode-deploy/docs/STRUCTURAL_RULES.md#url-rewriting-rules
			$content = AssetService::rewrite_asset_urls( $content, $project_slug );
			// Then rewrite page URLs (skip resources already converted to plugin URLs)
			$content = self::rewrite_urls( $content, $slug_set, $resources_base_url );
			$raw_content = $content;

			$content = HtmlToEtchConverter::convert( $raw_content );

			$title = self::title_from_dom( $dom, self::title_from_slug( $slug ) );

			// Check if custom block template exists - if so, clear post_content so template is used
			// Check both: 1) Already registered in WordPress, 2) File exists in theme directory
			$template_slug = 'page-' . $slug;
			$block_template = TemplateService::get_template_by_slug( $template_slug );
			$has_registered_template = $block_template && isset( $block_template->ID );
			
			// Also check if template file exists in theme directory (for templates not yet registered)
			$theme_dir = get_stylesheet_directory();
			$template_file = $theme_dir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template_slug . '.html';
			$has_template_file = is_file( $template_file );
			
			$has_custom_template = $has_registered_template || $has_template_file;

			// Detect if EtchWP plugin is active
			// EtchWP templates use wp:post-content block which requires post_content to be populated
			$is_etchwp = defined( 'ETCH_PLUGIN_FILE' );

			// If custom template exists, clear post_content so WordPress uses the template instead
			// EXCEPTION: EtchWP templates use wp:post-content block which requires post_content
			$final_content = $content;
			if ( $has_custom_template && ! $is_etchwp ) {
				$final_content = '';
				Logger::info( 'Cleared page content for custom template.', array( 
					'page_slug' => $slug, 
					'template_slug' => $template_slug,
					'registered' => $has_registered_template,
					'file_exists' => $has_template_file,
					'template_file' => $template_file
				), $project_slug );
			} elseif ( $has_custom_template && $is_etchwp ) {
				Logger::info( 'Keeping page content for EtchWP template (uses wp:post-content block).', array( 
					'page_slug' => $slug, 
					'template_slug' => $template_slug,
					'registered' => $has_registered_template,
					'file_exists' => $has_template_file,
					'template_file' => $template_file
				), $project_slug );
			}

			$existing = get_page_by_path( $slug );
			$postarr = array(
				'post_type' => 'page',
				'post_status' => 'publish',
				'post_title' => $title,
				'post_name' => $slug,
				'post_content' => $final_content,
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
			$css_assets = $assets['css'] ?? array();
			$js_assets = $assets['js'] ?? array();
			$font_assets = $assets['fonts'] ?? array();
			
			// Store CSS, JS, and Font assets in post meta for later enqueuing
			update_post_meta( $post_id, Importer::META_ASSET_CSS, $css_assets );
			update_post_meta( $post_id, Importer::META_ASSET_JS, $js_assets );
			update_post_meta( $post_id, Importer::META_ASSET_FONTS, $font_assets );
			
			// Store body class in post meta for body_class filter
			if ( $body_class !== '' ) {
				update_post_meta( $post_id, Importer::META_BODY_CLASS, $body_class );
				Logger::info( 'Stored body class in post meta.', array(
					'page_slug' => $slug,
					'post_id' => $post_id,
					'body_class' => $body_class,
				), $project_slug );
			} else {
				// Remove body class meta if not present in source
				delete_post_meta( $post_id, Importer::META_BODY_CLASS );
			}
			
			// Log asset storage for debugging
			if ( ! empty( $css_assets ) ) {
				Logger::info( 'Stored CSS assets in post meta.', array(
					'page_slug' => $slug,
					'post_id' => $post_id,
					'css_files' => $css_assets,
					'count' => count( $css_assets ),
				), $project_slug );
			}

			// Log template usage and verification
			if ( $has_custom_template ) {
				// Block templates are automatically used by WordPress template hierarchy
				// Standard themes: WordPress uses page-{slug}.html template when post_content is empty
				// EtchWP: Templates use wp:post-content block which requires post_content to be populated
				Logger::info( 'Page configured to use block template.', array( 
					'page_slug' => $slug, 
					'template_slug' => $template_slug,
					'has_registered' => $has_registered_template,
					'has_file' => $has_template_file,
					'content_cleared' => ( $final_content === '' ),
					'is_etchwp' => $is_etchwp,
					'template_file' => $template_file,
					'template_verified' => true,
				), $project_slug );
				
				// Verify template is actually registered/available
				if ( $has_registered_template ) {
					$verify_template = \VibeCode\Deploy\Services\TemplateService::get_template_by_slug( $template_slug );
					if ( ! $verify_template || ! isset( $verify_template->ID ) ) {
						Logger::warning( 'Template registered but not immediately queryable. May need cache flush.', array(
							'page_slug' => $slug,
							'template_slug' => $template_slug,
						), $project_slug );
					}
				}
			} else {
				// No custom template - page will use default page.html template with post_content
				Logger::info( 'Page will use default template with editor content.', array( 
					'page_slug' => $slug,
					'has_content' => ( $final_content !== '' ),
					'content_length' => strlen( $final_content ),
				), $project_slug );
			}

			if ( $slug === 'home' ) {
				$home_id = $post_id;
			}
		}

		// Auto-create block templates for all post types if they don't exist
		$cpt_template_results = TemplateService::ensure_post_type_templates( $project_slug, $fingerprint );
		
		// Verify CPT templates were created successfully
		if ( ! empty( $cpt_template_results['created'] ) ) {
			Logger::info( 'CPT single templates created successfully.', array(
				'created_count' => count( $cpt_template_results['created'] ),
				'created_templates' => $cpt_template_results['created'],
			), $project_slug );
		}
		if ( ! empty( $cpt_template_results['errors'] ) ) {
			Logger::warning( 'Some CPT templates failed to create.', array(
				'error_count' => count( $cpt_template_results['errors'] ),
				'errors' => $cpt_template_results['errors'],
			), $project_slug );
		}
		if ( ! empty( $cpt_template_results['existing'] ) ) {
			Logger::info( 'Some CPT templates already existed.', array(
				'existing_count' => count( $cpt_template_results['existing'] ),
				'existing_templates' => $cpt_template_results['existing'],
			), $project_slug );
		}
		
		// Auto-create default post type archive templates (home.html, archive.html)
		TemplateService::ensure_default_post_templates( $project_slug, $fingerprint );
		
		// Flush rewrite rules after CPT templates are created (needed for single post URLs to work)
		flush_rewrite_rules( false ); // Soft flush (faster)
		Logger::info( 'Flushed rewrite rules after template creation.', array(), $project_slug );

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

		// Auto-create page templates from pages/*.html files
		// This ensures templates always match source HTML files and include hero sections
		$page_templates_result = self::create_page_templates_from_pages(
			$project_slug,
			$fingerprint,
			$pages,
			$slug_set,
			$resources_base_url,
			(bool) $force_claim_templates
		);
		if ( is_array( $page_templates_result ) ) {
			$created += (int) ( $page_templates_result['created'] ?? 0 );
			$updated += (int) ( $page_templates_result['updated'] ?? 0 );
			$skipped += (int) ( $page_templates_result['skipped'] ?? 0 );
			$errors += (int) ( $page_templates_result['errors'] ?? 0 );
			if ( isset( $page_templates_result['created_templates'] ) && is_array( $page_templates_result['created_templates'] ) ) {
				$created_templates = array_merge( $created_templates, $page_templates_result['created_templates'] );
			}
			if ( isset( $page_templates_result['updated_templates'] ) && is_array( $page_templates_result['updated_templates'] ) ) {
				$updated_templates = array_merge( $updated_templates, $page_templates_result['updated_templates'] );
			}
		}

		$template_result = TemplateService::deploy_template_parts_and_404_template(
			$project_slug,
			$fingerprint,
			$build_root,
			$slug_set,
			$resources_base_url,
			(bool) $deploy_template_parts,
			(bool) $generate_404_template,
			(bool) $force_claim_templates,
			$selected_templates,
			$selected_template_parts
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
		
		// After templates are deployed, clear page content for pages that have custom templates
		// This ensures WordPress uses the block templates instead of page content
		// EXCEPTION: EtchWP templates use wp:post-content block which requires post_content to be populated
		$theme_dir = get_stylesheet_directory();
		$pages_to_clear = array();
		$pages_verified = array();
		foreach ( $pages as $path ) {
			$slug = (string) preg_replace( '/\.html$/', '', basename( $path ) );
			if ( $slug === '' ) {
				continue;
			}
			
			$template_slug = 'page-' . $slug;
			$template_file = $theme_dir . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . $template_slug . '.html';
			
			// Check if template exists in WordPress (registered) OR as file
			$block_template = TemplateService::get_template_by_slug( $template_slug );
			$has_registered_template = $block_template && isset( $block_template->ID );
			$template_exists = is_file( $template_file );
			
			// Check if template was just created/updated in this deployment
			$template_was_deployed = false;
			if ( is_array( $created_templates ) ) {
				foreach ( $created_templates as $created_template ) {
					if ( isset( $created_template['slug'] ) && $created_template['slug'] === $template_slug ) {
						$template_was_deployed = true;
						break;
					}
				}
			}
			if ( is_array( $updated_templates ) ) {
				foreach ( $updated_templates as $updated_template ) {
					if ( isset( $updated_template['slug'] ) && $updated_template['slug'] === $template_slug ) {
						$template_was_deployed = true;
						break;
					}
				}
			}
			
			$has_custom_template = $has_registered_template || $template_exists || $template_was_deployed;
			
			if ( $has_custom_template ) {
				$page = get_page_by_path( $slug );
				if ( $page && isset( $page->ID ) ) {
					$current_content = (string) ( $page->post_content ?? '' );
					if ( $current_content !== '' ) {
						$pages_to_clear[] = array(
							'post_id' => (int) $page->ID,
							'slug' => $slug,
							'template_slug' => $template_slug,
							'has_registered' => $has_registered_template,
							'has_file' => $template_exists,
							'was_deployed' => $template_was_deployed,
						);
					} else {
						// Content already empty - verify this is correct
						$pages_verified[] = array(
							'post_id' => (int) $page->ID,
							'slug' => $slug,
							'template_slug' => $template_slug,
						);
					}
				}
			}
		}
		
		// Detect if EtchWP plugin is active
		// EtchWP templates use wp:post-content block which requires post_content to be populated
		$is_etchwp = defined( 'ETCH_PLUGIN_FILE' );

		// Clear content for pages with custom templates
		// EXCEPTION: EtchWP templates use wp:post-content block which requires post_content
		if ( ! empty( $pages_to_clear ) && ! $is_etchwp ) {
			Logger::info( 'Clearing page content for pages with custom templates.', array(
				'count' => count( $pages_to_clear ),
				'pages' => array_map( function( $p ) {
					return $p['slug'];
				}, $pages_to_clear ),
			), $project_slug );
			
			foreach ( $pages_to_clear as $page_info ) {
				$update_result = wp_update_post( array(
					'ID' => $page_info['post_id'],
					'post_content' => '',
				), true );
				
				if ( ! is_wp_error( $update_result ) ) {
					Logger::info( 'Cleared page content after template deployment.', array(
						'page_slug' => $page_info['slug'],
						'template_slug' => $page_info['template_slug'],
						'post_id' => $page_info['post_id'],
						'has_registered' => $page_info['has_registered'],
						'has_file' => $page_info['has_file'],
						'was_deployed' => $page_info['was_deployed'],
					), $project_slug );
				} else {
					Logger::error( 'Failed to clear page content after template deployment.', array(
						'page_slug' => $page_info['slug'],
						'template_slug' => $page_info['template_slug'],
						'post_id' => $page_info['post_id'],
						'error' => $update_result->get_error_message(),
					), $project_slug );
				}
			}
		} elseif ( ! empty( $pages_to_clear ) && $is_etchwp ) {
			Logger::info( 'Keeping page content for EtchWP templates (uses wp:post-content block).', array(
				'count' => count( $pages_to_clear ),
				'pages' => array_map( function( $p ) {
					return $p['slug'];
				}, $pages_to_clear ),
			), $project_slug );
		}
		
		// Log verification of pages that already have empty content
		if ( ! empty( $pages_verified ) ) {
			Logger::info( 'Verified pages with custom templates have empty content.', array(
				'count' => count( $pages_verified ),
				'pages' => array_map( function( $p ) {
					return $p['slug'];
				}, $pages_verified ),
			), $project_slug );
		}

		// Deploy theme files (functions.php, ACF JSON) from staging
		// Use child theme slug derived from project slug (auto-created and activated if needed)
		$theme_slug = ThemeDeployService::get_child_theme_slug( $project_slug );
		$theme_snapshots = array();
		if ( $theme_slug !== '' ) {
			$theme_deploy = ThemeDeployService::deploy_theme_files( $build_root, $theme_slug, $selected_theme_files );
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
			// Capture theme file snapshots for rollback
			if ( isset( $theme_deploy['snapshots'] ) && is_array( $theme_deploy['snapshots'] ) ) {
				$theme_snapshots = $theme_deploy['snapshots'];
			}
		}

		// Collect asset information from staging
		$asset_info = array( 'css' => array(), 'js' => array() );
		$css_dir = $build_root . '/css';
		$js_dir = $build_root . '/js';
		if ( is_dir( $css_dir ) ) {
			$css_files = glob( $css_dir . '/*.css' ) ?: array();
			foreach ( $css_files as $css_file ) {
				$asset_info['css'][] = 'css/' . basename( $css_file );
			}
		}
		if ( is_dir( $js_dir ) ) {
			$js_files = glob( $js_dir . '/*.js' ) ?: array();
			foreach ( $js_files as $js_file ) {
				$asset_info['js'][] = 'js/' . basename( $js_file );
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

		// Validate CPT prefix compliance
		$cpt_prefix_validation = ShortcodePlaceholderService::validate_cpt_prefixes( $project_slug, $settings );
		$cpt_prefix_warnings = isset( $cpt_prefix_validation['warnings'] ) && is_array( $cpt_prefix_validation['warnings'] ) ? $cpt_prefix_validation['warnings'] : array();
		$cpt_prefix_errors = isset( $cpt_prefix_validation['errors'] ) && is_array( $cpt_prefix_validation['errors'] ) ? $cpt_prefix_validation['errors'] : array();
		if ( ! empty( $cpt_prefix_warnings ) ) {
			\VibeCode\Deploy\Logger::info( 'CPT prefix validation warnings.', array( 'warnings' => $cpt_prefix_warnings ), $project_slug );
		}
		if ( ! empty( $cpt_prefix_errors ) ) {
			$errors += count( $cpt_prefix_errors );
			\VibeCode\Deploy\Logger::error( 'CPT prefix validation failed.', array( 'errors' => $cpt_prefix_errors ), $project_slug );
		}

		// Detect unknown prefixed items (shortcodes/CPTs using prefix but not in config)
		$unknown_items = ShortcodePlaceholderService::detect_unknown_prefixed_items( $project_slug, $placeholder_config, $settings );
		$unknown_warnings = isset( $unknown_items['warnings'] ) && is_array( $unknown_items['warnings'] ) ? $unknown_items['warnings'] : array();
		$unknown_errors = isset( $unknown_items['errors'] ) && is_array( $unknown_items['errors'] ) ? $unknown_items['errors'] : array();
		if ( ! empty( $unknown_warnings ) ) {
			\VibeCode\Deploy\Logger::info( 'Unknown prefixed items detected.', array( 'warnings' => $unknown_warnings ), $project_slug );
		}
		if ( ! empty( $unknown_errors ) ) {
			$errors += count( $unknown_errors );
			\VibeCode\Deploy\Logger::error( 'Unknown prefixed items validation failed.', array( 'errors' => $unknown_errors ), $project_slug );
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
				'theme_files' => $theme_snapshots, // Theme file snapshots for rollback
				'assets' => $asset_info, // CSS/JS asset information
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
			
			// Set active fingerprint after successful deployment
			// This ensures CSS/JS files load from the correct staging directory
			if ( $errors === 0 ) {
				BuildService::set_active_fingerprint( $project_slug, $fingerprint );
			}
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
