<?php
/**
 * Etch CLI Components Command
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
use Etch\Services\ComponentsService;
use Etch\Cli\Traits\EtchCliHelper;

/**
 * Etch Components command
 */
class Components extends WP_CLI_Command {
	use EtchCliHelper;

	/**
	 * Deletes all Etch Components.
	 *
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public function delete( array $assoc_args ): void {
		$force = $assoc_args['force'] ?? null;

		if ( null === $force ) {
			WP_CLI::confirm( 'Are you sure you want to delete all components?', array( 'y' ) );
		}

		$service = new ComponentsService();
		$result = $service->delete_all_components();

		match ( $result['status'] ) {
			'success' => WP_CLI::success( $result['message'] ),
			'warning' => WP_CLI::warning( $result['message'] ),
			'error' => WP_CLI::error( $result['message'] ),
			default => WP_CLI::error( 'Failed to delete components' ),
		};
	}
}
