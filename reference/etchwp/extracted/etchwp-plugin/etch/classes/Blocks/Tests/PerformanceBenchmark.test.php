<?php
/**
 * Performance benchmark tests for Blocks dynamic content.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use Etch\Preprocessor\Utilities\LoopHandlerManager;
use Etch\Blocks\Global\CachedLoopPresets;

/**
 * Class PerformanceBenchmarkTest
 *
 * @group performance
 */
class PerformanceBenchmarkTest extends WP_UnitTestCase {
	use BlockTestHelper;

	/**
	 * Set up test state.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clear_cached_context();
		$this->clear_loop_context_stack();
		$this->ensure_block_registered( 'etch/component' );
		$this->ensure_block_registered( 'etch/loop' );
		$this->ensure_block_registered( 'etch/text' );
		LoopHandlerManager::reset();
		CachedLoopPresets::clear_cache();
	}

	/**
	 * Clean up after performance tests.
	 */
	public function tearDown(): void {
		delete_option( 'etch_loops' );
		CachedLoopPresets::clear_cache();
		$this->clear_loop_context_stack();
		parent::tearDown();
	}

	/**
	 * Run a benchmark and print average timing to STDERR.
	 *
	 * @param string   $label Label to print.
	 * @param callable $callback Workload callback.
	 * @param int      $iterations Number of timed iterations.
	 * @param int      $warmup Number of warmup iterations.
	 * @return float Average duration in milliseconds.
	 */
	private function run_benchmark( string $label, callable $callback, int $iterations = 5, int $warmup = 2 ): float {
		for ( $i = 0; $i < $warmup; $i++ ) {
			$callback();
		}

		$times = array();
		for ( $i = 0; $i < $iterations; $i++ ) {
			$start = microtime( true );
			$callback();
			$times[] = ( microtime( true ) - $start ) * 1000;
		}

		$average = array_sum( $times ) / max( 1, count( $times ) );
		fwrite(
			STDERR,
			sprintf(
				"PERF %s: avg=%.2fms (n=%d)\n",
				$label,
				$average,
				$iterations
			)
		);

		return $average;
	}

	/**
	 * Benchmark nested loop + component props rendering.
	 */
	public function test_performance_nested_component_loops(): void {
		// Seed posts for loop handlers.
		for ( $i = 1; $i <= 60; $i++ ) {
			$this->factory()->post->create(
				array(
					'post_title'  => 'Perf Post ' . $i,
					'post_status' => 'publish',
				)
			);
		}

		update_option(
			'etch_loops',
			array(
				'perf_outer_loop' => array(
					'name'   => 'Perf Outer Loop',
					'key'    => 'perf-outer',
					'global' => true,
					'config' => array(
						'type' => 'wp-query',
						'args' => array(
							'post_type'      => 'post',
							'posts_per_page' => '$count ?? 5',
							'orderby'        => 'date',
							'order'          => 'DESC',
							'post_status'    => 'publish',
						),
					),
				),
				'perf_inner_loop' => array(
					'name'   => 'Perf Inner Loop',
					'key'    => 'perf-inner',
					'global' => true,
					'config' => array(
						'type' => 'wp-query',
						'args' => array(
							'post_type'      => 'post',
							'posts_per_page' => '$count ?? 3',
							'orderby'        => 'date',
							'order'          => 'DESC',
							'post_status'    => 'publish',
						),
					),
				),
			)
		);

		$pattern_content = '<!-- wp:etch/loop {"target":"props.loop($count: props.count)","itemId":"item"} -->'
			. '<!-- wp:etch/text {"content":"Outer: {item.title} {props.suffix}"} /-->'
			. '<!-- wp:etch/text {"content":"Outer extra: {item.title} {props.suffix}"} /-->'
			. '<!-- wp:etch/loop {"target":"props.innerLoop($count: props.innerCount)","itemId":"innerItem"} -->'
			. '<!-- wp:etch/text {"content":"Inner: {innerItem.title} {props.suffix}"} /-->'
			. '<!-- wp:etch/text {"content":"Inner extra: {innerItem.title} {props.suffix}"} /-->'
			. '<!-- /wp:etch/loop -->'
			. '<!-- /wp:etch/loop -->';

		$pattern_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key'     => 'loop',
					'type'    => array(
						'primitive'   => 'string',
						'specialized' => 'array',
					),
					'default' => 'perf_outer_loop',
				),
				array(
					'key'     => 'count',
					'type'    => array( 'primitive' => 'number' ),
					'default' => 5,
				),
				array(
					'key'     => 'innerLoop',
					'type'    => array(
						'primitive'   => 'string',
						'specialized' => 'array',
					),
					'default' => 'perf_inner_loop',
				),
				array(
					'key'     => 'innerCount',
					'type'    => array( 'primitive' => 'number' ),
					'default' => 3,
				),
				array(
					'key'     => 'suffix',
					'type'    => array( 'primitive' => 'string' ),
					'default' => 'Perf',
				),
			)
		);

		$blocks = array(
			array(
				'blockName' => 'etch/component',
				'attrs' => array(
					'ref' => $pattern_id,
					'attributes' => array(
						'loop' => 'perf_outer_loop',
						'count' => 6,
						'innerLoop' => 'perf_inner_loop',
						'innerCount' => 4,
						'suffix' => 'Perf',
					),
				),
				'innerBlocks' => array(),
				'innerHTML' => "\n\n",
				'innerContent' => array( "\n\n" ),
			),
		);

		$expected_outer = 6;
		$expected_inner = 6 * 4;

		$benchmark = function () use ( $blocks, $expected_outer, $expected_inner ) {
			$rendered = $this->render_blocks_through_content_filter( $blocks );
			$this->assertSame( $expected_outer, substr_count( $rendered, 'Outer: ' ) );
			$this->assertSame( $expected_outer, substr_count( $rendered, 'Outer extra: ' ) );
			$this->assertSame( $expected_inner, substr_count( $rendered, 'Inner: ' ) );
			$this->assertSame( $expected_inner, substr_count( $rendered, 'Inner extra: ' ) );
		};

		$this->run_benchmark( 'nested_component_loops', $benchmark, 100, 5 );
	}

	/**
	 * Benchmark repeated renders with changing loop arguments.
	 *
	 * Ensures caching never freezes loop arguments or output.
	 */
	public function test_performance_loop_arg_variation(): void {
		for ( $i = 1; $i <= 20; $i++ ) {
			$this->factory()->post->create(
				array(
					'post_title'  => 'Arg Post ' . $i,
					'post_status' => 'publish',
				)
			);
		}

		update_option(
			'etch_loops',
			array(
				'perf_arg_loop' => array(
					'name'   => 'Perf Arg Loop',
					'key'    => 'perf-arg',
					'global' => true,
					'config' => array(
						'type' => 'wp-query',
						'args' => array(
							'post_type'      => 'post',
							'posts_per_page' => '$count ?? 1',
							'orderby'        => 'date',
							'order'          => 'DESC',
							'post_status'    => 'publish',
						),
					),
				),
			)
		);

		$pattern_content = '<!-- wp:etch/loop {"target":"props.loop($count: props.count)","itemId":"item"} -->'
			. '<!-- wp:etch/text {"content":"{item.title}|"} /-->'
			. '<!-- /wp:etch/loop -->';

		$pattern_id = $this->factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_content' => $pattern_content,
			)
		);

		update_post_meta(
			$pattern_id,
			'etch_component_properties',
			array(
				array(
					'key'     => 'loop',
					'type'    => array(
						'primitive'   => 'string',
						'specialized' => 'array',
					),
					'default' => 'perf_arg_loop',
				),
				array(
					'key'     => 'count',
					'type'    => array( 'primitive' => 'number' ),
					'default' => 1,
				),
			)
		);

		$counts = array( 1, 2, 3, 4, 5, 6 );

		$benchmark = function () use ( $counts, $pattern_id ) {
			foreach ( $counts as $count ) {
				$blocks = array(
					array(
						'blockName' => 'etch/component',
						'attrs' => array(
							'ref' => $pattern_id,
							'attributes' => array(
								'loop' => 'perf_arg_loop',
								'count' => $count,
							),
						),
						'innerBlocks' => array(),
						'innerHTML' => "\n\n",
						'innerContent' => array( "\n\n" ),
					),
				);

				$rendered = $this->render_blocks_through_content_filter( $blocks );
				$this->assertSame( $count, substr_count( $rendered, '|' ) );
			}
		};

		$this->run_benchmark( 'loop_arg_variation', $benchmark, 100, 5 );
	}
}
