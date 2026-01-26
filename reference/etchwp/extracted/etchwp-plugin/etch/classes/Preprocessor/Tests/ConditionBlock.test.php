<?php
/**
 * Tests for the ConditionBlock processor.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Tests;

use Etch\Preprocessor\Blocks\ConditionBlock;

/**
 * Class ConditionBlockTest
 *
 * Tests the ConditionBlock functionality in the Preprocessor system.
 */
class ConditionBlockTest extends PreprocessorTestBase {

	/**
	 * Test basic string equality condition true. "test" == "test"
	 */
	public function test_string_equality_condition_true(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"\u0022test\u0022","operator":"==","rightHand":"\u0022test\u0022"},"conditionString":"\u0022test\u0022 == \u0022test\u0022"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"name":"Heading","etchData":{"origin":"etch","name":"Heading","block":{"type":"html","tag":"h2"}}},"level":2} --><h2 class="wp-block-heading">show me test == test</h2><!-- /wp:heading --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<h2>show me test == test</h2>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when string equality is true.'
		);
	}

	/**
	 * Test basic string equality condition false. "test" == "other"
	 */
	public function test_string_equality_condition_false(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"\u0022test\u0022","operator":"==","rightHand":"\u0022other\u0022"},"conditionString":"\u0022test\u0022 == \u0022other\u0022"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"name":"Heading","etchData":{"origin":"etch","name":"Heading","block":{"type":"html","tag":"h2"}}},"level":2} --><h2 class="wp-block-heading">should not show</h2><!-- /wp:heading --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should hide content when string equality is false.'
		);
	}

	/**
	 * Test number greater than condition. 5 > 3
	 */
	public function test_number_greater_than_condition(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":5,"operator":"\u003e","rightHand":3},"conditionString":"5 \u003e 3"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>5 is greater than 3</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p>5 is greater than 3</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when number is greater than.'
		);
	}

	/**
	 * Test number less than condition. 2 < 5
	 */
	public function test_number_less_than_condition(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":2,"operator":"\u003c","rightHand":5},"conditionString":"2 \u003c 5"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>2 is less than 5</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p>2 is less than 5</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when number is less than.'
		);
	}

	/**
	 * Test number greater than or equal condition. 5 >= 5
	 */
	public function test_number_greater_than_or_equal_condition(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":5,"operator":"\u003e=","rightHand":5},"conditionString":"5 \u003e= 5"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>5 is greater than or equal to 5</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p>5 is greater than or equal to 5</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when number is greater than or equal.'
		);
	}

	/**
	 * Test inequality condition. "admin" != "user"
	 */
	public function test_inequality_condition(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"\u0022admin\u0022","operator":"!=","rightHand":"\u0022user\u0022"},"conditionString":"\u0022admin\u0022 != \u0022user\u0022"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Admin is not user</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p>Admin is not user</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when values are not equal.'
		);
	}

	/**
	 * Test truthy condition with true value.
	 */
	public function test_truthy_condition_true(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":true,"operator":"isTruthy","rightHand":null},"conditionString":"true"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>This is truthy</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p>This is truthy</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when value is truthy.'
		);
	}

	/**
	 * Test truthy condition with false value.
	 */
	public function test_truthy_condition_false(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":false,"operator":"isTruthy","rightHand":null},"conditionString":"false"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Should not show</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should hide content when value is falsy.'
		);
	}

	/**
	 * Test nested OR condition. (3 < 2) || (10 >= 5)
	 */
	public function test_nested_or_condition(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":{"leftHand":3,"operator":"\u003c","rightHand":2},"operator":"||","rightHand":{"leftHand":10,"operator":"\u003e=","rightHand":5}},"conditionString":"3 \u003c 2 || 10 \u003e= 5"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Nested OR condition works</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p>Nested OR condition works</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Nested OR condition should evaluate correctly (3 < 2 || 10 >= 5).'
		);
	}

	/**
	 * Test context data with user role.
	 */
	public function test_condition_with_context_data(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"props.role","operator":"==","rightHand":"\u0022admin\u0022"},"conditionString":"props.role == \u0022admin\u0022"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Welcome admin!</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'role' => 'admin',
			),
		);

		$expected_output = '<p>Welcome admin!</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should work with props context data.'
		);
	}

	/**
	 * Test context data with false condition.
	 */
	public function test_condition_with_context_data_false(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"props.role","operator":"==","rightHand":"\u0022admin\u0022"},"conditionString":"props.role == \u0022admin\u0022"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Welcome admin!</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'role' => 'editor',
			),
		);

		$expected_output = '';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should hide content when props context data does not match.'
		);
	}

	/**
	 * Test numeric context data comparison using props.
	 */
	public function test_condition_with_numeric_context(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"props.price","operator":"\u003e","rightHand":"100"},"conditionString":"props.price \u003e 100"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","attributes":{"class":"premium"},"block":{"type":"html","tag":"p"}}}} --><p>Premium Product</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'price' => 150,
			),
		);

		$expected_output = '<p class="premium">Premium Product</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should work with numeric props context data.'
		);
	}

	/**
	 * Test boolean context data with truthy check using props (like the working app example).
	 */
	public function test_condition_with_boolean_context(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"props.show","operator":"isTruthy","rightHand":null},"conditionString":"props.show"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>show this</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'show' => true,
			),
		);

		$expected_output = '<p>show this</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should work with boolean props context data (like working app example).'
		);
	}

	/**
	 * Test boolean context data with falsy value using props (like the working app example).
	 */
	public function test_condition_with_boolean_context_false(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"props.show","operator":"isTruthy","rightHand":null},"conditionString":"props.show"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>show this</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'show' => false,
			),
		);

		$expected_output = '';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should hide content when props.show is false (like working app example).'
		);
	}

	/**
	 * Test nested AND condition with context data using props.
	 */
	public function test_nested_and_condition_with_context(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":{"leftHand":"props.role","operator":"==","rightHand":"\u0022admin\u0022"},"operator":"&&","rightHand":{"leftHand":"props.level","operator":"\u003e","rightHand":"5"}},"conditionString":"props.role == \u0022admin\u0022 && props.level \u003e 5"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Super Admin Access</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'role' => 'admin',
				'level' => 10,
			),
		);

		$expected_output = '<p>Super Admin Access</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Nested AND condition should work with props context data.'
		);
	}

	/**
	 * Test complex condition with multiple inner blocks using props.
	 */
	public function test_condition_with_multiple_inner_blocks(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"props.enabled","operator":"==","rightHand":"\u0022true\u0022"},"conditionString":"props.enabled == \u0022true\u0022"}}}} --><div class="wp-block-group"><!-- wp:heading {"metadata":{"name":"Heading","etchData":{"origin":"etch","name":"Heading","block":{"type":"html","tag":"h2"}}},"level":2} --><h2 class="wp-block-heading">Premium Features</h2><!-- /wp:heading --><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Access to all premium content</p><!-- /wp:paragraph --><!-- wp:group {"metadata":{"name":"Link","etchData":{"origin":"etch","name":"Link","attributes":{"class":"cta-button","href":"#upgrade"},"block":{"type":"html","tag":"a"}}}} --><a>Upgrade Now</a><!-- /wp:group --></div><!-- /wp:group -->';

		$context = array(
			'props' => array(
				'enabled' => 'true',
			),
		);

		$expected_output = '<h2>Premium Features</h2><p>Access to all premium content</p><a class="cta-button" href="#upgrade">Upgrade Now</a>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render all inner blocks when props condition is true.'
		);
	}

	/**
	 * Test condition with missing context data (should be falsy).
	 */
	public function test_condition_with_missing_context_data(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":"{nonexistent.value}","operator":"isTruthy","rightHand":null},"conditionString":"{nonexistent.value}"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Should not appear</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'other' => array(
				'data' => 'value',
			),
		);

		$expected_output = '';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should be false for missing context data.'
		);
	}

	/**
	 * Test less than or equal condition. 3 <= 5
	 */
	public function test_number_less_than_or_equal_condition(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":3,"operator":"\u003c=","rightHand":5},"conditionString":"3 \u003c= 5"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","attributes":{"class":"low-value"},"block":{"type":"html","tag":"p"}}}} --><p>Low value item</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array();

		$expected_output = '<p class="low-value">Low value item</p>';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Condition should render content when number is less than or equal.'
		);
	}

	/**
	 * Test complex nested condition that should be false.
	 */
	public function test_complex_nested_condition_false(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"If (Condition)","etchData":{"origin":"etch","name":"If (Condition)","block":{"type":"condition","condition":{"leftHand":{"leftHand":"{user.role}","operator":"==","rightHand":"\u0022admin\u0022"},"operator":"&&","rightHand":{"leftHand":"{user.active}","operator":"==","rightHand":"false"}},"conditionString":"{user.role} == \u0022admin\u0022 && {user.active} == false"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","name":"Paragraph","block":{"type":"html","tag":"p"}}}} --><p>Inactive admin</p><!-- /wp:paragraph --></div><!-- /wp:group -->';

		$context = array(
			'user' => array(
				'role' => 'admin',
				'active' => true,
			),
		);

		$expected_output = '';

		$this->assert_condition_processing(
			$gutenberg_html,
			$context,
			$expected_output,
			'Complex nested condition should be false when AND condition not met.'
		);
	}
}
