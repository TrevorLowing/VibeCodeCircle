<?php
/**
 * @package DigitalGravy\FeatureFlag\Exception
 */

namespace DigitalGravy\FeatureFlag\Exception;

use DigitalGravy\FeatureFlag\Helpers\Functions;

class Flag_Key_Not_Found extends \Exception {

	public function __construct( string $flag_key ) {
		$message = sprintf( 'Flag key not found: %s', Functions::escape_output( $flag_key ) );
		parent::__construct( $message );
	}
}
