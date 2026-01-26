<?php
/**
 * EtchData class for Etch plugin
 *
 * This file contains the EtchData class which is responsible for
 * extracting and validating etch data from blocks.
 *
 * @package Etch
 */

namespace Etch\Preprocessor\Data;

use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * EtchData class for handling block data extraction and validation.
 */
class EtchData {

	/**
	 * The block type.
	 *
	 * @var string|null
	 */
	public $type;

	/**
	 * The HTML tag.
	 *
	 * @var string
	 */
	public $tag;

	/**
	 * Block attributes.
	 *
	 * @var array<string, string>
	 */
	public $attributes;

	/**
	 * Block styles.
	 *
	 * @var array<string>
	 */
	public $styles;

	/**
	 * Block origin.
	 *
	 * @var string|null
	 */
	public $origin;

	/**
	 * Whether the block is hidden.
	 *
	 * @var bool
	 */
	public $hidden;

	/**
	 * Block name.
	 *
	 * @var string|null
	 */
	public $name;

	/**
	 * Whether to remove wrapper.
	 *
	 * @var bool
	 */
	public $removeWrapper;

	/**
	 * Miscellaneous data.
	 *
	 * @var array<string, mixed>
	 */
	public $misc;

	/**
	 * Script data.
	 *
	 * @var array{id: string, code: string}|null
	 */
	public $script;

	/**
	 * Nested data.
	 *
	 * @var array<string, EtchData>
	 */
	public $nestedData;

	/**
	 * Loop data.
	 *
	 * @var EtchDataLoop|null
	 */
	public $loop;

	/**
	 * Component ID.
	 *
	 * @var int|null
	 */
	public $component;

	/**
	 * Inner Blocks.
	 *
	 * @var array<int|string, array<mixed>>|null
	 */
	public $innerBlocks;

	/**
	 * Condition data.
	 *
	 * @var EtchDataCondition|null
	 */
	public $condition;

	/**
	 * Condition string.
	 *
	 * @var string|null
	 */
	public $conditionString;

	/**
	 * Slot name.
	 *
	 * @var string|null
	 */
	public $slot;

	/**
	 * Specialized block type (e.g., 'svg' for SVG blocks).
	 *
	 * @var string|null
	 */
	public $specialized;

	/**
	 * Whether this is a valid etch block.
	 *
	 * @var bool
	 */
	private $isValid = false;

	/**
	 * Raw etch data.
	 *
	 * @var array<string, mixed>
	 */
	public readonly array $raw_data;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $rawData Raw etch data from block attributes.
	 */
	public function __construct( $rawData = array() ) {
		$this->initialize_defaults();
		$this->extract_data( $rawData );

		// Store raw data for reference
		$this->raw_data = $rawData;
	}

	/**
	 * Create EtchData from block.
	 *
	 * @param array<string, mixed> $block Block data.
	 * @return self|null EtchData instance or null if not valid.
	 */
	public static function from_block( $block ) {
		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			return null;
		}

		if ( ! isset( $block['attrs']['metadata'] ) || ! is_array( $block['attrs']['metadata'] ) ) {
			return null;
		}

		if ( ! isset( $block['attrs']['metadata']['etchData'] ) || ! is_array( $block['attrs']['metadata']['etchData'] ) ) {
			return null;
		}

		$etchData = new self( $block['attrs']['metadata']['etchData'] );
		return $etchData->is_valid() ? $etchData : null;
	}

	/**
	 * Check if this is a valid etch block.
	 *
	 * @return bool True if valid etch block.
	 */
	public function is_valid() {
		return $this->isValid;
	}

	/**
	 * Check if this is an etch block.
	 *
	 * @return bool True if etch block.
	 */
	public function is_etch_block() {
		// TODO: Remove the Gutenberg condition once all blocks have been converted to Etch Origin.
		return 'etch' === $this->origin || 'gutenberg' === $this->origin;
	}


	/**
	 * Check if has nested data.
	 *
	 * @return bool True if has nested data.
	 */
	public function has_nested_data() {
		return ! empty( $this->nestedData );
	}

	/**
	 * Get nested data for a specific key.
	 *
	 * @param string $key Nested data key.
	 * @return EtchData|null Nested data or null.
	 */
	public function get_nested_data( $key ) {
		return $this->nestedData[ $key ] ?? null;
	}

	/**
	 * Check if this is a text block.
	 *
	 * @return bool True if text block.
	 */
	public function is_text_block() {
		return 'text' === $this->type;
	}

	/**
	 * Check if this is an HTML block.
	 *
	 * @return bool True if HTML block.
	 */
	public function is_html_block() {
		return 'html' === $this->type;
	}

	/**
	 * Check if this is a loop block.
	 *
	 * @return bool True if loop block.
	 */
	public function is_loop_block() {
		return 'loop' === $this->type;
	}

	/**
	 * Check if this is a component block.
	 *
	 * @return bool True if component block.
	 */
	public function is_component_block() {
		return 'component' === $this->type;
	}

	/**
	 * Check if this is a condition block.
	 *
	 * @return bool True if condition block.
	 */
	public function is_condition_block() {
		return 'condition' === $this->type;
	}

	/**
	 * Check if this is a slot block.
	 *
	 * @return bool True if slot block.
	 */
	public function is_slot_block() {
		return 'slot' === $this->type;
	}

	/**
	 * Check if this is a slot placeholder block.
	 *
	 * @return bool True if slot placeholder block.
	 */
	public function is_slot_placeholder() {
		return 'slot-placeholder' === $this->type;
	}

	/**
	 * Set the block type.
	 *
	 * @param string|null $type Block type.
	 * @return void
	 */
	public function set_type( ?string $type ): void {
		$this->type = $type;
	}

	/**
	 * Set the HTML tag.
	 *
	 * @param string $tag HTML tag.
	 * @return void
	 */
	public function set_tag( string $tag ): void {
		$this->tag = $tag;
	}

	/**
	 * Set block attributes.
	 *
	 * @param array<string, string> $attributes Block attributes.
	 * @return void
	 */
	public function set_attributes( array $attributes ): void {
		$this->attributes = $attributes;
	}

	/**
	 * Add or update a single attribute.
	 *
	 * @param string $key Attribute key.
	 * @param string $value Attribute value.
	 * @return void
	 */
	public function set_attribute( string $key, string $value ): void {
		$this->attributes[ $key ] = $value;
	}

	/**
	 * Remove an attribute.
	 *
	 * @param string $key Attribute key to remove.
	 * @return void
	 */
	public function remove_attribute( string $key ): void {
		if ( isset( $this->attributes[ $key ] ) ) {
			unset( $this->attributes[ $key ] );
		}
	}

	/**
	 * Set block styles.
	 *
	 * @param array<string> $styles Block styles.
	 * @return void
	 */
	public function set_styles( array $styles ): void {
		$this->styles = $styles;
	}

	/**
	 * Add a style.
	 *
	 * @param string $style Style to add.
	 * @return void
	 */
	public function add_style( string $style ): void {
		if ( ! in_array( $style, $this->styles, true ) ) {
			$this->styles[] = $style;
		}
	}

	/**
	 * Remove a style.
	 *
	 * @param string $style Style to remove.
	 * @return void
	 */
	public function remove_style( string $style ): void {
		$this->styles = array_values(
			array_filter(
				$this->styles,
				function ( $s ) use ( $style ) {
					return $s !== $style;
				}
			)
		);
	}

	/**
	 * Set the origin.
	 *
	 * @param string|null $origin Block origin.
	 * @return void
	 */
	public function set_origin( ?string $origin ): void {
		$this->origin = $origin;
		// Update validity when origin changes
		// TODO: Remove the Gutenberg condition once all blocks have been converted to Etch Origin.
		$this->isValid = ( 'etch' === $this->origin || 'gutenberg' === $this->origin );
	}

	/**
	 * Set the hidden state.
	 *
	 * @param bool $hidden Whether the block is hidden.
	 * @return void
	 */
	public function set_hidden( bool $hidden ): void {
		$this->hidden = $hidden;
	}

	/**
	 * Set the block name.
	 *
	 * @param string|null $name Block name.
	 * @return void
	 */
	public function set_name( ?string $name ): void {
		$this->name = $name;
	}

	/**
	 * Set the remove wrapper flag.
	 *
	 * @param bool $removeWrapper Whether to remove wrapper.
	 * @return void
	 */
	public function set_remove_wrapper( bool $removeWrapper ): void {
		$this->removeWrapper = $removeWrapper;
	}

	/**
	 * Set miscellaneous data.
	 *
	 * @param array<string, mixed> $misc Miscellaneous data.
	 * @return void
	 */
	public function set_misc( array $misc ): void {
		$this->misc = $misc;
	}

	/**
	 * Set a miscellaneous data item.
	 *
	 * @param string $key Data key.
	 * @param mixed  $value Data value.
	 * @return void
	 */
	public function set_misc_item( string $key, $value ): void {
		$this->misc[ $key ] = $value;
	}

	/**
	 * Set script data.
	 *
	 * @param array{id: string, code: string}|null $script Script data.
	 * @return void
	 */
	public function set_script( ?array $script ): void {
		$this->script = $script;
	}

	/**
	 * Set nested data.
	 *
	 * @param array<string, EtchData> $nestedData Nested data.
	 * @return void
	 */
	public function set_nested_data( array $nestedData ): void {
		$this->nestedData = $nestedData;
	}

	/**
	 * Add nested data.
	 *
	 * @param string   $key Nested data key.
	 * @param EtchData $data Nested data.
	 * @return void
	 */
	public function add_nested_data( string $key, EtchData $data ): void {
		$this->nestedData[ $key ] = $data;
	}

	/**
	 * Remove nested data.
	 *
	 * @param string $key Nested data key to remove.
	 * @return void
	 */
	public function remove_nested_data( string $key ): void {
		unset( $this->nestedData[ $key ] );
	}

	/**
	 * Set loop data.
	 *
	 * @param EtchDataLoop|null $loop Loop data.
	 * @return void
	 */
	public function set_loop( ?EtchDataLoop $loop ): void {
		$this->loop = $loop;
	}

	/**
	 * Set component ID.
	 *
	 * @param int|null $component Component ID.
	 * @return void
	 */
	public function set_component( ?int $component ): void {
		$this->component = $component;
	}

	/**
	 * Set condition data.
	 *
	 * @param EtchDataCondition|null $condition Condition data.
	 * @return void
	 */
	public function set_condition( ?EtchDataCondition $condition ): void {
		$this->condition = $condition;
	}

	/**
	 * Set condition string.
	 *
	 * @param string|null $conditionString Condition string.
	 * @return void
	 */
	public function set_condition_string( ?string $conditionString ): void {
		$this->conditionString = $conditionString;
	}

	/**
	 * Set slot name.
	 *
	 * @param string|null $slot Slot name.
	 * @return void
	 */
	public function set_slot( ?string $slot ): void {
		$this->slot = $slot;
	}

	/**
	 * Initialize default values.
	 *
	 * @return void
	 */
	private function initialize_defaults() {
		$this->type = null;
		$this->tag = 'div';
		$this->attributes = array();
		$this->styles = array();
		$this->origin = null;
		$this->hidden = false;
		$this->name = null;
		$this->removeWrapper = false;
		$this->misc = array();
		$this->script = null;
		$this->nestedData = array();
		$this->loop = null;
		$this->component = null;
		$this->innerBlocks = null;
		$this->condition = null;
		$this->conditionString = null;
		$this->slot = null;
	}

	/**
	 * Extract data from raw etch data.
	 *
	 * @param array<string, mixed> $rawData Raw etch data.
	 * @return void
	 */
	private function extract_data( $rawData ) {
		if ( ! is_array( $rawData ) ) {
			return;
		}

		// Extract origin first to validate
		$this->origin = $this->extract_string( $rawData, 'origin' );

		// Mark as valid if origin is etch
		// TODO: Remove the Gutenberg condition once all blocks have been converted to Etch Origin.
		$this->isValid = ( 'etch' === $this->origin || 'gutenberg' === $this->origin );

		// Extract basic properties
		$this->name = $this->extract_string( $rawData, 'name' );
		$this->hidden = $this->extract_bool( $rawData, 'hidden' );
		$this->removeWrapper = $this->extract_bool( $rawData, 'removeWrapper' );

		// Extract arrays
		$this->styles = $this->extract_indexed_string_array( $rawData, 'styles' );
		$this->attributes = $this->extract_string_array( $rawData, 'attributes' );
		$this->misc = $this->extract_array( $rawData, 'misc' );
		$this->nestedData = $this->extract_nested_array( $rawData, 'nestedData' );

		// Extract script
		$this->script = $this->extract_script( $rawData );

		// Extract block data
		$this->extract_block_data( $rawData );
	}

	/**
	 * Extract block data.
	 *
	 * @param array<string, mixed> $rawData Raw etch data.
	 * @return void
	 */
	private function extract_block_data( $rawData ) {
		if ( ! isset( $rawData['block'] ) || ! is_array( $rawData['block'] ) ) {
			return;
		}

		$blockData = $rawData['block'];
		$this->type = $this->extract_string( $blockData, 'type' );

		switch ( $this->type ) {
			case 'html':
				$this->tag = $this->extract_string( $blockData, 'tag', 'div' ) ?? 'div';
				$this->specialized = $this->extract_string( $blockData, 'specialized' );
				break;

			case 'loop':
				if ( isset( $blockData['loop'] ) ) {
					$this->loop = EtchDataLoop::from_array( $blockData['loop'] );
				}
				break;

			case 'component':
				$this->component = $this->extract_int( $blockData, 'component' );

				if ( isset( $blockData['innerBlocks'] ) && is_array( $blockData['innerBlocks'] ) ) {
					$this->innerBlocks = $blockData['innerBlocks'];
				}

				break;

			case 'condition':
				if ( isset( $blockData['condition'] ) ) {
					$this->condition = EtchDataCondition::from_array( $blockData['condition'] );
				}
				$this->conditionString = $this->extract_string( $blockData, 'conditionString' );
				break;

			case 'slot':
			case 'slot-placeholder':
				$this->slot = $this->extract_string( $blockData, 'slot' );
				break;
		}
	}

	/**
	 * Extract string value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @param string|null          $default Default value.
	 * @return string|null Extracted string or default.
	 */
	private function extract_string( $data, $key, $default = null ) {
		if ( ! isset( $data[ $key ] ) ) {
			return $default;
		}

		$value = EtchTypeAsserter::to_string_or_null( $data[ $key ] );
		return $value ?? $default;
	}

	/**
	 * Extract boolean value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @param bool                 $default Default value.
	 * @return bool Extracted boolean or default.
	 */
	private function extract_bool( $data, $key, $default = false ) {
		if ( ! isset( $data[ $key ] ) ) {
			return $default;
		}

		return EtchTypeAsserter::to_bool( $data[ $key ] );
	}

	/**
	 * Extract integer value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @param int|null             $default Default value.
	 * @return int|null Extracted integer or default.
	 */
	private function extract_int( $data, $key, $default = null ) {
		if ( ! isset( $data[ $key ] ) ) {
			return $default;
		}

		return is_int( $data[ $key ] ) ? $data[ $key ] : $default;
	}

	/**
	 * Extract array value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @return array<string, mixed> Extracted array or empty array.
	 */
	private function extract_array( $data, $key ) {
		if ( ! isset( $data[ $key ] ) ) {
			return array();
		}

		return EtchTypeAsserter::to_array( $data[ $key ] );
	}

	/**
	 * Extract string array value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @return array<string, string> Extracted string array or empty array.
	 */
	private function extract_string_array( $data, $key ) {
		$array = $this->extract_array( $data, $key );
		$stringArray = array();

		foreach ( $array as $arrayKey => $value ) {
			if ( is_string( $arrayKey ) && is_string( $value ) ) {
				$stringArray[ $arrayKey ] = $value;
			}
		}

		return $stringArray;
	}

	/**
	 * Extract indexed string array value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @return array<string> Extracted indexed string array or empty array.
	 */
	private function extract_indexed_string_array( $data, $key ) {
		$array = $this->extract_array( $data, $key );
		$stringArray = array();

		foreach ( $array as $value ) {
			if ( is_string( $value ) ) {
				$stringArray[] = $value;
			}
		}

		return $stringArray;
	}

	/**
	 * Extract nested array data safely and convert to EtchData instances.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @return array<string, EtchData> Extracted nested EtchData instances or empty array.
	 */
	private function extract_nested_array( $data, $key ) {
		$array = $this->extract_array( $data, $key );
		$nestedArray = array();

		foreach ( $array as $arrayKey => $value ) {
			if ( is_string( $arrayKey ) && is_array( $value ) ) {
				$nestedEtchData = new self( $value );
				if ( $nestedEtchData->is_valid() ) {
					$nestedArray[ $arrayKey ] = $nestedEtchData;
				}
			}
		}

		return $nestedArray;
	}

	/**
	 * Extract script data safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @return array{id: string, code: string}|null Extracted script or null.
	 */
	private function extract_script( $data ) {
		if ( ! isset( $data['script'] ) || ! is_array( $data['script'] ) ) {
			return null;
		}

		$script = $data['script'];
		$id = $this->extract_string( $script, 'id' );
		$code = $this->extract_string( $script, 'code' );

		if ( null === $id || null === $code ) {
			return null;
		}

		return array(
			'id'   => $id,
			'code' => $code,
		);
	}
}
