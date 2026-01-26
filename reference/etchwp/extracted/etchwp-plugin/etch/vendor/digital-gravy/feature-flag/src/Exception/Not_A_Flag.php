<?php
/**
 * @package DigitalGravy\FeatureFlag\Exception
 */

namespace DigitalGravy\FeatureFlag\Exception;

class Not_A_Flag extends \Exception {

	public function __construct() {
		parent::__construct( 'Invalid flag type' );
	}
}
