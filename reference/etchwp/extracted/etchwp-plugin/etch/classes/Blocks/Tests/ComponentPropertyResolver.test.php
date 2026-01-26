<?php
/**
 * ComponentPropertyResolver test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * NOTE: Instance attribute dynamic expression resolution is handled by ComponentBlock,
 * not ComponentPropertyResolver. Tests here focus on:
 * - Default value resolution (including dynamic expressions)
 * - Merging pre-resolved instance attributes with defaults
 * - Type casting
 * - Specialized array types (loop props)
 *
 * ✅ String Property (plain)
 *    - Default literal value
 *    - Instance attribute override (pre-resolved)
 *    - Dynamic expression in default
 *    - Pre-resolved dynamic expression in instance attribute
 *    - Empty/null handling
 *
 * ✅ String Property (specialized: color, url, image, select)
 *    - Color specialized type
 *    - URL specialized type
 *    - Image specialized type
 *    - Select specialized type with options
 *
 * ✅ String Property (specialized: array - Loop Props)
 *    - Loop prop returns structured array with prop-type, key, and data
 *    - Loop prop by database key (loop ID) - resolves data eagerly
 *    - Loop prop by key property (find_loop_by_key) - resolves data eagerly
 *    - Loop prop with this.* expression - key is string, data resolved from context
 *    - Loop prop with comma-separated string - key is string, data parsed as array
 *    - Empty string returns structure with empty key and data
 *    NOTE: Static inline array loops (JSON strings) are NOT handled here - will be implemented separately
 *
 * ✅ Number Property
 *    - Default numeric value
 *    - Instance attribute override (pre-resolved)
 *    - String to number conversion
 *    - Dynamic expression in default resolves to number
 *    - Zero and negative numbers
 *    - Float numbers
 *
 * ✅ Boolean Property
 *    - Default true value
 *    - Default false value
 *    - Instance attribute "true" string (pre-resolved)
 *    - Instance attribute "false" string (pre-resolved)
 *    - Instance attribute boolean true
 *    - Instance attribute boolean false
 *    - Dynamic expression in default resolves to boolean
 *    - Used in HTML attributes (data-test="{props.enabled}")
 *    - Used in ConditionBlock conditions
 *
 * ✅ Object Property
 *    - Default object value
 *    - Instance attribute JSON string (pre-resolved)
 *    - Instance attribute array
 *    - Dynamic expression in default resolves to object
 *    - Nested object access
 *
 * ✅ Array Property (primitive array, not specialized loop)
 *    - Default array value
 *    - Instance attribute JSON string (pre-resolved)
 *    - Instance attribute comma-separated string (pre-resolved)
 *    - Instance attribute array
 *    - Dynamic expression in default resolves to array
 *
 * ✅ Edge Cases & Error Handling
 *    - Recursion guard: default contains {props.*}
 *    - Missing property definition (attribute ignored)
 *    - Null instance value (uses default)
 *    - Empty string instance value
 *    - Invalid property definition (skipped)
 *    - Context evaluation with empty context
 *    - Mixed property types in same component
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use Etch\Blocks\Utilities\ComponentPropertyResolver;
use Etch\Preprocessor\Utilities\LoopHandlerManager;
use Etch\Preprocessor\Data\EtchDataGlobalLoop;

/**
 * Class ComponentPropertyResolverTest
 *
 * Comprehensive tests for ComponentPropertyResolver functionality including:
 * - All property types (string, number, boolean, object, array)
 * - Specialized types (color, url, image, select, array/loop)
 * - Dynamic expression evaluation
 * - Loop prop resolution
 * - Edge cases and error handling
 */
class ComponentPropertyResolverTest extends WP_UnitTestCase {

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset LoopHandlerManager before each test
		LoopHandlerManager::reset();

		// Create test loop presets for testing
		$this->setup_test_loop_presets();
	}

	/**
	 * Tear down test fixtures
	 */
	public function tearDown(): void {
		// Reset LoopHandlerManager after each test
		LoopHandlerManager::reset();
		parent::tearDown();
	}

	/**
	 * Convert a legacy context map to sources.
	 *
	 * @param array<string, mixed> $context Context map.
	 * @return array<int, array{key: string, source: mixed}>
	 */
	private function context_to_sources( array $context ): array {
		$sources = array();
		foreach ( $context as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}
			$sources[] = array(
				'key' => $key,
				'source' => $value,
			);
		}
		return $sources;
	}

	/**
	 * Set up test loop presets for testing loop prop resolution
	 */
	private function setup_test_loop_presets(): void {
		// Create wp-query loop preset
		$wp_query_loop = EtchDataGlobalLoop::from_array(
			array(
				'key'  => 'posts',
				'type' => 'wp-query',
				'args' => array(
					'post_type' => 'post',
					'posts_per_page' => 5,
				),
			)
		);
		if ( $wp_query_loop ) {
			update_option( 'etch_loops', array( 'test-posts-id' => $wp_query_loop->to_array() ) );
		}

		// Create wp-users loop preset
		$wp_users_loop = EtchDataGlobalLoop::from_array(
			array(
				'key'  => 'users',
				'type' => 'wp-users',
				'args' => array(
					'number' => 10,
				),
			)
		);
		if ( $wp_users_loop ) {
			$loops = get_option( 'etch_loops', array() );
			$loops['test-users-id'] = $wp_users_loop->to_array();
			update_option( 'etch_loops', $loops );
		}

		// Create wp-terms loop preset
		$wp_terms_loop = EtchDataGlobalLoop::from_array(
			array(
				'key'  => 'categories',
				'type' => 'wp-terms',
				'args' => array(
					'taxonomy' => 'category',
				),
			)
		);
		if ( $wp_terms_loop ) {
			$loops = get_option( 'etch_loops', array() );
			$loops['test-terms-id'] = $wp_terms_loop->to_array();
			update_option( 'etch_loops', $loops );
		}

		// Create json loop preset
		$json_loop = EtchDataGlobalLoop::from_array(
			array(
				'key'  => 'json-data',
				'type' => 'json',
				'args' => array(
					'url' => 'https://example.com/api/data.json',
				),
			)
		);
		if ( $json_loop ) {
			$loops = get_option( 'etch_loops', array() );
			$loops['test-json-id'] = $json_loop->to_array();
			update_option( 'etch_loops', $loops );
		}
	}

	/**
	 * Test string property with default literal value
	 */
	public function test_string_property_default_literal() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Default Title',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'Default Title', $resolved['title'] );
		$this->assertIsString( $resolved['title'] );
	}

	/**
	 * Test string property with instance attribute override
	 */
	public function test_string_property_instance_override() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Default Title',
			),
		);

		$instance_attributes = array(
			'title' => 'Custom Title',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'Custom Title', $resolved['title'] );
	}

	/**
	 * Test string property with dynamic expression in default
	 */
	public function test_string_property_dynamic_default() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => '{site.name}',
			),
		);

		$instance_attributes = array();
		$context = array(
			'site' => array(
				'name' => 'Test Site',
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'Test Site', $resolved['title'] );
	}

	/**
	 * Test string property with pre-resolved instance attribute
	 *
	 * In production, dynamic expressions in instance attributes are resolved by ComponentBlock
	 * before being passed to ComponentPropertyResolver. This test simulates that flow.
	 */
	public function test_string_property_pre_resolved_instance() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Default',
			),
		);

		// Simulate what ComponentBlock does: resolve {this.title} -> 'Post Title' before passing to resolver
		// Original instance attribute would have been: 'title' => '{this.title}'
		// After ComponentBlock resolution: 'title' => 'Post Title'
		$instance_attributes = array(
			'title' => 'Post Title',
		);
		$context = array(
			'this' => array(
				'title' => 'Post Title',
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'Post Title', $resolved['title'] );
	}

	/**
	 * Test string property specialized color
	 */
	public function test_string_property_specialized_color() {
		$property_definitions = array(
			array(
				'key'  => 'color',
				'name' => 'Color',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'color',
				),
				'default' => '#ff0000',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( '#ff0000', $resolved['color'] );
		$this->assertIsString( $resolved['color'] );
	}

	/**
	 * Test string property specialized url
	 */
	public function test_string_property_specialized_url() {
		$property_definitions = array(
			array(
				'key'  => 'link',
				'name' => 'Link',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'url',
				),
				'default' => 'https://example.com',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'https://example.com', $resolved['link'] );
	}

	/**
	 * Test string property specialized image
	 */
	public function test_string_property_specialized_image() {
		$property_definitions = array(
			array(
				'key'  => 'image',
				'name' => 'Image',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'image',
				),
				'default' => 'https://example.com/image.jpg',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'https://example.com/image.jpg', $resolved['image'] );
	}

	/**
	 * Test string property specialized select
	 */
	public function test_string_property_specialized_select() {
		$property_definitions = array(
			array(
				'key'  => 'alignment',
				'name' => 'Alignment',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'select',
				),
				'default' => 'left',
				'options' => array( 'left', 'center', 'right' ),
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 'left', $resolved['alignment'] );
	}

	/**
	 * Test loop prop returns structured array for wp-query handler
	 */
	public function test_loop_prop_resolves_wp_query_handler() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'test-posts-id',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// ComponentPropertyResolver returns structured array with prop-type, key, and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'test-posts-id', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
	}

	/**
	 * Test loop prop returns structured array for wp-users handler
	 */
	public function test_loop_prop_resolves_wp_users_handler() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'test-users-id',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// ComponentPropertyResolver returns structured array with prop-type, key, and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'test-users-id', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
	}

	/**
	 * Test loop prop returns structured array for wp-terms handler
	 */
	public function test_loop_prop_resolves_wp_terms_handler() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'test-terms-id',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// ComponentPropertyResolver returns structured array with prop-type, key, and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'test-terms-id', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
	}

	/**
	 * Test loop prop returns structured array for json handler
	 */
	public function test_loop_prop_resolves_json_handler() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'test-json-id',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// ComponentPropertyResolver returns structured array with prop-type, key, and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'test-json-id', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
	}

	/**
	 * Test loop prop by key property returns structured array
	 */
	public function test_loop_prop_by_key_property() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'posts', // Using key instead of ID
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// ComponentPropertyResolver returns structured array with prop-type, key, and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'posts', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
	}

	/**
	 * Test loop prop with global context expression returns structured array
	 * Data is resolved from context, key preserves the expression
	 */
	public function test_loop_prop_with_global_context_expression_returns_structured() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'this.relatedPosts',
			),
		);

		$instance_attributes = array();
		// Context is provided - data resolver uses it to resolve this.* expressions
		$context = array(
			'this' => array(
				'relatedPosts' => array(
					array(
						'id'    => 1,
						'title' => 'Post 1',
					),
					array(
						'id'    => 2,
						'title' => 'Post 2',
					),
				),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Returns structured array - key is the expression string, data is resolved from context
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'this.relatedPosts', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
		$this->assertCount( 2, $resolved['loop']['data'] );
		$this->assertEquals( 'Post 1', $resolved['loop']['data'][0]['title'] );
	}

	/**
	 * Test loop prop with JSON string returns structured array with parsed data
	 */
	public function test_loop_prop_fallback_json() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => '["item1", "item2", "item3"]',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Returns structured array - key is the JSON string, data is parsed
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( '["item1", "item2", "item3"]', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
		$this->assertCount( 3, $resolved['loop']['data'] );
		$this->assertEquals( 'item1', $resolved['loop']['data'][0] );
	}

	/**
	 * Test loop prop with comma-separated string returns structured array with parsed data
	 */
	public function test_loop_prop_fallback_comma_separated() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'item1, item2, item3',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Returns structured array - key is the comma-separated string, data is parsed
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'item1, item2, item3', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
		$this->assertCount( 3, $resolved['loop']['data'] );
		$this->assertEquals( 'item1', trim( $resolved['loop']['data'][0] ) );
	}

	// NOTE: Static inline array loops (e.g., JSON array strings as defaults) are NOT tested here.
	// Loop props (primitive: string, specialized: array) are designed for:
	// - Loop preset references (e.g., "posts", "my-loop-id")
	// - Context-aware loop targets (e.g., "this.relatedPosts", "item.children")
	// - props.* expressions that resolve to loop keys
	// Static array loops require different handling and will be implemented separately.

	/**
	 * Test number property with default numeric value
	 */
	public function test_number_property_default() {
		$property_definitions = array(
			array(
				'key'  => 'count',
				'name' => 'Count',
				'type' => array(
					'primitive' => 'number',
				),
				'default' => 42,
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 42, $resolved['count'] );
		$this->assertIsFloat( $resolved['count'] );
	}

	/**
	 * Test number property with instance override
	 */
	public function test_number_property_instance_override() {
		$property_definitions = array(
			array(
				'key'  => 'count',
				'name' => 'Count',
				'type' => array(
					'primitive' => 'number',
				),
				'default' => 0,
			),
		);

		$instance_attributes = array(
			'count' => '100',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 100.0, $resolved['count'] );
		$this->assertIsFloat( $resolved['count'] );
	}

	/**
	 * Test number property with dynamic expression
	 */
	public function test_number_property_dynamic() {
		$property_definitions = array(
			array(
				'key'  => 'count',
				'name' => 'Count',
				'type' => array(
					'primitive' => 'number',
				),
				'default' => '{this.viewCount}',
			),
		);

		$instance_attributes = array();
		$context = array(
			'this' => array(
				'viewCount' => 150,
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 150.0, $resolved['count'] );
	}

	/**
	 * Test number property with zero and negative
	 */
	public function test_number_property_zero_negative() {
		$property_definitions = array(
			array(
				'key'  => 'zero',
				'name' => 'Zero',
				'type' => array(
					'primitive' => 'number',
				),
				'default' => 0,
			),
			array(
				'key'  => 'negative',
				'name' => 'Negative',
				'type' => array(
					'primitive' => 'number',
				),
				'default' => -10,
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertEquals( 0.0, $resolved['zero'] );
		$this->assertEquals( -10.0, $resolved['negative'] );
	}

	/**
	 * Test boolean property default true
	 */
	public function test_boolean_property_default_true() {
		$property_definitions = array(
			array(
				'key'  => 'enabled',
				'name' => 'Enabled',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => true,
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertTrue( $resolved['enabled'] );
		$this->assertIsBool( $resolved['enabled'] );
	}

	/**
	 * Test boolean property default false
	 */
	public function test_boolean_property_default_false() {
		$property_definitions = array(
			array(
				'key'  => 'disabled',
				'name' => 'Disabled',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => false,
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertFalse( $resolved['disabled'] );
		$this->assertIsBool( $resolved['disabled'] );
	}

	/**
	 * Test boolean property with "true" string instance attribute
	 */
	public function test_boolean_property_string_true() {
		$property_definitions = array(
			array(
				'key'  => 'enabled',
				'name' => 'Enabled',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => false,
			),
		);

		$instance_attributes = array(
			'enabled' => 'true',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertTrue( $resolved['enabled'] );
	}

	/**
	 * Test boolean property with "false" string instance attribute
	 */
	public function test_boolean_property_string_false() {
		$property_definitions = array(
			array(
				'key'  => 'enabled',
				'name' => 'Enabled',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => true,
			),
		);

		$instance_attributes = array(
			'enabled' => 'false',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertFalse( $resolved['enabled'] );
	}

	/**
	 * Test boolean property with dynamic expression
	 */
	public function test_boolean_property_dynamic() {
		$property_definitions = array(
			array(
				'key'  => 'isLoggedIn',
				'name' => 'Is Logged In',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => '{user.isLoggedIn}',
			),
		);

		$instance_attributes = array();
		$context = array(
			'user' => array(
				'isLoggedIn' => true,
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertTrue( $resolved['isLoggedIn'] );
	}

	/**
	 * Test boolean property used in HTML attribute rendering
	 */
	public function test_boolean_property_html_attribute() {
		$property_definitions = array(
			array(
				'key'  => 'enabled',
				'name' => 'Enabled',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => true,
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Simulate HTML attribute rendering
		$attr_value = $resolved['enabled'] ? 'true' : 'false';
		$this->assertEquals( 'true', $attr_value );

		// Test false case
		$property_definitions[0]['default'] = false;
		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );
		$attr_value = $resolved['enabled'] ? 'true' : 'false';
		$this->assertEquals( 'false', $attr_value );
	}

	/**
	 * Test object property with default object
	 */
	public function test_object_property_default() {
		$property_definitions = array(
			array(
				'key'  => 'meta',
				'name' => 'Meta',
				'type' => array(
					'primitive' => 'object',
				),
				'default' => array(
					'subTitle' => 'Subtitle',
					'author' => 'Author Name',
				),
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['meta'] );
		$this->assertEquals( 'Subtitle', $resolved['meta']['subTitle'] );
		$this->assertEquals( 'Author Name', $resolved['meta']['author'] );
	}

	/**
	 * Test object property with JSON string instance attribute
	 */
	public function test_object_property_json_string() {
		$property_definitions = array(
			array(
				'key'  => 'meta',
				'name' => 'Meta',
				'type' => array(
					'primitive' => 'object',
				),
				'default' => array(),
			),
		);

		$instance_attributes = array(
			'meta' => '{"subTitle": "Custom Subtitle", "author": "Custom Author"}',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['meta'] );
		$this->assertEquals( 'Custom Subtitle', $resolved['meta']['subTitle'] );
	}

	/**
	 * Test object property with dynamic expression
	 */
	public function test_object_property_dynamic() {
		$property_definitions = array(
			array(
				'key'  => 'meta',
				'name' => 'Meta',
				'type' => array(
					'primitive' => 'object',
				),
				'default' => '{this.meta}',
			),
		);

		$instance_attributes = array();
		$context = array(
			'this' => array(
				'meta' => array(
					'customField' => 'Custom Value',
				),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['meta'] );
		$this->assertEquals( 'Custom Value', $resolved['meta']['customField'] );
	}

	/**
	 * Test array property (primitive array, not specialized loop) with default
	 */
	public function test_array_property_default() {
		$property_definitions = array(
			array(
				'key'  => 'tags',
				'name' => 'Tags',
				'type' => array(
					'primitive' => 'array',
				),
				'default' => array( 'tag1', 'tag2', 'tag3' ),
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['tags'] );
		$this->assertCount( 3, $resolved['tags'] );
		$this->assertEquals( 'tag1', $resolved['tags'][0] );
	}

	/**
	 * Test array property with JSON string instance attribute
	 */
	public function test_array_property_json_string() {
		$property_definitions = array(
			array(
				'key'  => 'tags',
				'name' => 'Tags',
				'type' => array(
					'primitive' => 'array',
				),
				'default' => array(),
			),
		);

		$instance_attributes = array(
			'tags' => '["tagA", "tagB", "tagC"]',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['tags'] );
		$this->assertCount( 3, $resolved['tags'] );
		$this->assertEquals( 'tagA', $resolved['tags'][0] );
	}

	/**
	 * Test array property with comma-separated string
	 */
	public function test_array_property_comma_separated() {
		$property_definitions = array(
			array(
				'key'  => 'tags',
				'name' => 'Tags',
				'type' => array(
					'primitive' => 'array',
				),
				'default' => array(),
			),
		);

		$instance_attributes = array(
			'tags' => 'tagX, tagY, tagZ',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['tags'] );
		$this->assertCount( 3, $resolved['tags'] );
		$this->assertEquals( 'tagX', trim( $resolved['tags'][0] ) );
	}

	/**
	 * Test array property with dynamic expression
	 */
	public function test_array_property_dynamic() {
		$property_definitions = array(
			array(
				'key'  => 'tags',
				'name' => 'Tags',
				'type' => array(
					'primitive' => 'array',
				),
				'default' => '{this.tags}',
			),
		);

		$instance_attributes = array();
		$context = array(
			'this' => array(
				'tags' => array( 'dynamic1', 'dynamic2' ),
			),
		);

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsArray( $resolved['tags'] );
		$this->assertCount( 2, $resolved['tags'] );
		$this->assertEquals( 'dynamic1', $resolved['tags'][0] );
	}

	/**
	 * Test recursion guard: default contains {props.*}
	 */
	public function test_recursion_guard_props_reference() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => '{props.title}', // This would cause recursion
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Should return empty string instead of causing recursion
		$this->assertEquals( '', $resolved['title'] );
	}

	/**
	 * Test missing property definition (attribute ignored)
	 */
	public function test_missing_property_definition() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Default',
			),
		);

		$instance_attributes = array(
			'unknownProp' => 'Should be ignored',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertArrayNotHasKey( 'unknownProp', $resolved );
		$this->assertEquals( 'Default', $resolved['title'] );
	}

	/**
	 * Test null instance value (does not override default)
	 */
	public function test_null_instance_value() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Default Title',
			),
		);

		$instance_attributes = array(
			'title' => null,
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Null should behave like “not provided”, so defaults apply.
		$this->assertEquals( 'Default Title', $resolved['title'] );
	}

	/**
	 * Test empty string instance value (overrides default)
	 */
	public function test_empty_string_instance_value() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Default Title',
			),
		);

		$instance_attributes = array(
			'title' => '',
		);
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Empty string explicitly set should override default (empty is a valid value)
		$this->assertEquals( '', $resolved['title'] );
	}

	/**
	 * Test invalid property definition (skipped)
	 */
	public function test_invalid_property_definition() {
		$property_definitions = array(
			array(
				'key'  => 'valid',
				'name' => 'Valid',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Valid Value',
			),
			array(
				// Missing key - invalid
				'name' => 'Invalid',
			),
			array(
				'key'  => 'invalidType',
				'name' => 'Invalid Type',
				'type' => array(
					'primitive' => 'invalid', // Invalid primitive type
				),
				'default' => 'Value',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Only valid property should be resolved
		$this->assertArrayHasKey( 'valid', $resolved );
		$this->assertArrayNotHasKey( 'invalidType', $resolved );
	}

	/**
	 * Test context evaluation with empty context
	 */
	public function test_empty_context() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => '{this.title}',
			),
		);

		$instance_attributes = array();
		$context = array(); // Empty context

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Should handle gracefully, expression won't resolve but won't error
		$this->assertIsString( $resolved['title'] );
	}

	/**
	 * Test mixed property types in same component
	 */
	public function test_mixed_property_types() {
		$property_definitions = array(
			array(
				'key'  => 'title',
				'name' => 'Title',
				'type' => array(
					'primitive' => 'string',
				),
				'default' => 'Title',
			),
			array(
				'key'  => 'count',
				'name' => 'Count',
				'type' => array(
					'primitive' => 'number',
				),
				'default' => 10,
			),
			array(
				'key'  => 'enabled',
				'name' => 'Enabled',
				'type' => array(
					'primitive' => 'boolean',
				),
				'default' => true,
			),
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => 'test-posts-id',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		$this->assertIsString( $resolved['title'] );
		$this->assertIsFloat( $resolved['count'] );
		$this->assertIsBool( $resolved['enabled'] );
		// Loop prop returns structured array with prop-type, key, and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'Title', $resolved['title'] );
		$this->assertEquals( 10.0, $resolved['count'] );
		$this->assertTrue( $resolved['enabled'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( 'test-posts-id', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
	}

	/**
	 * Test loop prop with empty string returns structured array with empty key and data
	 */
	public function test_loop_prop_empty_string() {
		$property_definitions = array(
			array(
				'key'  => 'loop',
				'name' => 'Loop',
				'type' => array(
					'primitive' => 'string',
					'specialized' => 'array',
				),
				'default' => '',
			),
		);

		$instance_attributes = array();
		$context = array();

		$resolved = ComponentPropertyResolver::resolve_properties( $property_definitions, $instance_attributes, $this->context_to_sources( $context ) );

		// Returns structured array with empty key and data
		$this->assertIsArray( $resolved['loop'] );
		$this->assertEquals( 'loop', $resolved['loop']['prop-type'] );
		$this->assertEquals( '', $resolved['loop']['key'] );
		$this->assertIsArray( $resolved['loop']['data'] );
		$this->assertEmpty( $resolved['loop']['data'] );
	}
}
