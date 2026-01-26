<?php
/**
 * Etch CLI UiState Command
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Cli\SubCommands;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_CLI;
use WP_CLI_Command;
use Etch\Cli\Traits\EtchCliHelper;
use Etch\Helpers\Flag;

/**
 * Etch UiState command
 */
class UiState extends WP_CLI_Command {
	use EtchCliHelper;

	/**
	 * User meta key for storing UI state.
	 *
	 * @var string
	 */
	private const USER_META_KEY = '_etch_ui_state';

	/**
	 * Legacy option key for backwards compatibility.
	 *
	 * @var string
	 */
	private const LEGACY_OPTION_KEY = 'etch_ui_state';

	/**
	 * Deletes Etch UI state.
	 *
	 * Behavior depends on the REMOVE_DEPRECATED_ETCH_UI_STATE flag:
	 * - Flag OFF: Deletes the legacy global wp_option (etch_ui_state)
	 * - Flag ON: Deletes user meta (_etch_ui_state) for all users
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp etch delete ui-state
	 *     wp etch delete ui-state --force
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( array $assoc_args ): void {
		$force = $this->get_flag_args( $assoc_args, 'force', null );

		if ( Flag::is_on( 'REMOVE_DEPRECATED_ETCH_UI_STATE' ) ) {
			$this->delete_user_meta( $force );
		} else {
			$this->delete_legacy_option( $force );
		}
	}

	/**
	 * Deletes the legacy global option.
	 *
	 * @param string|null $force Whether to skip confirmation.
	 * @return void
	 */
	private function delete_legacy_option( ?string $force ): void {
		$ui_state_exists = get_option( self::LEGACY_OPTION_KEY, null );

		if ( null === $ui_state_exists ) {
			WP_CLI::warning( 'There is no legacy UI state option to delete.' );
			return;
		}

		if ( null === $force ) {
			WP_CLI::confirm( 'Are you sure you want to delete the global UI state option?', array( 'y' ) );
		}

		delete_option( self::LEGACY_OPTION_KEY );
		WP_CLI::success( 'Legacy UI state option has been deleted.' );
	}

	/**
	 * Deletes user meta for all users.
	 *
	 * @param string|null $force Whether to skip confirmation.
	 * @return void
	 */
	private function delete_user_meta( ?string $force ): void {
		global $wpdb;

		if ( null === $force ) {
			WP_CLI::confirm( 'Are you sure you want to delete UI state for ALL users?', array( 'y' ) );
		}

		$deleted = $wpdb->delete(
			$wpdb->usermeta,
			array( 'meta_key' => self::USER_META_KEY ),
			array( '%s' )
		);

		if ( $deleted > 0 ) {
			WP_CLI::success( "UI state deleted for {$deleted} user(s)." );
		} else {
			WP_CLI::warning( 'No user UI state found to delete.' );
		}
	}
}
