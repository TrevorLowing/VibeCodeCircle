<?php
/**
 * Etch CLI Styles Command
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

/**
 * Etch Styles command
 */
class Styles extends WP_CLI_Command {
	use EtchCliHelper;

	/**
	 * Deletes all Etch styles.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( array $assoc_args ): void {
		$styles_exists = get_option( 'etch_styles', null );

		if ( null === $styles_exists ) {
			WP_CLI::warning( 'There are no styles to delete.' );
			return;
		}

		$force = $this->get_flag_args( $assoc_args, 'force', null );

		if ( null === $force ) {
			WP_CLI::confirm( 'Are you sure you want to delete all styles?', array( 'y' ) );
		}

		delete_option( 'etch_styles' );
		WP_CLI::success( 'All styles have been deleted.' );
	}
}
