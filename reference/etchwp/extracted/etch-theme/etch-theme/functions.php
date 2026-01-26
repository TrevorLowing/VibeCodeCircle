<?php
/**
 * Etch Theme's function.php
 *
 * @package etch-theme
 *
 * NOTE: Do not change this file, your changes will be overwritten on update.
 * We advise to create a child theme to add your own customizations.
 *
 * child theme: https://github.com/Digital-Gravy/etch-child-theme.git
 */

add_action(
	'init',
	function () {
		if ( ! class_exists( 'Etch_Theme\SureCart\Licensing\Client' ) ) {
			require_once __DIR__ . '/includes/SureCart/Licensing/Client.php';
		}

		// initialize client with your plugin name and your public token.
		$client = new \Etch_Theme\SureCart\Licensing\Client( 'Etch Theme', 'pt_7eCsZFuK2NuCXK97jzkennFi', __FILE__ );

		// set your textdomain.
		$client->set_textdomain( 'etch-theme' );

		// add the pre-built license settings page.
		$client->settings()->add_page(
			array(
				'type'                 => 'submenu', // Can be: menu, options, submenu.
				'parent_slug'          => 'etch', // add your plugin menu slug.
				'page_title'           => 'Etch Theme',
				'menu_title'           => 'Etch Theme',
				'capability'           => 'manage_options',
				'menu_slug'            => $client->slug . '-manage-license',
				'icon_url'             => '',
				'position'             => null,
			)
		);
	}
);

add_action(
	'after_setup_theme',
	function () {

		$features = array(
			'core-block-patterns',
		);

		foreach ( $features as $feature ) {
			remove_theme_support( $feature );
		}
	}
);

add_filter( 'should_load_remote_block_patterns', '__return_false' );
