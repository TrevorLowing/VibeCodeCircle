<?php
/**
 * Settings Page
 *
 * @package Etch\WpAdmin
 * @since 0.15.1
 */

declare(strict_types=1);

namespace Etch\WpAdmin;

use Etch\Traits\Singleton;
use Etch\WpAdmin\SettingsPage\LicenseTab;

/**
 * Create a menu button with settings page
 */
class SettingsPage {
	use Singleton;

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public function init() {
		LicenseTab::get_instance()->init();

		add_action( 'admin_menu', array( self::get_instance(), 'add_admin_menu_button' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue the assets in the settings page
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$page = filter_input( INPUT_GET, 'page' );
		if ( 'etch' === $page ) {
			wp_enqueue_style(
				'etch-admin-styles',
				plugin_dir_url( __FILE__ ) . 'Assets/css/style.css',
				array(),
				'1.0.0'
			);
		}
	}

	/**
	 * Add button in admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu_button() {
		add_menu_page(
			'Etch',
			'Etch',
			'manage_options',
			'etch',
			array( $this, 'render_settings_page' ),
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGFyaWEtaGlkZGVuPSJ0cnVlIiByb2xlPSJpbWciIGNsYXNzPSJldGNoLWxvZ28gaWNvbmlmeSBpY29uaWZ5LS1ldGNoIiBhbHQ9IkV0Y2ggSWNvbiIgd2lkdGg9IjIwIiBoZWlnaHQ9IjIwIiB2aWV3Qm94PSIwIDAgMjAwIDIwMCI+PHBhdGggZmlsbC1ydWxlPSJldmVub2RkIiBjbGlwLXJ1bGU9ImV2ZW5vZGQiIGQ9Ik0xMS44IDgwLjVINDIuMTEyMUM1MS43OTc3IDgwLjUgNjAuMDMxIDczLjQ2MTMgNjEuNDg4OCA2My45MzQ3QzYxLjU1MzUgNjIuNjI0NCA2MS42NjI0IDYxLjU4NSA2MS43ODIgNjFDNjYuMzI3MSAzOC43NTk1IDg2LjExMiAyMiAxMDkuOCAyMkgxODguMkMxOTMuNjA5IDIyIDE5OCAyNi4zNjg3IDE5OCAzMS43NVY1MS4yNUMxOTggNTYuNjMxMyAxOTMuNjA5IDYxIDE4OC4yIDYxSDEwMS4yNjFDNzguOTE1MSA2MSA2MC44IDc5LjAyMzQgNjAuOCAxMDEuMjU1QzYwLjggMTExLjMzMSA1Mi41ODk5IDExOS41IDQyLjQ2MjIgMTE5LjVIMTEuOEM2LjM5MTE0IDExOS41IDIgMTE1LjEzMSAyIDEwOS43NVY5MC4yNUMyIDg0Ljg2ODcgNi4zOTExNCA4MC41IDExLjggODAuNVpNMTA5LjggODAuNUgxODguMkMxOTMuNjA5IDgwLjUgMTk4IDg0Ljg2ODcgMTk4IDkwLjI1VjEwOS43NUMxOTggMTE1LjEzMSAxOTMuNjA5IDExOS41IDE4OC4yIDExOS41SDEwOS44QzEwMS4xMDEgMTE5LjUgOTMuMjc4NiAxMjMuMjY3IDg3Ljg5MzkgMTI5LjI1Qzg2LjIxNyAxMzEuMTEzIDg0Ljc3NjMgMTMzLjE5MSA4My42MTk0IDEzNS40MzdDODEuNTYxNCAxMzkuNDMxIDgwLjQgMTQzLjk1NyA4MC40IDE0OC43NUM4MC40IDE2NC44OTMgNjcuMjI2MyAxNzggNTEgMTc4SDExLjhDNi4zOTExNCAxNzggMiAxNzMuNjMxIDIgMTY4LjI1VjE0OC43NUMyIDE0My4zNjkgNi4zOTExNCAxMzkgMTEuOCAxMzlINTFDNTkuNjk5NSAxMzkgNjcuNTIxNCAxMzUuMjMzIDcyLjkwNjEgMTI5LjI1QzczLjA4MTMgMTI5LjA1NSA3My4yNTQgMTI4Ljg1OCA3My40MjQxIDEyOC42NTlDNzcuNzc0NyAxMjMuNTU4IDgwLjQgMTE2Ljk1NyA4MC40IDEwOS43NUM4MC40IDEwNC45NTcgODEuNTYxNCAxMDAuNDMxIDgzLjYxOTQgOTYuNDM2N0M4OC40OTIyIDg2Ljk3ODcgOTguMzkxNyA4MC41IDEwOS44IDgwLjVaTTEwOS44IDEzOUgxODguMkMxOTMuNjA5IDEzOSAxOTggMTQzLjM2OSAxOTggMTQ4Ljc1VjE2OC4yNUMxOTggMTczLjYzMSAxOTMuNjA5IDE3OCAxODguMiAxNzhIMTA5LjhDMTA0LjM5MSAxNzggMTAwIDE3My42MzEgMTAwIDE2OC4yNVYxNDguNzVDMTAwIDE0My4zNjkgMTA0LjM5MSAxMzkgMTA5LjggMTM5WiIgZmlsbD0iY3VycmVudENvbG9yIj48L3BhdGg+PCEtLS0tPjwvc3ZnPgo='
		);
	}

	/**
	 * Output the settings page HTML
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$tab_get = filter_input( INPUT_GET, 'tab' );
		$tab = ( null === $tab_get || false === $tab_get ) ? 'license' : sanitize_text_field( $tab_get );

		?>
		<div class="wrap etch-wrapper">
			<h1>Welcome to the ETCH settings page</h1>

			<nav class="nav-tab-wrapper">
				<a href="?page=etch&tab=license" class="nav-tab<?php echo 'license' === $tab ? ' nav-tab-active' : ''; ?>">License</a>
			</nav>

			<div class="tab-content">
			<?php
			switch ( $tab ) :
				default:
					LicenseTab::get_instance()->render();
					break;
				endswitch;
			?>
			</div>
		</div>
		<?php
	}
}
