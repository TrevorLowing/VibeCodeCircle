<?php
/**
 * Interface for flag storage.
 *
 * @package DigitalGravy\FeatureFlag
 */

namespace DigitalGravy\FeatureFlag\Storage;

use DigitalGravy\FeatureFlag\FeatureFlag;

interface FlagStorageInterface {
	/**
	 * @return array<string,FeatureFlag> Array of flag states where values are 'on' or 'off'
	 * @throws \Exception If unable to retrieve flags.
	 */
	public function get_flags(): array;
}
