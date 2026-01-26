<?php
/**
 * Production asset loader for Vite applications.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite\Assets;

use Etch\Vite\Apps\AppConfig;
use Etch\Helpers\Logger;
use Etch\Vite\Assets\ScriptTagModifier;

/**
 * Class ProdAssetLoader
 *
 * Handles loading of production assets for Vite applications.
 */
class ProdAssetLoader {


	/**
	 * Enqueue production assets for an application.
	 *
	 * @param AppConfig $app Application configuration.
	 * @return void
	 */
	public function enqueue_assets( AppConfig $app ): void {
		$manifest = $this->load_manifest( $app );
		if ( null === $manifest ) {
			return;
		}

		$this->enqueue_main_script( $app, $manifest );
		$this->enqueue_styles( $app, $manifest );
	}

	/**
	 * Load manifest file for an application.
	 *
	 * @param AppConfig $app Application configuration.
	 * @return array<string, mixed>|null
	 */
	private function load_manifest( AppConfig $app ): ?array {
		$app_name = $app->get_name();
		$manifest_path = ETCH_PLUGIN_DIR . "apps/dist/{$app_name}/manifest.json";

		if ( ! file_exists( $manifest_path ) ) {
			Logger::log( sprintf( '%s: Manifest file not found for app: %s', __METHOD__, $app_name ) );
			return null;
		}

		$manifest_contents = file_get_contents( $manifest_path );
		if ( false === $manifest_contents ) {
			Logger::log( sprintf( '%s: Failed to read manifest file for app: %s', __METHOD__, $app_name ) );
			return null;
		}

		$manifest = json_decode( $manifest_contents, true );
		if ( ! is_array( $manifest ) ) {
			Logger::log( sprintf( '%s: Invalid manifest JSON for app: %s', __METHOD__, $app_name ) );
			return null;
		}

		return $manifest;
	}

	/**
	 * Enqueue main script file.
	 *
	 * @param AppConfig            $app Application configuration.
	 * @param array<string, mixed> $manifest Manifest data.
	 * @return void
	 */
	private function enqueue_main_script( AppConfig $app, array $manifest ): void {
		if ( ! isset( $manifest['src/main.ts'] )
			|| ! is_array( $manifest['src/main.ts'] )
			|| ! isset( $manifest['src/main.ts']['file'] )
			|| ! is_string( $manifest['src/main.ts']['file'] )
		) {
			return;
		}

		$app_name = $app->get_name();
		$main_js = $manifest['src/main.ts']['file'];
		$js_path = ETCH_PLUGIN_DIR . "apps/dist/{$app_name}/{$main_js}";

		if ( ! file_exists( $js_path ) ) {
			return;
		}

		$js_url = ETCH_PLUGIN_URL . "apps/dist/{$app_name}/{$main_js}";
		$version = (string) filemtime( $js_path );

		wp_enqueue_script(
			"svelte-app-{$app_name}",
			$js_url,
			array(),
			$version,
			true
		);
	}

	/**
	 * Enqueue CSS files.
	 *
	 * @param AppConfig            $app Application configuration.
	 * @param array<string, mixed> $manifest Manifest data.
	 * @return void
	 */
	private function enqueue_styles( AppConfig $app, array $manifest ): void {
		if ( ! isset( $manifest['src/main.ts'] )
			|| ! is_array( $manifest['src/main.ts'] )
			|| ! isset( $manifest['src/main.ts']['css'] )
			|| ! is_array( $manifest['src/main.ts']['css'] )
		) {
			return;
		}

		$app_name = $app->get_name();
		/**
		 *  Variable to store CSS files.
		 *
		 * @var array<string> $css_files
		 * */
		$css_files = $manifest['src/main.ts']['css'];

		foreach ( $css_files as $css_file ) {
			if ( ! is_string( $css_file ) ) {
				continue;
			}
			$css_path = ETCH_PLUGIN_DIR . "apps/dist/{$app_name}/{$css_file}";
			if ( ! file_exists( $css_path ) ) {
				continue;
			}

			$css_url = ETCH_PLUGIN_URL . "apps/dist/{$app_name}/{$css_file}";
			$version = (string) filemtime( $css_path );

			wp_enqueue_style(
				"svelte-app-{$app_name}-css",
				$css_url,
				array(),
				$version
			);
		}
	}
}
