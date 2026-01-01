<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\EnvService;

defined( 'ABSPATH' ) || exit;

final class LogsPage {
	private const MAX_DOWNLOAD_BYTES = 2097152;

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_vibecode_deploy_download_log', array( __CLASS__, 'download_log' ) );
		add_action( 'admin_post_vibecode_deploy_clear_log', array( __CLASS__, 'clear_log' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			'Logs',
			'Logs',
			'manage_options',
			'vibecode-deploy-logs',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$log = $project_slug !== '' ? Logger::tail( $project_slug ) : '';

		$cleared = ! empty( $_GET['cleared'] );
		$missing = ! empty( $_GET['missing'] );

		echo '<div class="wrap">';
		echo '<h1>Vibe Code Deploy Logs</h1>';
		EnvService::render_admin_notice();

		if ( $cleared ) {
			echo '<div class="notice notice-success"><p>Log cleared.</p></div>';
		} elseif ( $missing ) {
			echo '<div class="notice notice-warning"><p>No log file found.</p></div>';
		}

		if ( $project_slug === '' ) {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<p><strong>Project Slug is required.</strong> Set it in Vibe Code Deploy → Configuration.</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		$download_url = wp_nonce_url( admin_url( 'admin-post.php?action=vibecode_deploy_download_log' ), 'vibecode_deploy_download_log' );
		$clear_url = wp_nonce_url( admin_url( 'admin-post.php?action=vibecode_deploy_clear_log' ), 'vibecode_deploy_clear_log' );

		echo '<div class="card">';
		echo '<h2 class="title">Log</h2>';
		echo '<p>Project: <code>' . esc_html( $project_slug ) . '</code></p>';
		echo '<p>';
		echo '<a class="button" href="' . esc_url( $download_url ) . '">Download Log</a> ';
		echo '<a class="button" href="' . esc_url( $clear_url ) . '" onclick="return confirm(\'Clear the Vibe Code Deploy log?\');">Clear Log</a>';
		echo '</p>';

		echo '<textarea class="large-text code" readonly rows="22">';
		echo esc_textarea( $log !== '' ? $log : '' );
		echo '</textarea>';
		echo '</div>';

		echo '</div>';
	}

	public static function download_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}

		check_admin_referer( 'vibecode_deploy_download_log' );

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		if ( $project_slug === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-logs' ) );
			exit;
		}

		$log = Logger::tail( $project_slug, self::MAX_DOWNLOAD_BYTES );
		if ( $log === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-logs&missing=1' ) );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="vibecode-deploy-' . rawurlencode( $project_slug ) . '-log.txt"' );

		echo $log;
		exit;
	}

	public static function clear_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}

		check_admin_referer( 'vibecode_deploy_clear_log' );

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		if ( $project_slug !== '' ) {
			Logger::clear( $project_slug );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-logs&cleared=1' ) );
		exit;
	}
}
