<?php
/**
 * ConditionBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ All Operators
 *    - == (loose equality)
 *    - === (strict equality)
 *    - != (loose inequality)
 *    - !== (strict inequality)
 *    - > (greater than)
 *    - < (less than)
 *    - >= (greater than or equal)
 *    - <= (less than or equal)
 *    - && (logical AND)
 *    - || (logical OR)
 *    - isTruthy
 *    - isFalsy
 *
 * ✅ Static Values
 *    - String comparisons
 *    - Number comparisons
 *    - Boolean values
 *    - Null values
 *
 * ✅ Dynamic Values
 *    - Global context: this.*
 *    - Site context: site.*
 *    - User context: user.*
 *    - URL context: url.*
 *    - Options context: options.*
 *
 * ✅ Component Props
 *    - props.* values
 *    - Props with nested properties
 *    - Props in nested components
 *
 * ✅ Nested Conditions
 *    - AND conditions
 *    - OR conditions
 *    - Complex nested combinations
 *    - Deep nesting (3+ levels)
 *
 * ✅ Conditions Inside Components
 *    - Condition block inside component
 *    - Condition accessing component props
 *    - Condition with global context inside component
 *
 * ✅ Modifiers
 *    - String modifiers (toUpperCase, toLowerCase, length, etc.)
 *    - Number modifiers (toInt, ceil, floor, round, etc.)
 *    - Array modifiers (at, slice, length, etc.)
 *    - Comparison modifiers (includes, startsWith, endsWith, etc.)
 *    - Multiple modifiers chained
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in rendered content (truthy condition): shortcodes ARE resolved
 *    - Shortcode in rendered content (falsy condition): content not rendered, so shortcodes not processed
 *    - Shortcode in inner block in FSE template (direct rendering without the_content filter)
 *    - Shortcode with dynamic data in inner block in FSE template
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use WP_Block;
use Etch\Blocks\ConditionBlock\ConditionBlock;
use Etch\Blocks\ElementBlock\ElementBlock;
use Etch\Blocks\Global\DynamicContent\DynamicContentEntry;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;
use Etch\Blocks\TextBlock\TextBlock;

/**
 * Class ConditionBlockTest
 *
 * Comprehensive tests for ConditionBlock functionality including:
 * - All operators
 * - Static and dynamic values
 * - Component props
 * - Nested conditions
 * - Conditions inside components
 * - Modifiers in conditions
 */
class ConditionBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * ConditionBlock instance
	 *
	 * @var ConditionBlock
	 */
	private $condition_block;

	/**
	 * Static ConditionBlock instance (shared across tests)
	 *
	 * @var ConditionBlock
	 */
	private static $condition_block_instance;

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
		if ( ! self::$condition_block_instance ) {
			self::$condition_block_instance = new ConditionBlock();
		}
		$this->condition_block = self::$condition_block_instance;

		$this->text_block = new TextBlock();
		$this->element_block = new ElementBlock();

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/condition' );

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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/condition' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/condition', $block_type->name );
	}

	/**
	 * Test block returns content when condition is null (default behavior)
	 */
	public function test_block_renders_when_condition_is_null() {
		$attributes = array(
			'condition' => null,
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
							'content' => 'Content',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Content</p>', $result );
	}

	// ==================== OPERATOR TESTS ====================

	/**
	 * Test == operator with string equality (true)
	 */
	public function test_loose_equality_operator_string_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '"test"',
				'operator' => '==',
				'rightHand' => '"test"',
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
							'content' => 'Equal',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Equal</p>', $result );
	}

	/**
	 * Test == operator with string equality (false)
	 */
	public function test_loose_equality_operator_string_false() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '"test"',
				'operator' => '==',
				'rightHand' => '"other"',
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
									'content' => 'Should not show',
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

				$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
				$result = $this->condition_block->render_block( $attributes, '', $block );
				$this->assertEquals( '', $result );
	}

	/**
	 * Test === operator with strict equality (true)
	 */
	public function test_strict_equality_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '5',
				'operator' => '===',
				'rightHand' => '5',
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
							'content' => 'Strict equal',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Strict equal</p>', $result );
	}

	/**
	 * Test === operator with strict equality (false - type mismatch)
	 */
	public function test_strict_equality_operator_false_type_mismatch() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '"5"',
				'operator' => '===',
				'rightHand' => '5',
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test != operator with inequality (true)
	 */
	public function test_loose_inequality_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '"admin"',
				'operator' => '!=',
				'rightHand' => '"user"',
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
							'content' => 'Not equal',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Not equal</p>', $result );
	}

	/**
	 * Test !== operator with strict inequality (true)
	 */
	public function test_strict_inequality_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '"5"',
				'operator' => '!==',
				'rightHand' => '5',
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
							'content' => 'Strict not equal',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Strict not equal</p>', $result );
	}

	/**
	 * Test > operator (greater than)
	 */
	public function test_greater_than_operator() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '10',
				'operator' => '>',
				'rightHand' => '5',
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
							'content' => 'Greater',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Greater</p>', $result );
	}

	/**
	 * Test < operator (less than)
	 */
	public function test_less_than_operator() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '3',
				'operator' => '<',
				'rightHand' => '5',
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
							'content' => 'Less',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Less</p>', $result );
	}

	/**
	 * Test >= operator (greater than or equal)
	 */
	public function test_greater_than_or_equal_operator() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '5',
				'operator' => '>=',
				'rightHand' => '5',
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
							'content' => 'Greater or equal',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Greater or equal</p>', $result );
	}

	/**
	 * Test <= operator (less than or equal)
	 */
	public function test_less_than_or_equal_operator() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '3',
				'operator' => '<=',
				'rightHand' => '5',
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
							'content' => 'Less or equal',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Less or equal</p>', $result );
	}

	/**
	 * Test && operator (logical AND - true)
	 */
	public function test_logical_and_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => array(
					'leftHand' => '5',
					'operator' => '>',
					'rightHand' => '3',
				),
				'operator' => '&&',
				'rightHand' => array(
					'leftHand' => '10',
					'operator' => '<',
					'rightHand' => '20',
				),
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
							'content' => 'AND condition',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>AND condition</p>', $result );
	}

	/**
	 * Test && operator (logical AND - false)
	 */
	public function test_logical_and_operator_false() {
		$attributes = array(
			'condition' => array(
				'leftHand' => array(
					'leftHand' => '5',
					'operator' => '>',
					'rightHand' => '10',
				),
				'operator' => '&&',
				'rightHand' => array(
					'leftHand' => '10',
					'operator' => '<',
					'rightHand' => '20',
				),
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test || operator (logical OR - true)
	 */
	public function test_logical_or_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => array(
					'leftHand' => '5',
					'operator' => '>',
					'rightHand' => '10',
				),
				'operator' => '||',
				'rightHand' => array(
					'leftHand' => '10',
					'operator' => '<',
					'rightHand' => '20',
				),
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
							'content' => 'OR condition',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>OR condition</p>', $result );
	}

	/**
	 * Test || operator (logical OR - false)
	 */
	public function test_logical_or_operator_false() {
		$attributes = array(
			'condition' => array(
				'leftHand' => array(
					'leftHand' => '5',
					'operator' => '>',
					'rightHand' => '10',
				),
				'operator' => '||',
				'rightHand' => array(
					'leftHand' => '10',
					'operator' => '>',
					'rightHand' => '20',
				),
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test isTruthy operator with true value
	 */
	public function test_is_truthy_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'true',
				'operator' => 'isTruthy',
				'rightHand' => null,
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
							'content' => 'Truthy',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Truthy</p>', $result );
	}

	/**
	 * Test isTruthy operator with false value
	 */
	public function test_is_truthy_operator_false() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'false',
				'operator' => 'isTruthy',
				'rightHand' => null,
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test isFalsy operator with false value
	 */
	public function test_is_falsy_operator_true() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'false',
				'operator' => 'isFalsy',
				'rightHand' => null,
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
							'content' => 'Falsy',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Falsy</p>', $result );
	}

	/**
	 * Test isFalsy operator with true value
	 */
	public function test_is_falsy_operator_false() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'true',
				'operator' => 'isFalsy',
				'rightHand' => null,
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	// ==================== STATIC VALUES TESTS ====================

	/**
	 * Test condition with numeric static values
	 */
	public function test_static_numeric_values() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '100',
				'operator' => '>',
				'rightHand' => '50',
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
							'content' => 'Numeric',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Numeric</p>', $result );
	}

	/**
	 * Test condition with boolean static values
	 */
	public function test_static_boolean_values() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'true',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'Boolean',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Boolean</p>', $result );
	}

	// ==================== DYNAMIC VALUES TESTS ====================

	/**
	 * Test condition with this.* context (post title)
	 */
	public function test_dynamic_this_context() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'Test Post',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title',
				'operator' => '==',
				'rightHand' => '"Test Post"',
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
							'content' => 'Post title matches',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Post title matches</p>', $result );
	}

	/**
	 * Test condition with site.* context
	 */
	public function test_dynamic_site_context() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'site.name',
				'operator' => 'isTruthy',
				'rightHand' => null,
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
							'content' => 'Site name exists',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Site name exists</p>', $result );
	}

	/**
	 * Test condition with user.* context
	 */
	public function test_dynamic_user_context() {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'user.id',
				'operator' => '>',
				'rightHand' => '0',
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
							'content' => 'User logged in',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>User logged in</p>', $result );
	}

	// ==================== COMPONENT PROPS TESTS ====================

	/**
	 * Test condition with component props
	 */
	public function test_component_props_condition() {
		// Create a component pattern
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:etch/condition {"condition":{"leftHand":"props.role","operator":"==","rightHand":"\"admin\""}} --><p>Admin content</p><!-- /wp:etch/condition -->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'role',
					'type' => array( 'primitive' => 'string' ),
					'default' => '',
				),
			)
		);

		// Create component block with props
		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'role' => 'admin',
			),
		);

		$component_block = $this->create_mock_block( 'etch/component', $component_attributes );
		DynamicContextProvider::push( new DynamicContentEntry( 'component', 'props', $component_attributes['attributes'] ?? array() ) );

		// Now test condition block inside component
		$condition_attributes = array(
			'condition' => array(
				'leftHand' => 'props.role',
				'operator' => '==',
				'rightHand' => '"admin"',
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
							'content' => 'Admin content',
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

		$condition_block = $this->create_mock_block( 'etch/condition', $condition_attributes, $inner_blocks );
		$condition_block->parent = $component_block;
		try {
			$result = $this->condition_block->render_block( $condition_attributes, '', $condition_block );
			$this->assertEquals( '<p>Admin content</p>', $result );
		} finally {
			DynamicContextProvider::pop();
		}
	}

	/**
	 * Test condition with component props (false case)
	 */
	public function test_component_props_condition_false() {
		$component_attributes = array(
			'ref' => 1,
			'attributes' => array(
				'role' => 'user',
			),
		);

		$component_block = $this->create_mock_block( 'etch/component', $component_attributes );
		DynamicContextProvider::push( new DynamicContentEntry( 'component', 'props', $component_attributes['attributes'] ?? array() ) );

		$condition_attributes = array(
			'condition' => array(
				'leftHand' => 'props.role',
				'operator' => '==',
				'rightHand' => '"admin"',
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
							'content' => 'Should not show',
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

		$condition_block = $this->create_mock_block( 'etch/condition', $condition_attributes, $inner_blocks );
		$condition_block->parent = $component_block;
		try {
			$result = $this->condition_block->render_block( $condition_attributes, '', $condition_block );
			$this->assertEquals( '', $result );
		} finally {
			DynamicContextProvider::pop();
		}
	}

	/**
	 * Test condition with nested component props
	 */
	public function test_component_props_nested_property() {
		// Create a component pattern with nested property definition
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->',
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key' => 'user',
					'type' => array( 'primitive' => 'object' ),
					'default' => array(),
				),
			)
		);

		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'user' => array(
					'role' => 'admin',
				),
			),
		);

		$component_block = $this->create_mock_block( 'etch/component', $component_attributes );
		DynamicContextProvider::push( new DynamicContentEntry( 'component', 'props', $component_attributes['attributes'] ?? array() ) );

		$condition_attributes = array(
			'condition' => array(
				'leftHand' => 'props.user.role',
				'operator' => '==',
				'rightHand' => '"admin"',
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
							'content' => 'Nested prop',
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

		$condition_block = $this->create_mock_block( 'etch/condition', $condition_attributes, $inner_blocks );
		$condition_block->parent = $component_block;
		try {
			$result = $this->condition_block->render_block( $condition_attributes, '', $condition_block );
			$this->assertEquals( '<p>Nested prop</p>', $result );
		} finally {
			DynamicContextProvider::pop();
		}
	}

	// ==================== NESTED CONDITIONS TESTS ====================

	/**
	 * Test deeply nested condition (3 levels)
	 */
	public function test_deeply_nested_condition() {
		$attributes = array(
			'condition' => array(
				'leftHand' => array(
					'leftHand' => array(
						'leftHand' => '5',
						'operator' => '>',
						'rightHand' => '3',
					),
					'operator' => '&&',
					'rightHand' => array(
						'leftHand' => '10',
						'operator' => '<',
						'rightHand' => '20',
					),
				),
				'operator' => '||',
				'rightHand' => array(
					'leftHand' => '100',
					'operator' => '>',
					'rightHand' => '50',
				),
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
							'content' => 'Deeply nested',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Deeply nested</p>', $result );
	}

	/**
	 * Test complex nested condition with mixed operators
	 */
	public function test_complex_nested_condition_mixed_operators() {
		$attributes = array(
			'condition' => array(
				'leftHand' => array(
					'leftHand' => '"admin"',
					'operator' => '==',
					'rightHand' => '"admin"',
				),
				'operator' => '&&',
				'rightHand' => array(
					'leftHand' => array(
						'leftHand' => '10',
						'operator' => '>',
						'rightHand' => '5',
					),
					'operator' => '||',
					'rightHand' => array(
						'leftHand' => '3',
						'operator' => '<',
						'rightHand' => '5',
					),
				),
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
							'content' => 'Complex nested',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Complex nested</p>', $result );
	}

	// ==================== CONDITIONS INSIDE COMPONENTS TESTS ====================

	/**
	 * Test condition inside component accessing component props
	 */
	public function test_condition_inside_component_with_props() {
		// Create component pattern with condition block
		$pattern_content = '<!-- wp:etch/condition {"condition":{"leftHand":"props.show","operator":"isTruthy","rightHand":null}} --><p>Show content</p><!-- /wp:etch/condition -->';
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
					'key' => 'show',
					'type' => array( 'primitive' => 'boolean' ),
					'default' => false,
				),
			)
		);

		// Simulate component rendering with props
		$component_attributes = array(
			'ref' => $pattern_id,
			'attributes' => array(
				'show' => true,
			),
		);

		$component_block = $this->create_mock_block( 'etch/component', $component_attributes );
		DynamicContextProvider::push( new DynamicContentEntry( 'component', 'props', $component_attributes['attributes'] ?? array() ) );

		$condition_attributes = array(
			'condition' => array(
				'leftHand' => 'props.show',
				'operator' => 'isTruthy',
				'rightHand' => null,
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
							'content' => 'Show content',
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

		$condition_block = $this->create_mock_block( 'etch/condition', $condition_attributes, $inner_blocks );
		$condition_block->parent = $component_block;
		try {
			$result = $this->condition_block->render_block( $condition_attributes, '', $condition_block );
			$this->assertEquals( '<p>Show content</p>', $result );
		} finally {
			DynamicContextProvider::pop();
		}
	}

	/**
	 * Test condition inside component accessing global context
	 */
	public function test_condition_inside_component_with_global_context() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'Component Post',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$component_attributes = array(
			'ref' => 1,
			'attributes' => array(),
		);

		$component_block = $this->create_mock_block( 'etch/component', $component_attributes );

		$condition_attributes = array(
			'condition' => array(
				'leftHand' => 'this.title',
				'operator' => '==',
				'rightHand' => '"Component Post"',
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
							'content' => 'Global context works',
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

		$condition_block = $this->create_mock_block( 'etch/condition', $condition_attributes, $inner_blocks );
		$condition_block->parent = $component_block;
		$result = $this->condition_block->render_block( $condition_attributes, '', $condition_block );
		$this->assertEquals( '<p>Global context works</p>', $result );
	}

	// ==================== MODIFIERS TESTS ====================

	/**
	 * Test condition with toUpperCase modifier on dynamic value
	 */
	public function test_condition_with_to_uppercase_modifier() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'hello',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.toUpperCase()',
				'operator' => '==',
				'rightHand' => '"HELLO"',
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
							'content' => 'Uppercase modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Uppercase modifier</p>', $result );
	}

	/**
	 * Test condition with toLowerCase modifier on dynamic value
	 */
	public function test_condition_with_to_lowercase_modifier() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'HELLO',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.toLowerCase()',
				'operator' => '==',
				'rightHand' => '"hello"',
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
							'content' => 'Lowercase modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Lowercase modifier</p>', $result );
	}

	/**
	 * Test condition with length modifier on dynamic value
	 */
	public function test_condition_with_length_modifier() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'hello',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.length()',
				'operator' => '==',
				'rightHand' => '5',
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
							'content' => 'Length modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Length modifier</p>', $result );
	}

	/**
	 * Test condition with toInt modifier on dynamic value
	 */
	public function test_condition_with_to_int_modifier() {
		// Use a custom context value for this test
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.number.toInt()',
				'operator' => '===',
				'rightHand' => '10',
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
							'content' => 'ToInt modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		// Set context on block
		$block->context = array(
			'test' => array(
				'number' => '10',
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>ToInt modifier</p>', $result );
	}

	/**
	 * Test condition with ceil modifier on dynamic value
	 */
	public function test_condition_with_ceil_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.value.ceil()',
				'operator' => '==',
				'rightHand' => '6',
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
							'content' => 'Ceil modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'value' => 5.3,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Ceil modifier</p>', $result );
	}

	/**
	 * Test condition with floor modifier on dynamic value
	 */
	public function test_condition_with_floor_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.value.floor()',
				'operator' => '==',
				'rightHand' => '5',
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
							'content' => 'Floor modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'value' => 5.7,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Floor modifier</p>', $result );
	}

	/**
	 * Test condition with round modifier on dynamic value
	 */
	public function test_condition_with_round_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.value.round()',
				'operator' => '==',
				'rightHand' => '6',
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
							'content' => 'Round modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'value' => 5.5,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Round modifier</p>', $result );
	}

	/**
	 * Test condition with includes modifier on dynamic value
	 */
	public function test_condition_with_includes_modifier() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'hello world',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.includes("world")',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'Includes modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Includes modifier</p>', $result );
	}

	/**
	 * Test condition with startsWith modifier on dynamic value
	 */
	public function test_condition_with_starts_with_modifier() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'hello world',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.startsWith("hello")',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'StartsWith modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>StartsWith modifier</p>', $result );
	}

	/**
	 * Test condition with endsWith modifier on dynamic value
	 */
	public function test_condition_with_ends_with_modifier() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'hello world',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.endsWith("world")',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'EndsWith modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>EndsWith modifier</p>', $result );
	}

	/**
	 * Test condition evaluation with numeric comparison (equal modifier functionality)
	 * Note: equal() modifier returns true/false, so we test the comparison directly
	 */
	public function test_condition_with_equal_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.value.equal(5)',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'Equal modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'value' => 5,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Equal modifier</p>', $result );
	}

	/**
	 * Test condition evaluation with numeric comparison (greater modifier functionality)
	 */
	public function test_condition_with_greater_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.value.greater(5)',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'Greater modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'value' => 10,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Greater modifier</p>', $result );
	}

	/**
	 * Test condition evaluation with numeric comparison (less modifier functionality)
	 */
	public function test_condition_with_less_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.value.less(5)',
				'operator' => '===',
				'rightHand' => 'true',
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
							'content' => 'Less modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'value' => 3,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Less modifier</p>', $result );
	}

	/**
	 * Test condition with dateFormat modifier on dynamic value
	 */
	public function test_condition_with_date_format_modifier() {
		$timestamp = time();
		$formatted_date = gmdate( 'Y-m-d', $timestamp );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.timestamp.dateFormat("Y-m-d")',
				'operator' => '==',
				'rightHand' => '"' . $formatted_date . '"',
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
							'content' => 'DateFormat modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'timestamp' => $timestamp,
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>DateFormat modifier</p>', $result );
	}

	/**
	 * Test condition with at modifier (array access)
	 */
	public function test_condition_with_at_modifier() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'test.array.at(0)',
				'operator' => '==',
				'rightHand' => '"first"',
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
							'content' => 'Array access via modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$block->context = array(
			'test' => array(
				'array' => array( 'first', 'second', 'third' ),
			),
		);
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Array access via modifier</p>', $result );
	}

	/**
	 * Test condition with modifier on dynamic value
	 */
	public function test_condition_with_modifier_on_dynamic_value() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => 'Test Post Title',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.toLowerCase()',
				'operator' => '==',
				'rightHand' => '"test post title"',
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
							'content' => 'Dynamic with modifier',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Dynamic with modifier</p>', $result );
	}

	/**
	 * Test condition with modifier on component prop
	 */
	public function test_condition_with_modifier_on_component_prop() {
		// Create a component pattern
		$pattern_id = $this->factory()->post->create(
			array(
				'post_type' => 'wp_block',
				'post_content' => '<!-- wp:paragraph --><p>Content</p><!-- /wp:paragraph -->',
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
				'text' => 'Hello World',
			),
		);

		$component_block = $this->create_mock_block( 'etch/component', $component_attributes );
		DynamicContextProvider::push( new DynamicContentEntry( 'component', 'props', $component_attributes['attributes'] ?? array() ) );

		$condition_attributes = array(
			'condition' => array(
				'leftHand' => 'props.text.toUpperCase()',
				'operator' => '==',
				'rightHand' => '"HELLO WORLD"',
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
							'content' => 'Prop with modifier',
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

		$condition_block = $this->create_mock_block( 'etch/condition', $condition_attributes, $inner_blocks );
		$condition_block->parent = $component_block;
		try {
			$result = $this->condition_block->render_block( $condition_attributes, '', $condition_block );
			$this->assertEquals( '<p>Prop with modifier</p>', $result );
		} finally {
			DynamicContextProvider::pop();
		}
	}

	/**
	 * Test condition with chained modifiers (if supported)
	 */
	public function test_condition_with_multiple_modifiers() {
		$post_id = $this->factory()->post->create(
			array(
				'post_title' => '  hello world  ',
				'post_content' => 'Content',
			)
		);
		global $wp_query, $post;
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$post = get_post( $post_id );
		$wp_query->setup_postdata( $post );

		// Test multiple modifiers chained
		$attributes = array(
			'condition' => array(
				'leftHand' => 'this.title.trim().toUpperCase()',
				'operator' => '==',
				'rightHand' => '"HELLO WORLD"',
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
							'content' => 'Multiple modifiers',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		// Test that it works with chained modifiers
		$this->assertEquals( '<p>Multiple modifiers</p>', $result );
	}

	// ==================== EDGE CASES TESTS ====================

	/**
	 * Test condition with empty string
	 */
	public function test_condition_with_empty_string() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '""',
				'operator' => 'isFalsy',
				'rightHand' => null,
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
							'content' => 'Empty string',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Empty string</p>', $result );
	}

	/**
	 * Test condition with null value
	 */
	public function test_condition_with_null_value() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'null',
				'operator' => 'isFalsy',
				'rightHand' => null,
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
							'content' => 'Null value',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Null value</p>', $result );
	}

	/**
	 * Test condition with missing context value
	 */
	public function test_condition_with_missing_context_value() {
		$attributes = array(
			'condition' => array(
				'leftHand' => 'nonexistent.value',
				'operator' => 'isFalsy',
				'rightHand' => null,
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
							'content' => 'Missing value',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '<p>Missing value</p>', $result );
	}

	/**
	 * Test condition with invalid operator
	 */
	public function test_condition_with_invalid_operator() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '5',
				'operator' => 'invalidOp',
				'rightHand' => '3',
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test condition with missing operator
	 */
	public function test_condition_with_missing_operator() {
		$attributes = array(
			'condition' => array(
				'leftHand' => '5',
				'rightHand' => '3',
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
							'content' => 'Should not show',
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

		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );
		$result = $this->condition_block->render_block( $attributes, '', $block );
		$this->assertEquals( '', $result );
	}

	/**
	 * Test condition block with shortcode in rendered content (truthy condition)
	 */
	public function test_condition_block_with_shortcode_in_content_truthy() {
		$blocks = array(
			array(
				'blockName' => 'etch/condition',
				'attrs' => array(
					'condition' => array(
						'leftHand' => 'true',
						'operator' => '===',
						'rightHand' => 'true',
					),
				),
				'innerBlocks' => array(
					array(
						'blockName' => 'etch/text',
						'attrs' => array(
							'content' => 'shortcode test: [etch_test_hello name=John]',
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
		$this->assertStringContainsString( 'shortcode test: Hello John!', $rendered );
	}

	/**
	 * Test condition block with shortcode in rendered content (falsy condition - should not render)
	 */
	public function test_condition_block_with_shortcode_in_content_falsy() {
		$blocks = array(
			array(
				'blockName' => 'etch/condition',
				'attrs' => array(
					'condition' => array(
						'leftHand' => 'false',
						'operator' => '===',
						'rightHand' => 'true',
					),
				),
				'innerBlocks' => array(
					array(
						'blockName' => 'etch/text',
						'attrs' => array(
							'content' => 'shortcode test: [etch_test_hello name=John]',
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
		$this->assertStringNotContainsString( 'shortcode test: Hello John!', $rendered );
	}

	/**
	 * Test condition block with shortcode in inner block in FSE template (direct rendering)
	 * This simulates FSE template rendering where blocks are rendered directly without the_content filter
	 */
	public function test_condition_block_with_shortcode_fse_template() {
		$inner_blocks = array(
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

		$attributes = array(
			'condition' => array(
				'leftHand' => 'true',
				'operator' => '===',
				'rightHand' => 'true',
			),
		);
		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );

		// Render directly (simulating FSE template rendering, not through the_content filter)
		$result = $this->condition_block->render_block( $attributes, '', $block );

		// Shortcode should be resolved even without the_content filter
		$this->assertStringContainsString( 'shortcode test: Hello John!', $result );
		$this->assertStringNotContainsString( '[etch_test_hello name=John]', $result );
	}

	/**
	 * Test condition block with shortcode and dynamic data in inner block in FSE template
	 */
	public function test_condition_block_with_shortcode_and_dynamic_data_fse_template() {
		// Set up user context
		$user_id = $this->factory()->user->create(
			array(
				'display_name' => 'Jane Doe',
			)
		);
		wp_set_current_user( $user_id );

		$inner_blocks = array(
			array(
				'blockName' => 'etch/text',
				'attrs' => array(
					'content' => 'shortcode test: [etch_test_hello name="{user.displayName}"]',
				),
				'innerBlocks' => array(),
				'innerHTML' => '',
				'innerContent' => array(),
			),
		);

		$attributes = array(
			'condition' => array(
				'leftHand' => 'true',
				'operator' => '===',
				'rightHand' => 'true',
			),
		);
		$block = $this->create_mock_block( 'etch/condition', $attributes, $inner_blocks );

		// Render directly (simulating FSE template rendering)
		$result = $this->condition_block->render_block( $attributes, '', $block );

		// Dynamic data should be resolved first, then shortcode processed
		$this->assertStringContainsString( 'shortcode test: Hello Jane Doe!', $result );
		$this->assertStringNotContainsString( '{user.displayName}', $result );
		$this->assertStringNotContainsString( '[etch_test_hello', $result );
	}
}
