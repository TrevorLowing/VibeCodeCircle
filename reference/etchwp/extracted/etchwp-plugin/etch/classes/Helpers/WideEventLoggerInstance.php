<?php
/**
 * WideEventLoggerInstance - Instance-based wide event logger.
 *
 * @package Etch\Helpers
 */

namespace Etch\Helpers;

use Etch\Lib\WideEvents\WideEventSession;
use Etch\Lib\WideEvents\Emitter\EmitterInterface;
use Etch\Lib\WideEvents\Emitter\FileEmitter;
use Etch\Lib\WideEvents\Formatter\JsonFormatter;
use Etch\Lib\WideEvents\Sampler\ErrorAwareSampler;
use Etch\Plugin;

/**
 * WideEventLoggerInstance class.
 *
 * Instance-based logger that holds all state and logic.
 * The static WideEventLogger facade delegates to an instance of this class.
 */
class WideEventLoggerInstance {

	/**
	 * The session instance for the current request.
	 *
	 * @var WideEventSession|null
	 */
	private ?WideEventSession $session = null;

	/**
	 * Whether the logger has been initialized.
	 *
	 * @var bool
	 */
	private bool $initialized = false;

	/**
	 * Configuration options.
	 *
	 * @var array<string, mixed>
	 */
	private array $config = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $config Configuration options:
	 *                                     - sample_rate: float (0.0-1.0, default 0.01)
	 *                                     - max_file_size: int (bytes, default 5MB)
	 *                                     - max_files: int (default 3)
	 *                                     - log_path: string (optional, for testing)
	 *                                     - enabled: bool (optional, bypasses Flag check for testing).
	 */
	public function __construct( array $config = array() ) {
		$this->config = array_merge(
			array(
				'sample_rate'   => 0.01,
				'max_file_size' => 5 * 1024 * 1024,
				'max_files'     => 3,
			),
			$config
		);
	}

	/**
	 * Initialize the wide event logger.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$sample_rate = $this->config['sample_rate'];
		$sampler = new ErrorAwareSampler( is_float( $sample_rate ) || is_int( $sample_rate ) ? (float) $sample_rate : 0.01 );
		$formatter = new JsonFormatter();

		$max_file_size = $this->config['max_file_size'];
		$max_files = $this->config['max_files'];
		$emitter = new FileEmitter(
			$this->get_log_path(),
			is_int( $max_file_size ) ? $max_file_size : 5 * 1024 * 1024,
			is_int( $max_files ) ? $max_files : 3
		);

		$this->session = new WideEventSession( $sampler, $formatter, $emitter );

		// Set initial request metadata.
		$this->set( 'request.timestamp', gmdate( 'c' ) );
		$this->set( 'request.id', $this->generate_request_id() );
		$this->set( 'request.method', isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'UNKNOWN' );

		$this->initialized = true;
	}

	/**
	 * Set a value in the current event.
	 *
	 * @param string $key   The key (supports dot notation).
	 * @param mixed  $value The value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		if ( ! $this->is_enabled() || null === $this->session ) {
			return;
		}

		$this->session->set( $key, $value );
	}

	/**
	 * Append a value to an array in the current event.
	 *
	 * @param string $key   The key (supports dot notation).
	 * @param mixed  $value The value to append.
	 * @return void
	 */
	public function append( string $key, $value ): void {
		if ( ! $this->is_enabled() || null === $this->session ) {
			return;
		}

		$this->session->append( $key, $value );
	}

	/**
	 * Record a failure in the current event.
	 *
	 * Marks the event as failed (always emitted, not sampled).
	 * Records the failure key, message, and optional context data.
	 *
	 * @param string              $key     Identifier for the failure source (e.g., 'api', 'blocks', 'migration').
	 * @param string              $message The human-readable failure message.
	 * @param array<string,mixed> $context Optional additional context data.
	 * @return void
	 */
	public function failure( string $key, string $message, array $context = array() ): void {
		if ( ! $this->is_enabled() || null === $this->session ) {
			return;
		}

		$this->session->failure( $key, $message, $context );
	}

	/**
	 * Emit the current event.
	 *
	 * Called automatically on shutdown. Decides whether to emit based on sampling.
	 *
	 * @return void
	 */
	public function emit(): void {
		if ( ! $this->is_enabled() || null === $this->session ) {
			return;
		}

		$this->session->emit();
	}

	/**
	 * Check if wide event logging is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		// Allow tests to force-enable without Flag system.
		$enabled = $this->config['enabled'] ?? null;
		if ( true === $enabled ) {
			return true;
		}
		if ( false === $enabled ) {
			return false;
		}

		// Avoid circular dependency: Flag::init() may call WideEventLogger before Flag is ready.
		if ( ! Flag::is_initialized() ) {
			return false;
		}
		return Flag::is_on( 'ENABLE_ACTIVITY_LOG' );
	}

	/**
	 * Get the path to the wide events log file.
	 *
	 * @return string
	 */
	private function get_log_path(): string {
		$log_path = $this->config['log_path'] ?? null;
		if ( is_string( $log_path ) && '' !== $log_path ) {
			return $log_path;
		}
		return Plugin::get_dynamic_uploads_dir() . '/activity.log';
	}

	/**
	 * Generate a unique request ID.
	 *
	 * @return string
	 */
	private function generate_request_id(): string {
		return substr( bin2hex( random_bytes( 8 ) ), 0, 16 );
	}

	/**
	 * Inject a custom emitter (useful for testing).
	 *
	 * @param EmitterInterface $emitter The emitter to use.
	 * @return void
	 */
	public function inject_emitter( EmitterInterface $emitter ): void {
		if ( null !== $this->session ) {
			$this->session->set_emitter( $emitter );
		}
	}
}
