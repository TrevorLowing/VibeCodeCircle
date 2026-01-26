<?php
/**
 * ComponentBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/component)
 *    - Attributes structure (ref: number, attributes: object)
 *
 * ✅ Basic Rendering & Edge Cases
 *    - Returns empty when ref is null
 *    - Returns empty when WP_Block not provided
 *    - Returns empty when ref doesn't exist
 *    - Returns empty when ref is not wp_block post type
 *    - Renders pattern blocks correctly
 *    - Handles empty attributes array
 *
 * ✅ Component Props Resolution
 *    - Props resolved from component attributes
 *    - Props use default values when not provided
 *    - Null prop values handled (fallback to default)
 *    - Props merged correctly with existing context
 *
 * ✅ Component Slot Object Resolution
 *    - Specialized slots object gets added to the context
 *
 * ✅ Nested Component Scenarios
 *    - Same prop name shadows parent props
 *    - Parent → Nested → Regular block structure
 *      - Nested component gets its own props
 *      - Regular block gets parent props (context preservation)
 *    - Parent → Regular block → Nested structure
 *      - Regular block gets parent props
 *      - Nested component gets its own props
 *    - Deep nesting (3+ levels) with proper prop resolution
 *
 * ✅ Dynamic Expression Resolution in Props
 *    - Component prop with global context: {this.title}
 *    - Component prop with mixed static and dynamic: "Welcome to {this.title}"
 *    - Component prop with multiple expressions: "{this.title} - {site.name}"
 *    - Expression resolution happens before prop is passed to nested blocks
 *
 * ✅ Context Preservation
 *    - Parent component context preserved after nested component renders
 *    - Regular blocks after nested components still access parent props
 *    - Context correctly restored when nested component finishes
 *
 * ✅ Integration & Complex Scenarios
 *    - Component patterns with property definitions
 *    - Multiple components in same pattern
 *    - Component props passed through nested component chain
 *    - Nested component receives prop via {props.propName} expression from parent
 *    - Deeply nested prop pass-through (3+ levels via {props.propName})
 *
 * ✅ Shortcode Resolution
 *    - Component prop default contains shortcode: [etch_test_hello name=Jane]
 *    - Component prop instance value contains shortcode
 *    - Component prop with shortcode using dynamic prop in shortcode attribute: [etch_test_hello name={props.text}]
 *    - Shortcode in pattern block in FSE template (direct rendering without the_content filter)
 *    - Shortcode with dynamic props in FSE template
 *
 * 📝 Areas for Future Enhancement
 *    - Prop type casting tests (number, boolean, array)
 *    - Component with no property definitions
 *    - Component prop validation/error handling
 *    - Performance testing with many props (50+)
 *    - Circular reference detection (if applicable)
 *    - Component prop using another prop: "{props.title}"
 *    - Component prop chains (grandparent → parent → nested)
 *    - Component prop with deeply nested context: "{this.meta.customField}"
 *    - Component prop with modifiers: "{this.date.format('Y-m-d')}"
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\Global\ComponentSlotContextProvider;
use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class ComponentBlockTest
 *
 * Comprehensive tests for ComponentBlock functionality including:
 * - Basic rendering
 * - Prop resolution
 * - Nested components with prop shadowing
 * - Dynamic expression resolution in props
 * - Edge cases
 */
class ComponentBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * ComponentBlock instance
	 *
	 * @var ComponentBlock
	 */
	private $component_block;

	/**
	 * Static ComponentBlock instance (shared across tests)
	 *
	 * @var ComponentBlock
	 */
	private static $component_block_instance;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$component_block_instance ) {
			self::$component_block_instance = new ComponentBlock();
		}
		$this->component_block = self::$component_block_instance;

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/component' );

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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/component' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/component', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/component' );
		$this->assertArrayHasKey( 'ref', $block_type->attributes );
		$this->assertArrayHasKey( 'attributes', $block_type->attributes );
		$this->assertEquals( 'number', $block_type->attributes['ref']['type'] );
		$this->assertEquals( 'object', $block_type->attributes['attributes']['type'] );
	}

	/**
	 * Test component returns empty string when ref is null
	 */
	public function test_component_returns_empty_when_ref_is_null() {
		$attributes = array(
			'ref' => null,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test component returns empty string when WP_Block is not provided
	 */
	public function test_component_returns_empty_when_block_not_provided() {
		$attributes = array(
			'ref' => 1,
			'attributes' => array(),
		);
		$result = $this->component_block->render_block( $attributes, '', null );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test component returns empty string when ref doesn't exist
	 */
	public function test_component_returns_empty_when_ref_not_found() {
		$attributes = array(
			'ref' => 99999,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test component returns empty string when ref is not wp_block post type
	 */
	public function test_component_returns_empty_when_ref_not_wp_block() {
		$post_id = $this->factory()->post->create( array( 'post_type' => 'post' ) );
		$attributes = array(
			'ref' => $post_id,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test component renders pattern blocks correctly
	 */
	public function test_component_renders_pattern_blocks() {
		// Create a pattern (wp_block) with a simple text block
		$pattern_content = '<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Hello World', $result );
	}

	/**
	 * Test component props are resolved from attributes
	 */
	public function test_component_props_resolved_from_attributes() {
		// Create pattern that renders a prop.
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.text}"} /-->',
			)
		);

		// Add property definition
		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'Default Text',
				),
			)
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => 'Custom Text',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Custom Text', $result );
	}

	/**
	 * Test component props use default values when not provided
	 */
	public function test_component_props_use_default_values() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.text}"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'Default Text',
				),
			)
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Default Text', $result );
	}

	/**
	 * Test component slot object resolution
	 */
	public function test_component_slot_object_resolution() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->',
			)
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(),
		);

		$inner_blocks = array(
			$this->create_mock_block(
				'etch/slot-content',
				array( 'name' => 'default' ),
				array(),
			),
			$this->create_mock_block(
				'etch/slot-content',
				array( 'name' => 'header' ),
				array(
					$this->create_mock_block(
						'core/heading',
						array(
							'level' => 2,
							'content' => 'Header Content',
						),
						array(),
					),
				),
			),
		);

		$block = $this->create_mock_block( 'etch/component', $attributes, $inner_blocks );

		$slots_map = ComponentSlotContextProvider::extract_slot_contents( $block );
		$slots_object = array();
		foreach ( $slots_map as $slot_name => $slot_content ) {
			if ( ! is_string( $slot_name ) || '' === $slot_name ) {
				continue;
			}
			$slots_object[ $slot_name ] = array( 'empty' => empty( $slot_content ) );
		}

		DynamicContextProvider::push( new DynamicContentEntry( 'component-slots', 'slots', $slots_object ) );
		try {
			$sources = DynamicContextProvider::get_sources_for_wp_block( $block );
			$context = DynamicContentProcessor::process_expression(
				'slots',
				array(
					'sources' => $sources,
				)
			);
		} finally {
			DynamicContextProvider::pop();
		}

		$this->assertIsArray( $context );
		$this->assertArrayHasKey( 'default', $context );
		$this->assertArrayHasKey( 'header', $context );
		$this->assertEquals( true, $context['default']['empty'] );
		$this->assertEquals( false, $context['header']['empty'] );
	}

	/**
	 * Test nested component with same prop name shadows parent props
	 */
	public function test_nested_component_same_prop_name_shadows_parent() {
		// Create nested pattern
		$nested_pattern_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $nested_pattern_content,
			)
		);

		update_post_meta(
			$nested_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create parent pattern that includes nested component
		$parent_pattern_content = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"Nested Text"}} /-->',
			$nested_pattern_id
		);
		$parent_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $parent_pattern_content,
			)
		);

		update_post_meta(
			$parent_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'text' => 'Parent Text',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		// The nested component should render "Nested Text", not "Parent Text"
		$this->assertStringContainsString( 'Nested Text', $result );
		$this->assertStringNotContainsString( 'Parent Text', $result );
	}

	/**
	 * Test scenario: Parent → Nested (with text prop) → Regular block (with text prop)
	 */
	public function test_parent_nested_regular_block_structure() {
		// Create a text block pattern that uses props.text
		$text_pattern_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';

		// Create nested component pattern
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $text_pattern_content,
			)
		);

		update_post_meta(
			$nested_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create parent pattern with nested component and regular text block
		$parent_pattern_content = sprintf(
			'%s<!-- wp:etch/text {"content":"{props.text}"} /-->',
			sprintf(
				'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"Nested Text"}} /-->',
				$nested_pattern_id
			)
		);
		$parent_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $parent_pattern_content,
			)
		);

		update_post_meta(
			$parent_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'text' => 'Parent Text',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		// Should contain both "Nested Text" (from nested component) and "Parent Text" (from regular block)
		$this->assertStringContainsString( 'Nested Text', $result );
		$this->assertStringContainsString( 'Parent Text', $result );
	}

	/**
	 * Test scenario: Parent → Regular block (with text prop) → Nested (with text prop)
	 */
	public function test_parent_regular_block_nested_structure() {
		// Create nested component pattern
		$nested_pattern_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $nested_pattern_content,
			)
		);

		update_post_meta(
			$nested_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create parent pattern with regular text block first, then nested component
		$parent_pattern_content = sprintf(
			'<!-- wp:etch/text {"content":"{props.text}"} /-->%s',
			sprintf(
				'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"Nested Text"}} /-->',
				$nested_pattern_id
			)
		);
		$parent_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $parent_pattern_content,
			)
		);

		update_post_meta(
			$parent_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'text' => 'Parent Text',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		// Should contain both "Parent Text" (from regular block) and "Nested Text" (from nested component)
		$this->assertStringContainsString( 'Parent Text', $result );
		$this->assertStringContainsString( 'Nested Text', $result );
	}

	/**
	 * Test component prop with global context value: {this.title}
	 */
	public function test_component_prop_with_global_context() {
		// Create a post with a title
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

		// Create pattern with text block
		$pattern_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
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

		// Component prop uses global context
		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => '{this.title}',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		// Should resolve to the post title
		$this->assertStringContainsString( 'Test Post Title', $result );
	}

	/**
	 * Test component prop with mixed static and dynamic content
	 */
	public function test_component_prop_mixed_static_dynamic() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'Hello',
				'post_content' => 'Test content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$pattern_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
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

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => 'Welcome to {this.title}',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Welcome to Hello', $result );
	}

	/**
	 * Test component prop with multiple dynamic expressions
	 */
	public function test_component_prop_multiple_expressions() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'Test Post',
				'post_content' => 'Test content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$pattern_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
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

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => '{this.title} - {site.name}',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Test Post', $result );
		$this->assertStringContainsString( get_bloginfo( 'name' ), $result );
	}

	/**
	 * Test component with empty attributes array
	 */
	public function test_component_with_empty_attributes() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->',
			)
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Content', $result );
	}

	/**
	 * Test component with null prop values
	 */
	public function test_component_with_null_prop_values() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.text}"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => 'Default',
				),
			)
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => null,
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'Default', $result );
	}

	/**
	 * Test deeply nested components (3 levels)
	 */
	public function test_deeply_nested_components() {
		// Level 3: Deepest nested
		$deepest_content = '<!-- wp:etch/text {"content":"{props.text}"} /-->';
		$deepest_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $deepest_content,
			)
		);

		update_post_meta(
			$deepest_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Level 2: Middle nested
		$middle_content = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"Deepest Text"}} /-->',
			$deepest_id
		);
		$middle_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $middle_content,
			)
		);

		update_post_meta(
			$middle_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Level 1: Parent
		$parent_content = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"Middle Text"}} /-->',
			$middle_id
		);
		$parent_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $parent_content,
			)
		);

		update_post_meta(
			$parent_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$attributes = array(
			'ref' => $parent_id,
			'attributes' => array(
				'text' => 'Parent Text',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );
		// Deepest component should render "Deepest Text"
		$this->assertStringContainsString( 'Deepest Text', $result );
	}

	/**
	 * Test component prop default contains shortcode
	 */
	public function test_component_prop_default_contains_shortcode() {
		// Create component pattern with text block using prop default containing shortcode
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.text}"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '[etch_test_hello name=Jane]',
				),
			)
		);

		// Create component block without providing instance value (should use default)
		$blocks = array(
			array(
				'blockName' => 'etch/component',
				'attrs' => array(
					'ref' => $pattern_id,
					'attributes' => array(),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'Hello Jane!', $rendered );
	}

	/**
	 * Test component prop instance value contains shortcode
	 */
	public function test_component_prop_instance_contains_shortcode() {
		// Create component pattern with text block using prop
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.text}"} /-->',
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

		// Create component block with instance value containing shortcode
		$blocks = array(
			array(
				'blockName' => 'etch/component',
				'attrs' => array(
					'ref' => $pattern_id,
					'attributes' => array(
						'text' => '[etch_test_hello name=John]',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'Hello John!', $rendered );
	}

	/**
	 * Test component prop with shortcode using dynamic prop in shortcode attribute
	 */
	public function test_component_prop_with_shortcode_using_dynamic_prop() {
		// Create component pattern with element block containing shortcode in attribute using props
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/element {"tag":"h2","attributes":{"data-test":"[etch_test_hello name={props.text}]"}} /-->',
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
						'text' => 'asdfg',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'data-test="Hello asdfg!"', $rendered );
	}

	/**
	 * Test component block with shortcode in pattern block in FSE template (direct rendering)
	 * This simulates FSE template rendering where blocks are rendered directly without the_content filter
	 */
	public function test_component_block_with_shortcode_fse_template() {
		// Create component pattern with text block containing shortcode
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"shortcode test: [etch_test_hello name=John]"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array()
		);

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );

		// Render directly (simulating FSE template rendering, not through the_content filter)
		$result = $this->component_block->render_block( $attributes, '', $block );

		// Shortcode should be resolved even without the_content filter
		$this->assertStringContainsString( 'shortcode test: Hello John!', $result );
		$this->assertStringNotContainsString( '[etch_test_hello name=John]', $result );
	}

	/**
	 * Test component block with shortcode using dynamic props in FSE template
	 */
	public function test_component_block_with_shortcode_and_dynamic_props_fse_template() {
		// Create component pattern with element block containing shortcode in attribute using props
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/element {"tag":"h2","attributes":{"data-test":"[etch_test_hello name={props.text}]"}} /-->',
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

		$attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => 'Jane',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->component_block->render_block( $attributes, '', $block );

		// Dynamic props should be resolved first, then shortcode processed
		$this->assertStringContainsString( 'data-test="Hello Jane!"', $result );
		$this->assertStringNotContainsString( '{props.text}', $result );
		$this->assertStringNotContainsString( '[etch_test_hello', $result );
	}

	/**
	 * Test that props are passed through to nested components via {props.propName} expression
	 *
	 * This test verifies that when a parent component passes a prop to a nested component
	 * using a dynamic expression like {props.newProp}, the nested component correctly
	 * receives and renders the resolved value.
	 *
	 * Scenario:
	 * - Page renders Parent component with newProp="test"
	 * - Parent contains Nested component with newProp="{props.newProp}"
	 * - Nested displays "In nested: {props.newProp}"
	 * - Expected output: "In nested: test"
	 */
	public function test_nested_component_receives_passed_through_prop() {
		// Create Nested component that displays the prop
		$nested_pattern_content = '<!-- wp:etch/element {"tag":"h2","attributes":[]} -->' .
			'<!-- wp:etch/text {"content":"In nested: {props.newProp}"} /-->' .
			'<!-- /wp:etch/element -->';
		$nested_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $nested_pattern_content,
			)
		);

		update_post_meta(
			$nested_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'newProp',
					'name' => 'New Property',
					'keyTouched' => false,
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create Parent component that passes its prop to Nested using {props.newProp}
		$parent_pattern_content = sprintf(
			'<!-- wp:etch/element {"tag":"div","attributes":[]} -->' .
			'<!-- wp:etch/component {"ref":%d,"attributes":{"newProp":"{props.newProp}"}} /-->' .
			'<!-- /wp:etch/element -->',
			$nested_id
		);
		$parent_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $parent_pattern_content,
			)
		);

		update_post_meta(
			$parent_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'newProp',
					'name' => 'New Property',
					'keyTouched' => false,
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Render the Parent component with newProp="test"
		$attributes = array(
			'ref' => $parent_id,
			'attributes' => array(
				'newProp' => 'test',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );

		// The nested component should have received "test" and rendered it
		$this->assertStringContainsString( 'In nested: test', $result );
		// Should NOT contain the unresolved expression
		$this->assertStringNotContainsString( '{props.newProp}', $result );
	}

	/**
	 * Test deeply nested prop pass-through (3 levels)
	 *
	 * Verifies that props can be passed through multiple component levels:
	 * Page -> GrandParent (text="original") -> Parent (text="{props.text}") -> Child (text="{props.text}")
	 * Child should display "original"
	 */
	public function test_deeply_nested_prop_pass_through() {
		// Level 3: Child - displays the prop
		$child_content = '<!-- wp:etch/text {"content":"Child says: {props.text}"} /-->';
		$child_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $child_content,
			)
		);

		update_post_meta(
			$child_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Level 2: Parent - passes prop through to Child
		$parent_content = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"{props.text}"}} /-->',
			$child_id
		);
		$parent_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $parent_content,
			)
		);

		update_post_meta(
			$parent_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Level 1: GrandParent - passes prop through to Parent
		$grandparent_content = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"{props.text}"}} /-->',
			$parent_id
		);
		$grandparent_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $grandparent_content,
			)
		);

		update_post_meta(
			$grandparent_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'text',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Render GrandParent with text="original"
		$attributes = array(
			'ref' => $grandparent_id,
			'attributes' => array(
				'text' => 'original',
			),
		);
		$block = $this->create_mock_block( 'etch/component', $attributes );
		$result = $this->component_block->render_block( $attributes, '', $block );

		// Child should have received "original" through the entire chain
		$this->assertStringContainsString( 'Child says: original', $result );
		$this->assertStringNotContainsString( '{props.text}', $result );
	}
}
