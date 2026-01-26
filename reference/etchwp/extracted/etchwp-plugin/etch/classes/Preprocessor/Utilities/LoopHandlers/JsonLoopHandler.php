<?php
/**
 * JSON loop handler for Etch.
 *
 * @package Etch
 * @since 1.0.0
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\LoopHandlers;

use Etch\Helpers\Logger;

/**
 * Handles loops that use static JSON data.
 */
class JsonLoopHandler extends LoopHandlerInterface {

	/**
	 * Get loop data for the specified query/preset name using JSON data.
	 *
	 * @param string               $query_name The name of the query/loop preset.
	 * @param array<string, mixed> $loop_params The loop parameters.
	 * @return array<int, array<string, mixed>> Array of data items for the loop.
	 */
	public function get_loop_data( string $query_name, array $loop_params = array() ): array {
		$json_data = $this->get_json_data( $query_name );

		if ( empty( $json_data ) ) {
			return array();
		}

		// Ensure we have an array of items
		if ( ! is_array( $json_data ) ) {
			Logger::log( "JSON data for '{$query_name}' is not an array" );
			return array();
		}

		// If the data is a simple array of objects, return it directly
		$loop_data = array();
		foreach ( $json_data as $item ) {
			if ( is_object( $item ) ) {
				// Convert object to array
				$loop_data[] = (array) $item;
			} else {
				// For primitive values, wrap in an array with a 'value' key
				$loop_data[] = $item;
			}
		}

		return $loop_data;
	}

	/**
	 * Get JSON data for the specified query name.
	 *
	 * @param string $query_name The name of the query preset.
	 * @return array<mixed>|null JSON data array or null if not found.
	 */
	private function get_json_data( string $query_name ): ?array {
		// Check loop presets for JSON data
		$loop_presets = get_option( 'etch_loops', array() );
		if ( is_array( $loop_presets ) && isset( $loop_presets[ $query_name ]['config']['data'] ) ) {
			$data = $loop_presets[ $query_name ]['config']['data'];
			return is_array( $data ) ? $data : null;
		}

		// Could also check for other sources of JSON data here
		// For example, external API calls, files, etc.

		return null;
	}
}
