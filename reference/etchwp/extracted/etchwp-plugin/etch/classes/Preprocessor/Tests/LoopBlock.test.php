<?php
/**
 * Tests for the LoopBlock processor.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Tests;

use Etch\Preprocessor\Blocks\LoopBlock;

/**
 * Class LoopBlockTest
 *
 * Tests the LoopBlock functionality in the Preprocessor system.
 */
class LoopBlockTest extends PreprocessorTestBase {

	/**
	 * Test basic loop from global preset.
	 */
	public function test_loop_from_global_preset(): void {
		// I need the way to create a mocked presets
		// maybe a "test" cpt
		// posts for this cpt
		// after each test I need to also remove all those posts from this cpt and the cpt itself
	}
}
