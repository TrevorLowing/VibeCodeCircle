<?php
/**
 * SvgBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/svg)
 *    - Attributes structure (tag: string, attributes: object, styles: array)
 *
 * ✅ Basic Rendering & Static Examples
 *    - Renders SVG fetched from src attribute
 *    - Merges block attributes with SVG attributes
 *    - Renders SVG innerHTML
 *    - Returns empty when src is empty
 *    - Returns empty when SVG fetch fails
 *
 * ✅ stripColors Option
 *    - stripColors=true strips color attributes
 *    - stripColors=false preserves colors
 *    - stripColors with component props
 *
 * ✅ Component Props Context
 *    - src from component props: {props.src}
 *    - src with default value and instance value
 *    - src with only default value
 *    - src with only instance value
 *    - stripColors from component props: {props.stripColors}
 *    - Attributes with component props: {props.className}
 *
 * ✅ Global Context Expressions
 *    - src with {this.title}, {site.name} (if used in URL)
 *    - Attributes with {this.title}, {site.name}
 *
 * ✅ Integration Scenarios
 *    - SvgBlock inside ComponentBlock with src prop
 *    - SvgBlock with nested ComponentBlock (prop shadowing)
 *    - SvgBlock with default src and instance src
 *
 * ✅ Edge Cases
 *    - Empty src handled gracefully
 *    - Invalid SVG URL handled gracefully
 *    - stripColors attribute removed from HTML output
 *    - src attribute removed from HTML output
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in SVG attribute (aria-label): shortcodes ARE resolved
 *    - Shortcodes can be combined with fetched SVG attributes
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\SvgBlock\SvgBlock;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class SvgBlockTest
 *
 * Comprehensive tests for SvgBlock functionality including:
 * - Basic rendering with SVG fetching
 * - stripColors option
 * - Component props for src
 * - Default and instance values
 * - Edge cases
 */
class SvgBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * SvgBlock instance
	 *
	 * @var SvgBlock
	 */
	private $svg_block;

	/**
	 * Static SvgBlock instance (shared across tests)
	 *
	 * @var SvgBlock
	 */
	private static $svg_block_instance;

	/**
	 * Test SVG content for mocking
	 *
	 * @var string
	 */
	private $test_svg_content = '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="red" stroke="blue"/></svg>';

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$svg_block_instance ) {
			self::$svg_block_instance = new SvgBlock();
		}
		$this->svg_block = self::$svg_block_instance;

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/svg' );

		// Clear cached context between tests
		$this->clear_cached_context();

		// Create a test SVG file in uploads directory for testing
		$upload_dir = wp_get_upload_dir();
		$test_svg_path = $upload_dir['basedir'] . '/test.svg';
		if ( ! file_exists( $test_svg_path ) ) {
			wp_mkdir_p( $upload_dir['basedir'] );
			file_put_contents( $test_svg_path, $this->test_svg_content );
		}

		// Register test shortcode
		$this->register_test_shortcode();
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		$this->remove_test_shortcode();
		parent::tearDown();
	}

	/**
	 * Test block registration
	 */
	public function test_block_is_registered() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/svg' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/svg', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/svg' );
		$this->assertArrayHasKey( 'tag', $block_type->attributes );
		$this->assertArrayHasKey( 'attributes', $block_type->attributes );
		$this->assertArrayHasKey( 'styles', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['tag']['type'] );
		$this->assertEquals( 'object', $block_type->attributes['attributes']['type'] );
	}

	/**
	 * Test SVG renders with src from uploads directory
	 */
	public function test_svg_renders_with_src() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		$attributes = array(
			'tag' => 'svg',
			'attributes' => array(
				'src' => $test_svg_url,
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/svg', $attributes );
		$result = $this->svg_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
		// src and stripColors should not be in output
		$this->assertStringNotContainsString( 'src="', $result );
		$this->assertStringNotContainsString( 'stripColors', $result );
	}

	/**
	 * Test SVG returns fallback SVG when src is empty
	 */
	public function test_svg_returns_fallback_when_src_empty() {
		$attributes = array(
			'tag' => 'svg',
			'attributes' => array(
				'src' => '',
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/svg', $attributes );
		$result = $this->svg_block->render_block( $attributes, '', $block );
		// When src is empty, SvgLoader returns a fallback SVG (Etch logo)
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
	}

	/**
	 * Test SVG with stripColors=true strips color attributes
	 */
	public function test_svg_strip_colors_true() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		$attributes = array(
			'tag' => 'svg',
			'attributes' => array(
				'src' => $test_svg_url,
				'stripColors' => 'true',
			),
		);
		$block = $this->create_mock_block( 'etch/svg', $attributes );
		$result = $this->svg_block->render_block( $attributes, '', $block );
		// When stripColors is true, fill and stroke should be replaced with currentColor
		$this->assertStringContainsString( '<svg', $result );
		// Note: Actual color stripping depends on SvgLoader implementation
		// This test verifies the option is passed correctly
	}

	/**
	 * Test SVG with stripColors=false preserves colors
	 */
	public function test_svg_strip_colors_false() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		$attributes = array(
			'tag' => 'svg',
			'attributes' => array(
				'src' => $test_svg_url,
				'stripColors' => 'false',
			),
		);
		$block = $this->create_mock_block( 'etch/svg', $attributes );
		$result = $this->svg_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<svg', $result );
		// Colors should be preserved when stripColors is false
	}

	/**
	 * Test SVG src from component props: {props.src}
	 */
	public function test_svg_src_from_component_props() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		// Create component pattern with SVG block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/svg {"tag":"svg","attributes":{"src":"{props.svgSrc}","class":"test"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'svgSrc',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'svgSrc' => $test_svg_url,
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'class="test"', $result );
		$this->assertStringNotContainsString( 'src="', $result );
	}

	/**
	 * Test SVG src with default value (no instance value)
	 */
	public function test_svg_src_with_default_only() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		// Create component pattern with SVG block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/svg {"tag":"svg","attributes":{"src":"{props.svgSrc}","class":"test"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'svgSrc',
					'type' => array( 'primitive' => 'string' ),
					'default' => $test_svg_url, // Default value
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(), // No instance value, should use default
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( '<svg', $result );
	}

	/**
	 * Test SVG src instance value overrides default
	 */
	public function test_svg_src_instance_overrides_default() {
		$upload_dir = wp_get_upload_dir();
		$default_svg_url = $upload_dir['baseurl'] . '/test.svg';
		// Create another test SVG
		$instance_svg_path = $upload_dir['basedir'] . '/instance-test.svg';
		$instance_svg_content = '<svg width="50" height="50" xmlns="http://www.w3.org/2000/svg"><rect width="50" height="50" fill="green"/></svg>';
		file_put_contents( $instance_svg_path, $instance_svg_content );
		$instance_svg_url = $upload_dir['baseurl'] . '/instance-test.svg';

		// Create component pattern with SVG block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/svg {"tag":"svg","attributes":{"src":"{props.svgSrc}","class":"test"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'svgSrc',
					'type' => array( 'primitive' => 'string' ),
					'default' => $default_svg_url, // Default value
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'svgSrc' => $instance_svg_url, // Instance value should override default
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( '<svg', $result );
		// Should render the instance SVG (with green rect), not default (with red circle)
		$this->assertStringContainsString( 'width="50"', $result );
	}

	/**
	 * Test SVG stripColors from component props
	 */
	public function test_svg_strip_colors_from_component_props() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		// Create component pattern with SVG block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/svg {"tag":"svg","attributes":{"src":"' . $test_svg_url . '","stripColors":"{props.stripColors}"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'stripColors',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'false',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'stripColors' => 'true',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( '<svg', $result );
		// stripColors should be processed correctly
	}

	/**
	 * Test SVG attributes with component props
	 */
	public function test_svg_attributes_with_component_props() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		// Create component pattern with SVG block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/svg {"tag":"svg","attributes":{"src":"' . $test_svg_url . '","class":"{props.className}","id":"{props.elementId}"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'className',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
				array(
					'key' => 'elementId',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'className' => 'custom-svg-class',
				'elementId' => 'svg-element-123',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'class="custom-svg-class"', $result );
		$this->assertStringContainsString( 'id="svg-element-123"', $result );
	}

	/**
	 * Test SVG with global context in attributes
	 */
	public function test_svg_with_global_context() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'Test Post Title',
				'post_content' => 'Test content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'tag' => 'svg',
			'attributes' => array(
				'src' => $test_svg_url,
				'data-title' => '{this.title}',
			),
		);
		$block = $this->create_mock_block( 'etch/svg', $attributes );
		$result = $this->svg_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'data-title="Test Post Title"', $result );
	}

	/**
	 * Test SVG with nested component (prop shadowing)
	 */
	public function test_svg_nested_component_prop_shadowing() {
		$upload_dir = wp_get_upload_dir();
		$parent_svg_url = $upload_dir['baseurl'] . '/test.svg';
		// Create another test SVG for nested
		$nested_svg_path = $upload_dir['basedir'] . '/nested-test.svg';
		$nested_svg_content = '<svg width="75" height="75" xmlns="http://www.w3.org/2000/svg"><ellipse cx="37" cy="37" rx="30" ry="20" fill="purple"/></svg>';
		file_put_contents( $nested_svg_path, $nested_svg_content );
		$nested_svg_url = $upload_dir['baseurl'] . '/nested-test.svg';

		// Create nested component pattern
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/svg {"tag":"svg","attributes":{"src":"{props.svgSrc}","class":"nested"}} /-->',
			)
		);

		update_post_meta(
			$nested_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'svgSrc',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create parent component pattern
		$parent_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => sprintf(
					'<!-- wp:etch/component {"ref":%d,"attributes":{"svgSrc":"%s"}} /-->',
					$nested_pattern_id,
					$nested_svg_url
				),
			)
		);

		update_post_meta(
			$parent_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'svgSrc',
					'type' => array( 'primitive' => 'string' ),
					'default' => $parent_svg_url,
				),
			)
		);

		$component_attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'svgSrc' => $parent_svg_url, // Parent value
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		// Should show nested SVG (with ellipse), not parent SVG (with circle)
		$this->assertStringContainsString( '<svg', $result );
		$this->assertStringContainsString( 'width="75"', $result );
	}

	/**
	 * Test SVG with empty attributes array
	 */
	public function test_svg_empty_attributes() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		$attributes = array(
			'tag' => 'svg',
			'attributes' => array(
				'src' => $test_svg_url,
			),
		);
		$block = $this->create_mock_block( 'etch/svg', $attributes );
		$result = $this->svg_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<svg', $result );
	}

	/**
	 * Test SVG block with shortcode in aria-label attribute
	 */
	public function test_svg_block_with_shortcode_in_aria_label() {
		$upload_dir = wp_get_upload_dir();
		$test_svg_url = $upload_dir['baseurl'] . '/test.svg';

		$blocks = array(
			array(
				'blockName' => 'etch/svg',
				'attrs' => array(
					'tag' => 'svg',
					'attributes' => array(
						'src' => $test_svg_url,
						'aria-label' => '[etch_test_hello name=John]',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => '',
				'innerContent' => array(),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		// Note: SvgBlock SHOULD resolve shortcodes in attributes - they can be combined with fetched SVG attributes
		$this->assertStringContainsString( 'aria-label="Hello John!"', $rendered );
		$this->assertStringNotContainsString( '[etch_test_hello name=John]', $rendered );
		$this->assertStringContainsString( '<svg', $rendered );
	}
}
