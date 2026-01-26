<?php
/**
 * Etch Migration file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers;

/**
 * Etch Migration Helper class.
 */
class Migration {

	/**
	 * The option name for migrations.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'etch_migrations';

	/**
	 * Get the migrations array from the database.
	 *
	 * @return array<string, mixed> The migrations array.
	 */
	private static function get_migrations(): array {
		$migrations = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $migrations ) ) {
			$migrations = array();
		}
		return $migrations;
	}

	/**
	 * Check if a migration has been run.
	 *
	 * @param string $migration_key The migration key to check.
	 * @return bool True if the migration has been run, false otherwise.
	 */
	public static function has_run( string $migration_key ): bool {
		$migrations = self::get_migrations();
		return isset( $migrations[ $migration_key ] ) && true === $migrations[ $migration_key ];
	}

	/**
	 * Mark a migration as run.
	 *
	 * @param string $migration_key The migration key to mark as run.
	 * @return void
	 */
	public static function mark_done( string $migration_key ): void {
		$migrations = self::get_migrations();
		$migrations[ $migration_key ] = true;
		update_option( self::OPTION_NAME, $migrations );
	}

	/**
	 * Run a migration callback only once.
	 *
	 * @param string   $key      The unique key for the migration.
	 * @param callable $callback The migration callback to run.
	 * @return void
	 */
	public static function run_once( string $key, callable $callback ): void {
		if ( self::has_run( $key ) ) {
			return;
		}

		$callback();
		self::mark_done( $key );
	}
}
