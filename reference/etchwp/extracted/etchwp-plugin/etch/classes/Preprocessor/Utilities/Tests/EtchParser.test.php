<?php
/**
 * EtchParser test class.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\Tests;

use WP_UnitTestCase;
use Etch\Preprocessor\Utilities\EtchParser;

/**
 * Class EtchParserTest
 */
class EtchParserTest extends WP_UnitTestCase {
	/**
	 * Test the is_dynamic_expression method returns true for simple dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_simple_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{someVar}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{anotherVar}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for simple dynamic expression with modifier.
	 */
	public function test_is_dynamic_expression_returns_true_for_simple_dynamic_expression_with_modifier() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{someVar.format()}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{anotherVar.toUpperCase()}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for string dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_string_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{"stringValue"}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for number dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_number_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{123}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{45.67}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for boolean dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_boolean_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{true}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{false}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for complex dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_complex_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{user.name.toUpperCase()}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( "{post.title.format('Y-m-d')}" ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( "{items.filter(type: 'active')}" ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for array dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_array_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{[1, 2, 3]}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{["a", "b", "c"]}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for object dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_object_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{{key: "value", num: 42}}' ) );
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{{nested: {innerKey: "innerValue"}}}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns true for chained dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_true_for_chained_dynamic_expressions() {
		$this->assertEquals( true, EtchParser::is_dynamic_expression( '{user.getProfile().getName().toUpperCase()}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns false for dynamic expressions with unbalanced braces.
	 */
	public function test_is_dynamic_expression_returns_false_for_unbalanced_braces() {
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{someVar' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( 'someVar}' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{{someVar}' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{someVar}}' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{{someVar}}}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns false for strings starting and ending with braces but not standalone dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_false_for_non_standalone_expressions() {
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{firstExpression} middle {lastExpression}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns false for empty or too short strings.
	 */
	public function test_is_dynamic_expression_returns_false_for_empty_or_short_strings() {
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{a' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( 'a}' ) );
	}

	/**
	 * Test the is_dynamic_expression method returns false for invalid dynamic expressions.
	 */
	public function test_is_dynamic_expression_returns_false_for_invalid_expressions() {
		$this->assertEquals( false, EtchParser::is_dynamic_expression( 'notDynamic' ) );
		$this->assertEquals( false, EtchParser::is_dynamic_expression( '{invalid}expression' ) );
	}

	/**
	 * Test split_path with bracket notation.
	 */
	public function test_split_path_handles_bracket_notation() {
		$result = EtchParser::split_path( 'item.user[\'name\']' );
		$this->assertEquals( array( 'item', 'user', 'name' ), $result );

		$result2 = EtchParser::split_path( 'item.user["name"]' );
		$this->assertEquals( array( 'item', 'user', 'name' ), $result2 );

		$result3 = EtchParser::split_path( 'item.user[0]' );
		$this->assertEquals( array( 'item', 'user', '0' ), $result3 );

		$result4 = EtchParser::split_path( 'item.user[0].name' );
		$this->assertEquals( array( 'item', 'user', '0', 'name' ), $result4 );

		$result5 = EtchParser::split_path( 'item.user[0][\'name\']' );
		$this->assertEquals( array( 'item', 'user', '0', 'name' ), $result5 );

		$result6 = EtchParser::split_path( 'item.user[\'With-Dash\']' );
		$this->assertEquals( array( 'item', 'user', 'With-Dash' ), $result6 );

		$result7 = EtchParser::split_path( 'item.user["With Whitespace"]' );
		$this->assertEquals( array( 'item', 'user', 'With Whitespace' ), $result7 );
	}

	/**
	 * Test resolve with bracket notation.
	 */
	public function test_resolve_bracket_notation_with_actual_data() {
		$context = array(
			'item' => array(
				'user' => array(
					'full-name' => 'John Doe',
					'contact-info' => array( 'email' => 'john@example.com' ),
				),
				'items' => array( 'apple', 'banana', 'cherry' ),
			),
		);

		// Accessing properties with dashes
		$result = EtchParser::replace_string( 'Name: {item.user["full-name"]}', $context );
		$this->assertEquals( 'Name: John Doe', $result );

		// Accessing nested property with dashes
		$result2 = EtchParser::replace_string( 'Email: {item.user["contact-info"]["email"]}', $context );
		$this->assertEquals( 'Email: john@example.com', $result2 );

		// Array access with bracket notation
		$result3 = EtchParser::replace_string( 'First fruit: {item.items[0]}', $context );
		$this->assertEquals( 'First fruit: apple', $result3 );
	}

	/**
	 * Test that loop prop structure is unwrapped during path resolution
	 * so that modifiers and property access work correctly.
	 */
	public function test_loop_prop_resolution_with_modifiers_and_property_access() {
		$context = array(
			'props' => array(
				'loop' => array(
					'prop-type' => 'loop',
					'key' => 'some-loop-key',
					'data' => array(
						array( 'title' => 'Post 0' ),
						array( 'title' => 'Post 1' ),
						array( 'title' => 'Post 2' ),
					),
				),
			),
		);

		// 1. Test {props.loop.at(1).title}
		$result = EtchParser::process_expression( 'props.loop.at(1).title', $context );
		$this->assertEquals( 'Post 1', $result, 'Should resolve property on an item retrieved by modifier from a loop prop' );

		// 2. Test {props.loop.at(1)}
		$result = EtchParser::process_expression( 'props.loop.at(1)', $context );
		$this->assertIsArray( $result );
		$this->assertEquals( 'Post 1', $result['title'], 'Should resolve item retrieved by modifier from a loop prop' );

		// 3. Test {props.loop}
		$result = EtchParser::process_expression( 'props.loop', $context );
		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
		$this->assertEquals( 'Post 0', $result[0]['title'] );
	}
}
