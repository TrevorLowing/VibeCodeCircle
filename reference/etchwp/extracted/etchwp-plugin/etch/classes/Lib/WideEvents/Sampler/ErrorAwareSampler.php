<?php
/**
 * ErrorAwareSampler - Always samples errors, probabilistically samples successes.
 *
 * @package Etch\Lib\WideEvents\Sampler
 */

namespace Etch\Lib\WideEvents\Sampler;

/**
 * ErrorAwareSampler class.
 *
 * Implements a sampling strategy that:
 * - Always emits events that contain errors
 * - Emits successful events at a configurable rate (default 1%)
 */
class ErrorAwareSampler implements SamplerInterface {

	/**
	 * The sample rate for successful (non-error) events.
	 *
	 * @var float
	 */
	private float $success_sample_rate;

	/**
	 * Constructor.
	 *
	 * @param float $success_sample_rate Sample rate for successful events (0.0 to 1.0).
	 *                                   Default is 0.01 (1%).
	 */
	public function __construct( float $success_sample_rate = 0.01 ) {
		$this->success_sample_rate = max( 0.0, min( 1.0, $success_sample_rate ) );
	}

	/**
	 * Determine if the event should be sampled.
	 *
	 * @param bool $has_error Whether the event contains an error.
	 * @return bool True if the event should be emitted.
	 */
	public function should_sample( bool $has_error ): bool {
		if ( $has_error ) {
			return true;
		}

		if ( $this->success_sample_rate >= 1.0 ) {
			return true;
		}

		if ( $this->success_sample_rate <= 0.0 ) {
			return false;
		}

		return ( mt_rand() / mt_getrandmax() ) < $this->success_sample_rate;
	}
}
