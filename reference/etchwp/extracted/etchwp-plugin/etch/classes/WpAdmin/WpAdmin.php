<?php
/**
 * WP Admin Class
 *
 * Handles registration of things in the WordPress admin.
 *
 * @package Etch\WpAdmin
 * @since 0.11.1
 */

declare(strict_types=1);

namespace Etch\WpAdmin;

use WP_Admin_Bar;
use Etch\Traits\Singleton;
use Etch\WpAdmin\SettingsPage;
use Etch\Helpers\Logger;

/**
 * Registers things in the WordPress admin.
 */
class WpAdmin {
	use Singleton;

	/**
	 * Initializes the class.
	 */
	public static function init(): void {
		add_action( 'admin_bar_menu', array( self::get_instance(), 'add_frontend_admin_bar_edit_button' ), 100 );
		add_action( 'admin_bar_menu', array( self::get_instance(), 'add_dashboard_admin_bar_edit_button' ), 100 );
		SettingsPage::get_instance()->init();
	}

	/**
	 * Registers the Edit with Etch button to the admin bar.
	 *
	 * This function adds a button to the admin bar to open the Etch editor ( frontend only ).
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar.
	 */
	public function add_frontend_admin_bar_edit_button( WP_Admin_Bar $wp_admin_bar ): void {

		// Show only on the frontend
		if ( is_admin() ) {
			return;
		}

		// TODO: is this the right capability? i used administrator role but phpcs complains about it
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( false === $post_id || empty( $post_id ) ) {
			return;
		}

		$base_url = home_url();

		$button_url = add_query_arg(
			array(
				'etch'    => 'magic',
				'post_id' => absint( $post_id ),
			),
			$base_url
		);

		$button_url = esc_url( $button_url );
		$button_title = esc_html__( 'Edit with Etch', 'etch' );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'edit-with-etch',
				'title'  => $button_title,
				'href'   => $button_url,
				'parent' => 'root',
			)
		);
	}

	/**
	 * Summary of add_dashboard_admin_bar_edit_button.
	 *
	 * This function adds a button to the admin bar to open the Etch editor ( wp dashboard only ).
	 * It checks if a frontpage is set and if not, it queries for the first available page.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar The admin bar.
	 * @return void
	 */
	public function add_dashboard_admin_bar_edit_button( WP_Admin_Bar $wp_admin_bar ): void {

		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$available_page_id = null;

		// check if frontpage is set and get its id.
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$frontpage_id = get_option( 'page_on_front' );
			$available_page_id = is_scalar( $frontpage_id ) ? (int) $frontpage_id : null;
		}

		// if we dont have a frontpage set, we query for pages and get the first one.
		if ( ! $available_page_id ) {

			$first_available_page = get_posts(
				array(
					'post_type' => 'page',
					'post_status' => 'publish',
					'orderby' => 'date',
					'order' => 'ASC',
					'posts_per_page' => 1,
					'fields' => 'ids',
				)
			);

			if ( empty( $first_available_page ) ) {
				return;
			}

			$available_page_id = (int) $first_available_page[0];
		}

		$base_url = home_url();
		$button_title = esc_html__( 'Launch Etch', 'etch' );

		$button_url = add_query_arg(
			array(
				'etch'    => 'magic',
				'post_id' => absint( $available_page_id ),
			),
			$base_url
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'edit-with-etch',
				'title'  => $button_title,
				'href'   => $button_url,
				'parent' => 'root',
			)
		);
	}
}
