<?php
/**
 * CssToolbarService.php
 *
 * This file contains the CssToolbarService class which provides methods for managing snippets.
 *
 * PHP version 7.4+
 *
 * @category  Plugin
 * @package   Etch\RestApi\Services
 */

declare(strict_types=1);
namespace Etch\Services;

use Etch\Traits\Singleton;
use WP_Error;

/**
 * CssToolbarService
 *
 * This class provides methods for managing snippets.
 *
 * @phpstan-type SnippetOption array{
 *     name?: string,
 *     values: array<string>
 * }
 *
 * @phpstan-type SnippetDefinition array{
 *     defaultOption?: SnippetOption,
 *     additionalOptions?: array<SnippetOption>
 * }
 *
 * @phpstan-type SnippetObject array<string, SnippetDefinition>
 *
 * @package Etch\RestApi\Services
 */
class CssToolbarService {

	use Singleton;

	/**
	 * Option name where snippets are stored.
	 *
	 * @var string
	 */
	private $option_name = 'etch_css_toolbar_values';

	/**
	 * Initialize the CssToolbarService.
	 *
	 * @return void
	 */
	public function init(): void {
	}

	/**
	 * Retrieve all snippets.
	 *
	 * @return SnippetObject
	 */
	public function get_snippets() {
		$snippets = get_option( $this->option_name, array() );

		return is_array( $snippets ) ? $snippets : array();
	}

	/**
	 * Retrieve one snippets.
	 *
	 * @param string $id Snippet ID.
	 * @return SnippetDefinition|WP_Error
	 */
	public function get_snippet( string $id ) {
		$snippets = $this->get_snippets();

		if ( ! isset( $snippets[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Snippet not found', array( 'status' => 404 ) );
		}

		return $snippets[ $id ];
	}

	/**
	 * Update multiple snippets.
	 *
	 * @param SnippetObject $new_snippets New snippets to save.
	 * @return bool Return true if the value was updated or false otherwise.
	 */
	public function update_snippets( $new_snippets ): bool {
		return update_option( $this->option_name, $new_snippets, false );
	}

	/**
	 * Update an existing snippet.
	 *
	 * @param string            $id         The ID of the snippet to update.
	 * @param SnippetDefinition $snippet  s The updated snippet data.
	 * @return bool|WP_Error
	 */
	public function update_snippet( $id, $snippet ): bool|WP_Error {
		if ( ! isset( $snippet['defaultOption'] ) && ! isset( $snippet['additionalOptions'] ) ) {
			return new WP_Error( 'invalid_data', 'Invalid data provided', array( 'status' => 400 ) );
		}

		$snippets = $this->get_snippets();

		if ( ! isset( $snippets[ $id ] ) ) {
			$snippets[ $id ] = array();
		}

		$snippets[ $id ]['defaultOption'] = $snippet['defaultOption'];
		$snippets[ $id ]['additionalOptions'] = $snippet['additionalOptions'];

		if ( ! $this->update_snippets( $snippets ) ) {
			return new WP_Error( 'update_failed', 'Failed to update the snippet', array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Delete a snippet by ID.
	 *
	 * @param string $id The ID of the snippet to delete.
	 * @return bool|WP_Error
	 */
	public function delete_snippet( $id ): bool|WP_Error {
		$snippets = $this->get_snippets();

		if ( ! isset( $snippets[ $id ] ) ) {
			return new WP_Error( 'not_found', 'Snippet not found', array( 'status' => 404 ) );
		}

		unset( $snippets[ $id ] );

		if ( ! $this->update_snippets( $snippets ) ) {
			return new WP_Error( 'delete_failed', 'Failed to delete the snippet', array( 'status' => 500 ) );
		}

		return true;
	}
}
