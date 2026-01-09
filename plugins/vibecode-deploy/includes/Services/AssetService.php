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

	public static function extract_head_assets( \DOMDocument $dom, string $project_slug = '' ): array {
		$css = array();
		$js = array();
		$fonts = array(); // Google Fonts and other external font links

		$xpath = new \DOMXPath( $dom );
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

		// Copy from root level (css/, js/, resources/ folders)
		$folders = array( 'css', 'js', 'resources' );
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

	public static function rewrite_asset_urls( string $html, string $project_slug ): string {
		$plugin_url = plugins_url( 'assets', VIBECODE_DEPLOY_PLUGIN_FILE );
		$pattern = '/(href|src)="(css|js|resources)\/([^"]+)"/';
		$replacement = '$1="' . $plugin_url . '/$2/$3"';
		return preg_replace( $pattern, $replacement, $html );
	}
}
