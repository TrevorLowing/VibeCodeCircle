<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\CleanupService;
use VibeCode\Deploy\Services\EnvService;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_vibecode_deploy_purge_uploads', array( __CLASS__, 'purge_uploads' ) );
		add_action( 'admin_post_vibecode_deploy_detach_pages', array( __CLASS__, 'detach_pages' ) );
		add_action( 'admin_post_vibecode_deploy_purge_both', array( __CLASS__, 'purge_both' ) );
		add_action( 'admin_post_vibecode_deploy_nuclear_delete_pages', array( __CLASS__, 'nuclear_delete_pages' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			'Vibe Code Deploy',
			'Vibe Code Deploy',
			'manage_options',
			'vibecode-deploy',
			array( __CLASS__, 'render' ),
			'dashicons-admin-generic'
		);

		add_submenu_page(
			'vibecode-deploy',
			'Configuration',
			'Configuration',
			'manage_options',
			'vibecode-deploy',
			array( __CLASS__, 'render' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'vibecode_deploy',
			Settings::OPTION_NAME,
			array(
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'type'              => 'array',
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section( 'vibecode_deploy_main', '', '__return_false', 'vibecode_deploy' );

		add_settings_field(
			'vibecode_deploy_project_slug',
			'Project Slug',
			array( __CLASS__, 'field_project_slug' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_class_prefix',
			'Class Prefix',
			array( __CLASS__, 'field_class_prefix' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_staging_dir',
			'Staging Folder',
			array( __CLASS__, 'field_staging_dir' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_missing_required',
			'Placeholder Strict Mode (Required)',
			array( __CLASS__, 'field_on_missing_required' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_missing_recommended',
			'Placeholder Strict Mode (Recommended)',
			array( __CLASS__, 'field_on_missing_recommended' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_unknown_placeholder',
			'Placeholder Strict Mode (Unknown)',
			array( __CLASS__, 'field_on_unknown_placeholder' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>Vibe Code Deploy</h1>';
		EnvService::render_admin_notice();
		settings_errors( Settings::OPTION_NAME );

		$action = isset( $_GET['vibecode_deploy_action'] ) ? sanitize_text_field( (string) $_GET['vibecode_deploy_action'] ) : '';
		$result = isset( $_GET['vibecode_deploy_result'] ) ? sanitize_text_field( (string) $_GET['vibecode_deploy_result'] ) : '';
		$count = isset( $_GET['vibecode_deploy_count'] ) ? (int) $_GET['vibecode_deploy_count'] : 0;
		if ( $action !== '' && $result !== '' ) {
			if ( $result === 'ok' ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $action ) . ' complete.';
				if ( $count > 0 ) {
					echo ' Affected items: <strong>' . esc_html( (string) $count ) . '</strong>.';
				}
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $action ) . ' failed. Check Vibe Code Deploy → Logs.</p></div>';
			}
		}

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Configuration</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'vibecode_deploy' );
		do_settings_sections( 'vibecode_deploy' );
		submit_button();
		echo '</form>';
		echo '</div>';

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Danger Zone</h2>';
		echo '<p class="description">These actions are destructive and cannot be undone.</p>';

		echo '<h3>Delete uploads (staging builds + logs)</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_purge_uploads" />';
		wp_nonce_field( 'vibecode_deploy_purge_uploads', 'vibecode_deploy_purge_nonce' );
		echo '<p><input type="submit" class="button" value="Purge Vibe Code Deploy Uploads" onclick="return confirm(\'Delete all Vibe Code Deploy uploads (staging builds + logs)?\');" /></p>';
		echo '</form>';

		echo '<h3>Detach pages (stop loading Vibe Code Deploy assets)</h3>';
		if ( $project_slug === '' ) {
			echo '<p><strong>Project Slug is required</strong> to detach or delete pages.</p>';
		} else {
			echo '<p class="description">This removes Vibe Code Deploy meta from pages owned by <code>' . esc_html( $project_slug ) . '</code>. Pages remain, but Vibe Code Deploy will stop enqueuing their build CSS/JS.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_detach_pages" />';
			wp_nonce_field( 'vibecode_deploy_detach_pages', 'vibecode_deploy_detach_nonce' );
			echo '<p><input type="submit" class="button" value="Detach Vibe Code Deploy Pages" onclick="return confirm(\'Detach Vibe Code Deploy pages for this project?\');" /></p>';
			echo '</form>';
		}

		echo '<h3>Purge uploads + detach pages</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_purge_both" />';
		wp_nonce_field( 'vibecode_deploy_purge_both', 'vibecode_deploy_purge_both_nonce' );
		echo '<p><input type="submit" class="button" value="Purge Uploads + Detach Pages" onclick="return confirm(\'Purge uploads and detach pages?\');" /></p>';
		echo '</form>';

		echo '<h3>Nuclear: delete Vibe Code Deploy-owned pages (project)</h3>';
		if ( $project_slug !== '' ) {
			echo '<p class="description">Deletes all pages owned by <code>' . esc_html( $project_slug ) . '</code>. This cannot be undone.</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_nuclear_delete_pages" />';
			wp_nonce_field( 'vibecode_deploy_nuclear_delete_pages', 'vibecode_deploy_nuclear_nonce' );
			echo '<p><label>Type <code>DELETE VIBECODE DEPLOY PAGES</code> to confirm<br /><input type="text" class="regular-text" name="vibecode_deploy_confirm" value="" /></label></p>';
			echo '<p><input type="submit" class="button" value="Delete Vibe Code Deploy Pages" onclick="return confirm(\'Final confirmation: delete all Vibe Code Deploy pages for this project?\');" /></p>';
			echo '</form>';
		}
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">Vibe Code Deploy Disclaimer</h2>';
		echo '<p>Vibe Code Deploy is a separate plugin that integrates with other plugins and themes. Other plugins and themes are owned and licensed by their respective authors.</p>';
		echo '<p>Vibe Code Deploy does not bundle other plugin or theme source code. If you install/activate other plugins or use other theme assets/templates, you are responsible for ensuring your use complies with their license and terms.</p>';
		echo '<p>Vibe Code Deploy does not bundle Etch source code. If you install/activate Etch or use Etch theme assets/templates, you are responsible for ensuring your use complies with Etch\'s license and terms.</p>';
		echo '</div>';
		echo '</div>';
	}

	private static function redirect_result( string $action, bool $ok, int $count = 0 ): void {
		$url = add_query_arg(
			array(
				'page' => 'vibecode-deploy',
				'vibecode_deploy_action' => $action,
				'vibecode_deploy_result' => $ok ? 'ok' : 'failed',
				'vibecode_deploy_count' => (string) (int) $count,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public static function purge_uploads(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}
		check_admin_referer( 'vibecode_deploy_purge_uploads', 'vibecode_deploy_purge_nonce' );

		$ok = CleanupService::purge_uploads_root();
		CleanupService::delete_all_active_build_options();
		if ( ! $ok ) {
			Logger::error( 'Purge uploads failed.', array(), '' );
			self::redirect_result( 'Purge uploads', false );
		}

		Logger::info( 'Purge uploads complete.', array(), '' );
		self::redirect_result( 'Purge uploads', true );
	}

	public static function detach_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}
		check_admin_referer( 'vibecode_deploy_detach_pages', 'vibecode_deploy_detach_nonce' );

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		if ( $project_slug === '' ) {
			self::redirect_result( 'Detach pages', false );
		}

		$count = CleanupService::detach_pages_for_project( $project_slug );
		Logger::info( 'Detach pages complete.', array( 'project_slug' => $project_slug, 'count' => $count ), $project_slug );
		self::redirect_result( 'Detach pages', true, $count );
	}

	public static function purge_both(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}
		check_admin_referer( 'vibecode_deploy_purge_both', 'vibecode_deploy_purge_both_nonce' );

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		$ok = CleanupService::purge_uploads_root();
		CleanupService::delete_all_active_build_options();
		if ( ! $ok ) {
			Logger::error( 'Purge both failed: uploads purge failed.', array( 'project_slug' => $project_slug ), $project_slug );
			self::redirect_result( 'Purge uploads + detach pages', false );
		}

		$count = 0;
		if ( $project_slug !== '' ) {
			$count = CleanupService::detach_pages_for_project( $project_slug );
		}

		Logger::info( 'Purge both complete.', array( 'project_slug' => $project_slug, 'detached_pages' => $count ), $project_slug );
		self::redirect_result( 'Purge uploads + detach pages', true, $count );
	}

	public static function nuclear_delete_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}
		check_admin_referer( 'vibecode_deploy_nuclear_delete_pages', 'vibecode_deploy_nuclear_nonce' );

		$confirm = isset( $_POST['vibecode_deploy_confirm'] ) ? sanitize_text_field( (string) $_POST['vibecode_deploy_confirm'] ) : '';
		if ( $confirm !== 'DELETE VIBECODE DEPLOY PAGES' ) {
			self::redirect_result( 'Delete Vibe Code Deploy pages', false );
		}

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		if ( $project_slug === '' ) {
			self::redirect_result( 'Delete Vibe Code Deploy pages', false );
		}

		$count = CleanupService::delete_pages_for_project( $project_slug );
		Logger::info( 'Nuclear delete pages complete.', array( 'project_slug' => $project_slug, 'count' => $count ), $project_slug );
		self::redirect_result( 'Delete Vibe Code Deploy pages', true, $count );
	}

	public static function field_project_slug(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['project_slug'] );

		echo '<input type="text" class="regular-text" name="' . $name . '[project_slug]" value="' . $val . '" />';
		echo '<p class="description">Used to identify this project for imports, manifests, and rules packs.</p>';
	}

	public static function field_class_prefix(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['class_prefix'] );

		echo '<input type="text" class="regular-text" name="' . $name . '[class_prefix]" value="' . $val . '" />';
		echo '<p class="description">Must match ^[a-z0-9-]+-$ (lowercase, trailing dash required).</p>';
	}

	public static function field_staging_dir(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['staging_dir'] );

		echo '<input type="text" class="regular-text" name="' . $name . '[staging_dir]" value="' . $val . '" />';
		echo '<p class="description">Local deploy input folder name (default: vibecode-deploy-staging).</p>';
	}

	private static function render_mode_select( string $field_key, string $description ): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$current = isset( $opts[ $field_key ] ) && is_string( $opts[ $field_key ] ) ? (string) $opts[ $field_key ] : 'warn';
		$current = ( $current === 'fail' ) ? 'fail' : 'warn';
		$field = esc_attr( $field_key );

		echo '<select name="' . $name . '[' . $field . ']">';
		echo '<option value="warn"' . selected( $current, 'warn', false ) . '>Warn (default)</option>';
		echo '<option value="fail"' . selected( $current, 'fail', false ) . '>Fail deploy</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}

	public static function field_on_missing_required(): void {
		self::render_mode_select( 'on_missing_required', 'When a page is missing a required CFA_SHORTCODE placeholder (as defined in vibecode-deploy-shortcodes.json).' );
	}

	public static function field_on_missing_recommended(): void {
		self::render_mode_select( 'on_missing_recommended', 'When a page is missing a recommended CFA_SHORTCODE placeholder (as defined in vibecode-deploy-shortcodes.json).' );
	}

	public static function field_on_unknown_placeholder(): void {
		self::render_mode_select( 'on_unknown_placeholder', 'When an invalid/unparseable CFA_SHORTCODE placeholder is encountered in HTML.' );
	}
}
