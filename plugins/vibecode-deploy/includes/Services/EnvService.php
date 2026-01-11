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
			$warnings[] = __( 'Block templates are not supported by the active theme. Template parts/templates (header/footer/404) will be skipped.', 'vibecode-deploy' );
		}

		$etch_installed = false;
		$etch_plugin_file = 'etch/etch.php';
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$etch_installed = is_file( WP_PLUGIN_DIR . '/etch/etch.php' );
		}

		$etch_active = self::is_plugin_active_by_file( $etch_plugin_file );
		if ( ! $etch_installed ) {
			$warnings[] = __( 'Etch plugin is not installed. Install/activate Etch before deploying.', 'vibecode-deploy' );
		} elseif ( ! $etch_active ) {
			$warnings[] = __( 'Etch plugin is installed but not active. Activate Etch before deploying.', 'vibecode-deploy' );
		}

		$etch_theme_dir = '';
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$etch_theme_dir = WP_CONTENT_DIR . '/themes/etch-theme';
		}
		$etch_theme_installed = $etch_theme_dir !== '' ? is_dir( $etch_theme_dir ) : false;
		if ( ! $etch_theme_installed ) {
			$warnings[] = __( 'Etch theme (etch-theme) is not installed. Install the Etch theme before deploying.', 'vibecode-deploy' );
		}

		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$template = $theme && method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';
		$stylesheet = $theme && method_exists( $theme, 'get_stylesheet' ) ? (string) $theme->get_stylesheet() : '';
		
		// Check if active theme is etch-theme or a child theme of it
		$is_etch_theme = false;
		if ( $template === 'etch-theme' ) {
			$is_etch_theme = true;
		}
		
		if ( ! $is_etch_theme ) {
			/* translators: %s: Theme template name */
			$warnings[] = sprintf( __( 'Active theme is not etch-theme or a child theme. Current template: %s. Styling may not work correctly.', 'vibecode-deploy' ), $template );
		}

		// Check for ACF if ACF JSON files are present in active build
		$settings = \VibeCode\Deploy\Settings::get_all();
		$project_slug = isset( $settings['project_slug'] ) && $settings['project_slug'] !== '' ? (string) $settings['project_slug'] : '';
		if ( $project_slug !== '' ) {
			$active_fingerprint = \VibeCode\Deploy\Services\BuildService::get_active_fingerprint( $project_slug );
			if ( $active_fingerprint !== '' ) {
				$build_root = \VibeCode\Deploy\Services\BuildService::build_root_path( $project_slug, $active_fingerprint );
				$acf_json_dir = $build_root . '/theme/acf-json';
				$has_acf_json = is_dir( $acf_json_dir ) && ! empty( glob( $acf_json_dir . '/*.json' ) );
				
				if ( $has_acf_json ) {
					// ACF JSON files found - check if ACF is installed/active
					$acf_installed = false;
					$acf_plugin_files = array( 'advanced-custom-fields/acf.php', 'advanced-custom-fields-pro/acf.php' );
					if ( defined( 'WP_PLUGIN_DIR' ) ) {
						foreach ( $acf_plugin_files as $acf_file ) {
							if ( is_file( WP_PLUGIN_DIR . '/' . $acf_file ) ) {
								$acf_installed = true;
								break;
							}
						}
					}
					
					$acf_active = false;
					foreach ( $acf_plugin_files as $acf_file ) {
						if ( self::is_plugin_active_by_file( $acf_file ) ) {
							$acf_active = true;
							break;
						}
					}
					
					if ( ! $acf_installed ) {
						$warnings[] = __( 'Advanced Custom Fields (ACF) plugin is not installed. ACF JSON files detected in staging - install ACF for custom fields to work.', 'vibecode-deploy' );
					} elseif ( ! $acf_active ) {
						$warnings[] = __( 'Advanced Custom Fields (ACF) plugin is installed but not active. Activate ACF for custom fields to work.', 'vibecode-deploy' );
					}
				}
			}
		}

		return $warnings;
	}
	
	public static function get_critical_errors(): array {
		$errors = array();
		
		$etch_theme_dir = '';
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$etch_theme_dir = WP_CONTENT_DIR . '/themes/etch-theme';
		}
		$etch_theme_installed = $etch_theme_dir !== '' ? is_dir( $etch_theme_dir ) : false;
		if ( ! $etch_theme_installed ) {
			$errors[] = __( 'Etch theme (etch-theme) is required but not installed.', 'vibecode-deploy' );
		}
		
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$template = $theme && method_exists( $theme, 'get_template' ) ? (string) $theme->get_template() : '';
		
		if ( $template !== 'etch-theme' ) {
			/* translators: %s: Theme template name */
			$errors[] = sprintf( __( 'Active theme must be etch-theme or a child theme. Current: %s', 'vibecode-deploy' ), $template );
		}
		
		return $errors;
	}

	public static function render_admin_notice(): void {
		if ( ! is_admin() ) {
			return;
		}

		$warnings = self::get_environment_warnings();
		if ( empty( $warnings ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Vibe Code Deploy environment check:', 'vibecode-deploy' ) . '</strong></p>';
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
