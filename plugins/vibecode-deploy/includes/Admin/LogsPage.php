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
			__( 'Logs', 'vibecode-deploy' ),
			__( 'Logs', 'vibecode-deploy' ),
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
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();

		if ( $cleared ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Log cleared.', 'vibecode-deploy' ) . '</p></div>';
		} elseif ( $missing ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'No log file found.', 'vibecode-deploy' ) . '</p></div>';
		}

		if ( $project_slug === '' ) {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<p><strong>' . esc_html__( 'Project Slug is required.', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'Set it in', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Configuration', 'vibecode-deploy' ) . '.</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		$download_url = wp_nonce_url( admin_url( 'admin-post.php?action=vibecode_deploy_download_log' ), 'vibecode_deploy_download_log' );
		$clear_url = wp_nonce_url( admin_url( 'admin-post.php?action=vibecode_deploy_clear_log' ), 'vibecode_deploy_clear_log' );

		echo '<div class="card">';
		echo '<h2 class="title">' . esc_html__( 'Log', 'vibecode-deploy' ) . '</h2>';
		/* translators: %s: Project slug */
		echo '<p>' . sprintf( esc_html__( 'Project: %s', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
		echo '<p>';
		$clear_confirm = esc_js( __( 'Clear the Vibe Code Deploy log?', 'vibecode-deploy' ) );
		echo '<a class="button" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download Log', 'vibecode-deploy' ) . '</a> ';
		echo '<button type="button" class="button" id="vibecode-deploy-copy-log">' . esc_html__( 'Copy Log', 'vibecode-deploy' ) . '</button> ';
		echo '<a class="button" href="' . esc_url( $clear_url ) . '" onclick="return confirm(\'' . $clear_confirm . '\');">' . esc_html__( 'Clear Log', 'vibecode-deploy' ) . '</a>';
		echo '</p>';

		echo '<textarea class="large-text code" readonly rows="22" id="vibecode-deploy-log-textarea">';
		echo esc_textarea( $log !== '' ? $log : '' );
		echo '</textarea>';
		echo '</div>';
		echo '<script>
		(function() {
			var copyBtn = document.getElementById("vibecode-deploy-copy-log");
			var textarea = document.getElementById("vibecode-deploy-log-textarea");
			
			if (copyBtn && textarea) {
				copyBtn.addEventListener("click", function() {
					textarea.select();
					textarea.setSelectionRange(0, 99999); // For mobile devices
					
					try {
						var successful = document.execCommand("copy");
						if (successful) {
							var originalText = copyBtn.textContent;
							copyBtn.textContent = "' . esc_js( __( 'Copied!', 'vibecode-deploy' ) ) . '";
							setTimeout(function() {
								copyBtn.textContent = originalText;
							}, 2000);
						} else {
							alert("' . esc_js( __( 'Failed to copy log. Please select and copy manually.', 'vibecode-deploy' ) ) . '");
						}
					} catch (err) {
						// Fallback for browsers that don\'t support execCommand
						try {
							navigator.clipboard.writeText(textarea.value).then(function() {
								var originalText = copyBtn.textContent;
								copyBtn.textContent = "' . esc_js( __( 'Copied!', 'vibecode-deploy' ) ) . '";
								setTimeout(function() {
									copyBtn.textContent = originalText;
								}, 2000);
							}).catch(function() {
								alert("' . esc_js( __( 'Failed to copy log. Please select and copy manually.', 'vibecode-deploy' ) ) . '");
							});
						} catch (e) {
							alert("' . esc_js( __( 'Failed to copy log. Please select and copy manually.', 'vibecode-deploy' ) ) . '");
						}
					}
				});
			}
		})();
		</script>';

		echo '</div>';
	}

	public static function download_log(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
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
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
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
