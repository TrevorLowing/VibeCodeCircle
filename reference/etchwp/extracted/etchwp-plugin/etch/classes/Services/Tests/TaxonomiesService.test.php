<?php
/**
 * TaxonomiesServiceTest.php
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Services\Tests;

use Etch\Services\TaxonomiesService;
use WP_UnitTestCase;

/**
 * Class TaxonomiesServiceTest
 *
 * Tests the TaxonomiesService class.
 */
class TaxonomiesServiceTest extends WP_UnitTestCase {

	/**
	 * Test creating a taxonomy.
	 */
	public function test_get_taxonomy_returns_array_from_option(): void {
		// Arrange
		update_option(
			'etch_taxonomies',
			array(
				'manufacturer' => array(
					'args' => array(
						'label' => 'Manufacturer',
						'labels' => array(
							'name' => 'Manufacturers',
							'singular_name' => 'Manufacturer',
						),
						'public' => true,
						'hierarchical' => false,
						'capabilities' => array(
							'manage_terms' => 'manage_categories',
						),
					),
					'object_types' => array( 'car' ),
				),
			)
		);

		$service = new TaxonomiesService();

		// Act
		$result = $service->get_all_etch_taxonomies();

		// Assert
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'manufacturer', $result );

		$taxonomy = $result['manufacturer'];
		$this->assertEquals( 'Manufacturer', $taxonomy['args']['label'] );
		$this->assertEquals( 'Manufacturers', $taxonomy['args']['labels']['name'] );
		$this->assertEquals( 'Manufacturer', $taxonomy['args']['labels']['singular_name'] );
		$this->assertEquals( true, $taxonomy['args']['public'] );
		$this->assertEquals( false, $taxonomy['args']['hierarchical'] );
		$this->assertEquals( 'manage_categories', $taxonomy['args']['capabilities']['manage_terms'] );
		$this->assertEquals( array( 'car' ), $taxonomy['object_types'] );
	}

	/**
	 * Test creating a new taxonomy.
	 */
	public function test_create_method(): void {
		$service = new TaxonomiesService();

		$id = 'genre';
		$args = array(
			'label' => 'Genre',
			'public' => true,
		);
		$object_types = array( 'movie' );

		$response = $service->create( $id, $args, $object_types );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 201, $response->get_status() );

		$taxonomies = get_option( 'etch_taxonomies' );
		$this->assertArrayHasKey( 'genre', $taxonomies );
		$this->assertEquals( 'Genre', $taxonomies['genre']['args']['label'] );
		$this->assertEquals( array( 'movie' ), $taxonomies['genre']['object_types'] );
	}

	/**
	 * Test creating a new taxonomy.
	 */
	public function test_create_method_fails_if_taxonomy_already_exists(): void {
		update_option(
			'etch_taxonomies',
			array(
				'brand' => array(
					'args' => array( 'label' => 'Brand' ),
					'object_types' => array( 'car' ),
				),
			)
		);

		$service = new TaxonomiesService();

		$response = $service->create(
			'brand',
			array( 'label' => 'Brand2' ),
			array( 'car' ),
		);

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertEquals( 'taxonomy_exists', $response->get_error_code() );
		$this->assertEquals( 409, $response->get_error_data()['status'] );
	}

	/**
	 * Test updating a taxonomy.
	 */
	public function test_update_method_success(): void {
		update_option(
			'etch_taxonomies',
			array(
				'brand' => array(
					'args' => array( 'label' => 'Brand' ),
					'object_types' => array( 'car' ),
				),
			)
		);

		$service = new TaxonomiesService();

		$response = $service->update(
			'brand',
			array( 'label' => 'Updated Brand' ),
			array( 'vehicle' ),
		);

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$taxonomies = get_option( 'etch_taxonomies' );
		$this->assertEquals( 'Updated Brand', $taxonomies['brand']['args']['label'] );
		$this->assertEquals( array( 'vehicle' ), $taxonomies['brand']['object_types'] );
	}

	/**
	 * Test updating a taxonomy that does not exist.
	 */
	public function test_update_method_fails_if_taxonomy_not_found(): void {
		$service = new TaxonomiesService();

		$response = $service->update(
			'nonexistent',
			array( 'label' => 'Ghost' ),
			array( 'ghost_type' ),
		);

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertEquals( 'taxonomy_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test deleting a taxonomy.
	 */
	public function test_delete_method_success(): void {
		update_option(
			'etch_taxonomies',
			array(
				'to_delete' => array(
					'args' => array( 'label' => 'To Delete' ),
					'object_types' => array( 'trash' ),
				),
			)
		);

		$service = new TaxonomiesService();

		$response = $service->delete( 'to_delete' );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$taxonomies = get_option( 'etch_taxonomies' );
		$this->assertArrayNotHasKey( 'to_delete', $taxonomies );
	}

	/**
	 * Test deleting a taxonomy that does not exist.
	 */
	public function test_delete_method_fails_if_taxonomy_not_found(): void {
		$service = new TaxonomiesService();

		$response = $service->delete( 'nonexistent' );

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertEquals( 'taxonomy_not_found', $response->get_error_code() );
		$this->assertEquals( 404, $response->get_error_data()['status'] );
	}

	/**
	 * Test mapping args to WP args.
	 */
	public function test_map_args_to_wp_args_maps_correctly(): void {
		$service = new TaxonomiesService();

		$args = array(
			'labels' => array(
				'pluralName'    => 'Genres',
				'menuName'      => 'Genre Menu',
				'singularName'  => 'Genre',
				'allItems'      => 'All Genres',
				'editItem'      => 'Edit Genre',
				'viewItem'      => 'View Genre',
				'updateItem'    => 'Update Genre',
				'addNewItem'    => 'Add New Genre',
			),
			'description'         => 'A genre classification',
			'public'              => true,
			'hierarchical'        => false,
			'showUi'              => true,
			'showInMenu'          => true,
			'showInNavMenus'      => false,
			'showInRest'          => false,
			'showInQuickEdit'     => true,
			'showInAdminColumn'   => true,
			'capabilities'        => array(
				'manageTerms' => 'manage_genres',
				'editTerms'   => 'edit_genres',
				'deleteTerms' => 'delete_genres',
				'assignTerms' => 'assign_genres',
			),
		);

		$mapped = $service->map_args_to_wp_args( 'genre', $args );

		$this->assertIsArray( $mapped );

		// Labels
		$this->assertEquals( 'Genres', $mapped['labels']['name'] );
		$this->assertEquals( 'Genre Menu', $mapped['labels']['menu_name'] );
		$this->assertEquals( 'Genre', $mapped['labels']['singular_name'] );
		$this->assertEquals( 'All Genres', $mapped['labels']['all_items'] );
		$this->assertEquals( 'Edit Genre', $mapped['labels']['edit_item'] );
		$this->assertEquals( 'View Genre', $mapped['labels']['view_item'] );
		$this->assertEquals( 'Update Genre', $mapped['labels']['update_item'] );
		$this->assertEquals( 'Add New Genre', $mapped['labels']['add_new_item'] );

		// Core args
		$this->assertEquals( 'A genre classification', $mapped['description'] );
		$this->assertTrue( $mapped['public'] );
		$this->assertFalse( $mapped['hierarchical'] );
		$this->assertTrue( $mapped['show_ui'] );
		$this->assertTrue( $mapped['show_in_menu'] );
		$this->assertFalse( $mapped['show_in_nav_menus'] );
		$this->assertFalse( $mapped['show_in_rest'] );
		$this->assertTrue( $mapped['show_in_quick_edit'] );
		$this->assertTrue( $mapped['show_in_admin_column'] );

		// Capabilities
		$this->assertEquals( 'manage_genres', $mapped['capabilities']['manage_terms'] );
		$this->assertEquals( 'edit_genres', $mapped['capabilities']['edit_terms'] );
		$this->assertEquals( 'delete_genres', $mapped['capabilities']['delete_terms'] );
		$this->assertEquals( 'assign_genres', $mapped['capabilities']['assign_terms'] );
	}

	/**
	 * Test mapping args to WP args with no args.
	 */
	public function test_map_args_to_wp_args_applies_defaults(): void {
		$service = new TaxonomiesService();

		$mapped = $service->map_args_to_wp_args( 'genre', array() );

		$this->assertIsArray( $mapped );
		$this->assertEquals( '', $mapped['description'] );
		$this->assertTrue( $mapped['public'] );
		$this->assertFalse( $mapped['hierarchical'] );
		$this->assertTrue( $mapped['show_ui'] );
		$this->assertTrue( $mapped['show_in_menu'] );
		$this->assertTrue( $mapped['show_in_nav_menus'] );
		$this->assertTrue( $mapped['show_in_rest'] );
		$this->assertFalse( $mapped['show_in_quick_edit'] );
		$this->assertFalse( $mapped['show_in_admin_column'] );
		$this->assertEquals( array(), $mapped['capabilities'] );
	}

	/**
	 * Test validating taxonomy input with valid data.
	 */
	public function test_validate_taxonomy_input_valid(): void {
		$service = new TaxonomiesService();

		$error = $service->validate_taxonomy_input(
			'genre',
			array( 'label' => 'Genre' ),
			array( 'movie' )
		);

		$this->assertNull( $error );
	}

	/**
	 * Test validation fails with invalid taxonomy ID.
	 */
	public function test_validate_taxonomy_input_fails_with_invalid_id(): void {
		$service = new TaxonomiesService();

		$error = $service->validate_taxonomy_input(
			'', // Invalid ID (empty string)
			array( 'label' => 'Genre' ),
			array( 'movie' )
		);

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertEquals( 'invalid_request', $error->get_error_code() );
	}

	/**
	 * Test validation fails with invalid args.
	 */
	public function test_validate_taxonomy_input_fails_with_invalid_args(): void {
		$service = new TaxonomiesService();

		$error = $service->validate_taxonomy_input(
			'genre',
			null, // Invalid args
			array( 'movie' )
		);

		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertEquals( 'invalid_request', $error->get_error_code() );
	}

	/**
	 * Test validation fails with invalid object types array.
	 */
	public function test_validate_taxonomy_input_fails_with_invalid_object_types(): void {
		$service = new TaxonomiesService();

		$null_error = $service->validate_taxonomy_input(
			'genre',
			array( 'label' => 'Genre' ),
			null // Invalid object types
		);

		$this->assertInstanceOf( \WP_Error::class, $null_error );
		$this->assertEquals( 'invalid_request', $null_error->get_error_code() );

		$comma_error = $service->validate_taxonomy_input(
			'genre',
			array( 'label' => 'Genre' ),
			array( 'invalid,type' ) // contains comma
		);

		$this->assertInstanceOf( \WP_Error::class, $comma_error );
		$this->assertEquals( 'invalid_object_type', $comma_error->get_error_code() );
	}
}
