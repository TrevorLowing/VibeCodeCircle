<?php
/**
 * File Not Readable Exception
 *
 * @package DigitalGravy\FeatureFlag\Storage\Exception
 */

namespace DigitalGravy\FeatureFlag\Storage\Exception;

class FileNotReadableException extends \RuntimeException {

	public function __construct( string $filePath ) {
		parent::__construct( sprintf( 'JSON file is not readable at path: %s', $filePath ) );
	}
}
