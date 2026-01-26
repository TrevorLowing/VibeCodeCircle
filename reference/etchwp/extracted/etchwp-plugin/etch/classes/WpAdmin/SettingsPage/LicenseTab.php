<?php
/**
 * Licensing Tab
 *
 * @package Etch\WpAdmin\SettingsPage
 * @since 0.15.1
 */

declare(strict_types=1);

namespace Etch\WpAdmin\SettingsPage;

use Etch\Traits\Singleton;
use Etch\WpAdmin\License;

/**
 * Output the form for manage the license
 */
class LicenseTab {
	use Singleton;

	/**
	 * Slug for the page
	 *
	 * @var string
	 */
	private $plugin_license_page = 'etch&tab=license';

	/**
	 * License key for input name
	 *
	 * @var string
	 */
	private $license_key_option = 'etch_license_key';

	/**
	 * Name for the section
	 *
	 * @var string
	 */
	private $settings_section_name = 'etch_license';

	/**
	 * Nonce name
	 *
	 * @var string
	 */
	private $nonce_field = 'etch_license_nonce';

	/**
	 * Nonce value
	 *
	 * @var string
	 */
	private $nonce_value = 'etch_license_nonce';

	/**
	 * License handler
	 *
	 * @var \Etch\WpAdmin\License
	 */
	private $license_handler;

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public function init() {
		$this->license_handler = License::get_instance();
		add_action( 'admin_init', array( $this, 'handle_license_activation' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Output the settings page.
	 *
	 * @return void
	 */
	public function render() {
		add_settings_section(
			$this->settings_section_name,
			__( 'Plugin License' ),
			array( $this, 'settings_section' ),
			$this->plugin_license_page
		);

		add_settings_field(
			$this->license_key_option,
			'<label for="' . esc_attr( $this->license_key_option ) . '">' . __( 'License Key' ) . '</label>',
			array( $this, 'settings_fields' ),
			$this->plugin_license_page,
			$this->settings_section_name
		);

		?>
			<div class="wrap">
				<!-- <h2><?php esc_html_e( 'Plugin License Options' ); ?></h2> -->
				<form method="post" action="options.php">
				<?php
				do_settings_sections( $this->plugin_license_page );
				settings_fields( $this->settings_section_name );
				?>
				</form>
			</div>
		<?php
	}

	/**
	 * Adds content to the settings section.
	 *
	 * @return void
	 */
	public function settings_section() {
		esc_html_e( 'Please enter your Etch license key.' );
	}

	/**
	 * Outputs the license key settings field.
	 *
	 * @return void
	 */
	public function settings_fields() {
		$license = $this->license_handler->get_obfuscate_license();
		$status = $this->license_handler->get_status();

		switch ( $status ) {
			case 'valid':
				$status_message = 'Etch is active on this website';
				$status_class = 'success';
				break;

			case 'error':
				$status_message = 'Etch is NOT active on this website';
				$status_class = 'error';
				break;
			default:
				$status_message = 'Please activate your license key to receive updates and support.';
				$status_class = 'warning';
				break;
		}
		?>

		<?php if ( $this->license_handler->is_from_constant() ) : ?>
			<?php
			echo '<p>' . esc_html__( 'License key defined in wp-config.php', 'etch' ) . '</p>';
			echo '<code>' . esc_html( $license ) . '</code>';
			?>
		<?php else : ?>
			<?php
			printf(
				'<input type="password" class="regular-text" id="%1$s" name="%1$s" value="%2$s" />',
				esc_attr( $this->license_key_option ),
				esc_attr( $license )
			);
			?>
		<?php endif; ?>

		<div class="etch-license__field-group">
			<?php wp_nonce_field( $this->nonce_field, $this->nonce_value ); ?>
			<input type="submit" class="button-primary" name="etch_license_activate" value="Save & Activate"/>
			<input type="submit" class="button-secondary" name="etch_license_deactivate" value="Delete & Deactivate"/>
		</div>
		<div class="etch-license__field-group acss-settings__message-container">
			<p class="<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_message ); ?></p>
		</div>
		<?php
	}

	/**
	 * Handle plugin license activation & deactivation
	 *
	 * @return void
	 */
	public function handle_license_activation() {
		// listen for our activate button to be clicked.
		if ( ! isset( $_POST['etch_license_activate'] ) && ! isset( $_POST['etch_license_deactivate'] ) ) {
			return;
		}
		// run a quick security check.
		if ( ! check_admin_referer( $this->nonce_field, $this->nonce_value ) ) {
			return; // get out if we didn't click the Activate button.
		}

		$license = filter_input( INPUT_POST, $this->license_key_option );

		try {
			if ( isset( $_POST['etch_license_activate'] ) ) {
				$this->license_handler->activate_license( $license );
				$this->redirect_with_message( 'This site was successfully activated.', 'true' );
			} else {
				$this->license_handler->deactivate_license();
				$this->redirect_with_message( 'This site was successfully deactivated.', 'true' );
			}
		} catch ( \Exception $e ) {
			$this->redirect_with_message( $e->getMessage(), 'false' );
		}
	}

	/**
	 * Redirects on error.
	 *
	 * @param string $message The error message.
	 * @param string $sl_activation The activation status.
	 * @return void
	 */
	private function redirect_with_message( $message, $sl_activation = 'false' ) {
		$redirect = add_query_arg(
			array(
				'page'          => $this->plugin_license_page,
				'sl_activation' => $sl_activation,
				'message'       => rawurlencode( $message ),
				'nonce'         => wp_create_nonce( $this->nonce_field ),
			),
			admin_url( 'admin.php?page=' . $this->plugin_license_page ) // was: plugins.php.
		);
		wp_safe_redirect( $redirect );
		exit();
	}

	/**
	 * This is a means of catching errors from the activation method above and displaying it to the customer
	 */
	public function admin_notices(): void {
		$page = filter_input( INPUT_GET, 'page' );
		$sl_activation = filter_input( INPUT_GET, 'sl_activation' );
		$message = filter_input( INPUT_GET, 'message' );
		$nonce = filter_input( INPUT_GET, 'nonce' );
		?>
		<?php
		if ( isset( $sl_activation ) && ! empty( $message ) && ! empty( $nonce ) && wp_verify_nonce( $nonce, $this->nonce_field ) && current_user_can( 'manage_options' ) ) {
			$message = urldecode( $message );
			switch ( $sl_activation ) {
				case 'false':
					?>
				<div class="notice notice-error">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
					<?php
					break;
				case 'true':
					?>
				<div class="notice notice-success">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
					<?php
					break;
				default:
					// Developers can put a custom success message here for when activation is successful if they way.
					?>
					<div class="notice notice-error">
					<p><?php echo esc_html( $message ); ?></p>
				</div>
					<?php
					break;

			}
		}
	}
}
