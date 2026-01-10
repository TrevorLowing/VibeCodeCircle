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

		// Folder and file health checks
		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Folder & File Health Check', 'vibecode-deploy' ) . '</h2>';
		$folder_checks = self::check_folders_and_files( $project_slug );
		echo '<table class="widefat striped">';
		echo '<thead><tr><th style="width:300px;">' . esc_html__( 'Item', 'vibecode-deploy' ) . '</th><th style="width:100px;">' . esc_html__( 'Status', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Details', 'vibecode-deploy' ) . '</th></tr></thead>';
		echo '<tbody>';
		foreach ( $folder_checks as $check ) {
			$status_color = $check['status'] === 'ok' ? '#00a32a' : ( $check['status'] === 'warning' ? '#dba617' : '#d63638' );
			$status_text = $check['status'] === 'ok' ? esc_html__( 'OK', 'vibecode-deploy' ) : ( $check['status'] === 'warning' ? esc_html__( 'Warning', 'vibecode-deploy' ) : esc_html__( 'Error', 'vibecode-deploy' ) );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $check['label'] ) . '</strong></td>';
			echo '<td><span style="color: ' . esc_attr( $status_color ) . ';">' . esc_html( $status_text ) . '</span></td>';
			echo '<td>' . esc_html( $check['message'] ) . '</td>';
			echo '</tr>';
		}
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

	/**
	 * Check important folders and files for health.
	 *
	 * @param string $project_slug Project slug.
	 * @return array Array of check results with 'label', 'status', 'message' keys.
	 */
	private static function check_folders_and_files( string $project_slug ): array {
		$checks = array();

		// 1. Uploads directory
		$uploads = wp_upload_dir();
		$uploads_basedir = $uploads['basedir'];
		$uploads_writable = wp_is_writable( $uploads_basedir );
		$checks[] = array(
			'label' => __( 'Uploads Directory', 'vibecode-deploy' ),
			'status' => $uploads_writable ? 'ok' : 'error',
			'message' => $uploads_writable 
				? sprintf( __( 'Writable: %s', 'vibecode-deploy' ), esc_html( $uploads_basedir ) )
				: sprintf( __( 'Not writable: %s', 'vibecode-deploy' ), esc_html( $uploads_basedir ) ),
		);

		// 2. Staging directory (if project slug is set)
		if ( $project_slug !== '' ) {
			$staging_dir = BuildService::get_project_staging_dir( $project_slug );
			$staging_exists = is_dir( $staging_dir );
			$staging_writable = $staging_exists && wp_is_writable( $staging_dir );
			$checks[] = array(
				'label' => __( 'Staging Directory', 'vibecode-deploy' ),
				'status' => $staging_writable ? 'ok' : ( $staging_exists ? 'warning' : 'warning' ),
				'message' => $staging_writable
					? sprintf( __( 'Exists and writable: %s', 'vibecode-deploy' ), esc_html( $staging_dir ) )
					: ( $staging_exists
						? sprintf( __( 'Exists but not writable: %s', 'vibecode-deploy' ), esc_html( $staging_dir ) )
						: sprintf( __( 'Does not exist (will be created on upload): %s', 'vibecode-deploy' ), esc_html( $staging_dir ) ) ),
			);
		} else {
			$checks[] = array(
				'label' => __( 'Staging Directory', 'vibecode-deploy' ),
				'status' => 'warning',
				'message' => __( 'Cannot check: Project Slug not set', 'vibecode-deploy' ),
			);
		}

		// 3. Plugin assets directory
		$plugin_dir = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? VIBECODE_DEPLOY_PLUGIN_DIR : '';
		if ( $plugin_dir !== '' ) {
			$assets_dir = rtrim( $plugin_dir, '/\\' ) . DIRECTORY_SEPARATOR . 'assets';
			$assets_exists = is_dir( $assets_dir );
			$assets_writable = $assets_exists && wp_is_writable( $assets_dir );
			$checks[] = array(
				'label' => __( 'Plugin Assets Directory', 'vibecode-deploy' ),
				'status' => $assets_writable ? 'ok' : ( $assets_exists ? 'warning' : 'warning' ),
				'message' => $assets_writable
					? sprintf( __( 'Exists and writable: %s', 'vibecode-deploy' ), esc_html( $assets_dir ) )
					: ( $assets_exists
						? sprintf( __( 'Exists but not writable: %s', 'vibecode-deploy' ), esc_html( $assets_dir ) )
						: sprintf( __( 'Does not exist (will be created on deploy): %s', 'vibecode-deploy' ), esc_html( $assets_dir ) ) ),
			);
		}

		// 4. Plugin main file
		$plugin_file = defined( 'VIBECODE_DEPLOY_PLUGIN_FILE' ) ? VIBECODE_DEPLOY_PLUGIN_FILE : '';
		if ( $plugin_file !== '' ) {
			$plugin_exists = file_exists( $plugin_file );
			$checks[] = array(
				'label' => __( 'Plugin Main File', 'vibecode-deploy' ),
				'status' => $plugin_exists ? 'ok' : 'error',
				'message' => $plugin_exists
					? sprintf( __( 'Exists: %s', 'vibecode-deploy' ), esc_html( basename( $plugin_file ) ) )
					: sprintf( __( 'Missing: %s', 'vibecode-deploy' ), esc_html( $plugin_file ) ),
			);
		}

		// 5. Child theme directory
		$theme = wp_get_theme();
		$child_theme = $theme->get_stylesheet();
		$child_theme_dir = get_stylesheet_directory();
		$child_theme_exists = is_dir( $child_theme_dir );
		$child_theme_writable = $child_theme_exists && wp_is_writable( $child_theme_dir );
		$checks[] = array(
			'label' => __( 'Active Theme Directory', 'vibecode-deploy' ),
			'status' => $child_theme_writable ? 'ok' : ( $child_theme_exists ? 'warning' : 'error' ),
			'message' => $child_theme_writable
				? sprintf( __( 'Writable: %s (%s)', 'vibecode-deploy' ), esc_html( $child_theme_dir ), esc_html( $child_theme ) )
				: ( $child_theme_exists
					? sprintf( __( 'Exists but not writable: %s (%s)', 'vibecode-deploy' ), esc_html( $child_theme_dir ), esc_html( $child_theme ) )
					: sprintf( __( 'Missing: %s', 'vibecode-deploy' ), esc_html( $child_theme_dir ) ) ),
		);

		// 6. Theme functions.php
		$functions_file = $child_theme_dir . DIRECTORY_SEPARATOR . 'functions.php';
		$functions_exists = file_exists( $functions_file );
		$functions_writable = $functions_exists && wp_is_writable( $functions_file );
		$checks[] = array(
			'label' => __( 'Theme functions.php', 'vibecode-deploy' ),
			'status' => $functions_writable ? 'ok' : ( $functions_exists ? 'warning' : 'warning' ),
			'message' => $functions_writable
				? __( 'Exists and writable (smart merge enabled)', 'vibecode-deploy' )
				: ( $functions_exists
					? __( 'Exists but not writable (smart merge may fail)', 'vibecode-deploy' )
					: __( 'Does not exist (will be created on deploy)', 'vibecode-deploy' ) ),
		);

		// 7. ACF JSON directory
		$acf_json_dir = $child_theme_dir . DIRECTORY_SEPARATOR . 'acf-json';
		$acf_json_exists = is_dir( $acf_json_dir );
		$acf_json_writable = $acf_json_exists && wp_is_writable( $acf_json_dir );
		$checks[] = array(
			'label' => __( 'ACF JSON Directory', 'vibecode-deploy' ),
			'status' => $acf_json_writable ? 'ok' : ( $acf_json_exists ? 'warning' : 'warning' ),
			'message' => $acf_json_writable
				? sprintf( __( 'Exists and writable: %s', 'vibecode-deploy' ), esc_html( $acf_json_dir ) )
				: ( $acf_json_exists
					? sprintf( __( 'Exists but not writable: %s', 'vibecode-deploy' ), esc_html( $acf_json_dir ) )
					: __( 'Does not exist (will be created on deploy if ACF JSON files are present)', 'vibecode-deploy' ) ),
		);

		// 8. Required plugin includes
		$required_includes = array(
			'Bootstrap.php',
			'Settings.php',
			'Staging.php',
			'Importer.php',
		);
		$missing_includes = array();
		foreach ( $required_includes as $include ) {
			$include_path = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? VIBECODE_DEPLOY_PLUGIN_DIR . '/includes/' . $include : '';
			if ( $include_path !== '' && ! file_exists( $include_path ) ) {
				$missing_includes[] = $include;
			}
		}
		$checks[] = array(
			'label' => __( 'Required Plugin Files', 'vibecode-deploy' ),
			'status' => empty( $missing_includes ) ? 'ok' : 'error',
			'message' => empty( $missing_includes )
				? __( 'All required files present', 'vibecode-deploy' )
				: sprintf( __( 'Missing: %s', 'vibecode-deploy' ), esc_html( implode( ', ', $missing_includes ) ) ),
		);

		// 9. Active build directory (if project slug and fingerprint are set)
		if ( $project_slug !== '' && $active_fingerprint !== '' ) {
			$build_root = BuildService::build_root_path( $project_slug, $active_fingerprint );
			$build_exists = is_dir( $build_root );
			$pages_dir = $build_root . DIRECTORY_SEPARATOR . 'pages';
			$pages_exist = is_dir( $pages_dir );
			$checks[] = array(
				'label' => __( 'Active Build Directory', 'vibecode-deploy' ),
				'status' => $build_exists && $pages_exist ? 'ok' : 'error',
				'message' => $build_exists && $pages_exist
					? sprintf( __( 'Exists with pages: %s', 'vibecode-deploy' ), esc_html( $build_root ) )
					: sprintf( __( 'Missing or incomplete: %s', 'vibecode-deploy' ), esc_html( $build_root ) ),
			);
		}

		return $checks;
	}
}
