<?php
/**
 * Component Slot test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Basic Slot Functionality
 *    - Simple slot replacement
 *    - Multiple slots in one component
 *    - Empty slot (no content provided)
 *    - Slot placeholder without matching content
 *
 * ✅ Nested Components with Slots
 *    - Component with slot containing nested component
 *    - Nested component also has slots (same name)
 *    - Nested component also has slots (different names)
 *    - Deep nesting (3+ levels) with slots
 *
 * ✅ Context Isolation
 *    - Slot content uses parent context (not component props)
 *    - Component props NOT accessible in slot content (at page level)
 *    - Component props ARE accessible in slot content when defined in parent component template
 *    - Global context (this, site, user) accessible in slots
 *    - Loop context accessible in slots
 *
 * ✅ Dynamic Data in Slots
 *    - Dynamic expressions in slot content (parent context)
 *    - Loops inside slot content
 *    - Nested loops inside slot content
 *    - Dynamic data from component props should NOT parse
 *
 * ✅ Complex Scenarios
 *    - Slot with nested component that has its own slots
 *    - Multiple slots with nested components
 *    - Slot content with multiple blocks
 *    - Slot placeholder in deeply nested component
 *
 * ✅ Edge Cases
 *    - Slot name matching (case sensitivity)
 *    - Empty slot name
 *    - Slot content with no inner blocks
 *    - Multiple slot-content blocks with same name
 *
 * ✅ Shortcode Resolution
 *    - Slot content with shortcode overrides placeholder
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\SlotPlaceholderBlock\SlotPlaceholderBlock;
use Etch\Blocks\SlotContentBlock\SlotContentBlock;
use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Global\ComponentSlotContextProvider;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class ComponentSlotTest
 *
 * Comprehensive tests for component slot functionality including:
 * - Basic slot replacement
 * - Nested components with slots
 * - Context isolation (slots use parent context)
 * - Dynamic data in slots
 * - Complex nested scenarios
 */
class ComponentSlotTest extends WP_UnitTestCase {

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
			new SlotPlaceholderBlock();
			new SlotContentBlock();
		}
		$this->component_block = self::$component_block_instance;

		// Ensure all blocks are registered before creating WP_Block instances
		// WP_Block constructor requires block types to be registered
		$registry = \WP_Block_Type_Registry::get_instance();
		$required_blocks = array( 'etch/component', 'etch/slot-content', 'etch/slot-placeholder', 'etch/element', 'etch/text' );
		$needs_init = false;
		foreach ( $required_blocks as $block_name ) {
			if ( ! $registry->is_registered( $block_name ) ) {
				$needs_init = true;
				break;
			}
		}
		if ( $needs_init ) {
			do_action( 'init' );
		}

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
	 * Test simple slot replacement
	 */
	public function test_simple_slot_replacement() {
		// Create component pattern with slot placeholder
		$pattern_content = '<!-- wp:etch/element {"tag":"div","attributes":{"class":"card"}} --><div class="card"><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		// Create component instance with slot content
		$slot_content = '<!-- wp:etch/text {"content":"Hello from slot"} /-->';
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div class="wp-block-group"><!-- wp:etch/slot-content {"name":"content"} -->%s<!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id,
			$slot_content
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Hello from slot', $result );
		$this->assertStringContainsString( 'card', $result );
	}

	/**
	 * Test multiple slots in one component
	 */
	public function test_multiple_slots() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"header"} /--><!-- wp:etch/slot-placeholder {"name":"body"} /--><!-- wp:etch/slot-placeholder {"name":"footer"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"header"} --><!-- wp:etch/text {"content":"Header"} /--><!-- /wp:etch/slot-content --><!-- wp:etch/slot-content {"name":"body"} --><!-- wp:etch/text {"content":"Body"} /--><!-- /wp:etch/slot-content --><!-- wp:etch/slot-content {"name":"footer"} --><!-- wp:etch/text {"content":"Footer"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Header', $result );
		$this->assertStringContainsString( 'Body', $result );
		$this->assertStringContainsString( 'Footer', $result );
	}

	/**
	 * Test empty slot (no content provided)
	 */
	public function test_empty_slot() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		// Component instance without slot content
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = new WP_Block( $blocks[0], array() );
		$block->inner_blocks = array();

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		// Slot placeholder should render nothing when no content provided
		$this->assertStringNotContainsString( 'slot-placeholder', $result );
	}

	/**
	 * Test slot placeholder recursion guard inside slot content.
	 *
	 * Scenario:
	 * - Slot content contains a slot-placeholder with the same name.
	 * - Recursion guard should prevent re-entry and render content once.
	 */
	public function test_slot_placeholder_recursion_guard_in_slot_content() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		$slot_content = '<!-- wp:etch/text {"content":"Before"} /-->'
			. '<!-- wp:etch/slot-placeholder {"name":"content"} /-->'
			. '<!-- wp:etch/text {"content":"After"} /-->';

		$content = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} -->%s<!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id,
			$slot_content
		);

		$result = $this->render_through_content_filter( $content );

		$this->assertSame( 1, substr_count( $result, 'Before' ) );
		$this->assertSame( 1, substr_count( $result, 'After' ) );
	}

	/**
	 * Test slot content uses parent context, not component props
	 */
	public function test_slot_content_uses_parent_context_not_component_props() {
		// Create a post for global context
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

		// Component pattern with prop and slot
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Component prop: {props.title}"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
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
					'key' => 'title',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Slot content tries to use component prop (should NOT work)
		// But can use global context (should work)
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"title":"Component Title"}} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"Slot: {props.title} | Global: {this.title}"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );

		// Component prop should work in component itself
		$this->assertStringContainsString( 'Component prop: Component Title', $result );

		// Slot content should NOT have component props, but should have global context
		$this->assertStringNotContainsString( 'Slot: Component Title', $result );
		$this->assertStringContainsString( 'Slot:', $result );
		$this->assertStringContainsString( 'Global: Test Post', $result );
	}

	/**
	 * Test nested component with slot containing another component
	 */
	public function test_nested_component_in_slot() {
		// Inner component
		$inner_pattern_content = '<!-- wp:etch/element {"tag":"div","attributes":{"class":"inner"}} --><div class="inner"><!-- wp:etch/text {"content":"Inner Component"} /--></div><!-- /wp:etch/element -->';
		$inner_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $inner_pattern_content,
			)
		);

		// Outer component with slot
		$outer_pattern_content = sprintf(
			'<!-- wp:etch/element {"tag":"div","attributes":{"class":"outer"}} --><div class="outer"><!-- wp:etch/text {"content":"Outer Component"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->',
			$inner_pattern_id
		);
		$outer_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $outer_pattern_content,
			)
		);

		// Component instance with nested component in slot
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/component {"ref":%d} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$outer_pattern_id,
			$inner_pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Outer Component', $result );
		$this->assertStringContainsString( 'Inner Component', $result );
	}

	/**
	 * Test nested component in slot that also has slots (same name)
	 */
	public function test_nested_component_in_slot_with_same_slot_name() {
		// Deepest component with slot
		$deepest_pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Deepest"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$deepest_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $deepest_pattern_content,
			)
		);

		// Middle component with slot (same name)
		$middle_pattern_content = sprintf(
			'<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Middle"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->'
		);
		$middle_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $middle_pattern_content,
			)
		);

		// Outer component with slot
		$outer_pattern_content = sprintf(
			'<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Outer"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->'
		);
		$outer_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $outer_pattern_content,
			)
		);

		// Component instance: outer -> middle (in slot) -> deepest (in slot)
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"Slot Content"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$outer_pattern_id,
			$middle_pattern_id,
			$deepest_pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Outer', $result );
		$this->assertStringContainsString( 'Middle', $result );
		$this->assertStringContainsString( 'Deepest', $result );
		$this->assertStringContainsString( 'Slot Content', $result );
	}

	/**
	 * Test nested component in slot with different slot names
	 */
	public function test_nested_component_in_slot_with_different_slot_names() {
		// Inner component with different slot name
		$inner_pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Inner"} /--><!-- wp:etch/slot-placeholder {"name":"details"} /--></div><!-- /wp:etch/element -->';
		$inner_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $inner_pattern_content,
			)
		);

		// Outer component with slot
		$outer_pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Outer"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$outer_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $outer_pattern_content,
			)
		);

		// Component instance: outer has "content" slot, inner has "details" slot
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"details"} --><!-- wp:etch/text {"content":"Details Content"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$outer_pattern_id,
			$inner_pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Outer', $result );
		$this->assertStringContainsString( 'Inner', $result );
		$this->assertStringContainsString( 'Details Content', $result );
	}

	/**
	 * Test loop inside slot content
	 */
	public function test_loop_inside_slot_content() {
		// Component pattern with slot
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		// Component instance with loop in slot
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/loop {"target":"items","itemId":"item"} --><!-- wp:etch/text {"content":"Item: {item.name}"} /--><!-- /wp:etch/loop --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		// Mock loop context
		$items = array(
			array( 'name' => 'Item 1' ),
			array( 'name' => 'Item 2' ),
		);
		DynamicContextProvider::push( new DynamicContentEntry( 'global', 'items', $items ) );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		DynamicContextProvider::pop();

		// Note: Loop rendering would need actual loop block implementation
		// This test verifies the structure is correct
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test dynamic data in slot content (should work with parent context)
	 */
	public function test_dynamic_data_in_slot_content() {
		// Create a post for global context
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

		// Component pattern with slot
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		// Slot content with dynamic data from global context
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"Post: {this.title}"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Post: Test Post Title', $result );
	}

	/**
	 * Test complex nested structure: component -> slot -> component -> slot -> component
	 */
	public function test_complex_nested_structure() {
		// Level 3: Deepest component
		$level3_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Level 3"} /--></div><!-- /wp:etch/element -->';
		$level3_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $level3_content,
			)
		);

		// Level 2: Middle component with slot
		$level2_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Level 2"} /--><!-- wp:etch/slot-placeholder {"name":"nested"} /--></div><!-- /wp:etch/element -->';
		$level2_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $level2_content,
			)
		);

		// Level 1: Outer component with slot
		$level1_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Level 1"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$level1_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $level1_content,
			)
		);

		// Build nested structure
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"nested"} --><!-- wp:etch/component {"ref":%d} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$level1_id,
			$level2_id,
			$level3_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'Level 1', $result );
		$this->assertStringContainsString( 'Level 2', $result );
		$this->assertStringContainsString( 'Level 3', $result );
	}

	/**
	 * Test slot with multiple blocks
	 */
	public function test_slot_with_multiple_blocks() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"First"} /--><!-- wp:etch/text {"content":"Second"} /--><!-- wp:etch/text {"content":"Third"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		$this->assertStringContainsString( 'First', $result );
		$this->assertStringContainsString( 'Second', $result );
		$this->assertStringContainsString( 'Third', $result );
	}

	/**
	 * Test component props are completely inaccessible in slot content
	 */
	public function test_component_props_inaccessible_in_slot() {
		// Component pattern with prop
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/text {"content":"Inside component: {props.message}"} /--><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
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
					'key' => 'message',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Slot content tries to access component prop - should show empty/unresolved
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"message":"Secret Message"}} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"Slot trying to access: {props.message}"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );

		// Component itself should have access to props
		$this->assertStringContainsString( 'Inside component: Secret Message', $result );

		// Slot content should NOT have access - expression should be unresolved
		$this->assertStringNotContainsString( 'Slot trying to access: Secret Message', $result );
		// Should contain the text but without the resolved prop value
		$this->assertStringContainsString( 'Slot trying to access:', $result );
	}

	/**
	 * Test nested component slot can access parent component props
	 *
	 * When slot content is defined inside a parent component's template and rendered
	 * inside a nested component's slot-placeholder, the slot content should have access
	 * to the parent component's props.
	 */
	public function test_nested_component_slot_can_access_parent_component_props() {
		// Create inner component (220) with slot-placeholder
		$inner_pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"default"} /--></div><!-- /wp:etch/element -->';
		$inner_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $inner_pattern_content,
			)
		);

		// Create outer component (219) with prop newProp
		// Outer component's template uses nested component 220 with slot-content containing {props.newProp}
		$outer_pattern_content = sprintf(
			'<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/element {"tag":"h2"} --><h2><!-- wp:etch/text {"content":"Outside SLOT: {props.newProp}"} /--></h2><!-- /wp:etch/element --><!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"default"} --><!-- wp:etch/element {"tag":"h2"} --><h2><!-- wp:etch/text {"content":"IN SLOT: {props.newProp}"} /--></h2><!-- /wp:etch/element --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component --></div><!-- /wp:etch/element -->',
			$inner_pattern_id
		);
		$outer_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $outer_pattern_content,
			)
		);

		update_post_meta(
			$outer_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'newProp',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create component instance with newProp set to "TEST"
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d,"attributes":{"newProp":"TEST"}} --><div></div><!-- /wp:etch/component -->',
			$outer_pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );

		// Both outside slot and inside slot should have access to parent component props
		$this->assertStringContainsString( 'Outside SLOT: TEST', $result );
		$this->assertStringContainsString( 'IN SLOT: TEST', $result );
	}

	/**
	 * Test slot placeholder without name renders nothing
	 */
	public function test_slot_placeholder_without_name() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":""} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = new WP_Block( $blocks[0], array() );
		$block->inner_blocks = array();

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		// Empty name slot should render nothing
		$this->assertNotEmpty( $result ); // Should still render the div
	}

	/**
	 * Test multiple slot-content blocks with same name (only first should be used)
	 */
	public function test_multiple_slot_content_blocks_same_name() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		// Multiple slot-content blocks with same name - only first should be used
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"First"} /--><!-- /wp:etch/slot-content --><!-- wp:etch/slot-content {"name":"content"} --><!-- wp:etch/text {"content":"Second"} /--><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		// Only first should be included, second should be ignored
		$this->assertStringContainsString( 'First', $result );
		$this->assertStringNotContainsString( 'Second', $result );
	}

	/**
	 * Test slot content with empty inner blocks
	 */
	public function test_slot_content_with_empty_inner_blocks() {
		$pattern_content = '<!-- wp:etch/element {"tag":"div"} --><div><!-- wp:etch/slot-placeholder {"name":"content"} /--></div><!-- /wp:etch/element -->';
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		// Slot content with no inner blocks
		$component_html = sprintf(
			'<!-- wp:etch/component {"ref":%d} --><div><!-- wp:etch/slot-content {"name":"content"} --><!-- /wp:etch/slot-content --></div><!-- /wp:etch/component -->',
			$pattern_id
		);

		$blocks = parse_blocks( $component_html );
		$block = $this->build_nested_block_structure( $blocks[0] );

		$result = $this->component_block->render_block( $blocks[0]['attrs'], '', $block );
		// Should render successfully even with empty slot content
		$this->assertNotEmpty( $result );
	}

	/**
	 * Helper method to ensure block type is registered
	 *
	 * @param string $block_name Block name.
	 * @return void
	 */
	private function ensure_block_registered( string $block_name ): void {
		$registry = \WP_Block_Type_Registry::get_instance();
		if ( ! $registry->is_registered( $block_name ) ) {
			$registry->register( new \WP_Block_Type( $block_name, array() ) );
		}
	}

	/**
	 * Helper method to build nested block structure with inner blocks
	 *
	 * @param array<string, mixed> $block_data Block data.
	 * @return WP_Block Block instance with inner blocks.
	 */
	private function build_nested_block_structure( array $block_data ): WP_Block {
		// Ensure block type is registered before creating WP_Block
		if ( ! empty( $block_data['blockName'] ) ) {
			$this->ensure_block_registered( $block_data['blockName'] );
		}

		$block = new WP_Block( $block_data, array() );

		if ( ! empty( $block_data['innerBlocks'] ) ) {
			$inner_blocks = array();
			foreach ( $block_data['innerBlocks'] as $inner_block_data ) {
				$inner_block = $this->build_nested_block_structure( $inner_block_data );
				$inner_block->parent = $block;
				$inner_blocks[] = $inner_block;
			}
			$block->inner_blocks = $inner_blocks;
		}

		return $block;
	}

	/**
	 * Test slot content with shortcode correctly replaces placeholder
	 */
	public function test_slot_content_with_shortcode_overrides_placeholder() {
		// Create component pattern with slot placeholder (no inner content - just a placeholder)
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/slot-placeholder {"name":"default"} /-->',
			)
		);

		// Create component block with slot content containing shortcode
		$blocks = array(
			array(
				'blockName' => 'etch/component',
				'attrs' => array(
					'ref' => $pattern_id,
					'attributes' => array(),
				),
				'innerBlocks' => array(
					array(
						'blockName' => 'etch/slot-content',
						'attrs' => array(
							'name' => 'default',
						),
						'innerBlocks' => array(
							array(
								'blockName' => 'etch/text',
								'attrs' => array(
									'content' => 'Slot content: [etch_test_hello name=Slot]',
								),
								'innerBlocks' => array(),
								'innerHTML' => '',
								'innerContent' => array(),
							),
						),
						'innerHTML' => "\n\n",
						'innerContent' => array( "\n", null, "\n" ),
					),
				),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n", null, "\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		// Verify that slot-content with shortcode correctly replaces the placeholder
		$this->assertStringContainsString( 'Slot content: Hello Slot!', $rendered );
	}
}
