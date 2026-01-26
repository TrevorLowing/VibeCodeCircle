<?php
/**
 * TemplatesRoutesTest.php
 *
 * Tests for the TemplatesRoutes class, specifically the theme filtering logic.
 *
 * @package Etch\RestApi\Routes\Tests
 */

declare(strict_types=1);

namespace Etch\RestApi\Routes\Tests;

use Etch\RestApi\Routes\TemplatesRoutes;
use Etch\Services\TemplatesServiceInterface;
use WP_UnitTestCase;

/**
 * Class TemplatesRoutesTest
 *
 * Tests the TemplatesRoutes class.
 */
class TemplatesRoutesTest extends WP_UnitTestCase {

	/**
	 * Create a mock WP_Post object.
	 *
	 * @param int    $id    The post ID.
	 * @param string $title The post title.
	 * @param string $slug  The post slug.
	 * @return object
	 */
	private function create_mock_post( int $id, string $title, string $slug ): object {
		return (object) array(
			'ID'         => $id,
			'post_title' => $title,
			'post_name'  => $slug,
		);
	}

	/**
	 * Test that list_templates includes templates matching the active theme.
	 */
	public function test_list_templates_includes_active_theme_templates(): void {
		$mock_service = $this->createMock( TemplatesServiceInterface::class );

		$mock_service->method( 'get_block_templates' )->willReturn( array() );
		$mock_service->method( 'get_current_theme_slug' )->willReturn( 'etch-theme' );
		$mock_service->method( 'query_templates' )->willReturn(
			array(
				$this->create_mock_post( 1, 'Template One', 'template-one' ),
			)
		);
		$mock_service->method( 'get_template_theme' )->willReturn( 'etch-theme' );

		$routes = new TemplatesRoutes( $mock_service );
		$response = $routes->list_templates();
		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertEquals( 'template-one', $data[0]['slug'] );
		$this->assertEquals( 'etch-theme', $data[0]['theme'] );
	}

	/**
	 * Test that list_templates excludes templates from non-active themes.
	 */
	public function test_list_templates_excludes_non_active_theme_templates(): void {
		$mock_service = $this->createMock( TemplatesServiceInterface::class );

		$mock_service->method( 'get_block_templates' )->willReturn( array() );
		$mock_service->method( 'get_current_theme_slug' )->willReturn( 'etch-theme' );
		$mock_service->method( 'query_templates' )->willReturn(
			array(
				$this->create_mock_post( 1, 'Active Theme Template', 'active-template' ),
				$this->create_mock_post( 2, 'Other Theme Template', 'other-template' ),
			)
		);
		$mock_service->method( 'get_template_theme' )->willReturnMap(
			array(
				array( 1, 'etch-theme' ),
				array( 2, 'twentytwentyfour' ),
			)
		);

		$routes = new TemplatesRoutes( $mock_service );
		$response = $routes->list_templates();
		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertEquals( 'active-template', $data[0]['slug'] );
	}

	/**
	 * Test that list_templates excludes templates with null theme.
	 */
	public function test_list_templates_excludes_null_theme(): void {
		$mock_service = $this->createMock( TemplatesServiceInterface::class );

		$mock_service->method( 'get_block_templates' )->willReturn( array() );
		$mock_service->method( 'get_current_theme_slug' )->willReturn( 'etch-theme' );
		$mock_service->method( 'query_templates' )->willReturn(
			array(
				$this->create_mock_post( 1, 'Active Theme Template', 'active-template' ),
				$this->create_mock_post( 2, 'No Theme Template', 'no-theme-template' ),
			)
		);
		$mock_service->method( 'get_template_theme' )->willReturnMap(
			array(
				array( 1, 'etch-theme' ),
				array( 2, null ),
			)
		);

		$routes = new TemplatesRoutes( $mock_service );
		$response = $routes->list_templates();
		$data = $response->get_data();

		$this->assertCount( 1, $data );
		$this->assertEquals( 'active-template', $data[0]['slug'] );
	}

	/**
	 * Test that list_templates returns empty when no templates match active theme.
	 */
	public function test_list_templates_returns_empty_when_no_matching_templates(): void {
		$mock_service = $this->createMock( TemplatesServiceInterface::class );

		$mock_service->method( 'get_block_templates' )->willReturn( array() );
		$mock_service->method( 'get_current_theme_slug' )->willReturn( 'etch-theme' );
		$mock_service->method( 'query_templates' )->willReturn(
			array(
				$this->create_mock_post( 1, 'Other Template', 'other-template' ),
			)
		);
		$mock_service->method( 'get_template_theme' )->willReturn( 'twentytwentyfour' );

		$routes = new TemplatesRoutes( $mock_service );
		$response = $routes->list_templates();
		$data = $response->get_data();

		$this->assertCount( 0, $data );
	}
}
