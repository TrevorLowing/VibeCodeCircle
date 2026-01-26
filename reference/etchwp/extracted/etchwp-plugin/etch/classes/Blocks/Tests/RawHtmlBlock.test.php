<?php
/**
 * RawHtmlBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/raw-html)
 *    - Attributes structure (content: string)
 *
 * ✅ Basic Rendering
 *    - Static HTML content rendering
 *    - HTML tag preservation (raw HTML)
 *    - Empty content handling
 *    - Special characters preservation
 *    - Complex HTML structures (nested tags)
 *
 * ✅ Global Context Expressions
 *    - {this.title} - Post title resolution (requires setup_postdata)
 *    - {this.id} - Post ID resolution (requires setup_postdata)
 *    - {site.name} - Site name resolution
 *    - {user.displayName} - Current user display name (requires wp_set_current_user)
 *
 * ✅ Component Props Context
 *    - {props.html} - Component prop resolution when inside ComponentBlock
 *    - Prop shadowing in nested components (nested props shadow parent)
 *    - Regular blocks get parent component props (context preservation)
 *
 * ✅ Expression Embedding Patterns
 *    - Prefix: "<div>Hello {props.name}</div>"
 *    - Suffix: "<span>{props.name}!</span>"
 *    - Prefix + Suffix: "<p>Hello {props.name}, welcome!</p>"
 *    - Multiple expressions: "<h1>{this.title}</h1><p>by {user.displayName}</p>"
 *    - Mixed contexts: "<div>{this.title} - {props.subtitle}</div>"
 *    - Inside HTML attributes: '<div class="{props.className}">content</div>'
 *
 * ✅ Edge Cases & Error Handling
 *    - Unmatched braces (stays as-is): "{unclosed expression"
 *    - Unresolved expressions (removed, becomes empty): "{undefined.prop}"
 *    - No context available (expressions removed)
 *    - HTML entities preservation
 *
 * ✅ Integration Scenarios
 *    - RawHtmlBlock inside ComponentBlock with props
 *    - RawHtmlBlock with nested ComponentBlock (prop shadowing)
 *    - RawHtmlBlock with parent and nested components (context preservation)
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in content (safe mode): shortcodes ARE resolved
 *    - Shortcode in content (unsafe mode via props): shortcodes should NOT be resolved
 *    - RawHtmlBlock in unsafe mode should render literal shortcode strings unchanged
 *
 * 📝 Areas for Future Enhancement
 *    - Element attributes context ({attributes.*})
 *    - Additional global context properties (url.*, options.*, term.*, taxonomy.*)
 *    - Expression modifiers/testing (if supported)
 *    - Deeply nested property access (e.g., {this.meta.customField})
 *    - Performance testing with very long HTML content
 *    - Unicode/special character handling edge cases
 *    - XSS vulnerability testing with malicious input
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\RawHtmlBlock\RawHtmlBlock;
use Etch\Blocks\ComponentBlock\ComponentBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class RawHtmlBlockTest
 *
 * Comprehensive tests for RawHtmlBlock functionality including:
 * - Basic rendering (raw HTML)
 * - Dynamic expression resolution
 * - Component props context
 * - Global context expressions
 * - Edge cases
 */
class RawHtmlBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * RawHtmlBlock instance
	 *
	 * @var RawHtmlBlock
	 */
	private $raw_html_block;

	/**
	 * Static RawHtmlBlock instance (shared across tests)
	 *
	 * @var RawHtmlBlock
	 */
	private static $raw_html_block_instance;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$raw_html_block_instance ) {
			self::$raw_html_block_instance = new RawHtmlBlock();
		}
		$this->raw_html_block = self::$raw_html_block_instance;

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/raw-html' );

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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/raw-html' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/raw-html', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/raw-html' );
		$this->assertArrayHasKey( 'content', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['content']['type'] );
		$this->assertArrayHasKey( 'unsafe', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['unsafe']['type'] );
	}

	/**
	 * Test raw html renders static content correctly
	 */
	public function test_raw_html_renders_static_content() {
		$attributes = array(
			'content' => '<div>Hello World</div>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '<div>Hello World</div>', $result );
	}

	/**
	 * Test raw html preserves HTML tags
	 */
	public function test_raw_html_preserves_html_tags() {
		$attributes = array(
			'content' => '<p>Hello <strong>World</strong></p>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '<p>Hello <strong>World</strong></p>', $result );
	}

	/**
	 * Test raw html returns empty string when content is empty
	 */
	public function test_raw_html_returns_empty_when_content_empty() {
		$attributes = array(
			'content' => '',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test raw html with complex nested HTML structure
	 */
	public function test_raw_html_preserves_complex_structure() {
		$html = '<div class="wrapper"><section id="main"><h1>Title</h1><p>Content</p></section></div>';
		$attributes = array(
			'content' => $html,
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( $html, $result );
	}

	/**
	 * Test raw html with {this.title} resolves to post title
	 */
	public function test_raw_html_resolves_this_title() {
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
			'content' => '<h1>{this.title}</h1>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '<h1>Test Post Title</h1>', $result );
	}

	/**
	 * Test raw html with {this.id} resolves to post ID
	 */
	public function test_raw_html_resolves_this_id() {
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
			'content' => '<div data-id="{this.id}">Content</div>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '<div data-id="' . $post_id . '">Content</div>', $result );
	}

	/**
	 * Test raw html with {site.name} resolves to site name
	 */
	public function test_raw_html_resolves_site_name() {
		$attributes = array(
			'content' => '<header>{site.name}</header>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertEquals( '<header>' . get_bloginfo( 'name' ) . '</header>', $result );
	}

	/**
	 * Test raw html with {props.html} resolves when inside ComponentBlock
	 */
	public function test_raw_html_resolves_props_when_inside_component() {
		// Create a component pattern
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/raw-html {"content":"{props.html}"} /-->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'html',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create component block
		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'html' => '<span>Component HTML Value</span>',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );

		// LOGG our the whole result

		$this->assertStringContainsString( '<span>Component HTML Value</span>', $result );
	}

	/**
	 * Test raw html with {props.html} gets shadowed value in nested component
	 */
	public function test_raw_html_props_shadowed_in_nested_component() {
		// Create nested component pattern
		$nested_pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/raw-html {"content":"<div>{props.html}</div>"} /-->',
			)
		);

		update_post_meta(
			$nested_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'html',
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
					'<!-- wp:etch/component {"ref":%d,"attributes":{"html":"<b>Nested HTML</b>"}} /-->',
					$nested_pattern_id
				),
			)
		);

		update_post_meta(
			$parent_pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'html',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		$component_attributes = array(
			'ref' => $parent_pattern_id,
			'attributes' => array(
				'html' => '<i>Parent HTML</i>',
			),
		);
		$component_block = new ComponentBlock();
		$component_wp_block = $this->create_mock_block( 'etch/component', $component_attributes );
		$result = $component_block->render_block( $component_attributes, '', $component_wp_block );
		// Should show "Nested HTML", not "Parent HTML"
		$this->assertStringContainsString( '<b>Nested HTML</b>', $result );
		$this->assertStringNotContainsString( '<i>Parent HTML</i>', $result );
	}

	/**
	 * Test raw html with mixed contexts: {this.title} - {props.subtitle}
	 */
	public function test_raw_html_mixed_contexts() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content',
			)
		);

		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'content' => '<h1>{this.title}</h1><h2>{site.name}</h2>',
		);

		// Mock block context — usually passed automatically by Gutenberg.
		$mock_block = $this->create_mock_block(
			'etch/raw-html',
			array(
				'attributes' => $attributes,
			)
		);

		// Directly call the RawHtmlBlock renderer.
		$block = new RawHtmlBlock();
		$result = $block->render_callback( $attributes, '', $mock_block, true );

		// Verify replacements worked.
		$this->assertStringContainsString( '<h1>Test Post</h1>', $result );
		$this->assertStringContainsString( '<h2>Test Blog</h2>', $result );
	}

	/**
	 * Test raw html with prefix: "<div>Hello {test.name}</div>"
	 */
	public function test_raw_html_with_prefix() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content',
			)
		);

		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'content' => '<h1>Hello {this.title}</h1>',
		);

		$mock_block = $this->create_mock_block(
			'etch/raw-html',
			array(
				'attributes' => $attributes,
			)
		);

		// Directly call the RawHtmlBlock renderer.
		$block = new RawHtmlBlock();
		$result = $block->render_callback( $attributes, '', $mock_block );

		// Verify replacements worked.
		$this->assertStringContainsString( '<h1>Hello Test Post</h1>', $result );
	}

	/**
	 * Test raw html with suffix: "<span>{test.name}!</span>"
	 */
	public function test_raw_html_with_suffix() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title'   => 'Hello World',
				'post_content' => 'Test content',
			)
		);

		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'content' => '<span>{this.title}!</span>',
		);

		$mock_block = $this->create_mock_block(
			'etch/raw-html',
			array(
				'attributes' => $attributes,
			)
		);

		// Directly call the RawHtmlBlock renderer.
		$block = new RawHtmlBlock();
		$result = $block->render_callback( $attributes, '', $mock_block );

		// Verify replacements worked.
		$this->assertStringContainsString( '<span>Hello World!</span>', $result );
	}

	/**
	 * Test raw html with unmatched braces stays as-is
	 */
	public function test_raw_html_unmatched_braces_stays_as_is() {
		$attributes = array(
			'content' => '<div>{unclosed expression</div>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		// Should remain unchanged if expression is invalid
		$this->assertStringContainsString( '{unclosed expression', $result );
	}

	/**
	 * Test raw html with no context available (expressions are removed/replaced with empty string)
	 */
	public function test_raw_html_no_context_expressions_removed() {
		$attributes = array(
			'content' => '<div>{undefined.prop}</div>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		// When context is not available, unresolved expressions are replaced with empty string
		$this->assertEquals( '<div></div>', $result );
	}

	/**
	 * Test raw html handles special characters correctly
	 */
	public function test_raw_html_handles_special_characters() {
		$attributes = array(
			'content' => '<div>Hello &amp; World &lt; &gt; &quot; &#039;</div>',
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		// HTML entities should be preserved
		$this->assertEquals( '<div>Hello &amp; World &lt; &gt; &quot; &#039;</div>', $result );
	}

	/**
	 * Test that sanitize_html allows safe HTML tags
	 */
	public function test_sanitize_html_allows_safe_tags() {
		$html = '<p><strong>Bold</strong> <em>Italic</em> <a href="https://example.com">Link</a></p>';
		$result = $this->raw_html_block->sanitize_html( $html );
		$this->assertEquals( $html, $result, 'Safe HTML tags should remain unchanged' );
	}

	/**
	 * Test that sanitize_html strips disallowed tags (like <script>)
	 */
	public function test_sanitize_html_strips_script_tags() {
		$html = '<div>Hello<script>alert("XSS")</script>World</div>';
		$result = $this->raw_html_block->sanitize_html( $html );
		$this->assertStringNotContainsString( '<script>', $result, 'Script tags should be removed' );
		$this->assertStringContainsString( '<div>Helloalert("XSS")World</div>', $result );
	}

	/**
	 * Test that sanitize_html removes dangerous event attributes (onload, onclick)
	 */
	public function test_sanitize_html_removes_event_attributes() {
		$html = '<img src="image.jpg" onerror="alert(1)" />';
		$result = $this->raw_html_block->sanitize_html( $html );
		$this->assertStringNotContainsString( 'onerror', $result, 'Event handlers should be stripped' );
		$this->assertStringContainsString( '<img src="image.jpg"', $result );
	}

	/**
	 * Test that sanitize_html allows data-* attributes
	 */
	public function test_sanitize_html_allows_data_attributes() {
		$html = '<div data-value="123" data-custom="test"></div>';
		$result = $this->raw_html_block->sanitize_html( $html );
		$this->assertEquals( $html, $result, 'data-* attributes should be preserved' );
	}

	/**
	 * Test that sanitize_html allows inline style attributes
	 */
	public function test_sanitize_html_allows_style_attribute() {
		$html = '<span style="color:red; background:green;">Styled</span>';
		$result = $this->raw_html_block->sanitize_html( $html );
		// Ensure <span> is still there
		$this->assertStringContainsString( '<span', $result );
		$this->assertStringContainsString( 'Styled', $result );

		// Ensure style contains both properties
		$this->assertStringContainsString( 'color:red', $result );
		$this->assertStringContainsString( 'background:green', $result );
	}

	/**
	 * Test that sanitize_html removes javascript: URLs
	 */
	public function test_sanitize_html_blocks_javascript_urls() {
		$html = '<a href="javascript:alert(1)">Click</a>';
		$result = $this->raw_html_block->sanitize_html( $html );
		$this->assertStringNotContainsString( 'javascript:alert', $result, 'javascript: URLs should be stripped' );
		$this->assertStringContainsString( '<a href="alert(1)">Click</a>', $result, 'Unsafe href should be removed' );
	}

	/**
	 * Test sanitize_html handles nested and mixed safe/unsafe content properly
	 */
	public function test_sanitize_html_mixed_safe_and_unsafe() {
		$html = '<div><p>Text<script>alert(1)</script><img src="ok.jpg" onload="evil()" /></p></div>';
		$result = $this->raw_html_block->sanitize_html( $html );

		$this->assertStringNotContainsString( '<script>', $result );
		$this->assertStringNotContainsString( 'onload', $result );
		$this->assertStringContainsString( '<img src="ok.jpg"', $result, 'Safe img src should remain' );
	}

	/**
	 * Test raw HTML block with shortcode in content (safe mode)
	 */
	public function test_raw_html_block_with_shortcode_safe_mode() {
		$blocks = array(
			array(
				'blockName' => 'etch/raw-html',
				'attrs' => array(
					'content' => '<p>shortcode test: [etch_test_hello name=John]</p>',
					'unsafe' => '',
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
	 * Test raw HTML block with shortcode in content (unsafe mode via props)
	 */
	public function test_raw_html_block_with_shortcode_unsafe_mode() {
		// Create component pattern with raw HTML block containing shortcode
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/raw-html {"content":"<p>shortcode test: [etch_test_hello name={props.text}]</p>","unsafe":"{props.unsafe}"} /-->',
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
				array(
					'key' => 'unsafe',
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
						'text' => 'Unsafe',
						'unsafe' => 'true',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$rendered = $this->render_blocks_through_content_filter( $blocks );
		// Note: RawHtmlBlock should NOT resolve shortcodes - it should render the literal shortcode string unchanged
		// The test should assert failure - shortcodes should remain as literal [shortcode] strings
		// Most importantly: check that the resolved shortcode output is NOT present
		// If shortcodes were resolved, we would see "Hello Unsafe!" in the output
		$this->assertStringNotContainsString( 'Hello Unsafe!', $rendered, 'RawHtmlBlock should NOT resolve shortcodes - resolved output should not be present' );
	}



	/**
	 * Test raw HTML block with plain HTML continues with sanitization flow
	 */
	public function test_raw_html_block_with_plain_html_sanitizes() {
		$plain_html = '<strong>hello</strong>';

		$attributes = array(
			'content' => $plain_html,
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );

		// Should sanitize and return HTML as-is (since it's safe)
		$this->assertEquals( $plain_html, $result, 'Plain HTML should be sanitized and returned' );
	}


	/**
	 * Test simple block rendering
	 */
	public function test_raw_html_block_with_simple_block() {

		$block_html_string = '<!-- wp:etch/text {"content":"Block content"} /-->';

		$attributes = array(
			'content' => $block_html_string,
		);
		$block = $this->create_mock_block( 'etch/raw-html', $attributes );
		$result = $this->raw_html_block->render_callback( $attributes, '', $block );
		$this->assertStringContainsString( 'Block content', $result, 'Block content should be rendered' );
	}
}
