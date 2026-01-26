<?php
/**
 * WideEventSession - Request-scoped orchestrator for wide events.
 *
 * @package Etch\Lib\WideEvents
 */

namespace Etch\Lib\WideEvents;

use Etch\Lib\WideEvents\Emitter\EmitterInterface;
use Etch\Lib\WideEvents\Formatter\FormatterInterface;
use Etch\Lib\WideEvents\Sampler\SamplerInterface;

/**
 * WideEventSession class.
 *
 * Manages the lifecycle of a single wide event for one request.
 * Coordinates sampling, formatting, and emission.
 */
class WideEventSession {

	/**
	 * The WideEvent instance for this session.
	 *
	 * @var WideEvent
	 */
	private WideEvent $event;

	/**
	 * Request start time for duration calculation.
	 *
	 * @var float
	 */
	private float $start_time;

	/**
	 * The sampler instance.
	 *
	 * @var SamplerInterface
	 */
	private SamplerInterface $sampler;

	/**
	 * The formatter instance.
	 *
	 * @var FormatterInterface
	 */
	private FormatterInterface $formatter;

	/**
	 * The emitter instance.
	 *
	 * @var EmitterInterface|null
	 */
	private ?EmitterInterface $emitter;

	/**
	 * Constructor.
	 *
	 * @param SamplerInterface      $sampler   The sampler to determine if events should be emitted.
	 * @param FormatterInterface    $formatter The formatter to convert event data to string.
	 * @param EmitterInterface|null $emitter   The emitter to persist formatted data (can be set later).
	 */
	public function __construct(
		SamplerInterface $sampler,
		FormatterInterface $formatter,
		?EmitterInterface $emitter = null
	) {
		$this->event = new WideEvent();
		$this->start_time = microtime( true );
		$this->sampler = $sampler;
		$this->formatter = $formatter;
		$this->emitter = $emitter;
	}

	/**
	 * Set a value in the current event.
	 *
	 * @param string $key   The key (supports dot notation).
	 * @param mixed  $value The value.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->event->set( $key, $value );
	}

	/**
	 * Append a value to an array in the current event.
	 *
	 * @param string $key   The key (supports dot notation).
	 * @param mixed  $value The value to append.
	 * @return void
	 */
	public function append( string $key, $value ): void {
		$this->event->append( $key, $value );
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
		$error_entry = array_merge(
			array(
				'key' => $key,
				'message' => $message,
			),
			$context
		);

		$this->event->append( 'result.failure_messages', $error_entry );

		// Mark as error so it's always emitted (not sampled).
		$this->event->set_error( 'result.status', 'failure' );
	}

	/**
	 * Emit the current event.
	 *
	 * Decides whether to emit based on sampling.
	 *
	 * @return void
	 */
	public function emit(): void {
		// Build result object.
		$duration_ms = round( ( microtime( true ) - $this->start_time ) * 1000, 2 );
		$status = $this->event->has_error() ? 'failure' : 'success';

		$this->event->set( 'result.status', $status );
		$this->event->set( 'result.duration_ms', $duration_ms );

		// Check sampling.
		if ( ! $this->sampler->should_sample( $this->event->has_error() ) ) {
			return;
		}

		// Emit if we have an emitter.
		if ( null === $this->emitter ) {
			return;
		}

		$formatted = $this->formatter->format( $this->event->get_data() );
		$this->emitter->emit( $formatted );
	}

	/**
	 * Inject a custom emitter.
	 *
	 * @param EmitterInterface $emitter The emitter to use.
	 * @return void
	 */
	public function set_emitter( EmitterInterface $emitter ): void {
		$this->emitter = $emitter;
	}

	/**
	 * Check if the event has an error.
	 *
	 * @return bool
	 */
	public function has_error(): bool {
		return $this->event->has_error();
	}
}
