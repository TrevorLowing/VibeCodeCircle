<?php
/**
 * EtchDataLoop class for Etch plugin
 *
 * This file contains the EtchDataLoop class which handles
 * loop data structures and validation.
 *
 * @package Etch
 */

namespace Etch\Preprocessor\Data;

use Etch\Helpers\Logger;
use Etch\Preprocessor\Utilities\EtchTypeAsserter;

/**
 * EtchDataLoop class for handling loop data.
 */
class EtchDataLoop {

	/**
	 * Optional item ID.
	 *
	 * @var string|null
	 */
	public $itemId;

	/**
	 * Optional index ID.
	 *
	 * @var string|null
	 */
	public $indexId;


	/**
	 * Target.
	 *
	 * @var string|null
	 */
	public $target;

	/**
	 * Version (for legacy handling).
	 *
	 * @var int|null
	 */
	public $version;

	/**
	 * Loop parameters given on the block.
	 *
	 * @var array<string, mixed>|null
	 */
	public $loopParams;


	/**
	 * Constructor.
	 *
	 * @param string|null               $itemId Optional item ID.
	 * @param string|null               $indexId Optional index ID.
	 * @param string|null               $target Target (variant 2).
	 * @param array<string, mixed>|null $loopParams Loop parameters.
	 * @param int|null                  $version Version.
	 */
	public function __construct( $itemId = null, $indexId = null, $target = null, $loopParams = null, $version = null ) {
		$this->itemId = $itemId;
		$this->indexId = $indexId;
		$this->target = $target;
		$this->loopParams = $loopParams;
		$this->version = $version;
	}

	/**
	 * Create EtchDataLoop from array data.
	 *
	 * @param array<string, mixed> $data Loop data array.
	 * @return self|null EtchDataLoop instance or null if invalid.
	 */
	public static function from_array( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$loopParams = isset( $data['loopParams'] ) ? EtchTypeAsserter::to_array( $data['loopParams'] ) : null;

		// Extract optional itemId
		$itemId = self::extract_string( $data, 'itemId' );
		$indexId = self::extract_string( $data, 'indexId' );

		$version = self::extract_string( $data, 'version' );
		$version = null !== $version ? intval( $version ) : null;

		$loopTarget = null;

		// ! LEGACY SUPPORT: Check for variant 1: loopId
		$loopId = self::extract_string( $data, 'loopId' );
		if ( null !== $loopId ) {
			// Loop ID becomes target in the new variant
			$loopTarget = $loopId;
		}

		// ! LEGACY SUPPORT: Check for variant 2: targetItemId and targetPath
		$targetItemId = self::extract_string( $data, 'targetItemId' );
		$targetPath = self::extract_string( $data, 'targetPath' );
		if ( null !== $targetItemId && null !== $targetPath ) {
			// Combine to form target
			$loopTarget = $targetItemId . '.' . $targetPath;
		}

		// NEW VARIANT: Check for target directly
		$target = self::extract_string( $data, 'target' );
		if ( null !== $target ) {
			$loopTarget = $target;
		}

		if ( null !== $loopTarget ) {
			return new self( $itemId, $indexId, $loopTarget, $loopParams, $version );
		}

		// Neither variant is complete
		return null;
	}

	/**
	 * Extract string value safely.
	 *
	 * @param array<string, mixed> $data Data array.
	 * @param string               $key Key to extract.
	 * @return string|null Extracted string or null.
	 */
	private static function extract_string( $data, $key ) {
		if ( ! isset( $data[ $key ] ) ) {
			return null;
		}

		return EtchTypeAsserter::to_string_or_null( $data[ $key ] );
	}


	/**
	 * Get the loop identifier based on variant.
	 *
	 * @return string|null Loop identifier.
	 */
	public function get_loop_identifier() {
		if ( null !== $this->target ) {
			return $this->target;
		}

		return null;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array<string, array<string, mixed>|string|int> Array representation.
	 */
	public function to_array() {
		$result = array();

		if ( null !== $this->itemId ) {
			$result['itemId'] = $this->itemId;
		}

		if ( null !== $this->target ) {
			$result['target'] = $this->target;
		}

		if ( null !== $this->loopParams ) {
			$result['loopParams'] = $this->loopParams;
		}

		if ( null !== $this->indexId ) {
			$result['indexId'] = $this->indexId;
		}

		if ( null !== $this->version ) {
			$result['version'] = $this->version;
		}

		return $result;
	}

	/**
	 * Validate that the loop data is consistent.
	 *
	 * @return bool True if valid.
	 */
	public function is_valid() {
		return null !== $this->target;
	}
}
