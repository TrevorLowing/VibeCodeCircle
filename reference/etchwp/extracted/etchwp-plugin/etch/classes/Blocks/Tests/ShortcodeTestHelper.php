<?php
/**
 * Shortcode Test Helper Trait
 *
 * Provides helper methods for registering and removing test shortcodes in Etch block tests.
 *
 * @package Etch\Blocks\Tests
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

/**
 * Trait ShortcodeTestHelper
 *
 * Helper methods for shortcode registration in block tests.
 */
trait ShortcodeTestHelper {

	/**
	 * Register test shortcodes in setUp.
	 *
	 * Registers several test shortcodes for use in integration tests:
	 * - etch_test_hello: Simple greeting shortcode with name attribute
	 * - etch_test_dom: Returns a DOM element (div)
	 * - etch_test_box: DOM element with content and class attributes
	 * - etch_test_card: Card element with title and description
	 * - etch_test_widget: Widget with id, label, and value attributes
	 *
	 * @return void
	 */
	protected function register_test_shortcode(): void {
		add_shortcode(
			'etch_test_hello',
			function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'name' => 'World',
					),
					$atts,
					'etch_test_hello'
				);

				return 'Hello ' . esc_html( $atts['name'] ) . '!';
			}
		);

		// Register shortcode that returns DOM element
		add_shortcode(
			'etch_test_dom',
			function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'text' => 'default',
					),
					$atts,
					'etch_test_dom'
				);

				return '<div>' . esc_html( $atts['text'] ) . '</div>';
			}
		);

		// Register shortcode that returns DOM element with unquoted attributes support
		add_shortcode(
			'etch_test_box',
			function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'content' => 'default',
						'class'   => 'box',
					),
					$atts,
					'etch_test_box'
				);

				return '<div class="' . esc_attr( $atts['class'] ) . '">' . esc_html( $atts['content'] ) . '</div>';
			}
		);

		// Register shortcode that returns DOM element with quoted attributes support
		add_shortcode(
			'etch_test_card',
			function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'title'       => 'Default Title',
						'description' => 'Default Description',
					),
					$atts,
					'etch_test_card'
				);

				return '<div class="card"><h3>' . esc_html( $atts['title'] ) . '</h3><p>' . esc_html( $atts['description'] ) . '</p></div>';
			}
		);

		// Register shortcode that returns DOM element supporting both quoted and unquoted attributes
		add_shortcode(
			'etch_test_widget',
			function ( $atts ) {
				$atts = shortcode_atts(
					array(
						'id'    => '',
						'label' => 'Widget',
						'value' => '0',
					),
					$atts,
					'etch_test_widget'
				);

				return '<div id="' . esc_attr( $atts['id'] ) . '" class="widget"><span class="label">' . esc_html( $atts['label'] ) . '</span><span class="value">' . esc_html( $atts['value'] ) . '</span></div>';
			}
		);
	}

	/**
	 * Remove test shortcodes in tearDown.
	 *
	 * @return void
	 */
	protected function remove_test_shortcode(): void {
		remove_shortcode( 'etch_test_hello' );
		remove_shortcode( 'etch_test_dom' );
		remove_shortcode( 'etch_test_box' );
		remove_shortcode( 'etch_test_card' );
		remove_shortcode( 'etch_test_widget' );
	}
}
