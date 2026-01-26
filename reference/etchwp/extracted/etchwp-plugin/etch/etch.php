<?php
/**
 * Etch
 *
 * @package           Etch
 * @author            Digital Gravy
 * @copyright         2024 Digital Gravy
 * @gplv2
 *
 * @wordpress-plugin
 * Plugin Name:       Etch
 * Plugin URI:        https://etchwp.com
 * Description:       Your unified development environment for WordPress.
 * Version:           1.0.0-rc-1
 * Requires at least: 5.9
 * Requires PHP:      8.1
 * Author:            Digital Gravy
 * Author URI:        https://digitalgravy.co
 * Text Domain:       etch
 * License:           GPL v3 or later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 */

declare(strict_types=1);

use Etch\Helpers\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Define plugin directories and urls.
 */
define( 'ETCH_PLUGIN_FILE', __FILE__ );
define( 'ETCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ETCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Initialize the plugin.
 */
require_once ETCH_PLUGIN_DIR . '/vendor/autoload.php';
\Etch\Plugin::get_instance()->init();

/**
 * Run the plugin.
 *
 * @return void
 */
function etch_run_plugin() {
	\Etch\Plugin::get_instance()->run();
}
add_action( 'plugins_loaded', 'etch_run_plugin' );

/**
 * Generate a hash for a preset to detect modifications.
 *
 * @param array<string, mixed> $data The preset data to hash.
 * @return string MD5 hash of the preset data.
 */
function etch_generate_preset_hash( $data ): string {
	// Remove any existing hash field before hashing to avoid circular reference.
	$clean_data = $data;
	unset( $clean_data['_preset_hash'] );

	// Use JSON encode with flags for consistent hashing.
	$json = wp_json_encode( $clean_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	// Handle JSON encoding failure gracefully.
	if ( false === $json ) {
		$json = '';
	}

	return md5( $json );
}

/**
 * Initialize default loop presets for Etch plugin.
 *
 * @return void
 */
function init_etch_loop_presets() {
	$default_presets = array(
		'k7mrbkq' => array(
			'name' => 'Posts',
			'key' => 'posts',
			'global' => true,
			'config' => array(
				'type' => 'wp-query',
				'args' => array(
					'post_type' => 'post',
					'posts_per_page' => '$limit ?? -1',
					'orderby'        => "\$orderby ?? 'date'",
					'order'          => "\$order ?? 'DESC'",
					'post_status' => 'publish',
				),
			),
		),
		'etch1r1' => array(
			'name' => 'Nav',
			'key' => 'nav',
			'global' => true,
			'config' => array(
				'type' => 'json',
				'data' => array(
					array(
						'label' => 'Home',
						'url' => '/',
					),
					array(
						'label' => 'About',
						'url' => '/about',
					),
					array(
						'label' => 'Services',
						'children' => array(
							array(
								'label' => 'Item 3.1',
								'url' => '/dropdown1',
							),
							array(
								'label' => 'Item 3.2',
								'url' => '/dropdown2',
							),
							array(
								'label' => 'Item 3.3',
								'url' => '/dropdown3',
							),
							array(
								'label' => 'Item 3.4',
								'url' => '/dropdown4',
							),
						),
					),
					array(
						'label' => 'Contact',
						'url' => '/contact',
					),
					array(
						'label' => 'Call to Action',
						'url' => '/',
						'class' => 'btn--primary',
					),
				),
			),
		),
		'k5rb2t1' => array(
			'name' => 'Simple JSON',
			'key' => 'simple_json',
			'global' => true,
			'config' => array(
				'type' => 'json',
				'data' => array(
					array(
						'title' => 'Post 1',
						'content' => 'This is the content of post 1',
					),
					array(
						'title' => 'Post 2',
						'content' => 'This is the content of post 2',
					),
				),
			),
		),
		'etch_main_query' => array(
			'name' => 'Main Query',
			'key' => 'mainQuery',
			'global' => true,
			'config' => array(
				'type' => 'main-query',
				'args' => array(
					'posts_per_page' => '$count ?? 10',
					'orderby'        => "\$orderby ?? 'date'",
					'order'          => "\$order ?? 'DESC'",
					'offset'         => '$offset ?? 0',
				),
			),
		),
	);

	$option_name = 'etch_loops';

	$existing_loops = get_option( $option_name, array() );
	if ( ! is_array( $existing_loops ) ) {
		$existing_loops = array();
	}

	// Process each default preset.
	foreach ( $default_presets as $preset_id => $preset_data ) {
		// Generate hash for the new preset version.
		$preset_hash = etch_generate_preset_hash( $preset_data );

		if ( ! isset( $existing_loops[ $preset_id ] ) ) {
			// New loop - add it with the preset hash.
			$existing_loops[ $preset_id ] = $preset_data;
			$existing_loops[ $preset_id ]['_preset_hash'] = $preset_hash;
		} else {
			// Existing loop - check if it's been modified by the user.
			$existing_loop = $existing_loops[ $preset_id ];
			$stored_hash = $existing_loop['_preset_hash'] ?? null;

			// Calculate hash of the current loop data (excluding the hash field).
			$loop_copy = $existing_loop;
			unset( $loop_copy['_preset_hash'] );
			$current_hash = etch_generate_preset_hash( $loop_copy );

			// If the stored hash exists AND matches the current hash,
			// the user hasn't modified it - safe to update.
			if ( $stored_hash && $stored_hash === $current_hash ) {
				// Update to the new preset version.
				$existing_loops[ $preset_id ] = $preset_data;
				$existing_loops[ $preset_id ]['_preset_hash'] = $preset_hash;
			}
			// else: user has modified the loop, leave it alone.
		}
	}

	update_option( 'etch_loops', $existing_loops );
}


/**
 * Migrates old Etch components to WordPress block patterns (wp_block post type).
 *
 * @return void
 */
function migrate_old_components_to_patterns() {
	$existing_components = get_option( 'etch_components', array() );

	// early return on empty
	if ( empty( $existing_components ) || ! is_array( $existing_components ) ) {
		return;
	}

	// create pattern for every existing component
	foreach ( $existing_components as $component_id => $component_data ) {
		$existing_pattern_query = new WP_Query(
			array(
				'post_type'      => 'wp_block',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'     => 'etch_component_legacy_id',
						'value'   => $component_id,
						'compare' => '=',
					),
				),
				'fields' => 'ids',
			)
		);

		if ( $existing_pattern_query->have_posts() ) {
			Logger::log( 'Pattern for component ID ' . $component_id . ' already exists, skipping.' );
			continue;
		}

		$post_data = array(
			'post_type'    => 'wp_block',
			'post_title'   => sanitize_text_field( $component_data['name'] ?? 'New Pattern' ),
			'post_content' => wp_slash( serialize_blocks( $component_data['blocks'] ?? array() ) ),
			'post_excerpt' => sanitize_text_field( $component_data['description'] ?? '' ),
			'post_status'  => 'publish',
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			Logger::log( 'Failed to create pattern for component ID ' . $component_id );
			continue;
		}

		if ( isset( $component_data['properties'] ) ) {
			update_post_meta( $post_id, 'etch_component_properties', $component_data['properties'] );
		}

		if ( isset( $component_data['key'] ) ) {
			update_post_meta( $post_id, 'etch_component_html_key', $component_data['key'] );
		}

				update_post_meta( $post_id, 'etch_component_legacy_id', $component_id );

	}

		// delete old components option
	delete_option( 'etch_components' );
}

// function reset_custom_block_migration_flag() {
// $settings = get_option( 'etch_settings', array() );
// if ( ! is_array( $settings ) ) {
// $settings = array();
// }
// $settings['custom_block_migration_completed'] = false;
// update_option( 'etch_settings', $settings );
// }
// add_action( 'init', 'reset_custom_block_migration_flag', 11 );

add_action( 'init', 'init_etch_loop_presets', 9 );
add_action( 'init', 'migrate_old_components_to_patterns', 10 );
// function my_hello_world_shortcode( $atts ) {
// $atts = shortcode_atts(
// array(
// 'name' => 'Guest',
// ),
// $atts,
// 'hello_world'
// );

// $output = 'Hello World, ' . esc_html( $atts['name'] );

// return $output;
// }

// add_shortcode( 'hello_world', 'my_hello_world_shortcode' );
