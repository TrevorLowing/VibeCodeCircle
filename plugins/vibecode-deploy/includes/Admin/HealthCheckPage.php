<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Services\DeploymentValidator;
use VibeCode\Deploy\Services\EnvService;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Health Check Page
 *
 * Admin page for troubleshooting and diagnostics.
 *
 * @package VibeCode\Deploy\Admin
 */
final class HealthCheckPage {
	/**
	 * Initialize the admin page.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register admin menu page.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			__( 'Health Check', 'vibecode-deploy' ),
			__( 'Health Check', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-health-check',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$project_slug = isset( $settings['project_slug'] ) ? (string) $settings['project_slug'] : '';
		$active_fingerprint = BuildService::active_fingerprint( $project_slug );

		$validation_results = null;
		if ( $project_slug !== '' && $active_fingerprint !== '' ) {
			// Get CPTs from theme functions.php
			$cpts = self::detect_cpts();
			$assets = self::detect_assets( $project_slug, $active_fingerprint );
			$pages = self::detect_pages();

			$validation_results = DeploymentValidator::validate_deployment(
				$project_slug,
				$active_fingerprint,
				array(
					'cpts' => $cpts,
					'assets' => $assets,
					'pages' => $pages,
				)
			);
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'System Status', 'vibecode-deploy' ) . '</h2>';

		// Project configuration
		echo '<h3>' . esc_html__( 'Project Configuration', 'vibecode-deploy' ) . '</h3>';
		echo '<table class="widefat striped">';
		echo '<tbody>';
		echo '<tr><th style="width:220px;">' . esc_html__( 'Project Slug', 'vibecode-deploy' ) . '</th><td>' . ( $project_slug !== '' ? esc_html( $project_slug ) : '<span style="color: #d63638;">Not set</span>' ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Active Build', 'vibecode-deploy' ) . '</th><td>' . ( $active_fingerprint !== '' ? esc_html( $active_fingerprint ) : '<span style="color: #d63638;">No active build</span>' ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		if ( $validation_results !== null ) {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<h2 class="title">' . esc_html__( 'Deployment Validation', 'vibecode-deploy' ) . '</h2>';

			if ( $validation_results['ok'] ) {
				echo '<div class="notice notice-success inline"><p>' . esc_html__( 'All checks passed!', 'vibecode-deploy' ) . '</p></div>';
			} else {
				echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Some checks failed. See details below.', 'vibecode-deploy' ) . '</p></div>';
			}

			if ( ! empty( $validation_results['errors'] ) ) {
				echo '<h3 style="color: #d63638;">' . esc_html__( 'Errors', 'vibecode-deploy' ) . '</h3>';
				echo '<table class="widefat striped">';
				echo '<thead><tr><th>' . esc_html__( 'Issue', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Fix', 'vibecode-deploy' ) . '</th></tr></thead>';
				echo '<tbody>';
				foreach ( $validation_results['errors'] as $error ) {
					echo '<tr>';
					echo '<td>' . esc_html( $error['message'] ?? '' ) . '</td>';
					echo '<td>' . esc_html( $error['fix'] ?? '' ) . '</td>';
					echo '</tr>';
				}
				echo '</tbody>';
				echo '</table>';
			}

			if ( ! empty( $validation_results['warnings'] ) ) {
				echo '<h3 style="color: #dba617;">' . esc_html__( 'Warnings', 'vibecode-deploy' ) . '</h3>';
				echo '<table class="widefat striped">';
				echo '<thead><tr><th>' . esc_html__( 'Issue', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Fix', 'vibecode-deploy' ) . '</th></tr></thead>';
				echo '<tbody>';
				foreach ( $validation_results['warnings'] as $warning ) {
					echo '<tr>';
					echo '<td>' . esc_html( $warning['message'] ?? '' ) . '</td>';
					echo '<td>' . esc_html( $warning['fix'] ?? '' ) . '</td>';
					echo '</tr>';
				}
				echo '</tbody>';
				echo '</table>';
			}

			if ( ! empty( $validation_results['checks'] ) ) {
				$passed_checks = array_filter( $validation_results['checks'], fn( $check ) => $check['ok'] ?? false );
				if ( ! empty( $passed_checks ) ) {
					echo '<h3 style="color: #00a32a;">' . esc_html__( 'Passed Checks', 'vibecode-deploy' ) . '</h3>';
					echo '<ul style="list-style: disc; padding-left: 22px;">';
					foreach ( $passed_checks as $check ) {
						echo '<li>' . esc_html( $check['message'] ?? '' ) . '</li>';
					}
					echo '</ul>';
				}
			}

			echo '</div>';
		} else {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<p>' . esc_html__( 'No active deployment found. Deploy a build first.', 'vibecode-deploy' ) . '</p>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Detect registered CPTs from theme functions.php.
	 *
	 * @return array List of CPT slugs.
	 */
	private static function detect_cpts(): array {
		$cpts = array();
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $post_types as $post_type ) {
			// Skip built-in post types
			if ( in_array( $post_type, array( 'post', 'page', 'attachment' ), true ) ) {
				continue;
			}
			$cpts[] = $post_type;
		}
		return $cpts;
	}

	/**
	 * Detect assets from active build.
	 *
	 * @param string $project_slug Project slug.
	 * @param string $fingerprint Build fingerprint.
	 * @return array List of asset paths.
	 */
	private static function detect_assets( string $project_slug, string $fingerprint ): array {
		$assets = array();
		$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
		if ( ! is_dir( $build_root ) ) {
			return $assets;
		}

		// Check for CSS files
		$css_dir = $build_root . DIRECTORY_SEPARATOR . 'css';
		if ( is_dir( $css_dir ) ) {
			$css_files = glob( $css_dir . DIRECTORY_SEPARATOR . '*.css' );
			foreach ( $css_files as $file ) {
				$relative = str_replace( $build_root . DIRECTORY_SEPARATOR, '', $file );
				$assets[] = str_replace( '\\', '/', $relative );
			}
		}

		// Check for JS files
		$js_dir = $build_root . DIRECTORY_SEPARATOR . 'js';
		if ( is_dir( $js_dir ) ) {
			$js_files = glob( $js_dir . DIRECTORY_SEPARATOR . '*.js' );
			foreach ( $js_files as $file ) {
				$relative = str_replace( $build_root . DIRECTORY_SEPARATOR, '', $file );
				$assets[] = str_replace( '\\', '/', $relative );
			}
		}

		// Check for assets directory (new format)
		$assets_dir = $build_root . DIRECTORY_SEPARATOR . 'assets';
		if ( is_dir( $assets_dir ) ) {
			$css_dir_new = $assets_dir . DIRECTORY_SEPARATOR . 'css';
			if ( is_dir( $css_dir_new ) ) {
				$css_files = glob( $css_dir_new . DIRECTORY_SEPARATOR . '*.css' );
				foreach ( $css_files as $file ) {
					$relative = str_replace( $build_root . DIRECTORY_SEPARATOR, '', $file );
					$assets[] = str_replace( '\\', '/', $relative );
				}
			}
			$js_dir_new = $assets_dir . DIRECTORY_SEPARATOR . 'js';
			if ( is_dir( $js_dir_new ) ) {
				$js_files = glob( $js_dir_new . DIRECTORY_SEPARATOR . '*.js' );
				foreach ( $js_files as $file ) {
					$relative = str_replace( $build_root . DIRECTORY_SEPARATOR, '', $file );
					$assets[] = str_replace( '\\', '/', $relative );
				}
			}
		}

		return $assets;
	}

	/**
	 * Detect pages from deployment.
	 *
	 * @return array List of page slugs.
	 */
	private static function detect_pages(): array {
		$pages = array();
		$wp_pages = get_pages( array( 'number' => 100 ) );
		foreach ( $wp_pages as $page ) {
			$pages[] = $page->post_name;
		}
		return $pages;
	}
}
