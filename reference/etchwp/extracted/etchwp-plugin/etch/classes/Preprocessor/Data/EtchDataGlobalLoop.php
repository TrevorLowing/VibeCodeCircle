<?php
/**
 * EtchDataGlobalLoop Component file.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Data;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * EtchDataGlobalLoop class for handling global loop data with proper typing.
 */
class EtchDataGlobalLoop {

	/**
	 * The loop name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The loop key identifier.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Whether this is a global loop.
	 *
	 * @var bool
	 */
	private bool $global;

	/**
	 * The loop configuration containing type and type-specific data.
	 *
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Valid loop types.
	 *
	 * @var array<string>
	 */
	private const VALID_TYPES = array( 'wp-query', 'main-query', 'wp-terms', 'wp-users', 'json' );

	/**
	 * Whether this is a valid global loop.
	 *
	 * @var bool
	 */
	private bool $is_valid = false;

	/**
	 * Constructor for EtchDataGlobalLoop.
	 *
	 * @param array<string, mixed> $loop_data Raw loop data from database.
	 */
	public function __construct( array $loop_data ) {
		$this->initialize_defaults();
		$this->extract_and_validate_data( $loop_data );
	}

	/**
	 * Create EtchDataGlobalLoop from raw loop data.
	 *
	 * @param array<string, mixed> $loop_data Raw loop data.
	 * @return self|null EtchDataGlobalLoop instance or null if invalid.
	 */
	public static function from_array( array $loop_data ): ?self {
		if ( ! is_array( $loop_data ) ) {
			return null;
		}

		$loop = new self( $loop_data );
		return $loop->is_valid() ? $loop : null;
	}

	/**
	 * Get the loop name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the loop key.
	 *
	 * @return string
	 */
	public function get_key(): string {
		return $this->key;
	}

	/**
	 * Check if this is a global loop.
	 *
	 * @return bool
	 */
	public function is_global(): bool {
		return $this->global;
	}

	/**
	 * Get the loop type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return EtchTypeAsserter::to_non_empty_string( $this->config['type'], 'wp-query' );
	}

	/**
	 * Get the full configuration array.
	 *
	 * @return array<string, mixed>
	 */
	public function get_config(): array {
		return $this->config;
	}

	/**
	 * Get the loop arguments for wp-query, wp-terms, or wp-users types.
	 *
	 * @return array<string, mixed>
	 */
	public function get_args(): array {
		return EtchTypeAsserter::to_array( $this->config['args'] ?? array() );
	}

	/**
	 * Get the JSON data for json type loops.
	 *
	 * @return array<int|string, mixed>
	 */
	public function get_json_data(): array {
		return EtchTypeAsserter::to_array( $this->config['data'] ?? array() );
	}

	/**
	 * Check if this loop is of a specific type.
	 *
	 * @param string $type The type to check against.
	 * @return bool
	 */
	public function is_type( string $type ): bool {
		return $this->get_type() === $type;
	}

	/**
	 * Check if this is a valid global loop.
	 *
	 * @return bool
	 */
	public function is_valid(): bool {
		return $this->is_valid;
	}

	/**
	 * Convert the loop back to array format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'name'   => $this->name,
			'key'    => $this->key,
			'global' => $this->global,
			'config' => $this->config,
		);
	}

		/**
		 * Initialize default values.
		 *
		 * @return void
		 */
	private function initialize_defaults(): void {
		$this->name     = '';
		$this->key      = '';
		$this->global   = true;
		$this->config   = array();
		$this->is_valid = false;
	}

	/**
	 * Extract and validate the loop data.
	 *
	 * @param array<string, mixed> $loop_data Raw loop data.
	 * @return void
	 */
	private function extract_and_validate_data( array $loop_data ): void {
		// Validate required fields
		if ( ! isset( $loop_data['name'] ) || ! is_string( $loop_data['name'] ) ) {
			return;
		}

		if ( ! isset( $loop_data['key'] ) || ! is_string( $loop_data['key'] ) ) {
			return;
		}

		if ( ! isset( $loop_data['config'] ) || ! is_array( $loop_data['config'] ) ) {
			return;
		}

		// Validate loop type
		$type = $loop_data['config']['type'] ?? '';
		if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
			return;
		}

		// Validate type-specific data
		if ( ! $this->is_valid_type_specific_data( $loop_data['config'] ) ) {
			return;
		}

		// Set properties
		$this->name     = $loop_data['name'];
		$this->key      = $loop_data['key'];
		$this->global   = EtchTypeAsserter::to_bool( $loop_data['global'] ?? true );
		$this->config   = $loop_data['config'];
		$this->is_valid = true;
	}

		/**
		 * Validate type-specific configuration data.
		 *
		 * @param array<string, mixed> $config The configuration array.
		 * @return bool True if valid type-specific data.
		 */
	private function is_valid_type_specific_data( array $config ): bool {
		$type = $config['type'];

		switch ( $type ) {
			case 'wp-query':
			case 'wp-terms':
			case 'wp-users':
				return isset( $config['args'] ) && is_array( $config['args'] );

			case 'main-query':
				// main-query allows empty args since it inherits from global $wp_query
				return ! isset( $config['args'] ) || is_array( $config['args'] );

			case 'json':
				return isset( $config['data'] ) && is_array( $config['data'] );

			default:
				return false;
		}
	}
}
