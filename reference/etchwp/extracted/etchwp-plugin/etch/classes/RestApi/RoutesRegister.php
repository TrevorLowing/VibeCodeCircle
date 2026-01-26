<?php
/**
 * RoutesRegister.php
 *
 * This file contains the RoutesRegister class, responsible for registering all REST API routes.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi
 */

declare(strict_types=1);
namespace Etch\RestApi;

use Etch\RestApi\Routes\BlocksRoutes;
use Etch\RestApi\Routes\StylesRoutes;
use Etch\RestApi\Routes\QueriesRoutes;
use Etch\RestApi\Routes\UiRoutes;
use Etch\RestApi\Routes\ComponentsRoutes;
use Etch\RestApi\Routes\ComponentFieldRoutes;
use Etch\RestApi\Routes\CptRoutes;
use Etch\RestApi\Routes\CustomFieldsRoutes;
use Etch\RestApi\Routes\PostTypesRoutes;
use Etch\RestApi\Routes\LoopsRoutes;
use Etch\RestApi\Routes\MediaRoutes;
use Etch\RestApi\Routes\PatternsRoutes;
use Etch\RestApi\Routes\TemplatesRoutes as WpTemplatesRoutes;
use Etch\RestApi\Routes\TaxonomyRoutes;
use Etch\RestApi\Routes\SiteRoutes;
use Etch\RestApi\Routes\UserRoutes;
use Etch\RestApi\Routes\TaxonomiesRoutes;
use Etch\RestApi\Routes\OptionPagesRoutes;
use Etch\RestApi\Routes\SettingsRoutes;
use Etch\RestApi\Routes\ArchiveRoutes;
use Etch\RestApi\Routes\CssToolbarRoutes;
use Etch\RestApi\Routes\BlockParserRoutes;
use Etch\Traits\Singleton;
use Etch\Helpers\Flag;

/**
 * RoutesRegister
 *
 * The RoutesRegister class registers REST API routes for various Elements.
 * It uses the Singleton trait to ensure a single instance throughout the application.
 *
 * @package Etch\RestApi
 */
class RoutesRegister {

	use Singleton;

	/**
	 * Registers all routes for the plugin's REST API.
	 *
	 * This method instantiates the route classes and calls their respective
	 * register_routes() methods.
	 *
	 * @return void
	 */
	public function register_routes() {
		( new BlocksRoutes() )->register_routes();
		( new StylesRoutes() )->register_routes();
		( new LoopsRoutes() )->register_routes();
		( new QueriesRoutes() )->register_routes();
		( new UiRoutes() )->register_routes();
		( new ComponentsRoutes() )->register_routes();
		if ( Flag::is_on( 'ENABLE_BLOCK_COPY_PASTE_JSON_TO_CUSTOM_FIELD_SAVE' ) ) {
			( new ComponentFieldRoutes() )->register_routes();
		}
		( new CptRoutes() )->register_routes();
		( new CustomFieldsRoutes() )->register_routes();
		( new PostTypesRoutes() )->register_routes();
		( new WpTemplatesRoutes() )->register_routes();
		( new TaxonomyRoutes() )->register_routes();
		( new SiteRoutes() )->register_routes();
		( new UserRoutes() )->register_routes();
		( new MediaRoutes() )->register_routes();
		( new PatternsRoutes() )->register_routes();
		( new TaxonomiesRoutes() )->register_routes();
		( new OptionPagesRoutes() )->register_routes();
		( new SettingsRoutes() )->register_routes();
		( new ArchiveRoutes() )->register_routes();
		( new CssToolbarRoutes() )->register_routes();
		( new BlockParserRoutes() )->register_routes();
	}
}
