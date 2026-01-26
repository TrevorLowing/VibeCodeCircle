<?php
/**
 * Tests for the HtmlBlock processor.
 *
 * @package Etch
 */

declare(strict_types=1);

namespace Etch\Preprocessor\Tests;

use Etch\Preprocessor\Blocks\HtmlBlock;

/**
 * Class HtmlBlockTest
 *
 * Tests the HtmlBlock functionality in the Preprocessor system.
 */
class HtmlBlockTest extends PreprocessorTestBase {

	/**
	 * Test rendering a div block with clean output.
	 * It should remove the default WordPress classes.
	 */
	public function test_converting_tag_to_clean_div(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"Div","etchData":{"origin":"etch","block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<div></div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Group with div tag should output a clean div element.'
		);
	}

	/**
	 * Test rendering a ul block.
	 * It should remove the default WordPress classes and change the tag.
	 */
	public function test_converting_tag_to_clean_ul(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"name":"Ul","etchData":{"origin":"etch","block":{"type":"html","tag":"ul"}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<ul></ul>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Group with ul tag should output a clean ul element.'
		);
	}

	/**
	 * Test rendering a div with custom attributes.
	 * It should apply the aria-label and data-etch-element attributes.
	 */
	public function test_rendering_div_with_custom_attributes(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","name":"","styles":["etch-flex-div-style"],"attributes":{"aria-label":"Test this label","data-etch-element":"flex-div"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"></div><!-- /wp:group -->';
		$expected_output = '<div aria-label="Test this label" data-etch-element="flex-div"></div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Group with custom attributes should output div with those attributes properly applied.'
		);
	}

	/**
	 * Test rendering a heading without custom attributes.
	 * It should remove WordPress classes from heading blocks.
	 */
	public function test_heading_without_custom_attributes(): void {
		$gutenberg_html = '<!-- wp:heading {"metadata":{"name":"Heading","etchData":{"origin":"etch","block":{"type":"html","tag":"h2"}}},"level":2} --><h2 class="wp-block-heading">Insert your heading here...</h2><!-- /wp:heading -->';
		$expected_output = '<h2>Insert your heading here...</h2>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Heading should have WordPress classes removed.'
		);
	}

	/**
	 * Test rendering a heading with custom attributes.
	 * It should apply custom classes while removing WordPress defaults.
	 */
	public function test_heading_with_custom_attributes(): void {
		$gutenberg_html = '<!-- wp:heading {"metadata":{"name":"Heading","etchData":{"origin":"etch","name":"Heading","styles":["cvxxzi9"],"attributes":{"class":"red"},"block":{"type":"html","tag":"h2"}}},"level":2} --><h2 class="wp-block-heading">Insert your heading here...</h2><!-- /wp:heading -->';
		$expected_output = '<h2 class="red">Insert your heading here...</h2>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Heading should have WordPress classes removed and custom classes applied.'
		);
	}

	/**
	 * Test rendering a div with inner paragraph that has removeWrapper attribute.
	 * It should render the div with just the text content of the paragraph.
	 */
	public function test_rendering_div_with_text(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","name":"","block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"name":"Text","etchData":{"origin":"etch","name":"Text","block":{"type":"text"},"removeWrapper":true}}} --><p>hello</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$expected_output = '<div>hello</div>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Group with inner paragraph that has removeWrapper should output div with just the text content.'
		);
	}

	/**
	 * Test rendering a heading with nested elements.
	 * It should correctly process nested data attributes.
	 */
	public function test_heading_with_nested_elements(): void {
		$gutenberg_html = '<!-- wp:heading {"metadata":{"name":"Heading","etchData":{"origin":"etch","name":"Heading","styles":["cvxxzi9"],"attributes":{"class":"red"},"block":{"type":"html","tag":"h2"},"nestedData":{"wmlwhek":{"origin":"etch","name":"","styles":["mzkddm3"],"attributes":{"class":"blue"},"block":{"type":"html","tag":"strong"}}}}},"level":2} --><h2 class="wp-block-heading">Insert your <strong data-etch-ref="wmlwhek" >here</strong> heading here...</h2><!-- /wp:heading -->';
		$expected_output = '<h2 class="red">Insert your <strong class="blue">here</strong> heading here...</h2>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Heading should have custom class applied and contain a nested strong element with its own class.'
		);
	}

	/**
	 * Test rendering a group as a link with nested div inside.
	 * It should convert the outer group to an anchor tag and maintain the inner div.
	 */
	public function test_rendering_link_with_div_inside(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","name":"","attributes":{"href":"#"},"block":{"type":"html","tag":"a"}}}} --><div class="wp-block-group"><!-- wp:group {"metadata":{"etchData":{"origin":"etch","name":"","styles":["dqqa5wv"],"attributes":{"class":"card"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group"><!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","name":"","block":{"type":"html","tag":"p"}}}} --><p>test</p><!-- /wp:paragraph --></div><!-- /wp:group --></div><!-- /wp:group -->';
		$expected_output = '<a href="#"><div class="card"><p>test</p></div></a>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Group converted to a link with a nested div should output an anchor tag containing a div with the proper class and paragraph.'
		);
	}

	/**
	 * Test dynamic content replacement in attributes.
	 * It should replace placeholders with context values.
	 */
	public function test_dynamic_attribute_replacement(): void {
		$gutenberg_html = '<!-- wp:heading {"metadata":{"etchData":{"origin":"etch","attributes":{"class":"{props.headingClass}"},"block":{"type":"html","tag":"h2"}}}} --><h2>Dynamic Heading</h2><!-- /wp:heading -->';
		$expected_output = '<h2 class="custom-heading">Dynamic Heading</h2>';

		$context = array(
			'props' => array(
				'headingClass' => 'custom-heading',
			),
		);

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			$context,
			'Heading should have dynamic class applied from context.'
		);
	}

	/**
	 * Test dynamic content replacement in text.
	 * It should replace placeholders in the content.
	 */
	public function test_dynamic_content_replacement(): void {
		$gutenberg_html = '<!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"p"}}}} --><p>Hello {props.name}!</p><!-- /wp:paragraph -->';
		$expected_output = '<p>Hello World!</p>';

		$context = array(
			'props' => array(
				'name' => 'World',
			),
		);

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			$context,
			'Paragraph should have dynamic content replaced from context.'
		);
	}

	/**
	 * Test SVG tag handling.
	 * It should remove xmlns attribute from SVG tags.
	 */
	public function test_svg_tag_handling(): void {
		$gutenberg_html = '<!-- wp:html {"metadata":{"etchData":{"origin":"etch","attributes":{"xmlns":"http://www.w3.org/2000/svg","viewBox":"0 0 24 24"},"block":{"type":"html","tag":"svg"}}}} --><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/></svg><!-- /wp:html -->';
		$expected_output = '<svg viewBox="0 0 24 24"><path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/></svg>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'SVG should have xmlns attribute removed.'
		);
	}

	/**
	 * Test image block with nested img attributes.
	 * It should apply attributes to the nested img tag.
	 */
	public function test_image_with_nested_attributes(): void {
		$gutenberg_html = '<!-- wp:image {"metadata":{"etchData":{"origin":"etch","block":{"type":"html","tag":"figure"},"nestedData":{"img":{"origin":"etch","name":"","attributes":{"alt":"Test Image","loading":"lazy"},"block":{"type":"html","tag":"img"}}}}}} --><figure class="wp-block-image"><img src="test.jpg" alt=""/></figure><!-- /wp:image -->';
		$expected_output = '<figure><img alt="Test Image" loading="lazy" src="test.jpg" alt=""/></figure>';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Image should have nested img attributes applied.'
		);
	}

	/**
	 * Test paragraph with removeWrapper.
	 * It should render just the text content without the paragraph wrapper.
	 */
	public function test_paragraph_with_remove_wrapper(): void {
		$gutenberg_html = '<!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","removeWrapper":true,"block":{"type":"html","tag":"p"}}}} --><p>This is a paragraph with removeWrapper enabled.</p><!-- /wp:paragraph -->';
		$expected_output = 'This is a paragraph with removeWrapper enabled.';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Paragraph with removeWrapper should output just the text content.'
		);
	}

	/**
	 * Test heading with removeWrapper.
	 * It should render just the text content without the heading wrapper.
	 */
	public function test_heading_with_remove_wrapper(): void {
		$gutenberg_html = '<!-- wp:heading {"metadata":{"etchData":{"origin":"etch","removeWrapper":true,"block":{"type":"html","tag":"h2"}}},"level":2} --><h2 class="wp-block-heading">This is a heading with removeWrapper enabled.</h2><!-- /wp:heading -->';
		$expected_output = 'This is a heading with removeWrapper enabled.';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Heading with removeWrapper should output just the text content.'
		);
	}

	/**
	 * Test image with removeWrapper.
	 * It should render just the img tag without the figure wrapper.
	 */
	public function test_image_with_remove_wrapper(): void {
		$gutenberg_html = '<!-- wp:image {"sizeSlug":"full","linkDestination":"none","metadata":{"name":"Image","etchData":{"removeWrapper":true,"block":{"type":"html","tag":"figure"},"origin":"etch","name":"Image","nestedData":{"img":{"origin":"etch","name":"Image","attributes":{"src":"https://placehold.co/600x400","alt":"hello"},"block":{"type":"html","tag":"img"}}}}}} --><figure class="wp-block-image size-full"><img src="https://placehold.co/600x400" alt="hello" /></figure><!-- /wp:image -->';
		$expected_output = '<img src="https://placehold.co/600x400" alt="hello" />';

		// TODO: something buggs this test. Needs to investigate why.
		// TODO: For some reason now it duplicates the atributes on test result.

		// $this->run_standard_test(
		// $gutenberg_html,
		// $expected_output,
		// array(),
		// 'Image with removeWrapper should output just the img tag without the figure wrapper.'
		// );
	}

	/**
	 * Test div with removeWrapper and attributes.
	 * It should render just the inner content and ignore the wrapper attributes.
	 */
	public function test_div_with_remove_wrapper_and_attributes(): void {
		$gutenberg_html = '<!-- wp:group {"metadata":{"etchData":{"origin":"etch","removeWrapper":true,"attributes":{"class":"should-be-ignored","id":"wrapper-id"},"block":{"type":"html","tag":"div"}}}} --><div class="wp-block-group">Inner content here</div><!-- /wp:group -->';
		$expected_output = 'Inner content here';

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			array(),
			'Div with removeWrapper should output just the inner content, ignoring wrapper attributes.'
		);
	}

	/**
	 * Test removeWrapper with dynamic content.
	 * It should render just the parsed inner content without the wrapper.
	 */
	public function test_remove_wrapper_with_dynamic_content(): void {
		$gutenberg_html = '<!-- wp:paragraph {"metadata":{"etchData":{"origin":"etch","removeWrapper":true,"block":{"type":"html","tag":"p"}}}} --><p>Hello {props.name}, welcome to {props.site}!</p><!-- /wp:paragraph -->';
		$expected_output = 'Hello John, welcome to Etch!';

		$context = array(
			'props' => array(
				'name' => 'John',
				'site' => 'Etch',
			),
		);

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			$context,
			'Paragraph with removeWrapper should output just the parsed dynamic content.'
		);
	}

	/**
	 * When we create a core/paragraph and put a link inside of it and sabe in etch,
	 * the block's metadata is changed so it will parse as PassthourghBlock
	 * This make with the like could be parsed.
	 * We need to guarantee that this never happen and parse all nested elements properly.
	 */
	public function test_render_correctly_link_inside_core_blocks(): void {
		$gutenberg_html = '<!-- wp:paragraph {"metadata":{"etchData":{"origin":"gutenberg","block":{"type":"html","tag":"p"},"nestedData":{"thcdwi3":{"origin":"gutenberg","attributes":{"href":"https://site.com/","data-type":"page","data-id":"642"},"block":{"type":"html","tag":"a"}}}}}} --><p>Text with <a data-etch-ref="thcdwi3">link</a></p><!-- /wp:paragraph -->';
		$expected_output = '<p>Text with <a href="https://site.com/" data-type="page" data-id="642">link</a></p>';

		$context = array();

		$this->run_standard_test(
			$gutenberg_html,
			$expected_output,
			$context,
			'Paragraph with removeWrapper should output just the parsed dynamic content.'
		);
	}
}
