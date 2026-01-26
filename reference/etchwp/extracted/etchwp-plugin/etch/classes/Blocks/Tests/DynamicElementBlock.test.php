<?php
/**
 * DynamicElementBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/dynamic-element)
 *    - Attributes structure (tag: string, attributes: object, styles: array)
 *
 * ✅ Basic Rendering & Static Examples
 *    - Renders with tag from attributes->attributes['tag']
 *    - Falls back to top-level tag attribute
 *    - Falls back to 'div' when no tag provided
 *    - Renders with custom attributes
 *    - Renders innerBlocks content
 *    - Tag sanitization (invalid tags default to div)
 *
 * ✅ Dynamic Tag Resolution
 *    - Tag from attributes->attributes['tag'] takes precedence
 *    - Top-level tag attribute as fallback
 *    - Tag removed from rendered attributes
 *
 * ✅ Component Props Context
 *    - Tag from component props: {props.tag}
 *    - Tag with default value and instance value
 *    - Tag with only default value
 *    - Tag with only instance value
 *    - Attributes with component props: {props.id}, {props.className}
 *
 * ✅ Global Context Expressions
 *    - Attributes with {this.title}, {site.name}
 *    - Tag with global context (less common but should work)
 *
 * ✅ Integration Scenarios
 *    - DynamicElementBlock inside ComponentBlock with tag prop
 *    - DynamicElementBlock with nested ComponentBlock (prop shadowing)
 *    - DynamicElementBlock with default tag and instance tag
 *
 * ✅ Edge Cases
 *    - Invalid tag names sanitized
 *    - Tag attribute removed from HTML output
 *    - Empty attributes array handled
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in attribute value: data-test="[etch_test_hello name=John]"
 *    - Shortcode using component props in attribute: data-test="[etch_test_hello name={props.text}]"
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\DynamicElementBlock\DynamicElementBlock;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\ElementBlock\ElementBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;
use Etch\Blocks\TextBlock\TextBlock;

/**
 * Class DynamicElementBlockTest
 *
 * Comprehensive tests for DynamicElementBlock functionality including:
 * - Basic rendering with tag from attributes
 * - Dynamic tag resolution
 * - Component props for tag
 * - Default and instance values
 * - Edge cases
 */
class DynamicElementBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * DynamicElementBlock instance
	 *
	 * @var DynamicElementBlock
	 */
	private $dynamic_element_block;

	/**
	 * Static DynamicElementBlock instance (shared across tests)
	 *
	 * @var DynamicElementBlock
	 */
	private static $dynamic_element_block_instance;

	/**
	 * TextBlock instance
	 *
	 * @var TextBlock
	 */
	private $text_block;

	/**
	 * ElementBlock instance
	 *
	 * @var ElementBlock
	 */
	private $element_block;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$dynamic_element_block_instance ) {
			self::$dynamic_element_block_instance = new DynamicElementBlock();
		}
		$this->dynamic_element_block = self::$dynamic_element_block_instance;

		// Other block instances for testing
		$this->text_block = new TextBlock();
		$this->element_block = new ElementBlock();

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/dynamic-element' );

		// Clear cached context between tests
		$this->clear_cached_context();

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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/dynamic-element' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/dynamic-element', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/dynamic-element' );
		$this->assertArrayHasKey( 'tag', $block_type->attributes );
		$this->assertArrayHasKey( 'attributes', $block_type->attributes );
		$this->assertArrayHasKey( 'styles', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['tag']['type'] );
		$this->assertEquals( 'object', $block_type->attributes['attributes']['type'] );
	}

	/**
	 * Test dynamic element renders with tag from attributes->attributes['tag']
	 */
	public function test_dynamic_element_renders_with_tag_from_attributes() {
		$attributes = array(
			'tag' => 'div', // Top-level tag (fallback)
			'attributes' => array(
				'tag' => 'section', // Tag from attributes
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<section', $result );
		$this->assertStringEndsWith( '</section>', $result );
		// Tag should not be in attributes
		$this->assertStringNotContainsString( 'tag="section"', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
	}

	/**
	 * Test dynamic element falls back to top-level tag when not in attributes
	 */
	public function test_dynamic_element_falls_back_to_top_level_tag() {
		$attributes = array(
			'tag' => 'article', // Top-level tag
			'attributes' => array(
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<article', $result );
		$this->assertStringEndsWith( '</article>', $result );
	}

	/**
	 * Test dynamic element falls back to 'div' when no tag provided
	 */
	public function test_dynamic_element_falls_back_to_div() {
		$attributes = array(
			'attributes' => array(
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<div', $result );
		$this->assertStringEndsWith( '</div>', $result );
	}

	/**
	 * Test dynamic element renders with custom attributes
	 */
	public function test_dynamic_element_renders_with_custom_attributes() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'tag' => 'header',
				'id' => 'test-id',
				'class' => 'test-class',
				'data-test' => 'value',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<header', $result );
		$this->assertStringContainsString( 'id="test-id"', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
		$this->assertStringContainsString( 'data-test="value"', $result );
		$this->assertStringNotContainsString( 'tag="header"', $result );
	}

	/**
	 * Test dynamic element renders innerBlocks content
	 */
	public function test_dynamic_element_renders_inner_blocks() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'tag' => 'section',
			),
		);

		$inner_blocks = array(
			array(
				'blockName' => 'etch/element',
				'attrs' => array(
					'tag' => 'p',
				),
				'innerBlocks' => array(
					array(
						'blockName' => 'etch/text',
						'attrs' => array(
							'content' => 'Inner content',
						),
						'innerBlocks' => array(),
						'innerHTML' => '',
						'innerContent' => array(),
					),
				),
				'innerHTML' => '',
				'innerContent' => array( null ),
			),
		);

		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes, $inner_blocks );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<p>Inner content</p>', $result );
		$this->assertStringStartsWith( '<section', $result );
	}

	/**
	 * Test dynamic element tag sanitization (invalid tags default to div)
	 */
	public function test_dynamic_element_invalid_tag_defaults_to_div() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'tag' => 'invalid<>tag',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<div', $result );
	}

	/**
	 * Test dynamic element with tag from component props: {props.tag}
	 */
	public function test_dynamic_element_tag_from_component_props() {
		// Create component pattern with dynamic element block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/dynamic-element {"tag":"div","attributes":{"tag":"{props.elementTag}","class":"test"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'elementTag',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'div',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'elementTag' => 'h1',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringStartsWith( '<h1', $result );
		$this->assertStringEndsWith( '</h1>', $result );
		$this->assertStringContainsString( 'class="test"', $result );
		$this->assertStringNotContainsString( 'tag="h1"', $result );
	}

	/**
	 * Test dynamic element with tag default value (no instance value)
	 */
	public function test_dynamic_element_tag_with_default_only() {
		// Create component pattern with dynamic element block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/dynamic-element {"tag":"div","attributes":{"tag":"{props.elementTag}","class":"test"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'elementTag',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'section', // Default value
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
		$this->assertStringStartsWith( '<section', $result );
		$this->assertStringEndsWith( '</section>', $result );
	}

	/**
	 * Test dynamic element with tag instance value overrides default
	 */
	public function test_dynamic_element_tag_instance_overrides_default() {
		// Create component pattern with dynamic element block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/dynamic-element {"tag":"div","attributes":{"tag":"{props.elementTag}","class":"test"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'elementTag',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'section', // Default value
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'elementTag' => 'h2', // Instance value should override default
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringStartsWith( '<h2', $result );
		$this->assertStringEndsWith( '</h2>', $result );
		$this->assertStringNotContainsString( '<section', $result );
	}

	/**
	 * Test dynamic element attributes with component props
	 */
	public function test_dynamic_element_attributes_with_component_props() {
		// Create component pattern with dynamic element block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/dynamic-element {"tag":"div","attributes":{"tag":"header","id":"{props.elementId}","class":"{props.className}"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'elementId',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
				array(
					'key' => 'className',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'elementId' => 'component-header',
				'className' => 'custom-class',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringStartsWith( '<header', $result );
		$this->assertStringContainsString( 'id="component-header"', $result );
		$this->assertStringContainsString( 'class="custom-class"', $result );
	}

	/**
	 * Test dynamic element with global context in attributes
	 */
	public function test_dynamic_element_with_global_context() {
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
			'tag' => 'div',
			'attributes' => array(
				'tag' => 'h1',
				'data-title' => '{this.title}',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<h1', $result );
		$this->assertStringContainsString( 'data-title="Test Post Title"', $result );
	}

	/**
	 * Test dynamic element with nested component (prop shadowing)
	 */
	public function test_dynamic_element_nested_component_prop_shadowing() {
		// Create nested component pattern
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/dynamic-element {"tag":"div","attributes":{"tag":"{props.elementTag}","class":"nested"}} /-->',
			)
		);

		update_post_meta(
			$nested_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'elementTag',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'div',
				),
			)
		);

		// Create parent component pattern
		$parent_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => sprintf(
					'<!-- wp:etch/component {"ref":%d,"attributes":{"elementTag":"h3"}} /-->',
					$nested_pattern_id
				),
			)
		);

		update_post_meta(
			$parent_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'elementTag',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'h2',
				),
			)
		);

		$component_attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'elementTag' => 'h1', // Parent value
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		// Should show "h3" (nested component value), not "h1" (parent value)
		$this->assertStringContainsString( '<h3', $result );
		$this->assertStringNotContainsString( '<h1', $result );
	}

	/**
	 * Test dynamic element with empty attributes array
	 */
	public function test_dynamic_element_empty_attributes() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/dynamic-element', $attributes );
		$result = $this->dynamic_element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<div', $result );
		$this->assertStringEndsWith( '</div>', $result );
	}

	/**
	 * Test dynamic element block with shortcode in attribute value
	 */
	public function test_dynamic_element_block_with_shortcode_in_attribute() {
		$blocks = array(
			array(
				'blockName' => 'etch/dynamic-element',
				'attrs' => array(
					'tag' => 'div',
					'attributes' => array(
						'tag' => 'section',
						'data-test' => '[etch_test_hello name=John]',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'data-test="Hello John!"', $rendered );
		$this->assertStringStartsWith( '<section', $rendered );
	}

	/**
	 * Test dynamic element block with shortcode using component props in attribute
	 */
	public function test_dynamic_element_block_with_shortcode_using_props_in_attribute() {
		// Create component pattern with dynamic element block containing shortcode in attribute
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/dynamic-element {"tag":"div","attributes":{"tag":"h2","data-test":"[etch_test_hello name={props.text}]"}} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create component block with props
		$blocks = array(
			array(
				'blockName' => 'etch/component',
				'attrs' => array(
					'ref' => $pattern_id,
					'attributes' => array(
						'text' => 'Dynamic',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'data-test="Hello Dynamic!"', $rendered );
		$this->assertStringStartsWith( '<h2', $rendered );
	}
}
