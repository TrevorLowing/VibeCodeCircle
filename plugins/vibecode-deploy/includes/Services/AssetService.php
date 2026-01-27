<?php

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;

defined( 'ABSPATH' ) || exit;

final class AssetService {
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
				
				$href = self::normalize_local_asset_path( $href );
				if ( $href === '' ) {
					continue;
				}
				// Skip external URLs (http://, https://)
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
			'fonts' => array_values( array_unique( $fonts ) ), // Google Fonts and external fonts
		);
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
