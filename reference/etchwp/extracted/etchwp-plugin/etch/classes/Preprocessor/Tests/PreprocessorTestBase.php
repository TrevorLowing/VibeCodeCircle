<?php
/**
 * Base class for Preprocessor block tests.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Tests;

use WP_UnitTestCase;
use Etch\Preprocessor\Blocks\BaseBlock;
use Etch\Preprocessor\Blocks\WpBlock;
use Etch\Preprocessor\Data\EtchData;

/**
 * Class PreprocessorTestBase
 *
 * Base test class for Preprocessor block tests. Provides common testing patterns
 * to reduce code duplication across test cases.
 */
abstract class PreprocessorTestBase extends WP_UnitTestCase {

	/**
	 * Tests a gutenberg block string conversion through the Preprocessor system.
	 *
	 * @param string               $gutenberg_html     The Gutenberg HTML string to test.
	 * @param string               $expected_output    The expected HTML output.
	 * @param array<string, mixed> $context           Optional context data for dynamic placeholders.
	 * @param string               $message            Optional assertion message.
	 * @return void
	 */
	protected function assert_block_processing(
		string $gutenberg_html,
		string $expected_output,
		array $context = array(),
		string $message = 'Output does not match expected result.'
	): void {
		$blocks = parse_blocks( $gutenberg_html );

		$this->assertCount( 1, $blocks, 'Should parse 1 block.' );

		$block = $blocks[0];
		$this->assertNotEmpty( $block['blockName'], 'Block name should not be empty.' );

		// Create a BaseBlock instance from the parsed block
		$base_block = BaseBlock::create_from_block( $block, $context );

		// Process the block
		$processed_result = $base_block->process();

		// Serialize the processed block(s) back to HTML
		$actual_output = $this->serialize_processed_result( $processed_result );

		// Assert the result matches the expected output
		$this->assertEquals( $expected_output, $actual_output, $message );
	}

	/**
	 * Tests a component block that expands into multiple blocks.
	 *
	 * @param string               $gutenberg_html     The Gutenberg HTML string to test.
	 * @param string               $expected_output    The expected HTML output.
	 * @param array<string, mixed> $context           Optional context data.
	 * @param string               $message            Optional assertion message.
	 * @return void
	 */
	protected function assert_component_expansion(
		string $gutenberg_html,
		string $expected_output,
		array $context = array(),
		string $message = 'Component expansion does not match expected result.'
	): void {
		$blocks = parse_blocks( $gutenberg_html );

		$this->assertCount( 1, $blocks, 'Should parse 1 component block.' );

		$block = $blocks[0];

		// Create a BaseBlock instance from the parsed block
		$base_block = BaseBlock::create_from_block( $block, $context );

		// Process the block - components return array of blocks
		$processed_result = $base_block->process();

		// For components, the result should be an array of blocks
		$this->assertIsArray( $processed_result, 'Component should return array of blocks.' );

		// Serialize all processed blocks
		$actual_output = '';
		if ( is_array( $processed_result ) && isset( $processed_result[0] ) && is_array( $processed_result[0] ) ) {
			$actual_output = serialize_blocks( $processed_result );
		} else {
			$actual_output = serialize_blocks( array( $processed_result ) );
		}

		// Clean up the output for comparison
		$actual_output = $this->clean_output( $actual_output );
		$expected_output = $this->clean_output( $expected_output );

		$this->assertEquals( $expected_output, $actual_output, $message );
	}

	/**
	 * Tests a loop block that expands based on data.
	 *
	 * @param string               $gutenberg_html     The Gutenberg HTML string to test.
	 * @param array<string, mixed> $loop_data         Data for the loop to iterate over.
	 * @param string               $expected_output    The expected HTML output.
	 * @param array<string, mixed> $context           Optional additional context data.
	 * @param string               $message            Optional assertion message.
	 * @return void
	 */
	protected function assert_loop_expansion(
		string $gutenberg_html,
		array $loop_data,
		string $expected_output,
		array $context = array(),
		string $message = 'Loop expansion does not match expected result.'
	): void {
		// Merge loop data into context
		$full_context = array_merge( $context, $loop_data );

		$blocks = parse_blocks( $gutenberg_html );

		$this->assertCount( 1, $blocks, 'Should parse 1 loop block.' );

		$block = $blocks[0];

		// Create a BaseBlock instance from the parsed block
		$base_block = BaseBlock::create_from_block( $block, $full_context );

		// Process the block - loops return array of blocks
		$processed_result = $base_block->process();

		// Serialize all processed blocks
		$actual_output = '';
		if ( is_array( $processed_result ) ) {
			$actual_output = serialize_blocks( $processed_result );
		}

		// Clean up the output for comparison
		$actual_output = $this->clean_output( $actual_output );
		$expected_output = $this->clean_output( $expected_output );

		$this->assertEquals( $expected_output, $actual_output, $message );
	}

	/**
	 * Tests a condition block.
	 *
	 * @param string               $gutenberg_html     The Gutenberg HTML string to test.
	 * @param array<string, mixed> $context           Context data for condition evaluation.
	 * @param string               $expected_output    The expected HTML output (empty if condition is false).
	 * @param string               $message            Optional assertion message.
	 * @return void
	 */
	protected function assert_condition_processing(
		string $gutenberg_html,
		array $context,
		string $expected_output,
		string $message = 'Condition processing does not match expected result.'
	): void {
		$blocks = parse_blocks( $gutenberg_html );

		$this->assertCount( 1, $blocks, 'Should parse 1 condition block.' );

		$block = $blocks[0];

		// Create a BaseBlock instance from the parsed block
		$base_block = BaseBlock::create_from_block( $block, $context );

		// Process the block
		$processed_result = $base_block->process();

		// Serialize the processed block(s) if not null
		$actual_output = '';
		if ( null !== $processed_result ) {
			if ( is_array( $processed_result ) && isset( $processed_result[0] ) && is_array( $processed_result[0] ) ) {
				$actual_output = serialize_blocks( $processed_result );
			} else {
				$actual_output = serialize_blocks( array( $processed_result ) );
			}
		}

		// Clean up the output for comparison
		$actual_output = $this->clean_output( $actual_output );
		$expected_output = $this->clean_output( $expected_output );

		$this->assertEquals( $expected_output, $actual_output, $message );
	}

	/**
	 * Helper method to run a standard HTML block test.
	 *
	 * @param string               $gutenberg_html  The Gutenberg HTML string to test.
	 * @param string               $expected_output The expected HTML output.
	 * @param array<string, mixed> $context        Optional context data.
	 * @param string               $message         Optional assertion message.
	 * @return void
	 */
	protected function run_standard_test(
		string $gutenberg_html,
		string $expected_output,
		array $context = array(),
		string $message = 'Output does not match expected result.'
	): void {
		$this->assert_block_processing(
			$gutenberg_html,
			$expected_output,
			$context,
			$message
		);
	}

	/**
	 * Serialize processed result to HTML string.
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>>|null $processed_result The processed block data.
	 * @return string The serialized HTML.
	 */
	private function serialize_processed_result( $processed_result ): string {
		if ( null === $processed_result ) {
			return '';
		}

		// Handle single block
		if ( isset( $processed_result['blockName'] ) ) {
			$blocks = array( $processed_result );
		} else {
			// Handle array of blocks
			$blocks = $processed_result;
		}

		$serialized = serialize_blocks( $blocks );

		// Extract only the inner content for etch/block blocks
		return $this->extract_etch_block_content( $serialized );
	}

	/**
	 * Extract content from etch/block wrappers.
	 *
	 * @param string $html The HTML string.
	 * @return string The extracted content.
	 */
	private function extract_etch_block_content( string $html ): string {
		// Remove WordPress block comments
		$html = preg_replace( '/<!-- wp:etch\/block[^>]*-->/', '', $html );
		$html = preg_replace( '/<!-- \/wp:etch\/block -->/', '', $html );

		// Remove any remaining block comments
		$html = preg_replace( '/<!--[^>]*-->/', '', $html );

		return trim( $html );
	}

	/**
	 * Clean output for comparison.
	 *
	 * @param string $output The output to clean.
	 * @return string The cleaned output.
	 */
	private function clean_output( string $output ): string {
		// Remove block comments
		$output = preg_replace( '/<!--[^>]*-->/', '', $output );

		// Remove extra whitespace
		$output = preg_replace( '/\s+/', ' ', $output );
		$output = preg_replace( '/>\s+</', '><', $output );

		return trim( $output );
	}

	/**
	 * Create mock component post for testing.
	 *
	 * @param string               $component_content The component block content.
	 * @param array<string, mixed> $properties       Component properties definition.
	 * @return int The created post ID.
	 */
	protected function create_mock_component( string $component_content, array $properties = array() ): int {
		$post_id = wp_insert_post(
			array(
				'post_title'   => 'Test Component',
				'post_content' => $component_content,
				'post_status'  => 'publish',
				'post_type'    => 'wp_block',
			)
		);

		if ( ! empty( $properties ) ) {
			update_post_meta( $post_id, 'etch_component_properties', $properties );
		}

		return $post_id;
	}

	/**
	 * Create mock loop preset for testing.
	 *
	 * @param string               $loop_id   The loop ID.
	 * @param array<string, mixed> $loop_data The loop configuration.
	 * @return void
	 */
	protected function create_mock_loop_preset( string $loop_id, array $loop_data ): void {
		$loops = get_option( 'etch_loops', array() );
		$loops[ $loop_id ] = $loop_data;
		update_option( 'etch_loops', $loops );
	}

	/**
	 * Create mock global loop preset for testing.
	 *
	 * @param string               $name      The loop preset name.
	 * @param string               $key       The loop preset key.
	 * @param array<string, mixed> $config    The loop configuration.
	 * @return string The mocked loop preset ID.
	 */
	protected function create_mock_global_loop_preset( string $name, string $key, array $config ): string {
		$loop_id = 'test_' . uniqid();

		$loop_data = array(
			'name' => $name,
			'key' => $key,
			'global' => true,
			'config' => $config,
		);

		$loops = get_option( 'etch_loops', array() );
		$loops[ $loop_id ] = $loop_data;
		update_option( 'etch_loops', $loops );

		return $loop_id;
	}

	/**
	 * Delete mock global loop preset for testing cleanup.
	 *
	 * @param string $loop_id The loop ID to delete.
	 * @return void
	 */
	protected function delete_mock_global_loop_preset( string $loop_id ): void {
		$loops = get_option( 'etch_loops', array() );

		if ( isset( $loops[ $loop_id ] ) ) {
			unset( $loops[ $loop_id ] );
			update_option( 'etch_loops', $loops );
		}
	}
}
