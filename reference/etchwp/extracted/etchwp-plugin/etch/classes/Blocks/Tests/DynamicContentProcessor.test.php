<?php
/**
 * DynamicContentProcessor test class.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use Etch\Blocks\Global\Utilities\DynamicContentProcessor;

/**
 * Class DynamicContentProcessorTest
 */
class DynamicContentProcessorTest extends WP_UnitTestCase {

	/**
	 * Test sources precedence uses last entry when keys collide.
	 */
	public function test_sources_precedence_last_wins(): void {
		$sources = array(
			array(
				'key' => 'item',
				'source' => array(
					'name' => 'first',
				),
			),
			array(
				'key' => 'item',
				'source' => array(
					'name' => 'second',
				),
			),
		);

		$result = DynamicContentProcessor::apply( '{item.name}', array( 'sources' => $sources ) );

		$this->assertSame( 'second', $result );
	}
}
