<?php
/**
 * File Not Found Exception
 *
 * @package DigitalGravy\FeatureFlag\Storage\Exception
 */

namespace DigitalGravy\FeatureFlag\Storage\Exception;

class FileNotFoundException extends \RuntimeException {

	public function __construct( string $filePath ) {
		parent::__construct( sprintf( 'JSON file not found at path: %s', $filePath ) );
	}
}
