<?php
/**
 * Etch CLI Delete Command
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Cli\Commands;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_CLI;
use WP_CLI_Command;
use Etch\Cli\SubCommands\Styles;
use Etch\Cli\SubCommands\Components;
use Etch\Cli\SubCommands\Queries;
use Etch\Cli\SubCommands\UiState;
use Etch\Cli\Traits\EtchCliHelper;

/**
 * Delete action for Etch CLI.
 */
class Delete extends WP_CLI_Command {
	use EtchCliHelper;

	/**
	 * Deletes Etch data.
	 *
	 * @subcommand delete
	 *
	 * styles
	 * : Deletes all Etch styles.
	 *
	 * components
	 * : Deletes all Etch components.
	 *
	 * queries
	 * : Deletes all Etch queries.
	 *
	 * all
	 * : Deletes all Etch data (styles, components, and queries).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Skip confirmation prompt and forces to delete.
	 *
	 * ## EXAMPLES
	 *
	 *     wp etch delete styles
	 *     wp etch delete components
	 *     wp etch delete queries
	 *     wp etch delete all
	 *
	 * @param array<int, string>    $args     Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {

		try {
			$subcommand = $this->get_subcommand( $args );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}

		/**
		 * Handle subcommands
		 *
		 * Receives subcommands from each command class -> method
		 *
		 * @var string $subcommand
		 */
		switch ( $subcommand ) {
			case 'styles':
				( new Styles() )->delete( $assoc_args );
				break;
			case 'components':
				( new Components() )->delete( $assoc_args );
				break;
			case 'queries':
				( new Queries() )->delete( $assoc_args );
				break;
			case 'ui-state':
				( new UiState() )->delete( $assoc_args );
				break;
			case 'all':
				$this->delete_all( $assoc_args );
				break;
			default:
				WP_CLI::error( 'That subcommand does not exist.' );
		}
	}

	/**
	 * Deletes all Etch data.
	 *
	 * Forces the subcommands since that we get confirmation on the first prompt.
	 * TODO: Refactor this to another file maybe??.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	private function delete_all( array $assoc_args ): void {

		$force = $this->get_flag_args( $assoc_args, 'force', null );

		if ( null === $force ) {
			WP_CLI::confirm( 'Are you sure you want to delete all Etch data?', array( 'y' ) );
		}

		WP_CLI::log( 'Deleting all Etch data...' );

		// Passing force to true to skip confirmation prompt for each subcommand
		$assoc_args['force'] = 'true';

		( new Styles() )->delete( $assoc_args );
		( new Components() )->delete( $assoc_args );
		( new Queries() )->delete( $assoc_args );

		WP_CLI::success( 'All Etch data has been deleted.' );
	}
}
