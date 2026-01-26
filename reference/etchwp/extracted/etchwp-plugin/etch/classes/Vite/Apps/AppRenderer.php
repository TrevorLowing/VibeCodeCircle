<?php
/**
 * App renderer for Vite applications.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite\Apps;

use Etch\Helpers\Logger;

/**
 * Class AppRenderer
 *
 * Handles rendering conditions and DOM elements for Vite applications.
 */
class AppRenderer {

	/**
	 * Render conditions for applications.
	 *
	 * @var array<string, string>
	 */
	private array $render_conditions = array();

	/**
	 * Set render condition for an application.
	 *
	 * @param string $app_name Application name.
	 * @param string $env Environment condition ('frontend', 'magic-area', 'hide').
	 * @return void
	 */
	public function set_render_condition( string $app_name, string $env = 'frontend' ): void {
		$this->render_conditions[ $app_name ] = $env;
	}

	/**
	 * Get all render conditions.
	 *
	 * @return array<string, string>
	 */
	public function get_render_conditions(): array {
		return $this->render_conditions;
	}

	/**
	 * Check if an application should be rendered.
	 *
	 * @param string $app_name Application name.
	 * @return bool
	 */
	public function should_render( string $app_name ): bool {
		if ( ! isset( $this->render_conditions[ $app_name ] ) ) {
			return false;
		}

		return $this->check_render_condition( $this->render_conditions[ $app_name ] );
	}

	/**
	 * Check if a condition is met for rendering.
	 *
	 * @param string $env Environment condition.
	 * @return bool
	 */
	private function check_render_condition( string $env ): bool {
		switch ( $env ) {
			case 'frontend':
				return ! is_admin();
			case 'magic-area':
				return is_front_page() && isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] && current_user_can( 'edit_posts' );
			case 'hide':
				return false;
			default:
				return false;
		}
	}

	/**
	 * Render application mount points.
	 *
	 * @return void
	 */
	public function render_app_divs(): void {
		foreach ( $this->render_conditions as $app_name => $condition ) {
			if ( $this->check_render_condition( $condition ) ) {
				$this->render_app_div( $app_name );
			}
		}
	}

	/**
	 * Render single application mount point.
	 *
	 * @param string $app_name Application name.
	 * @return void
	 */
	private function render_app_div( string $app_name ): void {
		printf(
			'<div id="etch-%s"></div>',
			esc_attr( $app_name )
		);
	}

	/**
	 * Get applications that should be rendered.
	 *
	 * @return array<string>
	 */
	public function get_apps_to_render(): array {
		$apps_to_render = array();
		foreach ( $this->render_conditions as $app_name => $condition ) {
			if ( $this->check_render_condition( $condition ) ) {
				$apps_to_render[] = $app_name;
			}
		}
		return $apps_to_render;
	}
}
