<?php
/**
 * Etch License
 *
 * Handles the license key and activation
 *
 * @package Etch\WpAdmin
 * @since 0.15.1
 */

namespace Etch\WpAdmin;

use Etch\Helpers\WideEventLogger;
use Etch\Traits\Singleton;
use Etch\Includes\SureCart\Licensing\Client;

/**
 * Handles the license key and activation
 * Uses the SureCart integration
 */
class License {
	use Singleton;

	/**
	 * License key for the option table
	 *
	 * @var string
	 */
	private $license_key_option = 'etch_license_key';

	/**
	 * Status key for the option table to store the activation status
	 *
	 * @var string
	 */
	private $status_key_option = 'etch_license_status';

	/**
	 * SureCart Client
	 *
	 * @var Client
	 */
	private $client;

	/**
	 * Initialize
	 *
	 * @return void
	 */
	public function init() {
		if ( ! class_exists( 'Etch\Includes\SureCart\Licensing\Client' ) ) {
			require_once ETCH_PLUGIN_DIR . '/includes/SureCart/Licensing/Client.php';
		}
		$this->client = new Client( 'Etch', 'pt_7eCsZFuK2NuCXK97jzkennFi', ETCH_PLUGIN_FILE );
	}

	/**
	 * Returns the license key value no matter if in constant or in the database
	 *
	 * @return string
	 */
	public function get_license() {
		$license = $this->get_license_from_constant();

		if ( '' == $license ) {
			$license = get_option( $this->license_key_option, '' );
		}

		return is_string( $license ) ? $license : '';
	}

	/**
	 * Returns the obfuscate license
	 *
	 * @return string
	 */
	public function get_obfuscate_license() {
		return $this->obfuscate_license( $this->get_license() );
	}

	/**
	 * Returns the license from constant if exists
	 *
	 * @return string
	 */
	public function get_license_from_constant() {
		return defined( 'ETCH_LICENSE_KEY' ) && ! empty( ETCH_LICENSE_KEY ) && is_string( ETCH_LICENSE_KEY ) ? ETCH_LICENSE_KEY : '';
	}

	/**
	 * Check if exists a constant for the license
	 *
	 * @return boolean
	 */
	public function is_from_constant() {
		return '' != $this->get_license_from_constant() ? true : false;
	}

	/**
	 * Activate the license
	 *
	 * @param mixed $license License.
	 * @throws \Exception Error trying to activate the license.
	 * @return mixed
	 */
	public function activate_license( $license ) {
		if ( $this->is_from_constant() ) {
			$license = $this->get_license_from_constant();
		}

		if ( empty( $license ) ) {
			throw new \Exception( 'No license informed.' );
		}

		if ( ! is_string( $license ) ) {
			throw new \Exception( 'Invalid license.' );
		}

		// It's just trying to activate again, no need to process the request.
		if ( ( get_option( $this->license_key_option ) === $license || $this->is_obfuscated_license( $license ) ) && $this->is_active() ) {
			return true;
		}

		$response = $this->client->license()->activate( $license );

		if ( is_wp_error( $response ) ) {
			WideEventLogger::failure( 'license.activate', $response->get_error_message(), array( 'error_code' => $response->get_error_code() ) );
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		if ( ! $response ) {
			WideEventLogger::failure( 'license.activate', 'Error while trying to activate the license', array( 'error_code' => 'empty_response' ) );
			throw new \Exception( 'Error while try activate the license.' );
		}

		update_option( $this->license_key_option, $license );
		update_option( $this->status_key_option, 'valid' );
		delete_transient( 'etch_license_is_active' );
		WideEventLogger::set( 'license.activate', 'success' );
		return true;
	}

	/**
	 * Deactivate the license
	 *
	 * @throws \Exception Error trying to deactivate the license.
	 * @return mixed
	 */
	public function deactivate_license() {
		$response = $this->client->license()->deactivate();

		if ( is_wp_error( $response ) ) {
			WideEventLogger::failure( 'license.deactivate', $response->get_error_message(), array( 'error_code' => $response->get_error_code() ) );
			throw new \Exception( esc_html( $response->get_error_message() ) );
		}

		if ( ! $response ) {
			WideEventLogger::failure( 'license.deactivate', 'Error while trying to deactivate the license', array( 'error_code' => 'empty_response' ) );
			throw new \Exception( 'Error while try activate the license.' );
		}

		delete_option( $this->license_key_option );
		delete_option( $this->status_key_option );
		delete_transient( 'etch_license_is_active' );
		WideEventLogger::set( 'license.deactivate', 'success' );
	}

	/**
	 * Returns the status of activation
	 *
	 * @return mixed
	 */
	public function get_status() {
		return get_option( $this->status_key_option );
	}

	/**
	 * Obfuscates the license key.
	 *
	 * @param string $license The license key.
	 * @return string
	 */
	private function obfuscate_license( string $license ): string {
		return empty( $license ) ? '' : substr_replace( $license, 'XXXXXXXXXXXXXXXXXXXXXXXX', 4, 24 );
	}

	/**
	 * Checks if a license key is obfuscated.
	 *
	 * @param string $license The license key.
	 * @return boolean
	 */
	private static function is_obfuscated_license( string $license ): bool {
		return strlen( $license ) >= 4 && false !== strpos( $license, 'XXXXXXXXXXXXXXXXXXXXXXXX', 4 );
	}

	/**
	 * Check if the license is active or not
	 *
	 * @return boolean
	 */
	public function is_active() {
		$is_active = get_transient( 'etch_license_is_active' );

		if ( false === $is_active ) {
			$is_active = $this->client->license()->is_active() == true ? 1 : 0;

			set_transient( 'etch_license_is_active', $is_active, DAY_IN_SECONDS );
		}

		return 1 == $is_active ? true : false;
	}
}
