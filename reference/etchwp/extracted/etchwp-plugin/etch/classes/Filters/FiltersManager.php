<?php
/**
 * Filters Manager for Etch.
 *
 * Initializes various filter-related functionalities.
 *
 * @package Etch\Filters
 */

declare(strict_types=1);

namespace Etch\Filters;

use Etch\Traits\Singleton;
use Etch\Filters\CustomStyles;

/**
 * Manages the initialization of filter-related classes.
 */
class FiltersManager {

	use Singleton;

	/**
	 * Initialize all managed filters.
	 *
	 * @return void
	 */
	public function init(): void {
		// Initialize Custom Styles persistence
		CustomStyles::get_instance()->init();

		// Add initialization for other filter classes here in the future.
	}
}
