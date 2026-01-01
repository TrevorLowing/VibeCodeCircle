<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

final class EnvService {
	private static function is_plugin_active_by_file( string $plugin_file ): bool {
		$plugin_file = ltrim( trim( $plugin_file ), '/\\' );
		if ( $plugin_file === '' ) {
			return false;
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			return (bool) is_plugin_active( $plugin_file );
		}

		$active = get_option( 'active_plugins', array() );
		if ( is_array( $active ) && in_array( $plugin_file, $active, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$sitewide = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $sitewide ) && isset( $sitewide[ $plugin_file ] ) ) {
				return true;
			}
		}

		return false;
	}

	public static function get_environment_warnings(): array {
		$warnings = array();

		$block_templates_supported = post_type_exists( 'wp_template' ) && post_type_exists( 'wp_template_part' );
		if ( ! $block_templates_supported ) {
			$warnings[] = 'Block templates are not supported by the active theme. Template parts/templates (header/footer/404) will be skipped.';
		}

		$etch_installed = false;
		$etch_plugin_file = 'etch/etch.php';
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$etch_installed = is_file( WP_PLUGIN_DIR . '/etch/etch.php' );
		}

		$etch_active = self::is_plugin_active_by_file( $etch_plugin_file );
		if ( ! $etch_installed ) {
			$warnings[] = 'Etch plugin is not installed. Install/activate Etch before deploying.';
		} elseif ( ! $etch_active ) {
			$warnings[] = 'Etch plugin is installed but not active. Activate Etch before deploying.';
		}

		$etch_theme_dir = '';
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$etch_theme_dir = WP_CONTENT_DIR . '/themes/etch-theme';
		}
		$etch_theme_installed = $etch_theme_dir !== '' ? is_dir( $etch_theme_dir ) : false;
		if ( ! $etch_theme_installed ) {
			$warnings[] = 'Etch theme (etch-theme) is not installed. Install the Etch theme before deploying.';
		}

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$template = $theme && method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';
		if ( $template !== '' && $template !== 'etch-theme' ) {
			$warnings[] = 'Active theme is not based on etch-theme. Current template: ' . $template . '.';
		}

		return $warnings;
	}

	public static function render_admin_notice(): void {
		if ( ! is_admin() ) {
			return;
		}

		$warnings = self::get_environment_warnings();
		if ( empty( $warnings ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>Vibe Code Deploy environment check:</strong></p>';
		echo '<ul style="list-style: disc; padding-left: 22px;">';
		foreach ( $warnings as $w ) {
			if ( ! is_string( $w ) || $w === '' ) {
				continue;
			}
			echo '<li>' . esc_html( $w ) . '</li>';
		}
		echo '</ul></div>';
	}
}
