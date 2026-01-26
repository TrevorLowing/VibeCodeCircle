<?php
/**
 * Development asset loader for Vite applications.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite\Assets;

use Etch\Vite\Apps\AppConfig;

/**
 * Class DevAssetLoader
 *
 * Handles loading of development assets for Vite applications.
 */
class DevAssetLoader {

	/**
	 * Enqueue development assets for an application.
	 *
	 * @param AppConfig $app Application configuration.
	 * @return void
	 */
	public function enqueue_assets( AppConfig $app ): void {
		$dev_server_url = sprintf( 'http://localhost:%d', $app->get_port() );
		$app_name = $app->get_name();

		wp_enqueue_script(
			"vite-client-{$app_name}",
			$dev_server_url . '/@vite/client',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			"svelte-app-{$app_name}",
			$dev_server_url . '/src/main.ts',
			array( "vite-client-{$app_name}" ),
			null,
			true
		);
	}
}
