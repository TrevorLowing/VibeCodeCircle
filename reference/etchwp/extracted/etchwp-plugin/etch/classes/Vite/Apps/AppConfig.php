<?php
/**
 * App configuration entity.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite\Apps;

/**
 * Class AppConfig
 *
 * Represents the configuration for a Vite application.
 */
class AppConfig {

	/**
	 * Application name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Development server port.
	 *
	 * @var int
	 */
	private int $port;

	/**
	 * Constructor.
	 *
	 * @param string $name Application name.
	 * @param int    $port Development server port.
	 */
	public function __construct( string $name, int $port ) {
		$this->name = $name;
		$this->port = $port;
	}

	/**
	 * Get application name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get development server port.
	 *
	 * @return int
	 */
	public function get_port(): int {
		return $this->port;
	}

	/**
	 * Create AppConfig from array configuration.
	 *
	 * @param array<string, mixed> $config Configuration array.
	 * @return AppConfig|null
	 */
	public static function from_array( array $config ): ?AppConfig {
		if ( ! isset( $config['name'] ) || ! isset( $config['port'] ) ) {
			return null;
		}

		if ( ! is_string( $config['name'] ) || ! is_int( $config['port'] ) ) {
			return null;
		}

		return new self( $config['name'], $config['port'] );
	}

	/**
	 * Convert AppConfig to array.
	 *
	 * @return array{name: string, port: int}
	 */
	public function to_array(): array {
		return array(
			'name' => $this->name,
			'port' => $this->port,
		);
	}
}
