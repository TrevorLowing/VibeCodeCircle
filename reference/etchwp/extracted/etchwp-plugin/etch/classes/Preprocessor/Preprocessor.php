<?php
/**
 * Elements class for Etch plugin
 *
 * This file contains the Elements class which is responsible for
 * initializing and registering component classes for the Etch plugin.
 *
 * @package Etch
 */

 namespace Etch\Preprocessor;

use Etch\Helpers\Logger;
use Etch\Helpers\ServerTiming;
use Etch\Preprocessor\BlocksRegistry\EtchBlock;
use Etch\Preprocessor\BlocksRegistry\ContentWrapper;
use Etch\Preprocessor\Blocks\BaseBlock;
use Etch\Preprocessor\Registry\ScriptRegister;
use Etch\Preprocessor\Registry\StylesRegister;
use WP_Block_Template;

/**
 * Preprocessor class for handling WordPress blocks.
 */
class Preprocessor {

	/**
	 * Constructor for the Elements class.
	 *
	 * Instantiates all component classes.
	 */
	public function __construct() {
		// Do not preprocess blocks in the editor context
		if ( isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] ) {
			return;
		}

		add_filter( 'the_content', array( $this, 'prepare_content_blocks' ), 1 );
		add_filter( 'get_block_templates', array( $this, 'prepare_template_blocks' ), 10, 3 );
		add_action( 'init', array( $this, 'register_etch_block_block' ) );
		new ContentWrapper();
		new EtchBlock();
		new ScriptRegister();
		new StylesRegister();
	}

	/**
	 * Register the custom etch/block as a dynamic block.
	 *
	 * @return void
	 */
	public function register_etch_block_block() {
		register_block_type(
			'etch/block',
			array(
				'api_version' => '3',
				'render_callback' => function ( $attributes, $content ) {
					return $content;
				},
				'supports' => array(
					'html' => false,
					'className' => true,
				),
			)
		);
	}

	/**
	 * Prepare content blocks to be rendered.
	 *
	 * @param string $content The post content.
	 * @return string Modified content.
	 */
	public function prepare_content_blocks( $content ) {
		// Skip preprocessing in Gutenberg editor context
		if ( $this->is_gutenberg_editor_context() ) {
			return $content;
		}

		if ( empty( $content ) ) {
			return $content;
		}

		ServerTiming::start( 'prepare_content_blocks', 'Parse and process all blocks in post content.' );
		$treeFromContent = parse_blocks( $content );
		$treeFromContent = $this->parse_etch_blocks( $treeFromContent );
		$content = serialize_blocks( $treeFromContent );
		ServerTiming::stop( 'prepare_content_blocks' );

		return $content;
	}

	/**
	 * Test template filter function.
	 *
	 * @param WP_Block_Template[]  $query_result Array of found block templates.
	 * @param array<string, mixed> $query Arguments to retrieve templates.
	 * @param string               $template_type wp_template or wp_template_part.
	 * @return WP_Block_Template[] Modified array of block templates.
	 */
	public function prepare_template_blocks( $query_result, $query, $template_type ) {
		// Skip preprocessing in Gutenberg editor context
		if ( $this->is_gutenberg_editor_context() ) {
			return $query_result;
		}

		if ( empty( $query_result ) ) {
			return $query_result;
		}

		$template_hierarchy = $this->extract_template_hierarchy( $query );

		if ( empty( $template_hierarchy ) ) {
			return $query_result;
		}

		return $this->process_template_hierarchy( $query_result, $template_hierarchy );
	}

	/**
	 * Extract template hierarchy from query.
	 *
	 * @param array<string, mixed> $query Query arguments.
	 * @return string[] Template hierarchy array.
	 */
	private function extract_template_hierarchy( $query ) {
		return isset( $query['slug__in'] ) && is_array( $query['slug__in'] )
			? $query['slug__in']
			: array();
	}

	/**
	 * Process template hierarchy to find and modify the first matching template.
	 *
	 * @param WP_Block_Template[] $query_result Array of found block templates.
	 * @param string[]            $template_hierarchy Template hierarchy array.
	 * @return WP_Block_Template[] Modified array of block templates.
	 */
	private function process_template_hierarchy( $query_result, $template_hierarchy ) {
		// The hierarchy is ordered from most to least specific.
		// We need to find the first template from our results that matches the hierarchy.
		foreach ( $template_hierarchy as $slug ) {
			foreach ( $query_result as $index => $template ) {
				if ( ! $this->is_matching_template( $template, $slug ) ) {
					continue;
				}

				$query_result[ $index ] = $this->process_template_content( $template );

				// We've processed the first-choice template, so we are done.
				return $query_result;
			}
		}

		// No template in the hierarchy was found in the query_result.
		return $query_result;
	}

	/**
	 * Check if template matches the given slug.
	 *
	 * @param WP_Block_Template $template Template object.
	 * @param string            $slug Template slug to match.
	 * @return bool True if template matches slug.
	 */
	private function is_matching_template( $template, $slug ) {
		return property_exists( $template, 'slug' ) && $template->slug === $slug;
	}

	/**
	 * Process template content by parsing and modifying blocks.
	 *
	 * @param WP_Block_Template $template Template object.
	 * @return WP_Block_Template Modified template object.
	 */
	private function process_template_content( $template ) {
		if ( ! property_exists( $template, 'content' ) || empty( $template->content ) ) {
			return $template;
		}

		$blocks = parse_blocks( $template->content );
		$processed_blocks = $this->parse_etch_blocks( $blocks );

		$template->content = serialize_blocks( $processed_blocks );

		return $template;
	}

	/**
	 * Recursively process all blocks using the new BaseBlock system.
	 *
	 * @param array<int|string, array<string, mixed>> $blocks Array of block data from parse_blocks().
	 * @return array<int|string, array{blockName: string, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}> Modified blocks array for serialize_blocks().
	 */
	private static function parse_etch_blocks( $blocks ) {
		// Check if we're in editor context and skip processing if so
		if ( self::is_editor_context() ) {
			/**
			 * Block data array.
			 *
			 * @var array<int|string, array{blockName: string, attrs: array<string, mixed>, innerBlocks: array<int, array<string, mixed>>, innerHTML: string, innerContent: array<int, string|null>}> $blocks
			 */
			return $blocks;
		}

		$new_blocks = array();

		foreach ( $blocks as $block ) {
			if ( ! is_array( $block ) ) {
				$new_blocks[] = $block;
				continue;
			}

			$block_instance = BaseBlock::create_from_block( $block );
			$processed_result = $block_instance->process();

			// Handle case where a single block expands into multiple blocks (e.g., components)
			if ( is_array( $processed_result ) && isset( $processed_result[0] ) && is_array( $processed_result[0] ) ) {
				// This is an array of blocks, flatten them into the result
				foreach ( $processed_result as $expanded_block ) {
					$new_blocks[] = $expanded_block;
				}
			} else {
				// This is a single block
				$new_blocks[] = $processed_result;
			}
		}

		return $new_blocks;
	}

	/**
	 * Check if we're in the Gutenberg editor environment.
	 *
	 * @return bool True if in editor context.
	 */
	private function is_gutenberg_editor_context() {
		return self::is_editor_context();
	}

	/**
	 * Static method to check if we're in editor context.
	 * TODO: Experimental: There might be more edge cases to consider.
	 *
	 * @return bool True if in editor context.
	 */
	private static function is_editor_context() {
		// Check for REST requests with edit context
		if ( function_exists( 'wp_is_serving_rest_request' ) && wp_is_serving_rest_request() ) {
			if ( isset( $_GET['context'] ) && 'edit' === $_GET['context'] ) {
				return true;
			}
		}

		// Check for admin block editor pages
		if ( function_exists( 'is_admin' ) && is_admin() ) {
			global $pagenow;
			if ( in_array( $pagenow, array( 'post.php', 'post-new.php', 'site-editor.php' ), true ) ) {
				return true;
			}

			// Check for block editor screen
			if ( function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( $screen && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
					return true;
				}
			}
		}

		return false;
	}
}
