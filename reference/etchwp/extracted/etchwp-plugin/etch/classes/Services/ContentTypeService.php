<?php
/**
 * ContentTypeService.php
 *
 * This file contains the ContentTypeService class which defines the service for handling custom post types.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\Services
 */

namespace Etch\Services;

use Etch\Traits\Singleton;
use Etch\Helpers\Flag;
use Etch\Services\TaxonomiesService;
use Etch\Helpers\Logger;

/**
 * ContentTypeService
 *
 * This class is responsible for registering custom post types.
 *
 * @package Etch\Services
 */
class ContentTypeService {

	use Singleton;

	/**
	 * The name of the option that stores the custom post types.
	 *
	 * @var string
	 */
	private string $option_name = 'etch_cpts';

	/**
	 * The name of the option that stores the custom taxonomies.
	 *
	 * @var string
	 */
	private string $option_taxonomies_name = 'etch_taxonomies';

	/**
	 * The taxonomy service.
	 *
	 * @var TaxonomiesService
	 */
	private TaxonomiesService $taxonomies_service;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->taxonomies_service = new TaxonomiesService();
	}

	/**
	 * Initialize the Content Type Service.
	 */
	public function init(): void {
		add_action( 'init', array( $this, 'register_post_types' ), 5 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 11 );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 100 );
		add_action( 'admin_init', array( $this, 'maybe_flush_rewrite_rules' ), 100 );
	}

	/**
	 * Register all saved CPTs from the wp_options table.
	 *
	 * @return void
	 */
	public function register_post_types(): void {
		$cpts = get_option( $this->option_name, array() );

		if ( ! is_array( $cpts ) ) {
			return;
		}

		foreach ( $cpts as $id => $args ) {
			if ( ! is_string( $id ) || ! is_array( $args ) ) {
				continue;
			}

			if ( ! isset( $args['label'] ) ) {
				$args['label'] = ucfirst( $id ); // we are just shooting here the id as the label by capitalizing it, probably we can skip this when the implementation is done on svelte.
			}

			$args['show_in_rest'] = true; // TODO: i think is needed for GB, but not sure.

			register_post_type( $id, $args );
		}
	}

	/**
	 * Maybe flush rewrite rules.
	 *
	 * This is used for us to know that we need to flush rewrite rules ( permalinks ).
	 * This transient will expire in 60 ( when wordpress removes it ) or when we remove it.
	 * The transient is set in CustomPostTypeService set_etch_transient() and called in each CUD ( Create, Update, Delete ) method.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		if ( get_transient( 'etch_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'etch_flush_rewrite_rules' );
		}
	}

	/**
	 * Register all saved taxonomies from the wp_options table.
	 *
	 * @return void
	 */
	public function register_taxonomies() {
		$taxonomies = get_option( $this->option_taxonomies_name, array() );

		if ( ! is_array( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy_key => $data ) {
			if ( ! isset( $data['args'], $data['object_types'] ) || ! is_array( $data['args'] ) || ! is_array( $data['object_types'] ) ) {
				continue;
			}

			$object_types = $data['object_types'];
			$wp_args = $this->taxonomies_service->map_args_to_wp_args( $taxonomy_key, $data['args'] );

			register_taxonomy( $taxonomy_key, $object_types, $wp_args );
		}
	}
}
