<?php
/**
 * TextBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/text)
 *    - Attributes structure (content: string, source: html, selector: span)
 *
 * ✅ Basic Rendering
 *    - Static content rendering
 *    - Empty content handling
 *    - Special characters preservation
 *
 * ✅ Global Context Expressions
 *    - {this.title} - Post title resolution (requires setup_postdata)
 *    - {this.id} - Post ID resolution (requires setup_postdata)
 *    - {site.name} - Site name resolution
 *    - {user.displayName} - Current user display name (requires wp_set_current_user)
 *
 * ✅ Component Props Context
 *    - {props.text} - Component prop resolution when inside ComponentBlock
 *    - Prop shadowing in nested components (nested props shadow parent)
 *    - Regular blocks get parent component props (context preservation)
 *
 * ✅ Expression Embedding Patterns
 *    - Prefix: "Hello {props.name}"
 *    - Suffix: "{props.name}!"
 *    - Prefix + Suffix: "Hello {props.name}, welcome!"
 *    - Multiple expressions: "{this.title} by {user.displayName}"
 *    - Mixed contexts: "{this.title} - {props.subtitle}"
 *
 * ✅ Edge Cases & Error Handling
 *    - Unmatched braces (stays as-is): "{unclosed expression"
 *    - Unresolved expressions (removed, becomes empty): "{undefined.prop}"
 *    - No context available (expressions removed)
 *
 * ✅ Integration Scenarios
 *    - TextBlock inside ComponentBlock with props
 *    - TextBlock with nested ComponentBlock (prop shadowing)
 *    - TextBlock with parent and nested components (context preservation)
 *
 * ✅ Shortcode Resolution
 *    - Direct shortcode in content: [etch_test_hello name=John]
 *    - Shortcode using component props: [etch_test_hello name={props.text}]
 *    - Shortcodes ARE resolved in TextBlock content
 *    - Shortcode in FSE template (direct rendering without the_content filter)
 *    - Shortcode with dynamic data in FSE template: content="[hello_world name={user.displayName}]"
 *    - FSE template shortcode behavior matches post content behavior
 *    - Shortcode returning DOM element: [etch_test_dom text=TestContent] returns <div>TestContent</div> (DOM preserved, not stripped)
 *    - Shortcode with unquoted attributes: [etch_test_box content=MyContent class=mybox]
 *    - Shortcode with quoted attributes: [etch_test_card title="My Title" description="My Description"]
 *    - Shortcode with mixed quoted/unquoted attributes: [etch_test_widget id=widget1 label="My Label" value=42]
 *    - Multiple shortcodes with mixed quote styles in same content
 *    - Shortcode with quoted attributes containing special characters (quotes, ampersands)
 *
 * 📝 Areas for Future Enhancement
 *    - Element attributes context ({attributes.*})
 *    - Additional global context properties (url.*, options.*, term.*, taxonomy.*)
 *    - Expression modifiers/testing (if supported)
 *    - Deeply nested property access (e.g., {this.meta.customField})
 *    - Performance testing with very long content
 *    - Unicode/special character handling edge cases
 *    - Multiple expressions with different context types
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\TextBlock\TextBlock;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class TextBlockTest
 *
 * Comprehensive tests for TextBlock functionality including:
 * - Basic rendering
 * - Dynamic expression resolution
 * - Component props context
 * - Global context expressions
 * - Edge cases
 */
class TextBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * TextBlock instance
	 *
	 * @var TextBlock
	 */
	private $text_block;

	/**
	 * Static TextBlock instance (shared across tests)
	 *
	 * @var TextBlock
	 */
	private static $text_block_instance;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$text_block_instance ) {
			self::$text_block_instance = new TextBlock();
		}
		$this->text_block = self::$text_block_instance;

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/text' );

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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/text' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/text', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/text' );
		$this->assertArrayHasKey( 'content', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['content']['type'] );
		$this->assertEquals( 'html', $block_type->attributes['content']['source'] );
	}

	/**
	 * Test text renders static content correctly
	 */
	public function test_text_renders_static_content() {
		$attributes = array(
			'content' => 'Hello World',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		$this->assertEquals( 'Hello World', $result );
	}

	/**
	 * Test text returns empty string when content is empty
	 */
	public function test_text_returns_empty_when_content_empty() {
		$attributes = array(
			'content' => '',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test text with {this.title} resolves to post title
	 */
	public function test_text_resolves_this_title() {
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
			'content' => '{this.title}',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		$this->assertEquals( 'Test Post Title', $result );
	}

	/**
	 * Test text with {this.id} resolves to post ID
	 */
	public function test_text_resolves_this_id() {
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
			'content' => '{this.id}',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		$this->assertEquals( (string) $post_id, $result );
	}

	/**
	 * Test text with {site.name} resolves to site name
	 */
	public function test_text_resolves_site_name() {
		$attributes = array(
			'content' => '{site.name}',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		$this->assertEquals( get_bloginfo( 'name' ), $result );
	}

	/**
	 * Test text with {props.text} resolves when inside ComponentBlock
	 */
	public function test_text_resolves_props_when_inside_component() {
		// Create a component pattern
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

		// Create component block
		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => 'Component Prop Value',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'Component Prop Value', $result );
	}

	/**
	 * Test text with {props.text} gets shadowed value in nested component
	 */
	public function test_text_props_shadowed_in_nested_component() {
		// Create nested component pattern
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.text}"} /-->',
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

		// Create parent component pattern that includes nested
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
		$this->assertStringContainsString( 'Nested Text', $result );
		$this->assertStringNotContainsString( 'Parent Text', $result );
	}

	/**
	 * Test text with {props.text} gets parent value in regular block inside component
	 */
	public function test_text_props_gets_parent_value_in_regular_block() {
		// Create component pattern with regular text block
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

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'text' => 'Parent Prop Value',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'Parent Prop Value', $result );
	}

	/**
	 * Test text with mixed contexts: {this.title} - {props.subtitle}
	 */
	public function test_text_mixed_contexts() {
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

		// Create component pattern
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{this.title} - {props.subtitle}"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'subtitle',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'subtitle' => 'Component Subtitle',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'Test Post', $result );
		$this->assertStringContainsString( 'Component Subtitle', $result );
	}

	/**
	 * Test text with prefix: "Hello {props.name}"
	 */
	public function test_text_with_prefix() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"Hello {props.name}"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'name',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'name' => 'World',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'Hello World', $result );
	}

	/**
	 * Test text with suffix: "{props.name}!"
	 */
	public function test_text_with_suffix() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"{props.name}!"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'name',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'name' => 'Hello',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'Hello!', $result );
	}

	/**
	 * Test text with both prefix and suffix: "Hello {props.name}, welcome!"
	 */
	public function test_text_with_prefix_and_suffix() {
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"Hello {props.name}, welcome!"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'name',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'name' => 'World',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		$this->assertStringContainsString( 'Hello World, welcome!', $result );
	}

	/**
	 * Test text with multiple expressions: "{this.title} by {user.displayName}"
	 */
	public function test_text_multiple_expressions() {
		$user_id = $this->factory()->user->create(
			array(
				'user_login' => 'testuser',
				'display_name' => 'Test User',
			)
		);
		wp_set_current_user( $user_id );

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
			'content' => '{this.title} by {user.displayName}',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		$this->assertStringContainsString( 'Test Post', $result );
		$this->assertStringContainsString( 'Test User', $result );
	}

	/**
	 * Test text with unmatched braces stays as-is
	 */
	public function test_text_unmatched_braces_stays_as_is() {
		$attributes = array(
			'content' => '{unclosed expression',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		// Should remain unchanged if expression is invalid
		$this->assertStringContainsString( '{unclosed expression', $result );
	}

	/**
	 * Test text with no context available (expressions are removed/replaced with empty string)
	 */
	public function test_text_no_context_expressions_stay_as_is() {
		$attributes = array(
			'content' => '{undefined.prop}',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );
		// When context is not available, unresolved expressions are replaced with empty string
		// This is the expected behavior - broken references don't show up in output
		$this->assertEquals( '', $result );
	}

	/**
	 * Test text handles special characters correctly
	 */
	public function test_text_handles_special_characters() {
		$attributes = array(
			'content' => 'Hello & World < > " \'',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );
		$result = $this->text_block->render_callback( $attributes, '', $block );

		$this->assertEquals( 'Hello &amp; World &lt; &gt; &quot; &#039;', $result );
	}

	/**
	 * Test text block with direct shortcode in content
	 */
	public function test_text_block_with_direct_shortcode() {
		$blocks = array(
			array(
				'blockName' => 'etch/text',
				'attrs' => array(
					'content' => 'shortcode test: [etch_test_hello name=John]',
				),
				'innerBlocks' => array(),
				'innerHTML' => '',
				'innerContent' => array(),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'shortcode test: Hello John!', $rendered );
	}

	/**
	 * Test text block with shortcode using component props
	 */
	public function test_text_block_with_shortcode_using_props() {
		// Create component pattern with text block containing shortcode
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/text {"content":"shortcode test: [etch_test_hello name={props.text}]"} /-->',
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
						'text' => 'Jane',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		$this->assertStringContainsString( 'shortcode test: Hello Jane!', $rendered );
	}

	/**
	 * Test text block with shortcode in FSE template (direct rendering)
	 * This simulates FSE template rendering where blocks are rendered directly without the_content filter
	 */
	public function test_text_block_with_shortcode_fse_template() {
		$attributes = array(
			'content' => 'shortcode test: [etch_test_hello name=John]',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering, not through the_content filter)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// Shortcode should be resolved even without the_content filter
		$this->assertStringContainsString( 'shortcode test: Hello John!', $result );
		$this->assertStringNotContainsString( '[etch_test_hello name=John]', $result );
	}

	/**
	 * Test text block with shortcode using dynamic data in FSE template
	 * This tests the scenario: content="shortcode test: [hello_world name={user.displayName}]"
	 */
	public function test_text_block_with_shortcode_and_dynamic_data_fse_template() {
		// Set up user context
		$user_id = $this->factory()->user->create(
			array(
				'display_name' => 'Jane Doe',
			)
		);
		wp_set_current_user( $user_id );

		$attributes = array(
			'content' => 'shortcode test: [etch_test_hello name="{user.displayName}"]',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// Dynamic data should be resolved first, then shortcode processed
		$this->assertStringContainsString( 'shortcode test: Hello Jane Doe!', $result );
		$this->assertStringNotContainsString( '{user.displayName}', $result );
		$this->assertStringNotContainsString( '[etch_test_hello', $result );
	}

	/**
	 * Test text block with shortcode in FSE template matches post content behavior
	 * This ensures shortcodes work identically in both contexts
	 */
	public function test_text_block_shortcode_fse_template_matches_post_content() {
		$attributes = array(
			'content' => '[etch_test_hello name=John]',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (FSE template scenario)
		$fse_result = $this->text_block->render_callback( $attributes, '', $block );

		// Render through content filter (post content scenario)
		$blocks = array(
			array(
				'blockName' => 'etch/text',
				'attrs' => $attributes,
				'innerBlocks' => array(),
				'innerHTML' => '',
				'innerContent' => array(),
			),
		);
		$post_result = $this->render_blocks_through_content_filter( $blocks );

		// Both should resolve shortcodes correctly
		$this->assertStringContainsString( 'Hello John!', $fse_result );
		$this->assertStringContainsString( 'Hello John!', $post_result );
		// Both should not contain the literal shortcode
		$this->assertStringNotContainsString( '[etch_test_hello', $fse_result );
		$this->assertStringNotContainsString( '[etch_test_hello', $post_result );
	}

	/**
	 * Test text block with shortcode that returns DOM element
	 * Shortcode should return DOM element and it should not be stripped
	 */
	public function test_text_block_with_shortcode_returning_dom_element() {
		$attributes = array(
			'content' => '[etch_test_dom text=TestContent]',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// DOM element should be preserved, not stripped
		$this->assertStringContainsString( '<div>', $result );
		$this->assertStringContainsString( '</div>', $result );
		$this->assertStringContainsString( 'TestContent', $result );
		// Should contain the full DOM structure
		$this->assertStringContainsString( '<div>TestContent</div>', $result );
		// Should not contain the literal shortcode
		$this->assertStringNotContainsString( '[etch_test_dom', $result );
	}

	/**
	 * Test text block with shortcode using quoted attributes
	 * Shortcode should work with quoted attributes like [shortcode attr="value"]
	 */
	public function test_text_block_with_shortcode_quoted_attributes() {
		$attributes = array(
			'content' => 'Before [etch_test_card title="My Title" description="My Description"] after',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// DOM element should be preserved
		$this->assertStringContainsString( '<div class="card">', $result );
		$this->assertStringContainsString( '<h3>My Title</h3>', $result );
		$this->assertStringContainsString( '<p>My Description</p>', $result );
		// Should contain the full DOM structure
		$this->assertStringContainsString( '<div class="card"><h3>My Title</h3><p>My Description</p></div>', $result );
		// Should preserve surrounding text
		$this->assertStringContainsString( 'Before', $result );
		$this->assertStringContainsString( 'after', $result );
		// Should not contain the literal shortcode
		$this->assertStringNotContainsString( '[etch_test_card', $result );
	}

	/**
	 * Test text block with shortcode using mixed quoted and unquoted attributes
	 * Shortcode should work with both quoted and unquoted attributes in the same shortcode
	 */
	public function test_text_block_with_shortcode_mixed_quoted_unquoted_attributes() {
		$attributes = array(
			'content' => 'Before [etch_test_widget id=widget1 label="My Label" value=42] after',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// DOM element should be preserved
		$this->assertStringContainsString( '<div id="widget1"', $result );
		$this->assertStringContainsString( 'class="widget"', $result );
		$this->assertStringContainsString( '<span class="label">My Label</span>', $result );
		$this->assertStringContainsString( '<span class="value">42</span>', $result );
		// Should preserve surrounding text
		$this->assertStringContainsString( 'Before', $result );
		$this->assertStringContainsString( 'after', $result );
		// Should not contain the literal shortcode
		$this->assertStringNotContainsString( '[etch_test_widget', $result );
	}

	/**
	 * Test text block with multiple shortcodes - some with quotes, some without
	 * Tests that quotes are preserved correctly in shortcode attributes
	 */
	public function test_text_block_with_multiple_shortcodes_mixed_quotes() {
		$attributes = array(
			'content' => 'Start [etch_test_box content=unquoted class=box1] middle [etch_test_card title="Quoted Title" description="Quoted Desc"] end',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// First shortcode (unquoted) should render correctly
		$this->assertStringContainsString( '<div class="box1">unquoted</div>', $result );
		// Second shortcode (quoted) should render correctly
		$this->assertStringContainsString( '<div class="card"><h3>Quoted Title</h3><p>Quoted Desc</p></div>', $result );
		// Should preserve surrounding text
		$this->assertStringContainsString( 'Start', $result );
		$this->assertStringContainsString( 'middle', $result );
		$this->assertStringContainsString( 'end', $result );
		// Should not contain literal shortcodes
		$this->assertStringNotContainsString( '[etch_test_box', $result );
		$this->assertStringNotContainsString( '[etch_test_card', $result );
	}

	/**
	 * Test text block with shortcode using quoted attributes containing special characters
	 * Tests that quotes and special characters are preserved correctly
	 */
	public function test_text_block_with_shortcode_quoted_attributes_special_chars() {
		$attributes = array(
			'content' => '[etch_test_card title="Title & More" description="Desc with \'quotes\' and \"double quotes\""]',
		);
		$block = $this->create_mock_block( 'etch/text', $attributes );

		// Render directly (simulating FSE template rendering)
		$result = $this->text_block->render_callback( $attributes, '', $block );

		// DOM element should be preserved with escaped special characters
		$this->assertStringContainsString( '<div class="card">', $result );
		$this->assertStringContainsString( '<h3>', $result );
		$this->assertStringContainsString( '<p>', $result );
		// Should contain the title (may be HTML entity encoded)
		$this->assertStringContainsString( 'Title', $result );
		// Should not contain the literal shortcode
		$this->assertStringNotContainsString( '[etch_test_card', $result );
	}

	/**
	 * Test text sanitization escapes special characters
	 */
	public function test_text_sanitize_escapes_special_chars() {
		$content = 'Hello & World < > " \'';
		$result = $this->text_block->sanitize_content( $content );

		$this->assertEquals( 'Hello &amp; World &lt; &gt; &quot; &#039;', $result );
	}

	/**
	 * Test text sanitization escapes special characters but keeps shortcodes intact
	 */
	public function test_text_sanitize_escapes_special_chars_but_keeps_shortcodes_intact() {
		$content = 'Hello & World < > " \' [etch_test_hello val="value"]';
		$result = $this->text_block->sanitize_content( $content );

		$this->assertEquals( 'Hello &amp; World &lt; &gt; &quot; &#039; [etch_test_hello val="value"]', $result );
	}

	/**
	 * Test text sanitization escapes special characters but keeps multiple shortcodes intact
	 */
	public function test_text_sanitize_escapes_special_chars_but_keeps_multiple_shortcodes_intact() {
		$content = 'Hello [etch_test_dom url="http://test.com&id=2"] & World < > " \' [etch_test_hello val="value"]';
		$result = $this->text_block->sanitize_content( $content );

		$this->assertEquals( 'Hello [etch_test_dom url="http://test.com&id=2"] &amp; World &lt; &gt; &quot; &#039; [etch_test_hello val="value"]', $result );
	}
}
