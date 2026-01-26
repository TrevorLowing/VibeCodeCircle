<?php
/**
 * DynamicContextProvider test class.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use Etch\Blocks\Global\DynamicContent\DynamicContextProvider;
use Etch\Blocks\Tests\BlockTestHelper;

/**
 * Class DynamicContextProviderTest
 */
class DynamicContextProviderTest extends WP_UnitTestCase {
	use BlockTestHelper;

	/**
	 * Set up test state.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clear_cached_context();
		$this->clear_loop_context_stack();
	}

	/**
	 * Test keepGlobal reset does not retain block-local context.
	 */
	public function test_keep_global_excludes_block_context(): void {
		$block = $this->create_mock_block( 'etch/text', array() );

		$reflection = new \ReflectionClass( $block );
		$context_property = $reflection->getProperty( 'context' );
		$context_property->setAccessible( true );
		$context_property->setValue( $block, array( 'localKey' => 'localValue' ) );

		$sources = DynamicContextProvider::get_sources_for_wp_block( $block, array(), 'keepGlobal' );
		$keys = array();
		foreach ( $sources as $source ) {
			if ( is_array( $source ) && isset( $source['key'] ) && is_string( $source['key'] ) ) {
				$keys[] = $source['key'];
			}
		}

		$this->assertNotContains( 'localKey', $keys, 'Block context should not survive keepGlobal resets.' );
	}
}
