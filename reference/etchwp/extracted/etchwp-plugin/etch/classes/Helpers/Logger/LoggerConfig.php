<?php
/**
 * Etch Logger config file.
 * Used for dependency injection into the Logger class, allowing for unit testing.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Helpers\Logger;

use Etch\Plugin;
use Etch\Helpers\Logger;

/**
 * Class LoggerConfig
 */
class LoggerConfig {

	/**
	 * Whether logging is enabled.
	 *
	 * @var boolean
	 */
	public $enabled = false;

	/**
	 * The log level.
	 *
	 * @var int
	 * @see Logger
	 */
	public $logLevel = Logger::LOG_LEVEL_ERROR;

	/**
	 * The plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Constructor.
	 *
	 * @param boolean $enabled Whether logging is enabled.
	 * @param Plugin  $plugin The plugin instance.
	 */
	public function __construct( $enabled = false, $plugin = null ) {
		$this->enabled = (bool) $enabled;
		$this->plugin = $plugin ? $plugin : Plugin::get_instance();
		$this->logLevel = defined( 'ETCH_DEBUG_LOG_LEVEL' ) ? ETCH_DEBUG_LOG_LEVEL : Logger::LOG_LEVEL_ERROR;
	}
}
