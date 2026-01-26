<?php
/**
 * Etch CLI Queries Command
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
 * Etch Queries command
 */
class Queries extends WP_CLI_Command {
	use EtchCliHelper;

	/**
	 * Deletes all Etch queries.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( array $assoc_args ): void {
		$queries_exists = get_option( 'etch_queries', null );

		if ( null === $queries_exists ) {
			WP_CLI::warning( 'There are no queries to delete.' );
			return;
		}

		$force = $this->get_flag_args( $assoc_args, 'force', null );

		if ( null === $force ) {
			WP_CLI::confirm( 'Are you sure you want to delete all queries?', array( 'y' ) );
		}

		delete_option( 'etch_queries' );
		WP_CLI::success( 'All queries have been deleted.' );
	}
}
