<?php
/**
 * Etch CLI Helper Trait
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Cli\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;

/**
 * Abstract base class for WP-CLI commands in Etch.
 */
trait EtchCliHelper {


	/**
	 * Retrieves the first required argument ( subcommand ).
	 *
	 * @param array <int, string> $args Positional arguments.
	 * @return string
	 * @throws InvalidArgumentException If the argument is missing.
	 */
	protected function get_subcommand( array $args ): string {
		if ( empty( $args[0] ) ) {
			throw new InvalidArgumentException( 'A valid subcommand is required.' );
		}
		return (string) $args[0];
	}

	/**
	 * Retrieves a required argument from the command arguments.
	 *
	 * @param array<int, string> $args  Positional arguments.
	 * @param int                $index Index of the argument.
	 * @param string             $name  Name of the argument.
	 * @return string The argument value.
	 * @throws InvalidArgumentException If the argument is missing.
	 */
	protected function get_required_arg( array $args, int $index, string $name ): string {
		if ( ! array_key_exists( $index, $args ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new InvalidArgumentException( "Missing required argument: $name" );
		}
		return (string) $args[ $index ];
	}

	/**
	 * Retrieves an optional positional argument.
	 *
	 * @param array<int, string> $args    Positional arguments.
	 * @param int                $index   Index of the argument.
	 * @param string|null        $default Default value if argument is not set.
	 * @return string|null The argument value or default if not set.
	 */
	protected function get_optional_args( array $args, int $index, ?string $default = null ): ?string {
		return $args[ $index ] ?? $default;
	}

	/**
	 * Retrieves a named flag argument (`--flag=value`).
	 *
	 * @param array<string, string> $assoc_args Named flag arguments.
	 * @param string                $key        The flag name.
	 * @param string|null           $default    Default value if flag is not set.
	 * @return string|null The argument value or default if not set.
	 */
	protected function get_flag_args( array $assoc_args, string $key, ?string $default = null ): ?string {
		return isset( $assoc_args[ $key ] ) ? (string) $assoc_args[ $key ] : $default;
	}
}
