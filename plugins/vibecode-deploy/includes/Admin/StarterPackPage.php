<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Services\EnvService;
use VibeCode\Deploy\Services\StarterPackService;

defined( 'ABSPATH' ) || exit;

/**
 * Starter Pack Page
 *
 * Admin page for downloading project starter pack with build scripts.
 *
 * @package VibeCode\Deploy\Admin
 */
final class StarterPackPage {
	/**
	 * Initialize the admin page.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_vibecode_deploy_download_starter_pack', array( __CLASS__, 'download_starter_pack' ) );
	}

	/**
	 * Register admin menu page.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			__( 'Project Starter Pack', 'vibecode-deploy' ),
			__( 'Starter Pack', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-starter-pack',
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

		$download_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=vibecode_deploy_download_starter_pack' ),
			'vibecode_deploy_download_starter_pack'
		);

		$plugin_dir = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) : '';
		$starter_pack_dir = $plugin_dir !== '' ? $plugin_dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'starter-pack' : '';
		$readme_path = $starter_pack_dir !== '' ? $starter_pack_dir . DIRECTORY_SEPARATOR . 'README.md' : '';
		$readme_content = '';

		if ( $readme_path !== '' && is_file( $readme_path ) && is_readable( $readme_path ) ) {
			$readme_content = file_get_contents( $readme_path, false, null, 0, 262144 );
			$readme_content = is_string( $readme_content ) ? $readme_content : '';
		}

		// Check which files exist
		$files_status = array();
		if ( $starter_pack_dir !== '' && is_dir( $starter_pack_dir ) ) {
			$expected_files = array(
				'build-deployment-package.sh',
				'generate-manifest.php',
				'generate-functions-php.php',
				'.cursorrules.template',
				'README.md',
			);
			foreach ( $expected_files as $filename ) {
				$file_path = $starter_pack_dir . DIRECTORY_SEPARATOR . $filename;
				$files_status[ $filename ] = is_file( $file_path ) && is_readable( $file_path );
			}
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<p>' . esc_html__( 'Download a project starter pack containing build scripts and templates for creating deployment packages from your static HTML project.', 'vibecode-deploy' ) . '</p>';
		echo '<p>' . esc_html__( 'This pack includes build scripts, manifest generators, and example project structure to help you get started quickly.', 'vibecode-deploy' ) . '</p>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Pack Contents', 'vibecode-deploy' ) . '</h2>';
		echo '<ul style="list-style: disc; padding-left: 22px;">';
		echo '<li><code>build-deployment-package.sh</code> - Main build script</li>';
		echo '<li><code>generate-manifest.php</code> - Manifest generator</li>';
		echo '<li><code>generate-functions-php.php</code> - Functions.php generator</li>';
		echo '<li><code>README.md</code> - Setup and usage instructions</li>';
		echo '<li><code>.cursorrules.template</code> - Template for project rules</li>';
		echo '<li><code>example-structure/</code> - Example project structure</li>';
		echo '</ul>';
		echo '</div>';

		if ( ! empty( $files_status ) ) {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<h2 class="title">' . esc_html__( 'Detected Files', 'vibecode-deploy' ) . '</h2>';
			echo '<table class="widefat striped">';
			echo '<tbody>';
			foreach ( $files_status as $filename => $exists ) {
				echo '<tr><th style="width:220px;">' . esc_html( $filename ) . '</th><td>' . ( $exists ? '<span style="color: #00a32a;">✓ Found</span>' : '<span style="color: #d63638;">✗ Not found</span>' ) . '</td></tr>';
			}
			echo '</tbody>';
			echo '</table>';
			echo '</div>';
		}

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Download', 'vibecode-deploy' ) . '</h2>';
		echo '<p><a class="button button-primary" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download Starter Pack (zip)', 'vibecode-deploy' ) . '</a></p>';
		echo '</div>';

		if ( $readme_content !== '' ) {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<h2 class="title">' . esc_html__( 'Preview', 'vibecode-deploy' ) . '</h2>';
			echo '<p>' . esc_html__( 'Review the starter pack README below before downloading.', 'vibecode-deploy' ) . '</p>';
			echo '<h3 style="margin-top: 18px;">README.md</h3>';
			echo '<pre style="max-height: 520px; overflow: auto; white-space: pre-wrap; border: 1px solid #c3c4c7; background: #fff; padding: 12px;">' . esc_html( $readme_content ) . '</pre>';
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Handle starter pack download.
	 *
	 * @return void
	 */
	public static function download_starter_pack(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		check_admin_referer( 'vibecode_deploy_download_starter_pack' );

		$result = StarterPackService::build_starter_pack_zip();
		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-starter-pack' ) );
			exit;
		}

		$tmp = isset( $result['tmp_path'] ) && is_string( $result['tmp_path'] ) ? $result['tmp_path'] : '';
		$filename = isset( $result['filename'] ) && is_string( $result['filename'] ) ? $result['filename'] : 'vibecode-deploy-starter-pack.zip';
		if ( $tmp === '' || ! is_file( $tmp ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-starter-pack' ) );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . rawurlencode( $filename ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $tmp ) );

		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}
}
