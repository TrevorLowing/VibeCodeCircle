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
			__( 'Vibe Code Deploy', 'vibecode-deploy' ),
			__( 'Vibe Code Deploy', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy',
			array( __CLASS__, 'render' ),
			'dashicons-admin-generic'
		);

		add_submenu_page(
			'vibecode-deploy',
			__( 'Configuration', 'vibecode-deploy' ),
			__( 'Configuration', 'vibecode-deploy' ),
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
			__( 'Project Slug', 'vibecode-deploy' ),
			array( __CLASS__, 'field_project_slug' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_class_prefix',
			__( 'Class Prefix', 'vibecode-deploy' ),
			array( __CLASS__, 'field_class_prefix' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_staging_dir',
			__( 'Staging Folder', 'vibecode-deploy' ),
			array( __CLASS__, 'field_staging_dir' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_placeholder_prefix',
			__( 'Placeholder Prefix', 'vibecode-deploy' ),
			array( __CLASS__, 'field_placeholder_prefix' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_env_errors_mode',
			__( 'Environment Errors Mode', 'vibecode-deploy' ),
			array( __CLASS__, 'field_env_errors_mode' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_missing_required',
			__( 'Placeholder Strict Mode (Required)', 'vibecode-deploy' ),
			array( __CLASS__, 'field_on_missing_required' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_missing_recommended',
			__( 'Placeholder Strict Mode (Recommended)', 'vibecode-deploy' ),
			array( __CLASS__, 'field_on_missing_recommended' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_unknown_placeholder',
			__( 'Placeholder Strict Mode (Unknown)', 'vibecode-deploy' ),
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
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();
		settings_errors( Settings::OPTION_NAME );

		$action = isset( $_GET['vibecode_deploy_action'] ) ? sanitize_text_field( (string) $_GET['vibecode_deploy_action'] ) : '';
		$result = isset( $_GET['vibecode_deploy_result'] ) ? sanitize_text_field( (string) $_GET['vibecode_deploy_result'] ) : '';
		$count = isset( $_GET['vibecode_deploy_count'] ) ? (int) $_GET['vibecode_deploy_count'] : 0;
		if ( $action !== '' && $result !== '' ) {
			if ( $result === 'ok' ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $action ) . ' ' . esc_html__( 'complete.', 'vibecode-deploy' );
				if ( $count > 0 ) {
					echo ' ' . esc_html__( 'Affected items:', 'vibecode-deploy' ) . ' <strong>' . esc_html( (string) $count ) . '</strong>.';
				}
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $action ) . ' ' . esc_html__( 'failed. Check', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Logs', 'vibecode-deploy' ) . '.</p></div>';
			}
		}

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Configuration', 'vibecode-deploy' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'vibecode_deploy' );
		do_settings_sections( 'vibecode_deploy' );
		submit_button();
		echo '</form>';
		echo '</div>';

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Danger Zone', 'vibecode-deploy' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These actions are destructive and cannot be undone.', 'vibecode-deploy' ) . '</p>';

		echo '<h3>' . esc_html__( 'Delete uploads (staging builds + logs)', 'vibecode-deploy' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_purge_uploads" />';
		wp_nonce_field( 'vibecode_deploy_purge_uploads', 'vibecode_deploy_purge_nonce' );
		$confirm_msg = esc_js( __( 'Delete all Vibe Code Deploy uploads (staging builds + logs)?', 'vibecode-deploy' ) );
		echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Purge Vibe Code Deploy Uploads', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $confirm_msg . '\');" /></p>';
		echo '</form>';

		echo '<h3>' . esc_html__( 'Detach pages (stop loading Vibe Code Deploy assets)', 'vibecode-deploy' ) . '</h3>';
		if ( $project_slug === '' ) {
			echo '<p><strong>' . esc_html__( 'Project Slug is required', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'to detach or delete pages.', 'vibecode-deploy' ) . '</p>';
		} else {
			/* translators: %s: Project slug */
			echo '<p class="description">' . sprintf( esc_html__( 'This removes Vibe Code Deploy meta from pages owned by %s. Pages remain, but Vibe Code Deploy will stop enqueuing their build CSS/JS.', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_detach_pages" />';
			wp_nonce_field( 'vibecode_deploy_detach_pages', 'vibecode_deploy_detach_nonce' );
			$detach_confirm = esc_js( __( 'Detach Vibe Code Deploy pages for this project?', 'vibecode-deploy' ) );
			echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Detach Vibe Code Deploy Pages', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $detach_confirm . '\');" /></p>';
			echo '</form>';
		}

		echo '<h3>' . esc_html__( 'Purge uploads + detach pages', 'vibecode-deploy' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_purge_both" />';
		wp_nonce_field( 'vibecode_deploy_purge_both', 'vibecode_deploy_purge_both_nonce' );
		$purge_confirm = esc_js( __( 'Purge uploads and detach pages?', 'vibecode-deploy' ) );
		echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Purge Uploads + Detach Pages', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $purge_confirm . '\');" /></p>';
		echo '</form>';

		echo '<h3>' . esc_html__( 'Nuclear: delete Vibe Code Deploy-owned pages (project)', 'vibecode-deploy' ) . '</h3>';
		if ( $project_slug !== '' ) {
			/* translators: %s: Project slug */
			echo '<p class="description">' . sprintf( esc_html__( 'Deletes all pages owned by %s. This cannot be undone.', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_nuclear_delete_pages" />';
			wp_nonce_field( 'vibecode_deploy_nuclear_delete_pages', 'vibecode_deploy_nuclear_nonce' );
			echo '<p><label>' . esc_html__( 'Type', 'vibecode-deploy' ) . ' <code>DELETE VIBECODE DEPLOY PAGES</code> ' . esc_html__( 'to confirm', 'vibecode-deploy' ) . '<br /><input type="text" class="regular-text" name="vibecode_deploy_confirm" value="" /></label></p>';
			$nuclear_confirm = esc_js( __( 'Final confirmation: delete all Vibe Code Deploy pages for this project?', 'vibecode-deploy' ) );
			echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Delete Vibe Code Deploy Pages', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $nuclear_confirm . '\');" /></p>';
			echo '</form>';
		}
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Vibe Code Deploy Disclaimer', 'vibecode-deploy' ) . '</h2>';
		echo '<p>' . esc_html__( 'Vibe Code Deploy is a separate plugin that integrates with other plugins and themes. Other plugins and themes are owned and licensed by their respective authors.', 'vibecode-deploy' ) . '</p>';
		echo '<p>' . esc_html__( 'Vibe Code Deploy does not bundle other plugin or theme source code. If you install/activate other plugins or use other theme assets/templates, you are responsible for ensuring your use complies with their license and terms.', 'vibecode-deploy' ) . '</p>';
		echo '<p>' . esc_html__( 'Vibe Code Deploy does not bundle Etch source code. If you install/activate Etch or use Etch theme assets/templates, you are responsible for ensuring your use complies with Etch\'s license and terms.', 'vibecode-deploy' ) . '</p>';
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
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_purge_uploads', 'vibecode_deploy_purge_nonce' );

		$ok = CleanupService::purge_uploads_root();
		CleanupService::delete_all_active_build_options();
		if ( ! $ok ) {
			Logger::error( 'Purge uploads failed.', array(), '' );
			self::redirect_result( __( 'Purge uploads', 'vibecode-deploy' ), false );
		}

		Logger::info( 'Purge uploads complete.', array(), '' );
		self::redirect_result( __( 'Purge uploads', 'vibecode-deploy' ), true );
	}

	public static function detach_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_detach_pages', 'vibecode_deploy_detach_nonce' );

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		if ( $project_slug === '' ) {
			self::redirect_result( __( 'Detach pages', 'vibecode-deploy' ), false );
		}

		$count = CleanupService::detach_pages_for_project( $project_slug );
		Logger::info( 'Detach pages complete.', array( 'project_slug' => $project_slug, 'count' => $count ), $project_slug );
		self::redirect_result( __( 'Detach pages', 'vibecode-deploy' ), true, $count );
	}

	public static function purge_both(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_purge_both', 'vibecode_deploy_purge_both_nonce' );

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		$ok = CleanupService::purge_uploads_root();
		CleanupService::delete_all_active_build_options();
		if ( ! $ok ) {
			Logger::error( 'Purge both failed: uploads purge failed.', array( 'project_slug' => $project_slug ), $project_slug );
			self::redirect_result( __( 'Purge uploads + detach pages', 'vibecode-deploy' ), false );
		}

		$count = 0;
		if ( $project_slug !== '' ) {
			$count = CleanupService::detach_pages_for_project( $project_slug );
		}

		Logger::info( 'Purge both complete.', array( 'project_slug' => $project_slug, 'detached_pages' => $count ), $project_slug );
		self::redirect_result( __( 'Purge uploads + detach pages', 'vibecode-deploy' ), true, $count );
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
			self::redirect_result( __( 'Delete Vibe Code Deploy pages', 'vibecode-deploy' ), false );
		}

		$count = CleanupService::delete_pages_for_project( $project_slug );
		Logger::info( 'Nuclear delete pages complete.', array( 'project_slug' => $project_slug, 'count' => $count ), $project_slug );
		self::redirect_result( __( 'Delete Vibe Code Deploy pages', 'vibecode-deploy' ), true, $count );
	}

	public static function field_project_slug(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['project_slug'] );

		echo '<input type="text" class="regular-text" name="' . $name . '[project_slug]" value="' . $val . '" />';
		echo '<p class="description">' . esc_html__( 'Used to identify this project for imports, manifests, and rules packs.', 'vibecode-deploy' ) . '</p>';
	}

	public static function field_class_prefix(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['class_prefix'] );

		echo '<input type="text" class="regular-text" name="' . $name . '[class_prefix]" value="' . $val . '" />';
		echo '<p class="description">' . esc_html__( 'Must match ^[a-z0-9-]+-$ (lowercase, trailing dash required).', 'vibecode-deploy' ) . '</p>';
	}

	public static function field_staging_dir(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['staging_dir'] );

		echo '<input type="text" class="regular-text" name="' . $name . '[staging_dir]" value="' . $val . '" />';
		echo '<p class="description">' . esc_html__( 'Local deploy input folder name (default: vibecode-deploy-staging).', 'vibecode-deploy' ) . '</p>';
	}

	private static function render_mode_select( string $field_key, string $description ): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$current = isset( $opts[ $field_key ] ) && is_string( $opts[ $field_key ] ) ? (string) $opts[ $field_key ] : 'warn';
		$current = ( $current === 'fail' ) ? 'fail' : 'warn';
		$field = esc_attr( $field_key );

		echo '<select name="' . $name . '[' . $field . ']">';
		echo '<option value="warn"' . selected( $current, 'warn', false ) . '>' . esc_html__( 'Warn (default)', 'vibecode-deploy' ) . '</option>';
		echo '<option value="fail"' . selected( $current, 'fail', false ) . '>' . esc_html__( 'Fail deploy', 'vibecode-deploy' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}

	public static function field_placeholder_prefix(): void {
		$settings = Settings::get_all();
		$value = isset( $settings['placeholder_prefix'] ) ? esc_attr( (string) $settings['placeholder_prefix'] ) : 'VIBECODE_SHORTCODE';
		echo '<input type="text" name="' . esc_attr( Settings::OPTION_NAME ) . '[placeholder_prefix]" value="' . $value . '" class="regular-text" pattern="[A-Z0-9_]+" />';
		echo '<p class="description">' . esc_html__( 'Prefix for shortcode placeholder comments in HTML (e.g., VIBECODE_SHORTCODE). Use uppercase letters, numbers, and underscores only.', 'vibecode-deploy' ) . '</p>';
	}

	public static function field_env_errors_mode(): void {
		self::render_mode_select( 'env_errors_mode', __( 'How to handle critical environment errors (missing theme, unsupported WordPress version, etc.) during preflight.', 'vibecode-deploy' ) );
	}

	public static function field_on_missing_required(): void {
		$settings = Settings::get_all();
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		/* translators: %s: Placeholder prefix */
		self::render_mode_select( 'on_missing_required', sprintf( __( 'When a page is missing a required %s placeholder (as defined in vibecode-deploy-shortcodes.json).', 'vibecode-deploy' ), $prefix ) );
	}

	public static function field_on_missing_recommended(): void {
		$settings = Settings::get_all();
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		/* translators: %s: Placeholder prefix */
		self::render_mode_select( 'on_missing_recommended', sprintf( __( 'When a page is missing a recommended %s placeholder (as defined in vibecode-deploy-shortcodes.json).', 'vibecode-deploy' ), $prefix ) );
	}

	public static function field_on_unknown_placeholder(): void {
		$settings = Settings::get_all();
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		/* translators: %s: Placeholder prefix */
		self::render_mode_select( 'on_unknown_placeholder', sprintf( __( 'When an invalid/unparseable %s placeholder is encountered in HTML.', 'vibecode-deploy' ), $prefix ) );
	}
}
