<?php
/**
 * Etch CLI Loader
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Cli;

use WP_CLI;
use Etch\Cli\Commands\Delete;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Etch CLI Loader
 */
class EtchCliLoader {

	/**
	 * Register Etch CLI commands.
	 *
	 * @return void
	 */
	public static function register_etch_commands(): void {
		if ( ! class_exists( 'WP_CLI' ) ) {
			return;
		}

		WP_CLI::add_command(
			'etch delete',
			Delete::class,
			array(
				'shortdesc' => 'Deletes Etch data.',
				'when' => 'after_wp_load',
			)
		);
	}
}
