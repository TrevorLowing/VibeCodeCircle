<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\EnvService;
use VibeCode\Deploy\Services\RulesPackService;

defined( 'ABSPATH' ) || exit;

final class RulesPackPage {
	private static function read_text_file_for_preview( string $path, int $max_bytes = 262144 ): string {
		if ( $path === '' || ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$data = file_get_contents( $path, false, null, 0, $max_bytes );
		return is_string( $data ) ? $data : '';
	}

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_vibecode_deploy_download_rules_pack', array( __CLASS__, 'download_rules_pack' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			'Rules Pack',
			'Rules Pack',
			'manage_options',
			'vibecode-deploy-rules-pack',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$class_prefix = (string) $settings['class_prefix'];

		$download_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=vibecode_deploy_download_rules_pack' ),
			'vibecode_deploy_download_rules_pack'
		);
		$rules_md_path = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) . DIRECTORY_SEPARATOR . 'RULES.md' : '';
		$rules_md_content = self::read_text_file_for_preview( $rules_md_path );
		$readme_md_content = \VibeCode\Deploy\Services\RulesPackService::generate_readme_markdown();

		echo '<div class="wrap">';
		echo '<h1>Vibe Code Deploy Rules Pack</h1>';
		EnvService::render_admin_notice();

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<p>Download a rules pack for use in AI IDE tools (Cursor, Windsurf, VS Code extensions). This pack contains <code>RULES.md</code> plus a brief <code>README.md</code>.</p>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Detected files</h2>';
		echo '<table class="widefat striped">';
		echo '<tbody>';
		echo '<tr><th style="width:220px;">RULES.md</th><td>' . ( $rules_md_path !== '' && is_file( $rules_md_path ) ? '<code>' . esc_html( $rules_md_path ) . '</code>' : '<em>Not found</em>' ) . '</td></tr>';
		echo '</tbody>';
		echo '</table>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Pack contents</h2>';
		echo '<ul style="list-style: disc; padding-left: 22px;">';
		echo '<li><code>RULES.md</code> (project rules)</li>';
		echo '<li><code>README.md</code></li>';
		echo '</ul>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Current project settings</h2>';
		echo '<p>Project slug: <code>' . esc_html( $project_slug ) . '</code></p>';
		echo '<p>Class prefix: <code>' . esc_html( $class_prefix ) . '</code></p>';

		echo '<p><a class="button button-primary" href="' . esc_url( $download_url ) . '">Download Rules Pack (zip)</a></p>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Preview</h2>';
		echo '<p>Review the rules pack contents below before downloading.</p>';

		echo '<h3 style="margin-top: 18px;">RULES.md</h3>';
		if ( $rules_md_content === '' ) {
			echo '<div class="notice notice-warning"><p><strong>RULES.md not found or unreadable.</strong></p></div>';
		} else {
			echo '<pre style="max-height: 520px; overflow: auto; white-space: pre-wrap; border: 1px solid #c3c4c7; background: #fff; padding: 12px;">' . esc_html( $rules_md_content ) . '</pre>';
		}

		echo '<h3 style="margin-top: 18px;">README.md</h3>';
		echo '<pre style="max-height: 520px; overflow: auto; white-space: pre-wrap; border: 1px solid #c3c4c7; background: #fff; padding: 12px;">' . esc_html( $readme_md_content ) . '</pre>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Etch disclaimer</h2>';
		echo '<p>Vibe Code Deploy is a separate plugin that integrates with Etch (plugin) and etch-theme. Etch and etch-theme are owned and licensed by their respective authors.</p>';
		echo '<p>This rules pack does not include Etch source code. If your workflow uses Etch or Etch theme assets/templates, ensure your usage complies with Etch\'s license and terms.</p>';
		echo '</div>';

		echo '</div>';
	}

	public static function download_rules_pack(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}

		check_admin_referer( 'vibecode_deploy_download_rules_pack' );

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$class_prefix = (string) $settings['class_prefix'];

		$result = RulesPackService::build_rules_pack_zip( $project_slug, $class_prefix );
		if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-rules-pack' ) );
			exit;
		}

		$tmp = isset( $result['tmp_path'] ) && is_string( $result['tmp_path'] ) ? $result['tmp_path'] : '';
		$filename = isset( $result['filename'] ) && is_string( $result['filename'] ) ? $result['filename'] : 'vibecode-deploy-rules-pack.zip';
		if ( $tmp === '' || ! is_file( $tmp ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-rules-pack' ) );
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
