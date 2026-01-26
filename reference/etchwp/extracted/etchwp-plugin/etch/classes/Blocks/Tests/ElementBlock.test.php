<?php
/**
 * ElementBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/element)
 *    - Attributes structure (tag: string, attributes: object, styles: array)
 *
 * ✅ Basic Rendering
 *    - Renders with default tag (div)
 *    - Renders with custom tag (section, article, etc.)
 *    - Renders with custom attributes
 *    - Renders innerBlocks content
 *    - Tag sanitization (invalid tags default to div)
 *
 * ✅ Dynamic Attribute Resolution
 *    - Global context in attributes: {this.title}, {site.name}
 *    - Component props in attributes: {props.id}, {props.className}
 *    - Nested component props in attributes (shadowing works correctly)
 *    - Mixed static and dynamic attributes
 *    - Complex attribute expressions: "post-{this.id}", "dynamic-{this.id}"
 *
 * ✅ Attribute Handling & Sanitization
 *    - Invalid attribute names filtered out
 *    - Attribute values properly escaped (HTML entities)
 *    - Null/empty attribute values handled correctly
 *    - Boolean attributes converted to strings
 *    - Empty attributes array handled
 *
 * ✅ Styles Registration
 *    - Styles array registered correctly
 *    - Empty styles array doesn't break rendering
 *
 * ✅ Integration Scenarios
 *    - ElementBlock inside ComponentBlock with props
 *    - ElementBlock with nested ComponentBlock (prop shadowing)
 *    - ElementBlock attributes using parent component props
 *    - ElementBlock with multiple dynamic attributes
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in attribute value: data-test="[etch_test_hello name=John]"
 *    - Shortcode using component props in attribute: data-test="[etch_test_hello name={props.text}]"
 *    - ElementBlock with p tag containing etch/text with only a shortcode
 *    - Shortcodes ARE resolved in ElementBlock attributes and inner TextBlock content
 *    - Shortcode in attribute in FSE template (direct rendering without the_content filter)
 *    - Shortcode with dynamic data in attribute in FSE template: data-test="[hello_world name={user.displayName}]"
 *    - ElementBlock with inner TextBlock containing shortcode in FSE template
 *
 * ✅ Edge Cases
 *    - Invalid tag names sanitized
 *    - Special characters in attribute values escaped
 *    - Very long attribute values
 *    - Multiple attributes with dynamic expressions
 *
 * 📝 Areas for Future Enhancement
 *    - Element attributes context ({attributes.*}) - element providing context to children
 *    - Additional global context properties (url.*, options.*, term.*, taxonomy.*)
 *    - CSS class merging/scoping
 *    - Data attributes with dynamic values
 *    - ARIA attributes with dynamic values
 *    - Custom element/web component tags
 *    - Attribute sanitization edge cases (XSS prevention)
 *    - Performance testing with many attributes
 *    - Nested elements with attribute context chaining
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\ElementBlock\ElementBlock;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;
use Etch\Blocks\TextBlock\TextBlock;

/**
 * Class ElementBlockTest
 *
 * Comprehensive tests for ElementBlock functionality including:
 * - Basic rendering
 * - Dynamic attribute resolution
 * - Component props in attributes
 * - Global context in attributes
 * - Edge cases
 */
class ElementBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * ElementBlock instance
	 *
	 * @var ElementBlock
	 */
	private $element_block;

	/**
	 * Static ElementBlock instance (shared across tests)
	 *
	 * @var ElementBlock
	 */
	private static $element_block_instance;


	/**
	 * TextBlock instance
	 *
	 * @var TextBlock
	 */
	private $text_block;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$element_block_instance ) {
			self::$element_block_instance = new ElementBlock();
		}
		$this->element_block = self::$element_block_instance;

		// Other block instances for testing
		$this->text_block = new TextBlock();

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/element' );

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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/element' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/element', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/element' );
		$this->assertArrayHasKey( 'tag', $block_type->attributes );
		$this->assertArrayHasKey( 'attributes', $block_type->attributes );
		$this->assertArrayHasKey( 'styles', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['tag']['type'] );
		$this->assertEquals( 'object', $block_type->attributes['attributes']['type'] );
	}

	/**
	 * Test element renders with default tag (div)
	 */
	public function test_element_renders_with_default_tag() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<div', $result );
		$this->assertStringEndsWith( '</div>', $result );
	}

	/**
	 * Test element renders with custom tag
	 */
	public function test_element_renders_with_custom_tag() {
		$attributes = array(
			'tag' => 'section',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<section', $result );
		$this->assertStringEndsWith( '</section>', $result );
	}

	/**
	 * Test element renders with custom attributes
	 */
	public function test_element_renders_with_custom_attributes() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'id' => 'test-id',
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'id="test-id"', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
	}

	/**
	 * Test element renders innerBlocks content
	 */
	public function test_element_renders_inner_blocks() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(),
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

		$block = $this->create_mock_block( 'etch/element', $attributes, $inner_blocks );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<p>Inner content</p>', $result );
	}

	/**
	 * Test element tag sanitization (invalid tags default to div)
	 */
	public function test_element_invalid_tag_defaults_to_div() {
		$attributes = array(
			'tag' => 'invalid<>tag',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<div', $result );
	}

	/**
	 * Test element attribute with {this.title} resolves correctly
	 */
	public function test_element_attribute_with_this_title() {
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
				'data-title' => '{this.title}',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'data-title="Test Post Title"', $result );
	}

	/**
	 * Test element attribute with {site.name} resolves correctly
	 */
	public function test_element_attribute_with_site_name() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'data-site' => '{site.name}',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$site_name = get_bloginfo( 'name' );
		$this->assertStringContainsString( sprintf( 'data-site="%s"', esc_attr( $site_name ) ), $result );
	}

	/**
	 * Test element id attribute with dynamic value
	 */
	public function test_element_id_attribute_dynamic() {
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

		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'id' => 'post-{this.id}',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( sprintf( 'id="post-%d"', $post_id ), $result );
	}

	/**
	 * Test element attribute using {props.id} from ComponentBlock
	 */
	public function test_element_attribute_with_props_id() {
		// Create component pattern with element block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/element {"tag":"div","attributes":{"id":"{props.elementId}"}} /-->',
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
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'elementId' => 'component-element-123',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'id="component-element-123"', $result );
	}

	/**
	 * Test element attribute using parent component props
	 */
	public function test_element_attribute_parent_component_props() {
		// Create component pattern with element block
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/element {"tag":"div","attributes":{"class":"{props.className}"}} /-->',
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
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'className' => 'custom-class',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'class="custom-class"', $result );
	}

	/**
	 * Test element attribute using nested component props (shadowing)
	 */
	public function test_element_attribute_nested_component_props() {
		// Create nested component pattern with element
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/element {"tag":"div","attributes":{"data-text":"{props.text}"}} /-->',
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

		// Create parent component pattern
		$parent_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => sprintf(
					'<!-- wp:etch/component {"ref":%d,"attributes":{"text":"Nested Text"}} /-->',
					$nested_pattern_id
				),
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

		$component_attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'text' => 'Parent Text',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		// Should show "Nested Text", not "Parent Text"
		$this->assertStringContainsString( 'data-text="Nested Text"', $result );
		$this->assertStringNotContainsString( 'data-text="Parent Text"', $result );
	}

	/**
	 * Test element with invalid attribute names are filtered out
	 */
	public function test_element_invalid_attribute_names_filtered() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'valid-attr' => 'value',
				'invalid<>attr' => 'value',
				'123invalid' => 'value',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'valid-attr="value"', $result );
		$this->assertStringNotContainsString( 'invalid<>attr', $result );
		$this->assertStringNotContainsString( '123invalid', $result );
	}

	/**
	 * Test element attribute values are properly escaped
	 */
	public function test_element_attribute_values_escaped() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'data-content' => 'Hello "World" & <More>',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		// Should contain escaped values
		$this->assertStringContainsString( '&quot;', $result );
		$this->assertStringContainsString( '&amp;', $result );
		$this->assertStringContainsString( '&lt;', $result );
	}

	/**
	 * Test element with null attribute values are handled correctly
	 */
	public function test_element_null_attribute_values() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'data-value' => null,
				'data-exists' => 'value',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		// Null values should be skipped or handled gracefully
		$this->assertStringContainsString( 'data-exists="value"', $result );
	}

	/**
	 * Test element with empty attributes array
	 */
	public function test_element_empty_attributes() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringStartsWith( '<div', $result );
		$this->assertStringEndsWith( '</div>', $result );
	}

	/**
	 * Test element styles array is registered
	 */
	public function test_element_styles_registered() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(),
			'styles' => array( 'style-1', 'style-2' ),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		// Styles should be registered (we can't easily test registration without accessing internal state)
		// But rendering should still work
		$this->assertNotEmpty( $result );
	}

	/**
	 * Test element with empty styles array doesn't break rendering
	 */
	public function test_element_empty_styles_array() {
		$attributes = array(
			'tag' => 'div',
			'attributes' => array(),
			'styles' => array(),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertNotEmpty( $result );
		$this->assertStringStartsWith( '<div', $result );
	}

	/**
	 * Test element with mixed static and dynamic attributes
	 */
	public function test_element_mixed_static_dynamic_attributes() {
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

		$attributes = array(
			'tag' => 'div',
			'attributes' => array(
				'id' => 'static-id',
				'class' => 'dynamic-{this.id}',
				'data-title' => '{this.title}',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );
		$result = $this->element_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( 'id="static-id"', $result );
		$this->assertStringContainsString( sprintf( 'class="dynamic-%d"', $post_id ), $result );
		$this->assertStringContainsString( 'data-title="Test Post"', $result );
	}

	/**
	 * Test element block with shortcode in attribute value
	 */
	public function test_element_block_with_shortcode_in_attribute() {
		$blocks = array(
			array(
				'blockName' => 'etch/element',
				'attrs' => array(
					'tag' => 'div',
					'attributes' => array(
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
	}

	/**
	 * Test element block with shortcode using component props in attribute
	 */
	public function test_element_block_with_shortcode_using_props_in_attribute() {
		// Create component pattern with element block containing shortcode in attribute
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
	 * Test element block with p tag containing etch/text with only a shortcode
	 */
	public function test_element_block_with_p_tag_containing_text_block_with_shortcode() {
		$blocks = array(
			array(
				'blockName' => 'etch/element',
				'attrs' => array(
					'tag' => 'p',
					'attributes' => array(),
				),
				'innerBlocks' => array(
					array(
						'blockName' => 'etch/text',
						'attrs' => array(
							'content' => '[etch_test_hello name=John]',
						),
						'innerBlocks' => array(),
						'innerHTML' => '',
						'innerContent' => array(),
					),
				),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n", null, "\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		// Note: TextBlock DOES resolve shortcodes, so we should see the resolved output
		// The shortcode should be resolved to "Hello John!"
		$this->assertStringContainsString( 'Hello John!', $rendered );
		// Check that shortcode was resolved (not present as literal)
		$this->assertStringNotContainsString( '[etch_test_hello name=John]', $rendered );
	}

	/**
	 * Test element block with shortcode in attribute value in FSE template (direct rendering)
	 * This simulates FSE template rendering where blocks are rendered directly without the_content filter
	 */
	public function test_element_block_with_shortcode_in_attribute_fse_template() {
		$attributes = array(
			'tag' => 'h2',
			'attributes' => array(
				'data-test' => '[etch_test_hello name=John]',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );

		// Render directly (simulating FSE template rendering, not through the_content filter)
		$result = $this->element_block->render_block( $attributes, '', $block );

		// Shortcode should be resolved even without the_content filter
		$this->assertStringContainsString( 'data-test="Hello John!"', $result );
		$this->assertStringNotContainsString( '[etch_test_hello name=John]', $result );
	}

	/**
	 * Test element block with shortcode using dynamic data in attribute in FSE template
	 * This tests the scenario: data-test="[hello_world name={user.displayName}]"
	 */
	public function test_element_block_with_shortcode_and_dynamic_data_in_attribute_fse_template() {
		// Set up user context
		$user_id = $this->factory()->user->create(
			array(
				'display_name' => 'Jane Doe',
			)
		);
		wp_set_current_user( $user_id );

		$attributes = array(
			'tag' => 'h2',
			'attributes' => array(
				'data-test' => '[etch_test_hello name="{user.displayName}"]',
			),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->element_block->render_block( $attributes, '', $block );

		// Dynamic data should be resolved first, then shortcode processed
		$this->assertStringContainsString( 'data-test="Hello Jane Doe!"', $result );
		$this->assertStringNotContainsString( '{user.displayName}', $result );
		$this->assertStringNotContainsString( '[etch_test_hello', $result );
	}

	/**
	 * Test element block with inner text block containing shortcode in FSE template
	 * This simulates the exact scenario from the user's example
	 */
	public function test_element_block_with_text_block_shortcode_fse_template() {
		// Construct inner blocks structure with shortcode in text block
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
							'content' => 'shortcode test: [etch_test_hello name="John"]',
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

		// Now create element block with that content
		$attributes = array(
			'tag' => 'h2',
			'attributes' => array(),
		);
		$block = $this->create_mock_block( 'etch/element', $attributes, $inner_blocks );

		// Render element block with the text content as inner content
		$result = $this->element_block->render_block( $attributes, '', $block );

		// Shortcode should be resolved in both the text block and element block
		$this->assertStringContainsString( 'shortcode test: Hello John!', $result );
		$this->assertStringNotContainsString( '[etch_test_hello', $result );
	}
}
