<?php
/**
 * WpScriptLoader file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Assets\WpScript;

/**
 * WpScriptLoader class.
 */
class WpScriptLoader {
	/**
	 * Enqueues the editor modification script.
	 *
	 * @return void
	 */
	public function enqueue_editor_modification() {
			$asset_name = 'attrs-registration';
			// $asset_file = include ETCH_PLUGIN_DIR . 'blocks/' . $asset_name . '/build/index.asset.php';
			$asset_file = include ETCH_PLUGIN_DIR . 'classes/Assets/WpScript/build/index.asset.php';

			wp_enqueue_script(
				'etch-' . $asset_name,
				plugins_url( 'classes/Assets/WpScript/build/index.js', ETCH_PLUGIN_FILE ),
				$asset_file['dependencies'],
				$asset_file['version'],
				true
			);
	}

	/**
	 * Initializes the class by adding action hooks.
	 *
	 * @return void
	 */
	public function init() {
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_modification' ) );
	}
}
