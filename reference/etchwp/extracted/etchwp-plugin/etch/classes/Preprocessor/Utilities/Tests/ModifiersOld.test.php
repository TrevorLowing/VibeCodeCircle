<?php
/**
 * Modifiers test class.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Utilities\Tests;

use WP_UnitTestCase;
use Etch\Preprocessor\Utilities\ModifiersOld;

/**
 * Class ModifiersTest
 */
class ModifiersTestOld extends WP_UnitTestCase {
	/**
	 * Test the parse_modifier_arguments method.
	 */
	public function test_parse_modifier_arguments_with_double_quotes() {
		$this->assertEquals(
			array( '2', '"."', '","' ),
			ModifiersOld::parse_modifier_arguments( '2, ".", ","' )
		);
	}

	/**
	 * Test the parse_quoted_arguments method.
	 */
	public function test_parse_modifier_arguments_with_single_quotes() {

		$this->assertEquals(
			array( '2', "'.'", "','" ),
			ModifiersOld::parse_modifier_arguments( "2, '.', ','" )
		);
	}

	/**
	 * Test the parse_modifier_arguments method.
	 */
	public function test_parse_modifier_arguments_with_spaces() {

		$this->assertEquals(
			array( '2', '". "', '", "' ),
			ModifiersOld::parse_modifier_arguments( '2 , ". ", ", "' )
		);
	}

	/**
	 * Test formatting ISO date strings.
	 */
	public function test_format_modifier_formats_iso_date_strings() {
		$isoDate = '2025-05-07T10:00:00.000Z';
		$formatted = ModifiersOld::apply_modifier( $isoDate, 'format("Y-m-d")', array() );

		$this->assertIsString( $formatted );
		$this->assertEquals( '2025-05-07', $formatted );
	}

	/**
	 * Test formatting Unix timestamps.
	 */
	public function test_format_modifier_formats_unix_timestamps() {
		$timestamp = 1709211909; // 2024-02-29T13:05:09Z
		$formatted = ModifiersOld::apply_modifier( $timestamp, 'format("Y-m-d H:i:s")', array() );

		$this->assertIsString( $formatted );
		$this->assertEquals( '2024-02-29 13:05:09', $formatted );
	}

	/**
	 * Test formatting Unix timestamps given as strings.
	 */
	public function test_format_modifier_formats_unix_timestamps_as_strings() {
		$timestamp = '1709211909'; // 2024-02-29T13:05:09Z
		$formatted = ModifiersOld::apply_modifier( $timestamp, 'format("Y-m-d")', array() );

		$this->assertIsString( $formatted );
		$this->assertEquals( '2024-02-29', $formatted );
	}

	/**
	 * Test formatting Unix timestamps with fewer than 10 digits.
	 */
	public function test_format_modifier_formats_short_unix_timestamps() {
		$timestamp = 20589635; // 1970-08-27T07:20:35Z
		$formatted = ModifiersOld::apply_modifier( $timestamp, 'format("Y-m-d")', array() );

		$this->assertIsString( $formatted );
		$this->assertEquals( '1970-08-27', $formatted );
	}

	/**
	 * Test formatting well-formed non-ISO date strings.
	 */
	public function test_format_modifier_formats_non_iso_date_strings() {
		$date = '2025-07-31 10:00:00';
		$formatted = ModifiersOld::apply_modifier( $date, 'format("Y-m-d H:i:s")', array() );

		$this->assertEquals( '2025-07-31 10:00:00', $formatted );
	}

	/**
	 * Test formatting well-formed non-ISO date strings.
	 */
	public function test_format_modifier_formats_other_non_iso_date_strings() {
		$date = '12/03/2025';
		$formatted = ModifiersOld::apply_modifier( $date, 'format("Y-m-d")', array() );

		$this->assertEquals( '2025-12-03', $formatted );
	}

	/**
	 * Test that invalid or non-date values return the original input.
	 */
	public function test_format_modifier_returns_original_if_invalid() {
		$this->assertTrue( ModifiersOld::apply_modifier( true, 'format("Y-m-d")', array() ) );
		$this->assertNull( ModifiersOld::apply_modifier( null, 'format("Y-m-d")', array() ) );
		$this->assertEquals(
			'not-a-date',
			ModifiersOld::apply_modifier( 'not-a-date', 'format("Y-m-d")', array() )
		);
	}

	/**
	 * Test the apply_modifier method.
	 */
	public function test_number_format_modifier_with_different_decimal_point_and_thousands_separator() {

		// 2 decimals
		$this->assertEquals( '123,456.00', ModifiersOld::apply_modifier( 123456, 'numberFormat(2,".",",")', array() ) );
		$this->assertEquals( '123.456,00', ModifiersOld::apply_modifier( 123456, 'numberFormat(2,",",".")', array() ) );

		// Round up
		$this->assertEquals( '1,235', ModifiersOld::apply_modifier( 1234.56, 'numberFormat(0,".",",")', array() ) );

		$this->assertEquals( '123.456', ModifiersOld::apply_modifier( 123456, 'numberFormat(0,",",".")', array() ) );

		// Round down
		$this->assertEquals( '1,234', ModifiersOld::apply_modifier( 1234.33, 'numberFormat(0,"",",")', array() ) );

		// Etch parses if the string input is numeric
		// This one doesnt have decimals so the comma should not be there
		$this->assertEquals( '1.234.567', ModifiersOld::apply_modifier( '1234567', 'numberFormat(0,",",".")', array() ) );
		// This have a decimal so the comma should be there and is padded with a 0
		$this->assertEquals( '1.234.567,0', ModifiersOld::apply_modifier( '1234567', 'numberFormat(1,",",".")', array() ) );

		$this->assertEquals( '1.234', ModifiersOld::apply_modifier( '1234', 'numberFormat(0,"",".")', array() ) );
	}

	/**
	 * Test that the number format modifier returns the original value if not a number.
	 */
	public function test_number_format_returns_original_value_if_not_a_number() {

		$this->assertEquals( 'some string', ModifiersOld::apply_modifier( 'some string', 'numberFormat(0,"",",")', array() ) );
		$this->assertEquals( array( 1, 2, 3 ), ModifiersOld::apply_modifier( array( 1, 2, 3 ), 'numberFormat(0,"",",")', array() ) );
	}

	/**
	 * Test the toBool modifier.
	 */
	public function test_toBool_modifier_true_values() {
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'true', 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( '1', 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( '222', 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( 222, 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'yes', 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'y', 'toBool()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'on', 'toBool()', array() ) );
	}

	/**
	 * Test the toBool modifier.
	 */
	public function test_toBool_modifier_false_values() {
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'false', 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( false, 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( '0', 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 0, 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'no', 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'n', 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'off', 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( '', 'toBool()', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( null, 'toBool()', array() ) );
	}

	/**
	 * Test the trim modifier.
	 */
	public function test_trim_modifier_trims_string_values() {
		$this->assertEquals( 'hello', ModifiersOld::apply_modifier( '  hello  ', 'trim()', array() ) );
		$this->assertEquals( 'hello', ModifiersOld::apply_modifier( "\t\nhello\t\n", 'trim()', array() ) );
		$this->assertEquals( 'spaces at beginning', ModifiersOld::apply_modifier( '   spaces at beginning', 'trim()', array() ) );
		$this->assertEquals( 'spaces at end', ModifiersOld::apply_modifier( 'spaces at end   ', 'trim()', array() ) );
	}

	/**
	 * Test that the trim modifier returns the original value if not a string.
	 */
	public function test_trim_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'trim()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'trim()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'trim()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'trim()', array() ) );
	}

	/**
	 * Test the ltrim modifier.
	 */
	public function test_ltrim_modifier_trims_string_values() {
		$this->assertEquals( 'hello', ModifiersOld::apply_modifier( '  hello', 'ltrim()', array() ) );
		$this->assertEquals( 'hello', ModifiersOld::apply_modifier( "\t\nhello", 'ltrim()', array() ) );
		$this->assertEquals( 'spaces at end ', ModifiersOld::apply_modifier( 'spaces at end ', 'ltrim()', array() ) );
	}

	/**
	 * Test that the ltrim modifier returns the original value if not a string.
	 */
	public function test_ltrim_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'ltrim()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'ltrim()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'ltrim()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'ltrim()', array() ) );
	}

	/**
	 * Test the rtrim modifier.
	 */
	public function test_rtrim_modifier_trims_string_values() {
		$this->assertEquals( 'hello', ModifiersOld::apply_modifier( 'hello  ', 'rtrim()', array() ) );
		$this->assertEquals( 'hello', ModifiersOld::apply_modifier( "hello\t\n", 'rtrim()', array() ) );
		$this->assertEquals( '  spaces at beginning', ModifiersOld::apply_modifier( '  spaces at beginning', 'rtrim()', array() ) );
	}

	/**
	 * Test that the rtrim modifier returns the original value if not a string.
	 */
	public function test_rtrim_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'rtrim()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'rtrim()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'rtrim()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'rtrim()', array() ) );
	}

	/**
	 * Test the toInt modifier.
	 */
	public function test_toInt_modifier_converts_string_to_int() {
		$this->assertEquals( 123, ModifiersOld::apply_modifier( '123', 'toInt()', array() ) );
		$this->assertEquals( 123, ModifiersOld::apply_modifier( 123, 'toInt()', array() ) );
		$this->assertEquals( 123, ModifiersOld::apply_modifier( '123.456', 'toInt()', array() ) );
		$this->assertEquals( 123, ModifiersOld::apply_modifier( 123.456, 'toInt()', array() ) );
	}

	/**
	 * Test that the toInt modifier returns the original value if not a number.
	 */
	public function test_toInt_returns_original_value_if_not_a_number() {
		$this->assertEquals( 'string', ModifiersOld::apply_modifier( 'string', 'toInt()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'toInt()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'toInt()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'toInt()', array() ) );
	}

	/**
	 * Test the toString modifier.
	 */
	public function test_toString_modifier_converts_value_to_string() {
		$this->assertEquals( 'string', ModifiersOld::apply_modifier( 'string', 'toString()', array() ) );
		$this->assertEquals( '1', ModifiersOld::apply_modifier( 1, 'toString()', array() ) );
		$this->assertEquals( '0', ModifiersOld::apply_modifier( 0, 'toString()', array() ) );
		$this->assertEquals( 'true', ModifiersOld::apply_modifier( true, 'toString()', array() ) );
		$this->assertEquals( 'false', ModifiersOld::apply_modifier( false, 'toString()', array() ) );
		$this->assertEquals( '', ModifiersOld::apply_modifier( null, 'toString()', array() ) );
	}

	/**
	 * Test the ceil modifier.
	 */
	public function test_ceil_modifier_rounds_up_to_nearest_integer() {
		$this->assertEquals( 1, ModifiersOld::apply_modifier( 0.5, 'ceil()', array() ) );
		$this->assertEquals( 2, ModifiersOld::apply_modifier( 1.5, 'ceil()', array() ) );
		$this->assertEquals( 124, ModifiersOld::apply_modifier( ' 123.45 ', 'ceil()', array() ) );
	}

	/**
	 * Test that the ceil modifier returns the original value if not a number.
	 */
	public function test_ceil_returns_original_value_if_not_a_number() {
		$this->assertEquals( 'string', ModifiersOld::apply_modifier( 'string', 'ceil()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'ceil()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'ceil()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'ceil()', array() ) );
	}

	/**
	 * Test the round modifier.
	 */
	public function test_round_modifier_rounds_to_nearest_integer() {
		$this->assertEquals( 1, ModifiersOld::apply_modifier( 0.5, 'round()', array() ) );
		$this->assertEquals( 2, ModifiersOld::apply_modifier( 1.5, 'round()', array() ) );
		$this->assertEquals( 123, ModifiersOld::apply_modifier( ' 123.45 ', 'round()', array() ) );
		$this->assertEquals( 3.14, ModifiersOld::apply_modifier( '3.14159', 'round(2)', array() ) );
		$this->assertEquals( 3.142, ModifiersOld::apply_modifier( '3.14159', 'round(3)', array() ) );
		$this->assertEquals( 3.141, ModifiersOld::apply_modifier( '3.14149', 'round(3)', array() ) );
	}

	/**
	 * Test that the round modifier returns the original value if not a number.
	 */
	public function test_round_returns_original_value_if_not_a_number() {
		$this->assertEquals( 'string', ModifiersOld::apply_modifier( 'string', 'round()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'round()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'round()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'round()', array() ) );
	}

	/**
	 * Test the floor modifier.
	 */
	public function test_floor_modifier_rounds_down_to_nearest_integer() {
		$this->assertEquals( 0, ModifiersOld::apply_modifier( 0.5, 'floor()', array() ) );
		$this->assertEquals( 123, ModifiersOld::apply_modifier( ' 123.75 ', 'floor()', array() ) );
	}

	/**
	 * Test that the floor modifier returns the original value if not a number.
	 */
	public function test_floor_returns_original_value_if_not_a_number() {
		$this->assertEquals( 'string', ModifiersOld::apply_modifier( 'string', 'floor()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'floor()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'floor()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'floor()', array() ) );
	}

	/**
	 * Test the toSlug modifier.
	 */
	public function test_toSlug_modifier_converts_string_to_slug() {
		$this->assertEquals( 'hello-world', ModifiersOld::apply_modifier( 'Hello World', 'toSlug()', array() ) );
		$this->assertEquals( 'hello-world-a', ModifiersOld::apply_modifier( 'Hello World á', 'toSlug()', array() ) );
		$this->assertEquals( 'hello-world-a', ModifiersOld::apply_modifier( '_Hello World á-', 'toSlug()', array() ) );
	}

	/**
	 * Test that the toSlug modifier returns the original value if not a string.
	 */
	public function test_toSlug_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'toSlug()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'toSlug()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'toSlug()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'toSlug()', array() ) );
	}

	/**
	 * Test the truncateChars modifier.
	 */
	public function test_truncateChars_modifier_truncates_string_to_char_count() {
		$this->assertEquals(
			'The quick ...',
			ModifiersOld::apply_modifier( 'The quick brown fox jumps over the lazy dog', 'truncateChars(10)', array() )
		);
		$this->assertEquals(
			'The quick ...',
			ModifiersOld::apply_modifier( 'The quick brown fox jumps over the lazy dog', 'truncateChars("10")', array() )
		);
		$this->assertEquals(
			'Short',
			ModifiersOld::apply_modifier( 'Short', 'truncateChars(10)', array() )
		);
	}

	/**
	 * Test the truncateChars modifier with custom ellipsis.
	 */
	public function test_truncateChars_modifier_with_custom_ellipsis() {
		$this->assertEquals(
			'The quick  (more)',
			ModifiersOld::apply_modifier( 'The quick brown fox jumps over the lazy dog', 'truncateChars(10," (more)")', array() )
		);
	}

	/**
	 * Test that the truncateChars modifier returns the original value if not a string.
	 */
	public function test_truncateChars_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'truncateChars(10)', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'truncateChars(10)', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'truncateChars(10)', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'truncateChars(10)', array() ) );
	}

	/**
	 * Test the truncateWords modifier.
	 */
	public function test_truncateWords_modifier_truncate_wordss_string_to_word_count() {
		$this->assertEquals(
			'The quick brown fox...',
			ModifiersOld::apply_modifier( 'The quick brown fox jumps over the lazy dog', 'truncateWords(4)', array() )
		);
		$this->assertEquals(
			'The quick brown fox...',
			ModifiersOld::apply_modifier( 'The quick brown fox jumps over the lazy dog', 'truncateWords("4")', array() )
		);
		$this->assertEquals(
			'The quick brown fox',
			ModifiersOld::apply_modifier( 'The quick brown fox', 'truncateWords(10)', array() )
		);
		$this->assertEquals(
			'The quick brown fox (more)',
			ModifiersOld::apply_modifier( 'The quick brown fox jumps over the lazy dog', 'truncateWords(4," (more)")', array() )
		);
	}

	/**
	 * Test that the truncateWords modifier returns the original value if not a string.
	 */
	public function test_truncateWords_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'truncateWords(2)', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'truncateWords(2)', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'truncateWords(2)', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'truncateWords(2)', array() ) );
	}

	/**
	 * Test the startsWith modifier.
	 */
	public function test_startsWith_modifier() {
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'Hello World', 'startsWith("Hello")', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'Hello World', 'startsWith("H")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'Hello World', 'startsWith("World")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'Hello World', 'startsWith("h")', array() ) );
	}

	/**
	 * Test that the startsWith modifier returns false if not a string.
	 */
	public function test_startsWith_returns_false_if_not_a_string() {
		$this->assertEquals( false, ModifiersOld::apply_modifier( 42, 'startsWith("4")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( true, 'startsWith("t")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( null, 'startsWith("n")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( array( 'array' ), 'startsWith("a")', array() ) );
	}

	/**
	 * Test the endsWith modifier.
	 */
	public function test_endsWith_modifier() {
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'Hello World', 'endsWith("World")', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( 'Hello World', 'endsWith("d")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'Hello World', 'endsWith("Hello")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( 'Hello World', 'endsWith("D")', array() ) );
	}

	/**
	 * Test that the endsWith modifier returns false if not a string.
	 */
	public function test_endsWith_returns_false_if_not_a_string() {
		$this->assertEquals( false, ModifiersOld::apply_modifier( 42, 'endsWith("2")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( true, 'endsWith("e")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( null, 'endsWith("l")', array() ) );
		$this->assertEquals( false, ModifiersOld::apply_modifier( array( 'array' ), 'endsWith("y")', array() ) );
	}

	/**
	 * Test the length modifier.
	 */
	public function test_length_modifier() {
		$this->assertEquals( 5, ModifiersOld::apply_modifier( 'Hello', 'length()', array() ) );
		$this->assertEquals( 0, ModifiersOld::apply_modifier( '', 'length()', array() ) );
		$this->assertEquals( 3, ModifiersOld::apply_modifier( array( 1, 2, 3 ), 'length()', array() ) );
		$this->assertEquals( 0, ModifiersOld::apply_modifier( array(), 'length()', array() ) );
	}

	/**
	 * Test that the length modifier returns the original value if not a string or array.
	 */
	public function test_length_returns_original_value_if_not_string_or_array() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'length()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'length()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'length()', array() ) );
		$this->assertEquals( array( 'key' => 'value' ), ModifiersOld::apply_modifier( array( 'key' => 'value' ), 'length()', array() ) );
	}

	/**
	 * Test the reverse modifier.
	 */
	public function test_reverse_modifier() {
		$this->assertEquals( 'olleH', ModifiersOld::apply_modifier( 'Hello', 'reverse()', array() ) );
		$this->assertEquals( '', ModifiersOld::apply_modifier( '', 'reverse()', array() ) );
		$this->assertEquals( array( 3, 2, 1 ), ModifiersOld::apply_modifier( array( 1, 2, 3 ), 'reverse()', array() ) );
		$this->assertEquals( array(), ModifiersOld::apply_modifier( array(), 'reverse()', array() ) );
	}

	/**
	 * Test that the reverse modifier returns the original value if not a string or array.
	 */
	public function test_reverse_returns_original_value_if_not_string_or_array() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'reverse()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'reverse()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'reverse()', array() ) );
		$this->assertEquals( array( 'key' => 'value' ), ModifiersOld::apply_modifier( array( 'key' => 'value' ), 'reverse()', array() ) );
	}

	/**
	 * Test the at modifier.
	 */
	public function test_at_modifier() {
		$this->assertEquals( 20, ModifiersOld::apply_modifier( array( 10, 20, 30 ), 'at(1)', array() ) );
		$this->assertEquals( 10, ModifiersOld::apply_modifier( array( 10, 20, 30 ), 'at(0)', array() ) );
		$this->assertEquals( 30, ModifiersOld::apply_modifier( array( 10, 20, 30 ), 'at(-1)', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( array( 10, 20, 30 ), 'at(5)', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( array( 10, 20, 30 ), 'at(-5)', array() ) );
	}

	/**
	 * Test that the at modifier returns the original value if not an array.
	 */
	public function test_at_returns_original_value_if_not_array() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'at(0)', array() ) );
		$this->assertEquals( 'test', ModifiersOld::apply_modifier( 'test', 'at(0)', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'at(0)', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'at(0)', array() ) );
		$this->assertEquals( array( 'key' => 'value' ), ModifiersOld::apply_modifier( array( 'key' => 'value' ), 'at(0)', array() ) );
	}

	/**
	 * Test the slice modifier.
	 */
	public function test_slice_modifier() {
		$this->assertEquals( array( 20, 30 ), ModifiersOld::apply_modifier( array( 10, 20, 30, 40, 50 ), 'slice(1,3)', array() ) );
		$this->assertEquals( array( 10, 20 ), ModifiersOld::apply_modifier( array( 10, 20, 30, 40, 50 ), 'slice(0,2)', array() ) );
		$this->assertEquals( array( 30, 40, 50 ), ModifiersOld::apply_modifier( array( 10, 20, 30, 40, 50 ), 'slice(2)', array() ) );
		$this->assertEquals( array( 40, 50 ), ModifiersOld::apply_modifier( array( 10, 20, 30, 40, 50 ), 'slice(-2)', array() ) );
	}

	/**
	 * Test that the slice modifier returns the original value if not an array.
	 */
	public function test_slice_returns_original_value_if_not_array() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'slice(0,1)', array() ) );
		$this->assertEquals( 'test', ModifiersOld::apply_modifier( 'test', 'slice(0,1)', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'slice(0,1)', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'slice(0,1)', array() ) );
		$this->assertEquals( array( 'key' => 'value' ), ModifiersOld::apply_modifier( array( 'key' => 'value' ), 'slice(0,1)', array() ) );
	}

	/**
	 * Test the urlEncode modifier.
	 */
	public function test_urlEncode_modifier() {
		$this->assertEquals( 'Hello%20World!', ModifiersOld::apply_modifier( 'Hello World!', 'urlEncode()', array() ) );
		$this->assertEquals( '', ModifiersOld::apply_modifier( '', 'urlEncode()', array() ) );
		$this->assertEquals( 'a%2Bb%3Dc%26d', ModifiersOld::apply_modifier( 'a+b=c&d', 'urlEncode()', array() ) );
	}

	/**
	 * Test that the urlEncode modifier returns the original value if not a string.
	 */
	public function test_urlEncode_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'urlEncode()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'urlEncode()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'urlEncode()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'urlEncode()', array() ) );
	}

	/**
	 * Test the urlDecode modifier.
	 */
	public function test_urlDecode_modifier() {
		$this->assertEquals( 'Hello World!', ModifiersOld::apply_modifier( 'Hello%20World!', 'urlDecode()', array() ) );
		$this->assertEquals( '', ModifiersOld::apply_modifier( '', 'urlDecode()', array() ) );
		$this->assertEquals( 'a+b=c&d', ModifiersOld::apply_modifier( 'a%2Bb%3Dc%26d', 'urlDecode()', array() ) );
	}

	/**
	 * Test that the urlDecode modifier returns the original value if not a string.
	 */
	public function test_urlDecode_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'urlDecode()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'urlDecode()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'urlDecode()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'urlDecode()', array() ) );
	}
	/**
	 * Test the split modifier.
	 */
	public function test_split_modifier_splits_string_by_separator() {
		$this->assertEquals(
			array( 'a', 'b', 'c' ),
			ModifiersOld::apply_modifier( 'a,b,c', 'split(",")', array() )
		);
		$this->assertEquals(
			array( 'one', 'two', 'three' ),
			ModifiersOld::apply_modifier( 'one two three', 'split(" ")', array() )
		);
		$this->assertEquals(
			array( 'apple', 'banana', 'cherry' ),
			ModifiersOld::apply_modifier( 'apple|banana|cherry', 'split("|")', array() )
		);
	}

	/**
	 * Test the split modifier uses comma as default separator.
	 */
	public function test_split_modifier_uses_comma_as_default_separator() {
		$this->assertEquals(
			array( 'a', 'b', 'c' ),
			ModifiersOld::apply_modifier( 'a,b,c', 'split()', array() )
		);
		$this->assertEquals(
			array( 'one', 'two', 'three' ),
			ModifiersOld::apply_modifier( 'one,two,three', 'split()', array() )
		);
	}

	/**
	 * Test that the split modifier returns the original value if not a string.
	 */
	public function test_split_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'split(",")', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'split(",")', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'split(",")', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'split(",")', array() ) );
	}

	/**
	 * Test the indexOf modifier returns index of substring in string.
	 */
	public function test_indexOf_modifier_returns_index_in_string() {
		$this->assertEquals( 6, ModifiersOld::apply_modifier( 'Hello World', 'indexOf("World")', array() ) );
		$this->assertEquals( 0, ModifiersOld::apply_modifier( 'Hello World', 'indexOf("H")', array() ) );
		$this->assertEquals( 4, ModifiersOld::apply_modifier( 'Hello World', 'indexOf("o")', array() ) );
	}

	/**
	 * Test the indexOf modifier returns -1 if substring not found.
	 */
	public function test_indexOf_modifier_returns_minus_one_if_not_found_in_string() {
		$this->assertEquals( -1, ModifiersOld::apply_modifier( 'Hello World', 'indexOf("Universe")', array() ) );
		$this->assertEquals( -1, ModifiersOld::apply_modifier( 'Hello World', 'indexOf("h")', array() ) );
	}

	/**
	 * Test the indexOf modifier returns index of element in array.
	 */
	public function test_indexOf_modifier_returns_index_in_array() {
		$this->assertEquals( 1, ModifiersOld::apply_modifier( array( 'apple', 'banana', 'cherry' ), 'indexOf("banana")', array() ) );
		$this->assertEquals( 0, ModifiersOld::apply_modifier( array( 'apple', 'banana', 'cherry' ), 'indexOf("apple")', array() ) );
	}

	/**
	 * Test the indexOf modifier returns -1 if element not found in array.
	 */
	public function test_indexOf_modifier_returns_minus_one_if_not_found_in_array() {
		$this->assertEquals( -1, ModifiersOld::apply_modifier( array( 'apple', 'banana', 'cherry' ), 'indexOf("date")', array() ) );
	}

	/**
	 * Test that the indexOf modifier returns -1 if not a string or array.
	 */
	public function test_indexOf_returns_minus_one_if_not_string_or_array() {
		$this->assertEquals( -1, ModifiersOld::apply_modifier( 42, 'indexOf("4")', array() ) );
		$this->assertEquals( -1, ModifiersOld::apply_modifier( true, 'indexOf("t")', array() ) );
		$this->assertEquals( -1, ModifiersOld::apply_modifier( null, 'indexOf("n")', array() ) );
	}

	/**
	 * Test the concat modifier concatenates strings.
	 */
	public function test_concat_modifier_concatenates_strings() {
		$this->assertEquals(
			'Hello World',
			ModifiersOld::apply_modifier( 'Hello', 'concat(" ","World")', array() )
		);
		$this->assertEquals(
			'foobar',
			ModifiersOld::apply_modifier( 'foo', 'concat("bar")', array() )
		);
	}

	/**
	 * Test that the concat modifier returns the original value if not a string.
	 */
	public function test_concat_returns_original_value_if_not_a_string() {
		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'concat()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'concat()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'concat()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'concat()', array() ) );
	}

	/**
	 * Test the less modifier.
	 */
	public function test_less_modifier() {
		$this->assertTrue( ModifiersOld::apply_modifier( 5, 'less(10)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( -1, 'less(0)', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( 10, 'less(5)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 0, 'less(-1)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'less(5)', array() ) );

		$this->assertTrue( ModifiersOld::apply_modifier( '5', 'less("10")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( '10', 'less("5")', array() ) );

		$this->assertTrue( ModifiersOld::apply_modifier( 'apple', 'less("banana")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 'banana', 'less("apple")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 'apple', 'less("apple")', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( null, 'less(5)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'less(null)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( array(), 'less(5)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'less([])', array() ) );

		$this->assertEquals( 'yes', ModifiersOld::apply_modifier( 5, 'less(10,"yes","no")', array() ) );
		$this->assertEquals( 'no', ModifiersOld::apply_modifier( 10, 'less(5,"yes","no")', array() ) );
	}

	/**
	 * Test the lessOrEqual modifier.
	 */
	public function test_lessOrEqual_modifier() {
		$this->assertTrue( ModifiersOld::apply_modifier( 5, 'lessOrEqual(10)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( -1, 'lessOrEqual(0)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 5, 'lessOrEqual(5)', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( 10, 'lessOrEqual(5)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 5.1, 'lessOrEqual(5)', array() ) );

		$this->assertTrue( ModifiersOld::apply_modifier( '5', 'lessOrEqual("10")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( '10', 'lessOrEqual("5")', array() ) );

		$this->assertTrue( ModifiersOld::apply_modifier( 'apple', 'lessOrEqual("banana")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 'banana', 'lessOrEqual("apple")', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 'apple', 'lessOrEqual("apple")', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( null, 'lessOrEqual(5)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'lessOrEqual(null)', array() ) );

		$this->assertEquals( 'yes', ModifiersOld::apply_modifier( 5, 'lessOrEqual(10,"yes","no")', array() ) );
		$this->assertEquals( 'no', ModifiersOld::apply_modifier( 10, 'lessOrEqual(5,"yes","no")', array() ) );
	}

	/**
	 * Test the greater modifier.
	 */
	public function test_greater_modifier() {
		$this->assertTrue( ModifiersOld::apply_modifier( 10, 'greater(5)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 0, 'greater(-1)', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'greater(10)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'greater(5)', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( '5', 'greater("10")', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( '10', 'greater("5")', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( 'apple', 'greater("banana")', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 'banana', 'greater("apple")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 'apple', 'greater("apple")', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( null, 'greater(5)', array() ) );

		$this->assertEquals( 'no', ModifiersOld::apply_modifier( 5, 'greater(10,"yes","no")', array() ) );
		$this->assertEquals( 'yes', ModifiersOld::apply_modifier( 10, 'greater(5,"yes","no")', array() ) );
	}

	/**
	 * Test the greaterOrEqual modifier.
	 */
	public function test_greaterOrEqual_modifier() {
		$this->assertTrue( ModifiersOld::apply_modifier( 10, 'greaterOrEqual(5)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 0, 'greaterOrEqual(-1)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 5, 'greaterOrEqual(5)', array() ) );

		$this->assertFalse( ModifiersOld::apply_modifier( 1, 'greaterOrEqual(5)', array() ) );

		$this->assertTrue( ModifiersOld::apply_modifier( 'apple', 'greaterOrEqual("apple")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 'apple', 'greaterOrEqual("banana")', array() ) );

		$this->assertEquals( 'no', ModifiersOld::apply_modifier( 5, 'greaterOrEqual(10,"yes","no")', array() ) );
		$this->assertEquals( 'yes', ModifiersOld::apply_modifier( 10, 'greaterOrEqual(5,"yes","no")', array() ) );
	}

	/**
	 * Test the equal modifier.
	 */
	public function test_equal_modifier() {
		$this->assertTrue( ModifiersOld::apply_modifier( 5, 'equal(5)', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( 'test', 'equal("test")', array() ) );
		$this->assertTrue( ModifiersOld::apply_modifier( true, 'equal(true)', array() ) );
		$this->assertTrue(
			ModifiersOld::apply_modifier(
				'http://localhost:9000/',
				'equal(site.home_url.concat("/"))',
				array(
					'site' => (object) array( 'home_url' => 'http://localhost:9000' ),
				)
			)
		);

		$this->assertFalse( ModifiersOld::apply_modifier( 5, 'equal(10)', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( 'test', 'equal("TEST")', array() ) );
		$this->assertFalse( ModifiersOld::apply_modifier( true, 'equal(false)', array() ) );

		$this->assertEquals( 'yes', ModifiersOld::apply_modifier( 5, 'equal(5,"yes","no")', array() ) );
		$this->assertEquals( 'no', ModifiersOld::apply_modifier( 5, 'equal(10,"yes","no")', array() ) );
	}

	/**
	 * Test the stripTags modifier.
	 */
	public function test_stripTags_modifier_removes_html_tags() {
		$this->assertEquals( 'Hello World', ModifiersOld::apply_modifier( '<p>Hello <strong>World</strong></p>', 'stripTags()', array() ) );
		$this->assertEquals( 'Link', ModifiersOld::apply_modifier( '<a href="#">Link</a>', 'stripTags()', array() ) );
		$this->assertEquals( 'No tags here', ModifiersOld::apply_modifier( 'No tags here', 'stripTags()', array() ) );
		$this->assertEquals( '', ModifiersOld::apply_modifier( '', 'stripTags()', array() ) );

		// More complex HTML
		$this->assertEquals( 'TitleThis is a paragraph.', ModifiersOld::apply_modifier( '<div><h1>Title</h1><p>This is a <em>paragraph</em>.</p></div>', 'stripTags()', array() ) );

		// Self-closing tags
		$this->assertEquals( '', ModifiersOld::apply_modifier( '<img src="image.jpg" alt="Image"/>', 'stripTags()', array() ) );

		$this->assertEquals( 42, ModifiersOld::apply_modifier( 42, 'stripTags()', array() ) );
		$this->assertEquals( true, ModifiersOld::apply_modifier( true, 'stripTags()', array() ) );
		$this->assertEquals( null, ModifiersOld::apply_modifier( null, 'stripTags()', array() ) );
		$this->assertEquals( array( 'array' ), ModifiersOld::apply_modifier( array( 'array' ), 'stripTags()', array() ) );
	}

	/**
	 * Test the pluck modifier plucks values from an array of objects.
	 */
	public function test_pluck_modifier_plucks_values_from_array_of_objects() {
		$users = array(
			array(
				'id' => 1,
				'name' => 'Alice',
				'email' => 'alice@example.com',
			),
			array(
				'id' => 2,
				'name' => 'Bob',
				'email' => 'bob@example.com',
			),
			array(
				'id' => 3,
				'name' => 'Charlie',
				'email' => 'charlie@example.com',
			),
		);

		$this->assertEquals(
			array( 'Alice', 'Bob', 'Charlie' ),
			ModifiersOld::apply_modifier( $users, 'pluck("name")', array() )
		);
		$this->assertEquals(
			array( 1, 2, 3 ),
			ModifiersOld::apply_modifier( $users, 'pluck("id")', array() )
		);
		$this->assertEquals(
			array( 'alice@example.com', 'bob@example.com', 'charlie@example.com' ),
			ModifiersOld::apply_modifier( $users, 'pluck("email")', array() )
		);
	}

	/**
	 * Test the pluck modifier plucks nested values using dot notation.
	 */
	public function test_pluck_modifier_plucks_nested_values() {
		$posts = array(
			array(
				'id' => 1,
				'title' => 'Post 1',
				'author' => array(
					'name' => 'Alice',
					'age' => 30,
				),
			),
			array(
				'id' => 2,
				'title' => 'Post 2',
				'author' => array(
					'name' => 'Bob',
					'age' => 25,
				),
			),
			array(
				'id' => 3,
				'title' => 'Post 3',
				'author' => array(
					'name' => 'Charlie',
					'age' => 35,
				),
			),
		);

		$this->assertEquals(
			array( 'Alice', 'Bob', 'Charlie' ),
			ModifiersOld::apply_modifier( $posts, 'pluck("author.name")', array() )
		);
		$this->assertEquals(
			array( 30, 25, 35 ),
			ModifiersOld::apply_modifier( $posts, 'pluck("author.age")', array() )
		);
	}

	/**
	 * Test the pluck modifier plucks deeply nested values.
	 */
	public function test_pluck_modifier_plucks_deeply_nested_values() {
		$data = array(
			array(
				'id' => 1,
				'user' => array( 'profile' => array( 'address' => array( 'city' => 'New York' ) ) ),
			),
			array(
				'id' => 2,
				'user' => array( 'profile' => array( 'address' => array( 'city' => 'London' ) ) ),
			),
			array(
				'id' => 3,
				'user' => array( 'profile' => array( 'address' => array( 'city' => 'Tokyo' ) ) ),
			),
		);

		$this->assertEquals(
			array( 'New York', 'London', 'Tokyo' ),
			ModifiersOld::apply_modifier( $data, 'pluck("user.profile.address.city")', array() )
		);
	}

	/**
	 * Test the pluck modifier returns null for missing properties.
	 */
	public function test_pluck_modifier_returns_null_for_missing_properties() {
		$users = array(
			array(
				'id' => 1,
				'name' => 'Alice',
			),
			array(
				'id' => 2,
				'name' => 'Bob',
				'age' => 25,
			),
			array(
				'id' => 3,
				'name' => 'Charlie',
			),
		);

		$this->assertEquals(
			array( null, 25, null ),
			ModifiersOld::apply_modifier( $users, 'pluck("age")', array() )
		);
	}

	/**
	 * Test the pluck modifier returns null for missing nested properties.
	 */
	public function test_pluck_modifier_returns_null_for_missing_nested_properties() {
		$posts = array(
			array(
				'id' => 1,
				'title' => 'Post 1',
				'author' => array( 'name' => 'Alice' ),
			),
			array(
				'id' => 2,
				'title' => 'Post 2',
			),
			array(
				'id' => 3,
				'title' => 'Post 3',
				'author' => array( 'name' => 'Charlie' ),
			),
		);

		$this->assertEquals(
			array( 'Alice', null, 'Charlie' ),
			ModifiersOld::apply_modifier( $posts, 'pluck("author.name")', array() )
		);
	}

	/**
	 * Test the pluck modifier returns null for non-object items.
	 */
	public function test_pluck_modifier_returns_null_for_non_object_items() {
		$mixed = array(
			array(
				'id' => 1,
				'name' => 'Alice',
			),
			null,
			array(
				'id' => 3,
				'name' => 'Charlie',
			),
			'string',
			42,
		);

		$this->assertEquals(
			array( 'Alice', null, 'Charlie', null, null ),
			ModifiersOld::apply_modifier( $mixed, 'pluck("name")', array() )
		);
	}

	/**
	 * Test the pluck modifier returns an empty array if value is not an array.
	 */
	public function test_pluck_modifier_returns_empty_array_if_not_array() {
		$this->assertEquals( array(), ModifiersOld::apply_modifier( 'not an array', 'pluck("name")', array() ) );
		$this->assertEquals( array(), ModifiersOld::apply_modifier( 42, 'pluck("name")', array() ) );
		$this->assertEquals( array(), ModifiersOld::apply_modifier( null, 'pluck("name")', array() ) );
		$this->assertEquals(
			array(),
			ModifiersOld::apply_modifier(
				array(
					'id' => 1,
					'name' => 'Alice',
				),
				'pluck("name")',
				array()
			)
		);
	}

	/**
	 * Test the pluck modifier returns an empty array if no property is provided.
	 */
	public function test_pluck_modifier_returns_empty_array_if_no_property_provided() {
		$users = array(
			array(
				'id' => 1,
				'name' => 'Alice',
			),
			array(
				'id' => 2,
				'name' => 'Bob',
			),
		);

		$this->assertEquals( array(), ModifiersOld::apply_modifier( $users, 'pluck()', array() ) );
	}

	/**
	 * Test the pluck modifier returns an empty array for an empty array.
	 */
	public function test_pluck_modifier_returns_empty_array_for_empty_array() {
		$this->assertEquals( array(), ModifiersOld::apply_modifier( array(), 'pluck("name")', array() ) );
	}

	/**
	 * Test the pluck modifier handles properties with falsy values correctly.
	 */
	public function test_pluck_modifier_handles_falsy_values() {
		$data = array(
			array(
				'id' => 1,
				'value' => 0,
			),
			array(
				'id' => 2,
				'value' => false,
			),
			array(
				'id' => 3,
				'value' => '',
			),
			array(
				'id' => 4,
				'value' => null,
			),
		);

		$this->assertEquals(
			array( 0, false, '', null ),
			ModifiersOld::apply_modifier( $data, 'pluck("value")', array() )
		);
	}

	/**
	 * Test the pluck modifier works with objects (not just arrays).
	 */
	public function test_pluck_modifier_works_with_objects() {
		$posts = array(
			(object) array(
				'id' => 1,
				'author' => (object) array(
					'name' => 'Alice',
					'age' => 30,
				),
			),
			(object) array(
				'id' => 2,
				'author' => (object) array(
					'name' => 'Bob',
					'age' => 25,
				),
			),
		);

		$this->assertEquals(
			array( 'Alice', 'Bob' ),
			ModifiersOld::apply_modifier( $posts, 'pluck("author.name")', array() )
		);
	}

	/**
	 * Test the unserializePHP modifier works as expected.
	 */
	public function test_unserialize_php_array_modifier() {
		$serialized = 'a:3:{i:0;s:5:"apple";i:1;s:6:"banana";i:2;s:6:"cherry";}';
		$this->assertEquals(
			array( 'apple', 'banana', 'cherry' ),
			ModifiersOld::apply_modifier( $serialized, 'unserializePHP()', array() )
		);

		$invalid_serialized = 'not a serialized string';
		$this->assertEquals(
			$invalid_serialized,
			ModifiersOld::apply_modifier( $invalid_serialized, 'unserializePHP()', array() )
		);

		// if the input is not a string, it should return the original value.
		$this->assertEquals(
			42,
			ModifiersOld::apply_modifier( 42, 'unserializePHP()', array() )
		);
	}
}
