<?php
/**
 * EmitterInterface - Contract for emitting wide events.
 *
 * @package Etch\Lib\WideEvents\Emitter
 */

namespace Etch\Lib\WideEvents\Emitter;

/**
 * EmitterInterface.
 *
 * Defines how formatted event data should be persisted or transmitted.
 */
interface EmitterInterface {

	/**
	 * Emit the formatted event data.
	 *
	 * @param string $formatted_data The formatted event data to emit.
	 * @return bool True on success, false on failure.
	 */
	public function emit( string $formatted_data ): bool;
}
