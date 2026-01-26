<?php
/**
 * Asset registry for managing all assets.
 *
 * @package Etch
 * @subpackage Assets
 */

declare(strict_types=1);

namespace Etch\Assets;

use Etch\Assets\Styles\StyleLoader;
use Etch\Assets\Styles\WpDefaultsRemover;
use Etch\Assets\WpScript\WpScriptLoader;
/**
 * Class AssetRegistry
 *
 * Manages registration and loading of all assets.
 */
class AssetRegistry {

	/**
	 * Custom style loader instance.
	 *
	 * @var StyleLoader
	 */
	private StyleLoader $style_loader;

	/**
	 * WordPress defaults remover instance.
	 *
	 * @var WpDefaultsRemover
	 */
	private WpDefaultsRemover $wp_defaults_remover;

	/**
	 * WpScriptLoader instance.
	 *
	 * @var WpScriptLoader
	 */
	private WpScriptLoader $wp_script_loader;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->style_loader = new StyleLoader();
		$this->wp_defaults_remover = new WpDefaultsRemover();
		$this->wp_script_loader = new WpScriptLoader();
	}

	/**
	 * Initialize asset loading.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->style_loader->init();
		$this->wp_defaults_remover->init();
		$this->wp_script_loader->init();
	}
}
