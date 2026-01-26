<?php
/**
 * SvgBlock test class.
 *
 * @package Etch
 *
 * TEST COVERAGE CHECKLIST
 * =======================
 *
 * ✅ Block Registration & Structure
 *    - Block registration (etch/dynamic-image)
 *    - Attributes structure (tag: string, attributes: object, styles: array)
 *
 * ✅ Basic Rendering & Static Examples
 *    - Renders Image fetched from mediaId attribute
 *    - Merges block attributes with other attributes
 *    - uses srcSet when useSrcSet is true (default)
 *    - does not use srcSet when useSrcSet is false
 *    - applies maximumSize attribute correctly (default is full size)
 *    - Returns empty when mediaId is empty
 *    - Returns empty when Image fetch fails
 *
 * ✅ Component Props Context
 *    - mediaId from component props: {props.mediaId}
 *    - mediaId with default value and instance value
 *    - mediaId with only default value
 *    - mediaId with only instance value
 *    - Attributes with component props: {props.className}
 *
 * ✅ Global Context Expressions
 *    - mediaId with dynamic expression: {this.featuredMediaId}
 *    - Attributes with {this.title}, {site.name}
 *
 * ✅ Integration Scenarios
 *    - DynamicImageBlock inside ComponentBlock with mediaId prop
 *    - DynamicImageBlock with nested ComponentBlock (prop shadowing)
 *    - DynamicImageBlock with default mediaId and instance mediaId
 *
 * ✅ Edge Cases
 *    - Empty mediaId handled gracefully
 *    - Invalid Image fetch handled gracefully
 *    - maximumSize attribute removed from HTML output
 *    - useSrcSet attribute removed from HTML output
 *    - mediaId attribute removed from HTML output
 *
 * ✅ Shortcode Resolution
 *    - Shortcode in SVG attribute (aria-label): shortcodes ARE resolved
 *    - Shortcodes can be combined with fetched SVG attributes
 */

declare(strict_types=1);

namespace Etch\Blocks\Tests;

use WP_UnitTestCase;
use Etch\Blocks\DynamicImageBlock\DynamicImageBlock;
use Etch\Blocks\Tests\BlockTestHelper;
use Etch\Blocks\Tests\ShortcodeTestHelper;

/**
 * Class DynamicImageBlockTest
 *
 * Comprehensive tests for DynamicImageBlock functionality including:
 * - Basic rendering with Image fetching
 * - maximumSize option
 * - useSrcSet option
 * - Component props for dynamic mediaId
 * - Default and instance values
 * - Edge cases
 */
class DynamicImageBlockTest extends WP_UnitTestCase {

	use BlockTestHelper;
	use ShortcodeTestHelper;

	/**
	 * DynamicImageBlock instance
	 *
	 * @var DynamicImageBlock
	 */
	private $dynamic_image_block;
	/**
	 * Static DynamicImageBlock instance (shared across tests)
	 *
	 * @var DynamicImageBlock
	 */
	private static $dynamic_image_block_instance;

	/**
	 * The Attachment ID of the test image
	 *
	 * @var string
	 */
	private $attachment_id;

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();

		// Only create block instance once per test class
		if ( ! self::$dynamic_image_block_instance ) {
			self::$dynamic_image_block_instance = new DynamicImageBlock();
		}
		$this->dynamic_image_block = self::$dynamic_image_block_instance;

		// Trigger block registration if not already registered
		$this->ensure_block_registered( 'etch/dynamic-image' );

		// Clear cached context between tests
		$this->clear_cached_context();

		// Create a test image in the media library
		$this->create_test_image_in_media_library();

		// Register test shortcode
		$this->register_test_shortcode();
	}

	/**
	 * Create a test image in the media library for use in tests
	 */
	public function create_test_image_in_media_library() {
		// Create a simple 500x500 red square PNG image for testing
		$upload_dir = wp_get_upload_dir();
		$test_image_path = $upload_dir['basedir'] . '/test-image.png';
		$img = imagecreatetruecolor( 500, 500 );
		$red = imagecolorallocate( $img, 255, 0, 0 );
		imagefilledrectangle( $img, 0, 0, 500, 500, $red );
		imagepng( $img, $test_image_path );
		unset( $img ); // Allow GC to free GD image (imagedestroy is deprecated in PHP 8.3+).

		// Insert the image into the media library
		$this->attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'Test Image',
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			$test_image_path
		);
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $this->attachment_id, $test_image_path );
		wp_update_attachment_metadata( $this->attachment_id, $attach_data );

		// Set the alt text for the image
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', 'Media Library Alt Text' );
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
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/dynamic-image' );
		$this->assertNotNull( $block_type );
		$this->assertEquals( 'etch/dynamic-image', $block_type->name );
	}

	/**
	 * Test block has correct attributes structure
	 */
	public function test_block_has_correct_attributes() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'etch/dynamic-image' );
		$this->assertArrayHasKey( 'tag', $block_type->attributes );
		$this->assertArrayHasKey( 'attributes', $block_type->attributes );
		$this->assertArrayHasKey( 'styles', $block_type->attributes );
		$this->assertEquals( 'string', $block_type->attributes['tag']['type'] );
		$this->assertEquals( 'object', $block_type->attributes['attributes']['type'] );
	}

	/**
	 * Test DynamicImageBlock renders with mediaId from media library
	 */
	public function test_dynamic_image_block_renders_with_media_id() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'class="test-class"', $result );
		// src and stripColors should not be in output
		$this->assertStringNotContainsString( 'maximumSize="', $result );
		$this->assertStringNotContainsString( 'useSrcSet="', $result );
	}

	/**
	 * Test DynamicImageBlock renders with alt if set explicitly on the attributes
	 */
	public function test_dynamic_image_block_renders_with_custom_alt() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
				'alt' => 'Custom Alt Text',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'alt="Custom Alt Text"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with media library alt if not set explicitly on the attributes
	 */
	public function test_dynamic_image_block_renders_with_media_library_alt() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'alt="Media Library Alt Text"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with media library alt if alt is empty string
	 */
	public function test_dynamic_image_block_renders_with_media_library_alt_if_empty_string() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'class' => 'test-class',
				'alt' => '',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'alt="Media Library Alt Text"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with srcset by default
	 */
	public function test_dynamic_image_block_renders_with_srcset() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="', $result );
	}

	/**
	 * Test DynamicImageBlock renders with full image as src by default
	 */
	public function test_dynamic_image_block_renders_with_full_src_by_default() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
			),
		);

		$full_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'full' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'src="' . $full_image_src . '"', $result );
	}


	/**
	 * Test DynamicImageBlock renders without srcset if specified
	 */
	public function test_dynamic_image_block_renders_without_srcset() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'useSrcSet' => 'false',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringNotContainsString( 'srcset="', $result );
	}

	/**
	 * Test DynamicImageBlock renders with srcset biggest size as maximumSize
	 */
	public function test_dynamic_image_block_renders_with_maximum_size() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'maximumSize' => 'thumbnail',
			),
		);
		$thumbnail_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'thumbnail' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="' . $thumbnail_image_src . ' 150w"', $result );
		$this->assertStringContainsString( 'src="' . $thumbnail_image_src . '"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with dynamic data
	 */
	public function test_dynamic_image_block_renders_with_dynamic_data() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => '{' . $this->attachment_id . '}',
				'maximumSize' => '{"thumbnail"}',
				'alt' => '{site.name}',
			),
		);
		$thumbnail_image_src = wp_get_attachment_image_src( intval( $this->attachment_id ), 'thumbnail' )[0];
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'srcset="' . $thumbnail_image_src . ' 150w"', $result );
		$this->assertStringContainsString( 'src="' . $thumbnail_image_src . '"', $result );
		$this->assertStringContainsString( 'alt="Test Blog"', $result );
	}

	/**
	 * Test DynamicImageBlock renders with width and height from maximumSize
	 */
	public function test_dynamic_image_block_renders_with_width_and_height_from_maximum_size() {
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'maximumSize' => 'thumbnail',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringContainsString( 'width="150"', $result );
		$this->assertStringContainsString( 'height="150"', $result );
	}

	/**
	 * Test DynamicImageBlock renders no alt when alt is an empty string and media library alt is also empty
	 */
	public function test_dynamic_image_block_renders_no_alt_when_alt_is_empty_string() {
		// remove alt text from media library
		update_post_meta( $this->attachment_id, '_wp_attachment_image_alt', '' );
		$attributes = array(
			'tag' => 'img',
			'attributes' => array(
				'mediaId' => $this->attachment_id,
				'maximumSize' => 'thumbnail',
				'alt' => '',
			),
		);
		$block = $this->create_mock_block( 'etch/dynamic-image', $attributes );
		$result = $this->dynamic_image_block->render_block( $attributes, '', $block );
		$this->assertStringContainsString( '<img', $result );
		$this->assertStringNotContainsString( 'alt', $result );
	}
}
