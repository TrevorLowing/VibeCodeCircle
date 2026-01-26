<?php
/**
 * Tests for the ComponentBlock processor.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Tests;

use Etch\Preprocessor\Blocks\ComponentBlock;

/**
 * Class ComponentBlockTest
 *
 * Tests the ComponentBlock functionality in the Preprocessor system.
 */
class ComponentBlockTest extends PreprocessorTestBase {

	/**
	 * Test basic component expansion.
	 * It should expand a component into its definition blocks.
	 */
	public function test_basic_component_expansion(): void {
		// Create a mock component
		$component_content = '<!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}}} --><h2>Component Title</h2><!-- /wp:heading -->';
		$component_id = $this->create_mock_component( $component_content );

		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<h2>Component Title</h2>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Component should expand to its definition blocks.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}

	/**
	 * Test component with properties.
	 * It should pass properties to the component and use them in rendering.
	 */
	public function test_component_with_properties(): void {
		// Create a mock component with dynamic content
		$component_content = '<!-- wp:heading {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"{props.className}"},"block":{"type":"html","tag":"h2"}}}} --><h2>{props.title}</h2><!-- /wp:heading -->';

		$properties = array(
			array(
				'key' => 'title',
				'type' => 'string',
				'default' => 'Default Title',
			),
			array(
				'key' => 'className',
				'type' => 'string',
				'default' => 'default-class',
			),
		);

		$component_id = $this->create_mock_component( $component_content, $properties );

		// Use component with custom properties
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"title":"Custom Title","className":"custom-class"},"block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<h2 class="custom-class">Custom Title</h2>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Component should use provided properties.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}

	/**
	 * Test component with default properties.
	 * It should use default values when properties are not provided.
	 */
	public function test_component_with_default_properties(): void {
		// Create a mock component with dynamic content
		$component_content = '<!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>{props.text}</p><!-- /wp:paragraph -->';

		$properties = array(
			array(
				'key' => 'text',
				'type' => 'string',
				'default' => 'Default text content',
			),
		);

		$component_id = $this->create_mock_component( $component_content, $properties );

		// Use component without providing properties
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<p>Default text content</p>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Component should use default property values.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}

	/**
	 * Test component with slots.
	 * It should replace slot placeholders with provided content.
	 */
	public function test_component_with_slots(): void {
		// Create a mock component with slot placeholders
		$component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}}} --><h2>Component Header</h2><!-- /wp:heading --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"content"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';

		$component_id = $this->create_mock_component( $component_content );

		// Use component with slot content
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"content"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Slot content here</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group -->';
		$expected_output = '<div><h2>Component Header</h2><p>Slot content here</p></div>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Component should replace slot placeholders with provided content.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}

	/**
	 * Test component with multiple slots.
	 * It should replace multiple slot placeholders correctly.
	 */
	public function test_component_with_multiple_slots(): void {
		// Create a mock component with multiple slot placeholders
		$component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"card"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"header"}}}} --><div class="wp-block-group"></div><!-- /wp:group --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"card-body"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"body"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"footer"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';

		$component_id = $this->create_mock_component( $component_content );

		// Use component with multiple slots
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"header"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h3"}}}} --><h3>Card Title</h3><!-- /wp:heading --></div><!-- /wp:group --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"body"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Card content</p><!-- /wp:paragraph --></div><!-- /wp:group --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"footer"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"footer-text"},"block":{"type":"html","tag":"p"}}}} --><p>Footer</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group -->';

		$expected_output = '<div class="card"><h3>Card Title</h3><div class="card-body"><p>Card content</p></div><p class="footer-text">Footer</p></div>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Component should replace multiple slot placeholders correctly.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}


	/**
	 * Test nested components.
	 * Components should be able to contain other components.
	 */
	public function test_nested_components(): void {
		// Create inner component
		$inner_component_content = '<!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"{props.className}"},"block":{"type":"html","tag":"p"}}}} --><p>{props.text}</p><!-- /wp:paragraph -->';
		$inner_properties = array(
			array(
				'key' => 'text',
				'type' => 'string',
				'default' => 'Inner text',
			),
			array(
				'key' => 'className',
				'type' => 'string',
				'default' => 'inner-class',
			),
		);
		$inner_component_id = $this->create_mock_component( $inner_component_content, $inner_properties );

		// Create outer component that uses inner component
		$outer_component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}}} --><h2>{props.title}</h2><!-- /wp:heading --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"text":"{props.content}","className":"nested-content"},"block":{"type":"component","component":' . $inner_component_id . '}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';
		$outer_properties = array(
			array(
				'key' => 'title',
				'type' => 'string',
				'default' => 'Outer Title',
			),
			array(
				'key' => 'content',
				'type' => 'string',
				'default' => 'Outer content',
			),
		);
		$outer_component_id = $this->create_mock_component( $outer_component_content, $outer_properties );

		// Use the outer component
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"title":"Main Title","content":"Main content"},"block":{"type":"component","component":' . $outer_component_id . '}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<div><h2>Main Title</h2><p class="nested-content">Main content</p></div>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Nested components should expand correctly.'
		);

		// Clean up
		wp_delete_post( $inner_component_id, true );
		wp_delete_post( $outer_component_id, true );
	}

	/**
	 * Test component slots with dynamic data parsing.
	 * Slot content should have dynamic placeholders parsed using parent context.
	 */
	public function test_component_slots_with_dynamic_data(): void {
		// Create a mock component with slot placeholder
		$component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"card"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}}} --><h2>Component Title</h2><!-- /wp:heading --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"content"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';

		$component_id = $this->create_mock_component( $component_content );

		// Use component with slot content containing dynamic data from parent context
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"content"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Hello {item.title}!</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group -->';
		$expected_output = '<div class="card"><h2>Component Title</h2><p>Hello Test Item!</p></div>';

		// Context with dynamic data that should be accessible to slot content
		$context = array(
			'item' => array(
				'title' => 'Test Item',
			),
		);

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			$context,
			'Component slots should parse dynamic data placeholders using parent context.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}

	/**
	 * Test nested components with slots and dynamic data parsing.
	 * Multiple levels of nested components with slots should all have access to the outermost parent context.
	 */
	public function test_nested_component_slots_with_dynamic_data(): void {
		// Create inner component - displays user info with slot for additional content
		$inner_component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"user-card"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>User: {user.name}</p><!-- /wp:paragraph --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"details"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';
		$inner_component_id = $this->create_mock_component( $inner_component_content );

		// Create middle component - displays item info with slot that will contain the inner component
		$middle_component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"item-wrapper"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h3"}}}} --><h3>Item: {item.title}</h3><!-- /wp:heading --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"content"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';
		$middle_component_id = $this->create_mock_component( $middle_component_content );

		// Use middle component with inner component in its slot
		// The inner component should have access to both {item.title} and {user.name} from the outermost context
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $middle_component_id . '}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"content"}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"component","component":' . $inner_component_id . '}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"details"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Category: {item.category}</p><!-- /wp:paragraph --><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Admin: {user.role}</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group --></div><!-- /wp:group -->';

		$expected_output = '<div class="item-wrapper"><h3>Item: Test Product</h3><div class="user-card"><p>User: John Doe</p><p>Category: Electronics</p><p>Admin: Administrator</p></div></div>';

		// Context with multiple levels of data that should be accessible at all nesting levels
		$context = array(
			'item' => array(
				'title' => 'Test Product',
				'category' => 'Electronics',
			),
			'user' => array(
				'name' => 'John Doe',
				'role' => 'Administrator',
			),
		);

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			$context,
			'Nested component slots should parse dynamic data placeholders using outermost parent context at all levels.'
		);

		// Clean up
		wp_delete_post( $inner_component_id, true );
		wp_delete_post( $middle_component_id, true );
	}

	/**
	 * Test component with slots not parsing its own props correctly.
	 * This test reproduces the issue where components with slots lose the ability to parse their own props.
	 */
	public function test_component_with_slots_and_props_parsing(): void {
		// Create a mock component with both slot placeholder and prop usage
		$component_content = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"card"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}}} --><h2>Hello: {props.text}</h2><!-- /wp:heading --><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot-placeholder","slot":"content"}}}} --><div class="wp-block-group"></div><!-- /wp:group --></div><!-- /wp:group -->';

		$properties = array(
			array(
				'key' => 'text',
				'type' => 'string',
				'default' => 'Default World',
			),
		);

		$component_id = $this->create_mock_component( $component_content, $properties );

		// Use component with custom text prop and slot content
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"text":"World"},"block":{"type":"component","component":' . $component_id . '}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","block":{"type":"slot","slot":"content"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Slot content here</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group -->';
		$expected_output = '<div class="card"><h2>Hello: World</h2><p>Slot content here</p></div>';

		$this->assert_component_expansion(
			$gutenberg_html,
			$expected_output,
			array(),
			'Component with slots should still parse its own props correctly.'
		);

		// Clean up
		wp_delete_post( $component_id, true );
	}
}
