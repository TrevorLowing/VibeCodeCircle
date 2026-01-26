<?php
/**
 * ComponentsService.php
 *
 * This file contains the ComponentsService class which defines the service for handling components.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\Services
 */

declare(strict_types=1);

namespace Etch\Services;

/**
 * ComponentsService
 */
class ComponentsService {

	/**
	 * The name of the option that stores the components.
	 *
	 * @var string
	 */
	private string $components_option_name = 'etch_components';

	/**
	 * Delete all components.
	 *
	 * @return array{status: string, message: string} The result of the deletion operation
	 */
	public function delete_all_components(): array {
		$data = get_option( $this->components_option_name, null );

		if ( null === $data ) {
			return array(
				'status'  => 'warning',
				'message' => 'There are no components to delete',
			);
		}

		$deleted = delete_option( $this->components_option_name );

		return $deleted ? array(
			'status'  => 'success',
			'message' => 'All components have been deleted',
		) : array(
			'status'  => 'error',
			'message' => 'Failed to delete components',
		);
	}
}
