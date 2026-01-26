<?php
/**
 * @package DigitalGravy\FeatureFlag
 */

namespace DigitalGravy\FeatureFlag\Exception;

use DigitalGravy\FeatureFlag\Helpers\Functions;

class Invalid_Flag_Key extends \Exception {

	/**
	 * @param string $key The invalid key.
	 */
	public function __construct( string $key ) {
		$message = sprintf( 'Flag key must contain only alphanumeric characters, underscores, and dashes ~ received: %s', Functions::escape_output( $key ) );
		parent::__construct( $message );
	}
}
