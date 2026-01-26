<?php
/**
 * App registry for managing Vite applications.
 *
 * @package Etch
 * @subpackage Vite
 */

declare(strict_types=1);

namespace Etch\Vite\Apps;

use Etch\Helpers\Logger;

/**
 * Class AppRegistry
 *
 * Manages registration and lookup of Vite applications.
 */
class AppRegistry {

	/**
	 * Registered applications.
	 *
	 * @var AppConfig[]
	 */
	private array $apps = array();

	/**
	 * Load applications from configuration file.
	 *
	 * @param string $config_file Path to configuration file.
	 * @return void
	 */
	public function load_from_config( string $config_file ): void {
		if ( ! file_exists( $config_file ) ) {
			Logger::log( sprintf( '%s: Configuration file not found: %s', __METHOD__, $config_file ) );
			return;
		}

		$file_contents = file_get_contents( $config_file );
		if ( false === $file_contents ) {
			Logger::log( sprintf( '%s: Failed to read configuration file', __METHOD__ ) );
			return;
		}

		$configs = json_decode( $file_contents, true );
		if ( ! is_array( $configs ) ) {
			Logger::log( sprintf( '%s: Invalid JSON in configuration file', __METHOD__ ) );
			return;
		}

		foreach ( $configs as $config ) {
			$app_config = AppConfig::from_array( $config );
			if ( null !== $app_config ) {
				$this->register_app( $app_config );
			}
		}
	}

	/**
	 * Register a new application.
	 *
	 * @param AppConfig $app_config Application configuration.
	 * @return void
	 */
	public function register_app( AppConfig $app_config ): void {
		$this->apps[] = $app_config;
	}

	/**
	 * Get all registered applications.
	 *
	 * @return AppConfig[]
	 */
	public function get_all_apps(): array {
		return $this->apps;
	}

	/**
	 * Find application by name.
	 *
	 * @param string $name Application name.
	 * @return AppConfig|null
	 */
	public function get_app_by_name( string $name ): ?AppConfig {
		foreach ( $this->apps as $app ) {
			if ( $name === $app->get_name() ) {
				return $app;
			}
		}
		return null;
	}

	/**
	 * Check if an application is registered.
	 *
	 * @param string $name Application name.
	 * @return bool
	 */
	public function has_app( string $name ): bool {
		return null !== $this->get_app_by_name( $name );
	}
}
