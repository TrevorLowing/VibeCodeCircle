<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;

defined( 'ABSPATH' ) || exit;

final class AssetService {
	/**
	 * CDN whitelist configuration for common mapping and utility libraries.
	 * 
	 * Maps CDN URL patterns to WordPress script handles and dependencies.
	 * When a whitelisted CDN is detected, it will be automatically enqueued.
	 * 
	 * @var array<string, array{handle: string, deps: string[], version: string|null}>
	 */
	private static function get_cdn_whitelist(): array {
		return array(
			// Leaflet.js - Interactive maps
			'leaflet' => array(
				'patterns' => array(
					'leaflet.js',
					'leaflet@',
					'unpkg.com/leaflet',
					'cdn.jsdelivr.net/npm/leaflet',
				),
				'handle' => 'leaflet-js',
				'deps' => array(),
				'version' => null, // Use CDN version
				'css_patterns' => array(
					'leaflet.css',
					'unpkg.com/leaflet/dist/leaflet.css',
					'cdn.jsdelivr.net/npm/leaflet/dist/leaflet.css',
				),
				'css_handle' => 'leaflet-css',
			),
			// Leaflet MarkerCluster - Marker clustering for Leaflet
			'leaflet-markercluster' => array(
				'patterns' => array(
					'leaflet.markercluster',
					'markercluster',
				),
				'handle' => 'leaflet-markercluster',
				'deps' => array( 'leaflet-js' ),
				'version' => null,
			),
			// Google Maps API
			'google-maps' => array(
				'patterns' => array(
					'maps.googleapis.com',
					'googleapis.com/maps',
				),
				'handle' => 'google-maps-api',
				'deps' => array(),
				'version' => null,
			),
		);
	}

	/**
	 * Detect if a URL matches a whitelisted CDN pattern.
	 * 
	 * @param string $url CDN URL to check.
	 * @return array|null CDN configuration if matched, null otherwise.
	 */
	private static function detect_cdn( string $url ): ?array {
		$whitelist = self::get_cdn_whitelist();
		$url_lower = strtolower( $url );
		
		foreach ( $whitelist as $cdn_key => $cdn_config ) {
			foreach ( $cdn_config['patterns'] as $pattern ) {
				if ( strpos( $url_lower, strtolower( $pattern ) ) !== false ) {
					return array_merge( $cdn_config, array( 'key' => $cdn_key, 'url' => $url ) );
				}
			}
		}
		
		return null;
	}

	/**
	 * Detect CDN CSS from URL.
	 * 
	 * @param string $url CSS URL to check.
	 * @return array|null CDN CSS configuration if matched, null otherwise.
	 */
	private static function detect_cdn_css( string $url ): ?array {
		$whitelist = self::get_cdn_whitelist();
		$url_lower = strtolower( $url );
		
		foreach ( $whitelist as $cdn_key => $cdn_config ) {
			if ( ! isset( $cdn_config['css_patterns'] ) ) {
				continue;
			}
			foreach ( $cdn_config['css_patterns'] as $pattern ) {
				if ( strpos( $url_lower, strtolower( $pattern ) ) !== false ) {
					return array(
						'key' => $cdn_key,
						'url' => $url,
						'handle' => $cdn_config['css_handle'] ?? $cdn_key . '-css',
					);
				}
			}
		}
		
		return null;
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

	/**
	 * Extract CSS and JavaScript assets from HTML document.
	 * 
	 * **IMPORTANT:** Despite the function name suggesting head-only extraction,
	 * this function actually queries the ENTIRE document for scripts (//script[@src]).
	 * CSS links are extracted from <head> only, but scripts are extracted from anywhere.
	 * 
	 * **Best Practice:** Place all scripts in <head> with defer attribute for
	 * better performance and reliability, even though body scripts will work.
	 * 
	 * @param \DOMDocument $dom Parsed HTML document.
	 * @param string $project_slug Project slug for logging.
	 * @return array Array with 'css', 'js', and 'fonts' keys.
	 * 
	 * @see VibeCodeCircle/plugins/vibecode-deploy/docs/STRUCTURAL_RULES.md#javascript-asset-placement
	 */
	public static function extract_head_assets( \DOMDocument $dom, string $project_slug = '' ): array {
		$css = array();
		$js = array();
		$fonts = array(); // Google Fonts and other external font links
		$cdn_scripts = array(); // Whitelisted CDN scripts
		$cdn_css = array(); // Whitelisted CDN CSS

		$xpath = new \DOMXPath( $dom );
		// CSS: Extract from <head> only
		$links = $xpath->query( '//head//link[@rel="stylesheet"]' );
		if ( $links ) {
			foreach ( $links as $node ) {
				if ( ! ( $node instanceof \DOMElement ) ) {
					continue;
				}
				$href = (string) $node->getAttribute( 'href' );
				
				// Extract Google Fonts and other external font links
				if ( strpos( $href, 'fonts.googleapis.com' ) !== false || strpos( $href, 'fonts.gstatic.com' ) !== false ) {
					$fonts[] = $href;
					continue;
				}
				
				// Check for whitelisted CDN CSS (e.g., Leaflet.css)
				if ( strpos( $href, 'http://' ) === 0 || strpos( $href, 'https://' ) === 0 ) {
					$cdn_css_config = self::detect_cdn_css( $href );
					if ( $cdn_css_config !== null ) {
						$cdn_css[] = $cdn_css_config;
						continue;
					}
				}
				
				$href = self::normalize_local_asset_path( $href );
				if ( $href === '' ) {
					continue;
				}
				// Skip external URLs (http://, https://) that aren't whitelisted
				if ( strpos( $href, 'http://' ) === 0 || strpos( $href, 'https://' ) === 0 ) {
					continue;
				}
				// Only extract CSS files from css/ directory (e.g., css/secure-drop.css, css/styles.css)
				if ( strpos( $href, 'css/' ) !== 0 ) {
					continue;
				}
				$css[] = $href;
			}
		}
		
		// Log extracted CSS files for debugging
		if ( ! empty( $css ) ) {
			$log_project_slug = $project_slug !== '' ? $project_slug : ( Settings::get_all()['project_slug'] ?? '' );
			\VibeCode\Deploy\Logger::info( 'Extracted CSS assets from HTML head.', array(
				'css_files' => $css,
				'count' => count( $css ),
			), $log_project_slug );
		}

		// JavaScript: Extract from ENTIRE document (not just <head>)
		// Note: Function name suggests head-only, but query searches entire document
		$scripts = $xpath->query( '//script[@src]' );
		if ( $scripts ) {
			foreach ( $scripts as $node ) {
				if ( ! ( $node instanceof \DOMElement ) ) {
					continue;
				}
				$src = (string) $node->getAttribute( 'src' );
				$original_src = $src;
				
				// Check for whitelisted CDN scripts (e.g., Leaflet.js)
				if ( strpos( $src, 'http://' ) === 0 || strpos( $src, 'https://' ) === 0 ) {
					$cdn_config = self::detect_cdn( $src );
					if ( $cdn_config !== null ) {
						$cdn_scripts[] = array(
							'url' => $src,
							'handle' => $cdn_config['handle'],
							'deps' => $cdn_config['deps'] ?? array(),
							'version' => $cdn_config['version'] ?? null,
							'key' => $cdn_config['key'],
						);
						continue;
					}
				}
				
				$src = self::normalize_local_asset_path( $src );
				if ( $src === '' ) {
					continue;
				}
				// Skip external URLs (http://, https://) that aren't whitelisted
				if ( strpos( $src, 'http://' ) === 0 || strpos( $src, 'https://' ) === 0 ) {
					// Allow whitelisted external assets (e.g. for maps)
					$allowed_domains = array( 'unpkg.com', 'cdnjs.cloudflare.com', 'fonts.googleapis.com', 'fonts.gstatic.com', 'ajax.googleapis.com' );
					$is_allowed = false;
					foreach ( $allowed_domains as $domain ) {
						if ( strpos( $src, $domain ) !== false ) {
							$is_allowed = true;
							break;
						}
					}
					if ( ! $is_allowed ) {
						continue;
					}
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

		// Auto-detect CDN dependencies for local scripts
		// Example: If map.js is detected, automatically add Leaflet.js as dependency
		$auto_cdn_deps = self::detect_auto_cdn_dependencies( $js, $project_slug );
		$cdn_scripts = array_merge( $cdn_scripts, $auto_cdn_deps );

		// Log detected CDN scripts
		if ( ! empty( $cdn_scripts ) || ! empty( $cdn_css ) ) {
			$log_project_slug = $project_slug !== '' ? $project_slug : ( Settings::get_all()['project_slug'] ?? '' );
			Logger::info( 'Detected whitelisted CDN assets.', array(
				'cdn_scripts' => $cdn_scripts,
				'cdn_css' => $cdn_css,
				'script_count' => count( $cdn_scripts ),
				'css_count' => count( $cdn_css ),
			), $log_project_slug );
		}

		return array(
			'css' => array_values( array_unique( $css ) ),
			'js' => $js,
			'fonts' => array_values( array_unique( $fonts ) ), // Google Fonts and external fonts
			'cdn_scripts' => $cdn_scripts, // Whitelisted CDN scripts
			'cdn_css' => $cdn_css, // Whitelisted CDN CSS
		);
	}

	/**
	 * Auto-detect CDN dependencies for local scripts.
	 * 
	 * Example: If map.js is detected, automatically add Leaflet.js as dependency.
	 * 
	 * @param array $local_js Array of local JavaScript files.
	 * @param string $project_slug Project slug for logging.
	 * @return array Array of CDN script configurations to auto-enqueue.
	 */
	private static function detect_auto_cdn_dependencies( array $local_js, string $project_slug = '' ): array {
		$auto_deps = array();
		
		// Check each local JS file for known dependencies
		foreach ( $local_js as $js_file ) {
			$src = $js_file['src'] ?? '';
			if ( $src === '' ) {
				continue;
			}
			
			// map.js depends on Leaflet.js
			if ( strpos( $src, 'map.js' ) !== false ) {
				// Check if Leaflet.js is already in the list (avoid duplicates)
				$leaflet_already_added = false;
				foreach ( $auto_deps as $dep ) {
					if ( isset( $dep['key'] ) && $dep['key'] === 'leaflet' ) {
						$leaflet_already_added = true;
						break;
					}
				}
				
				if ( ! $leaflet_already_added ) {
					$leaflet_config = self::get_cdn_whitelist()['leaflet'];
					$auto_deps[] = array(
						'url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', // Default Leaflet CDN URL
						'handle' => $leaflet_config['handle'],
						'deps' => $leaflet_config['deps'],
						'version' => $leaflet_config['version'],
						'key' => 'leaflet',
						'auto_detected' => true,
						'triggered_by' => $src,
					);
					
					// Also add Leaflet CSS
					$auto_deps[] = array(
						'url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
						'handle' => $leaflet_config['css_handle'],
						'type' => 'css',
						'key' => 'leaflet',
						'auto_detected' => true,
						'triggered_by' => $src,
					);
				}
			}
		}
		
		return $auto_deps;
	}

	public static function copy_assets_to_plugin_folder( string $staging_root ): void {
		$plugin_dir = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) : '';
		if ( $plugin_dir === '' ) {
			return;
		}
		$target_dir = $plugin_dir . '/assets';
		wp_mkdir_p( $target_dir );

		// Check image storage method setting
		$settings = Settings::get_all();
		$storage_method = isset( $settings['image_storage_method'] ) && is_string( $settings['image_storage_method'] ) ? (string) $settings['image_storage_method'] : 'media_library';
		
		// Copy from root level (css/, js/, resources/ folders)
		// Skip resources/ folder if Media Library mode is active (images will be uploaded to Media Library instead)
		$folders = array( 'css', 'js' );
		if ( $storage_method !== 'media_library' ) {
			$folders[] = 'resources';
		}
		
		foreach ( $folders as $folder ) {
			$src = $staging_root . '/' . $folder;
			$dst = $target_dir . '/' . $folder;
			if ( is_dir( $src ) ) {
				self::rrcopy( $src, $dst );
			}
		}
		
		// Also check if there's an assets subfolder (for backward compatibility)
		$assets_base = $staging_root . '/assets';
		if ( is_dir( $assets_base ) ) {
			foreach ( $folders as $folder ) {
				$src = $assets_base . '/' . $folder;
				$dst = $target_dir . '/' . $folder;
				if ( is_dir( $src ) ) {
					self::rrcopy( $src, $dst );
				}
			}
		}
	}

	private static function rrcopy( string $src, string $dst ): void {
		if ( ! is_dir( $dst ) ) {
			wp_mkdir_p( $dst );
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			$relative_path = str_replace( $src . '/', '', $item->getPathname() );
			$target = $dst . '/' . $relative_path;
			if ( $item->isDir() ) {
				wp_mkdir_p( $target );
			} else {
				copy( $item->getPathname(), $target );
			}
		}
	}

	/**
	 * Convert relative asset path to full plugin asset URL.
	 * 
	 * Converts paths like:
	 * - `resources/image.jpg` → `[plugin_url]/assets/resources/image.jpg`
	 * - `css/styles.css` → `[plugin_url]/assets/css/styles.css`
	 * - `js/main.js` → `[plugin_url]/assets/js/main.js`
	 * 
	 * **Returns original URL if:**
	 * - Already absolute (starts with http:// or https://)
	 * - Not a recognized asset path (css/, js/, resources/)
	 * 
	 * @param string $path Relative asset path (e.g., `resources/image.jpg`).
	 * @return string Full plugin asset URL or original path if not convertible.
	 */
	public static function convert_asset_path_to_url( string $path ): string {
		$path = trim( $path );
		if ( $path === '' ) {
			return $path;
		}
		
		// If already absolute URL, return as-is
		if ( strpos( $path, 'http://' ) === 0 || strpos( $path, 'https://' ) === 0 ) {
			return $path;
		}
		
		// Normalize path (remove leading ./ or /)
		$path = (string) preg_replace( '/^\.\//', '', $path );
		$path = ltrim( $path, '/' );
		
		// Check if it's a recognized asset path
		if ( strpos( $path, 'css/' ) === 0 || strpos( $path, 'js/' ) === 0 || strpos( $path, 'resources/' ) === 0 ) {
			$plugin_url = plugins_url( 'assets', VIBECODE_DEPLOY_PLUGIN_FILE );
			return rtrim( $plugin_url, '/' ) . '/' . $path;
		}
		
		// Not a recognized asset path, return as-is
		return $path;
	}

	/**
	 * Rewrite asset URLs (css/, js/, resources/) to plugin asset URLs.
	 * 
	 * **IMPORTANT:** This function ONLY rewrites paths starting with:
	 * - `css/` → Plugin CSS URL
	 * - `js/` → Plugin JS URL
	 * - `resources/` → Plugin resources URL
	 * 
	 * **NOT Rewritten:**
	 * - `images/` paths are NOT rewritten (causes 404 errors)
	 * - Use `resources/images/` instead of `images/` for all images
	 * 
	 * **URL Rewriting Order:**
	 * This function should be called FIRST, before page URL rewriting.
	 * DeployService calls this before rewrite_urls() to ensure correct order.
	 * 
	 * @param string $html HTML content to rewrite.
	 * @param string $project_slug Project slug (unused, kept for compatibility).
	 * @return string HTML with rewritten asset URLs.
	 * 
	 * @see VibeCodeCircle/plugins/vibecode-deploy/docs/STRUCTURAL_RULES.md#image-path-conventions
	 * @see VibeCodeCircle/plugins/vibecode-deploy/docs/STRUCTURAL_RULES.md#url-rewriting-rules
	 */
	public static function rewrite_asset_urls( string $html, string $project_slug ): string {
		$plugin_url = plugins_url( 'assets', VIBECODE_DEPLOY_PLUGIN_FILE );
		// Pattern matches: css/, js/, resources/ - but NOT images/
		$pattern = '/(href|src)="(css|js|resources)\/([^"]+)"/';
		$replacement = '$1="' . $plugin_url . '/$2/$3"';
		return preg_replace( $pattern, $replacement, $html );
	}
}
