<?php
/**
 * Tests for the PassthroughBlock processor.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Tests;

use Etch\Preprocessor\Blocks\PassthroughBlock;

/**
 * Class PassthroughBlockTest
 *
 * Tests the PassthroughBlock functionality in the Preprocessor system.
 */
class PassthroughBlockTest extends PreprocessorTestBase {

	/**
	 * Test regular WordPress blocks without EtchData.
	 * They should pass through unchanged but process inner blocks.
	 */
	public function test_wordpress_block_without_etch_data(): void {
		$gutenberg_html = '<!-- wp:paragraph --><p>This is a regular WordPress paragraph.</p><!-- /wp:paragraph -->';
		$expected_output = '<p>This is a regular WordPress paragraph.</p>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Regular WordPress block should pass through unchanged.'
		);
	}

	/**
	 * Test WordPress group block with Etch blocks inside.
	 * The group should pass through but inner Etch blocks should be processed.
	 */
	public function test_wordpress_group_with_etch_inner_blocks(): void {
		$gutenberg_html = '<!-- wp:group --><div class="wp-block-group"><!-- wp:heading {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}}} --><h2 class="wp-block-heading">Etch Heading</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Regular paragraph</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$expected_output = '<div class="wp-block-group"><h2>Etch Heading</h2><p>Regular paragraph</p></div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'WordPress group should pass through but inner Etch blocks should be processed.'
		);
	}

	/**
	 * Test columns block with mixed content.
	 * Columns structure should remain but Etch blocks inside should be processed.
	 */
	public function test_columns_with_mixed_content(): void {
		$gutenberg_html = '<!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"custom-text"},"block":{"type":"html","tag":"p"}}}} --><p>Column 1 with Etch</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Column 2 regular</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns -->';
		$expected_output = '<div class="wp-block-columns"><div class="wp-block-column"><p class="custom-text">Column 1 with Etch</p></div><div class="wp-block-column"><p>Column 2 regular</p></div></div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Columns structure should remain intact with processed Etch blocks inside.'
		);
	}

	/**
	 * Test deeply nested WordPress blocks.
	 * All levels should pass through correctly with inner Etch blocks processed.
	 */
	public function test_deeply_nested_wordpress_blocks(): void {
		$gutenberg_html = '<!-- wp:group --><div class="wp-block-group"><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"inner-group"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Deep content</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->';
		$expected_output = '<div class="wp-block-group"><div class="wp-block-columns"><div class="wp-block-column"><div class="inner-group"><p>Deep content</p></div></div></div></div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Deeply nested structure should be preserved with Etch blocks processed.'
		);
	}

	/**
	 * Test list block passthrough.
	 * WordPress list blocks should pass through unchanged.
	 */
	public function test_list_block_passthrough(): void {
		$gutenberg_html = '<!-- wp:list --><ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul><!-- /wp:list -->';
		$expected_output = '<ul><li>Item 1</li><li>Item 2</li><li>Item 3</li></ul>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'WordPress list block should pass through unchanged.'
		);
	}

	/**
	 * Test image block without EtchData.
	 * Regular WordPress image blocks should pass through unchanged.
	 */
	public function test_regular_image_block(): void {
		$gutenberg_html = '<!-- wp:image {"sizeSlug":"large"} --><figure class="wp-block-image size-large"><img src="image.jpg" alt="Test image"/></figure><!-- /wp:image -->';
		$expected_output = '<figure class="wp-block-image size-large"><img src="image.jpg" alt="Test image"/></figure>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Regular WordPress image block should pass through unchanged.'
		);
	}

	/**
	 * Test quote block passthrough.
	 * WordPress quote blocks should pass through unchanged.
	 */
	public function test_quote_block_passthrough(): void {
		$gutenberg_html = '<!-- wp:quote --><blockquote class="wp-block-quote"><p>This is a quote.</p><cite>Author Name</cite></blockquote><!-- /wp:quote -->';
		$expected_output = '<blockquote class="wp-block-quote"><p>This is a quote.</p><cite>Author Name</cite></blockquote>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'WordPress quote block should pass through unchanged.'
		);
	}

	/**
	 * Test custom third-party blocks.
	 * Unknown blocks should pass through unchanged.
	 */
	public function test_third_party_block_passthrough(): void {
		$gutenberg_html = '<!-- wp:acme/custom-block {"customProp":"value"} --><div class="acme-custom-block">Custom content</div><!-- /wp:acme/custom-block -->';
		$expected_output = '<div class="acme-custom-block">Custom content</div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Third-party blocks should pass through unchanged.'
		);
	}

	/**
	 * Test empty blocks.
	 * Empty blocks should still pass through correctly.
	 */
	public function test_empty_block_passthrough(): void {
		$gutenberg_html = '<!-- wp:spacer {"height":50} --><div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div><!-- /wp:spacer -->';
		$expected_output = '<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Empty spacer block should pass through with attributes intact.'
		);
	}

	/**
	 * Test block with inline styles.
	 * Blocks with inline styles should preserve them.
	 */
	public function test_block_with_inline_styles(): void {
		$gutenberg_html = '<!-- wp:paragraph {"style":{"color":{"background":"#ff0000","text":"#ffffff"}}} --><p class="has-text-color has-background" style="background-color:#ff0000;color:#ffffff">Styled paragraph</p><!-- /wp:paragraph -->';
		$expected_output = '<p class="has-text-color has-background" style="background-color:#ff0000;color:#ffffff">Styled paragraph</p>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Block with inline styles should preserve all styling.'
		);
	}
}
