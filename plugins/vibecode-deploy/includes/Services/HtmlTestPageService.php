<?php
/**
 * HTML Test Page Service
 *
 * Generates comprehensive HTML test pages covering all HTML4 and HTML5 elements
 * for testing block conversion accuracy and EtchWP IDE editability.
 *
 * @package VibeCode\Deploy
 */

namespace VibeCode\Deploy\Services;

use VibeCode\Deploy\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * HTML Test Page Service
 */
final class HtmlTestPageService {

	/**
	 * Generate comprehensive HTML test page.
	 *
	 * @return string Complete HTML document.
	 */
	public static function generate_test_page_html(): string {
		$html = '<!DOCTYPE html>' . "\n";
		$html .= '<html lang="en">' . "\n";
		$html .= '<head>' . "\n";
		$html .= '  <meta charset="UTF-8">' . "\n";
		$html .= '  <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
		$html .= '  <title>Comprehensive HTML Test Page - HTML4 & HTML5 Elements</title>' . "\n";
		$html .= '  <style>' . "\n";
		$html .= '    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; max-width: 1200px; margin: 0 auto; padding: 20px; line-height: 1.6; }' . "\n";
		$html .= '    section { margin: 40px 0; padding: 20px; border: 1px solid #ddd; border-radius: 4px; }' . "\n";
		$html .= '    h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }' . "\n";
		$html .= '    h2 { color: #555; border-bottom: 2px solid #555; padding-bottom: 8px; margin-top: 30px; }' . "\n";
		$html .= '    .element-group { margin: 20px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #0073aa; }' . "\n";
		$html .= '    .element-label { font-weight: bold; color: #666; margin-bottom: 5px; font-size: 0.9em; }' . "\n";
		$html .= '    table { border-collapse: collapse; width: 100%; margin: 10px 0; }' . "\n";
		$html .= '    table td, table th { border: 1px solid #ddd; padding: 8px; }' . "\n";
		$html .= '    table th { background: #f0f0f0; }' . "\n";
		$html .= '    form { margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 4px; }' . "\n";
		$html .= '    input, textarea, select { margin: 5px 0; padding: 5px; }' . "\n";
		$html .= '  </style>' . "\n";
		$html .= '</head>' . "\n";
		$html .= '<body>' . "\n";
		$html .= '  <h1>Comprehensive HTML Test Page</h1>' . "\n";
		$html .= '  <p>This page contains examples of all HTML4 and HTML5 elements for testing block conversion accuracy and EtchWP IDE editability.</p>' . "\n";
		
		$html .= self::generate_headings_section();
		$html .= self::generate_text_formatting_section();
		$html .= self::generate_lists_section();
		$html .= self::generate_links_section();
		$html .= self::generate_images_section();
		$html .= self::generate_tables_section();
		$html .= self::generate_forms_section();
		$html .= self::generate_semantic_html5_section();
		$html .= self::generate_media_section();
		$html .= self::generate_code_section();
		$html .= self::generate_quotes_section();
		$html .= self::generate_block_elements_section();
		$html .= self::generate_edge_cases_section();
		
		$html .= '</body>' . "\n";
		$html .= '</html>';
		
		return $html;
	}

	/**
	 * Deploy test page to WordPress.
	 *
	 * @return int|false Page ID on success, false on failure.
	 */
	public static function deploy_test_page_to_wordpress(): int|false {
		// Generate HTML content
		$html_content = self::generate_test_page_html();
		
		// Extract body content (remove DOCTYPE, html, head tags)
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html_content );
		libxml_clear_errors();
		
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return false;
		}
		
		// Get inner HTML of body
		$body_html = '';
		foreach ( $body->childNodes as $node ) {
			$body_html .= $dom->saveHTML( $node );
		}
		
		// Convert HTML to Gutenberg blocks
		$blocks = Importer::html_to_etch_blocks( $body_html );
		
		// Create WordPress page
		$page_id = wp_insert_post(
			array(
				'post_title' => 'Comprehensive HTML Test Page',
				'post_content' => $blocks,
				'post_status' => 'publish',
				'post_type' => 'page',
				'post_name' => 'html-test-page',
			),
			true
		);
		
		if ( is_wp_error( $page_id ) ) {
			return false;
		}
		
		return $page_id;
	}

	/**
	 * Generate headings section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_headings_section(): string {
		$html = '  <section id="headings">' . "\n";
		$html .= '    <h2>Headings (h1-h6)</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">All Heading Levels:</div>' . "\n";
		$html .= '      <h1>Heading 1 - Main Title</h1>' . "\n";
		$html .= '      <h2>Heading 2 - Section Title</h2>' . "\n";
		$html .= '      <h3>Heading 3 - Subsection</h3>' . "\n";
		$html .= '      <h4>Heading 4 - Sub-subsection</h4>' . "\n";
		$html .= '      <h5>Heading 5 - Minor Heading</h5>' . "\n";
		$html .= '      <h6>Heading 6 - Smallest Heading</h6>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Headings with Classes and IDs:</div>' . "\n";
		$html .= '      <h2 id="main-heading" class="title primary">Heading with ID and Classes</h2>' . "\n";
		$html .= '      <h3 class="subtitle" data-test="value">Heading with Data Attribute</h3>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Headings with Inline Elements:</div>' . "\n";
		$html .= '      <h2>Heading with <strong>bold</strong> and <em>italic</em> text</h2>' . "\n";
		$html .= '      <h3>Heading with <span class="highlight">highlighted</span> and <a href="#test">linked</a> text</h3>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate text formatting section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_text_formatting_section(): string {
		$html = '  <section id="text-formatting">' . "\n";
		$html .= '    <h2>Text Formatting</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Paragraphs:</div>' . "\n";
		$html .= '      <p>This is a regular paragraph with normal text.</p>' . "\n";
		$html .= '      <p class="intro" id="first-paragraph">This is a paragraph with class and ID attributes.</p>' . "\n";
		$html .= '      <p>This paragraph contains <strong>bold text</strong>, <em>italic text</em>, and <u>underlined text</u>.</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Inline Formatting Elements:</div>' . "\n";
		$html .= '      <p>' . "\n";
		$html .= '        <strong>Strong/Bold text</strong>, ' . "\n";
		$html .= '        <em>Emphasized/Italic text</em>, ' . "\n";
		$html .= '        <b>Bold text</b>, ' . "\n";
		$html .= '        <i>Italic text</i>, ' . "\n";
		$html .= '        <u>Underlined text</u>, ' . "\n";
		$html .= '        <small>Small text</small>, ' . "\n";
		$html .= '        <sub>Subscript</sub>, ' . "\n";
		$html .= '        <sup>Superscript</sup>, ' . "\n";
		$html .= '        <mark>Marked/highlighted text</mark>, ' . "\n";
		$html .= '        <del>Deleted text</del>, ' . "\n";
		$html .= '        <ins>Inserted text</ins>' . "\n";
		$html .= '      </p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Span Elements:</div>' . "\n";
		$html .= '      <p>This paragraph has <span class="highlight">highlighted span</span> and <span id="special" data-value="123">span with attributes</span>.</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Other Text Elements:</div>' . "\n";
		$html .= '      <p>' . "\n";
		$html .= '        <abbr title="HyperText Markup Language">HTML</abbr>, ' . "\n";
		$html .= '        <cite>Citation text</cite>, ' . "\n";
		$html .= '        <q>Inline quote</q>, ' . "\n";
		$html .= '        <dfn>Definition term</dfn>, ' . "\n";
		$html .= '        <kbd>Keyboard input</kbd>, ' . "\n";
		$html .= '        <samp>Sample output</samp>, ' . "\n";
		$html .= '        <var>Variable</var>, ' . "\n";
		$html .= '        <time datetime="2026-01-26">January 26, 2026</time>' . "\n";
		$html .= '      </p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate lists section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_lists_section(): string {
		$html = '  <section id="lists">' . "\n";
		$html .= '    <h2>Lists</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Unordered List (ul):</div>' . "\n";
		$html .= '      <ul class="list-primary">' . "\n";
		$html .= '        <li>First list item</li>' . "\n";
		$html .= '        <li>Second list item with <strong>bold</strong> text</li>' . "\n";
		$html .= '        <li class="special" id="item-3">Third item with attributes</li>' . "\n";
		$html .= '        <li>Fourth item with <a href="#test">link</a> and <em>emphasis</em></li>' . "\n";
		$html .= '      </ul>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Ordered List (ol):</div>' . "\n";
		$html .= '      <ol class="numbered-list" start="1">' . "\n";
		$html .= '        <li>First numbered item</li>' . "\n";
		$html .= '        <li>Second numbered item</li>' . "\n";
		$html .= '        <li>Third numbered item</li>' . "\n";
		$html .= '      </ol>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Nested Lists:</div>' . "\n";
		$html .= '      <ul>' . "\n";
		$html .= '        <li>Parent item 1' . "\n";
		$html .= '          <ul>' . "\n";
		$html .= '            <li>Nested item 1.1</li>' . "\n";
		$html .= '            <li>Nested item 1.2</li>' . "\n";
		$html .= '          </ul>' . "\n";
		$html .= '        </li>' . "\n";
		$html .= '        <li>Parent item 2</li>' . "\n";
		$html .= '      </ul>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Definition List (dl):</div>' . "\n";
		$html .= '      <dl class="definition-list">' . "\n";
		$html .= '        <dt>Term 1</dt>' . "\n";
		$html .= '        <dd>Definition for term 1</dd>' . "\n";
		$html .= '        <dt>Term 2</dt>' . "\n";
		$html .= '        <dd>Definition for term 2 with <strong>formatting</strong></dd>' . "\n";
		$html .= '      </dl>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate links section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_links_section(): string {
		$html = '  <section id="links">' . "\n";
		$html .= '    <h2>Links</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Various Link Types:</div>' . "\n";
		$html .= '      <p>' . "\n";
		$html .= '        <a href="https://example.com">External link</a>, ' . "\n";
		$html .= '        <a href="/internal-page">Internal link</a>, ' . "\n";
		$html .= '        <a href="#section">Anchor link</a>, ' . "\n";
		$html .= '        <a href="mailto:test@example.com">Email link</a>, ' . "\n";
		$html .= '        <a href="tel:+1234567890">Phone link</a>' . "\n";
		$html .= '      </p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Links with Attributes:</div>' . "\n";
		$html .= '      <p>' . "\n";
		$html .= '        <a href="#test" class="button" id="link-1">Link with class and ID</a>, ' . "\n";
		$html .= '        <a href="#test" target="_blank" rel="noopener">Link with target</a>, ' . "\n";
		$html .= '        <a href="#test" title="Tooltip text">Link with title</a>' . "\n";
		$html .= '      </p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate images section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_images_section(): string {
		$html = '  <section id="images">' . "\n";
		$html .= '    <h2>Images</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Basic Image:</div>' . "\n";
		$html .= '      <img src="https://via.placeholder.com/300x200" alt="Placeholder image" />' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Image with Attributes:</div>' . "\n";
		$html .= '      <img src="https://via.placeholder.com/400x300" alt="Image with attributes" width="400" height="300" class="featured-image" id="img-1" loading="lazy" />' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Figure with Image:</div>' . "\n";
		$html .= '      <figure class="image-figure">' . "\n";
		$html .= '        <img src="https://via.placeholder.com/500x300" alt="Figure image" />' . "\n";
		$html .= '        <figcaption>Caption for the image</figcaption>' . "\n";
		$html .= '      </figure>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate tables section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_tables_section(): string {
		$html = '  <section id="tables">' . "\n";
		$html .= '    <h2>Tables</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Simple Table:</div>' . "\n";
		$html .= '      <table class="simple-table">' . "\n";
		$html .= '        <tr>' . "\n";
		$html .= '          <th>Header 1</th>' . "\n";
		$html .= '          <th>Header 2</th>' . "\n";
		$html .= '          <th>Header 3</th>' . "\n";
		$html .= '        </tr>' . "\n";
		$html .= '        <tr>' . "\n";
		$html .= '          <td>Cell 1</td>' . "\n";
		$html .= '          <td>Cell 2</td>' . "\n";
		$html .= '          <td>Cell 3</td>' . "\n";
		$html .= '        </tr>' . "\n";
		$html .= '      </table>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Complex Table with thead, tbody, tfoot:</div>' . "\n";
		$html .= '      <table class="complex-table" id="data-table">' . "\n";
		$html .= '        <caption>Table Caption</caption>' . "\n";
		$html .= '        <thead>' . "\n";
		$html .= '          <tr>' . "\n";
		$html .= '            <th>Name</th>' . "\n";
		$html .= '            <th>Age</th>' . "\n";
		$html .= '            <th>City</th>' . "\n";
		$html .= '          </tr>' . "\n";
		$html .= '        </thead>' . "\n";
		$html .= '        <tbody>' . "\n";
		$html .= '          <tr>' . "\n";
		$html .= '            <td>John</td>' . "\n";
		$html .= '            <td>30</td>' . "\n";
		$html .= '            <td>New York</td>' . "\n";
		$html .= '          </tr>' . "\n";
		$html .= '          <tr>' . "\n";
		$html .= '            <td>Jane</td>' . "\n";
		$html .= '            <td>25</td>' . "\n";
		$html .= '            <td>London</td>' . "\n";
		$html .= '          </tr>' . "\n";
		$html .= '        </tbody>' . "\n";
		$html .= '        <tfoot>' . "\n";
		$html .= '          <tr>' . "\n";
		$html .= '            <td colspan="3">Total: 2 people</td>' . "\n";
		$html .= '          </tr>' . "\n";
		$html .= '        </tfoot>' . "\n";
		$html .= '      </table>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate forms section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_forms_section(): string {
		$html = '  <section id="forms">' . "\n";
		$html .= '    <h2>Forms</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Form with Various Input Types:</div>' . "\n";
		$html .= '      <form class="test-form" method="post" action="#">' . "\n";
		$html .= '        <fieldset>' . "\n";
		$html .= '          <legend>Form Fields</legend>' . "\n";
		$html .= '          <label for="text-input">Text Input:</label>' . "\n";
		$html .= '          <input type="text" id="text-input" name="text" value="Sample text" />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="email-input">Email Input:</label>' . "\n";
		$html .= '          <input type="email" id="email-input" name="email" value="test@example.com" />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="password-input">Password Input:</label>' . "\n";
		$html .= '          <input type="password" id="password-input" name="password" />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="number-input">Number Input:</label>' . "\n";
		$html .= '          <input type="number" id="number-input" name="number" value="42" />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="date-input">Date Input:</label>' . "\n";
		$html .= '          <input type="date" id="date-input" name="date" />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="checkbox-input">Checkbox:</label>' . "\n";
		$html .= '          <input type="checkbox" id="checkbox-input" name="checkbox" checked />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="radio-1">Radio 1:</label>' . "\n";
		$html .= '          <input type="radio" id="radio-1" name="radio" value="1" checked />' . "\n";
		$html .= '          <label for="radio-2">Radio 2:</label>' . "\n";
		$html .= '          <input type="radio" id="radio-2" name="radio" value="2" />' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="textarea-input">Textarea:</label>' . "\n";
		$html .= '          <textarea id="textarea-input" name="textarea" rows="4" cols="50">Sample textarea content</textarea>' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <label for="select-input">Select:</label>' . "\n";
		$html .= '          <select id="select-input" name="select">' . "\n";
		$html .= '            <option value="1">Option 1</option>' . "\n";
		$html .= '            <option value="2" selected>Option 2</option>' . "\n";
		$html .= '            <option value="3">Option 3</option>' . "\n";
		$html .= '          </select>' . "\n";
		$html .= '          <br />' . "\n";
		$html .= '          <button type="submit">Submit Button</button>' . "\n";
		$html .= '        </fieldset>' . "\n";
		$html .= '      </form>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate semantic HTML5 section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_semantic_html5_section(): string {
		$html = '  <section id="semantic-html5">' . "\n";
		$html .= '    <h2>Semantic HTML5 Elements</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Article Element:</div>' . "\n";
		$html .= '      <article class="article-content">' . "\n";
		$html .= '        <h3>Article Title</h3>' . "\n";
		$html .= '        <p>Article content goes here.</p>' . "\n";
		$html .= '      </article>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Section Element:</div>' . "\n";
		$html .= '      <section class="content-section">' . "\n";
		$html .= '        <h3>Section Title</h3>' . "\n";
		$html .= '        <p>Section content.</p>' . "\n";
		$html .= '      </section>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Nav Element:</div>' . "\n";
		$html .= '      <nav class="navigation">' . "\n";
		$html .= '        <a href="#home">Home</a> | ' . "\n";
		$html .= '        <a href="#about">About</a> | ' . "\n";
		$html .= '        <a href="#contact">Contact</a>' . "\n";
		$html .= '      </nav>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Aside Element:</div>' . "\n";
		$html .= '      <aside class="sidebar">' . "\n";
		$html .= '        <p>Sidebar content goes here.</p>' . "\n";
		$html .= '      </aside>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Main Element:</div>' . "\n";
		$html .= '      <main class="main-content">' . "\n";
		$html .= '        <p>Main content area.</p>' . "\n";
		$html .= '      </main>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate media section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_media_section(): string {
		$html = '  <section id="media">' . "\n";
		$html .= '    <h2>Media Elements</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Audio Element:</div>' . "\n";
		$html .= '      <audio controls class="audio-player">' . "\n";
		$html .= '        <source src="audio.mp3" type="audio/mpeg" />' . "\n";
		$html .= '        Your browser does not support audio.' . "\n";
		$html .= '      </audio>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Video Element:</div>' . "\n";
		$html .= '      <video controls width="400" height="300" class="video-player">' . "\n";
		$html .= '        <source src="video.mp4" type="video/mp4" />' . "\n";
		$html .= '        Your browser does not support video.' . "\n";
		$html .= '      </video>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">SVG Element:</div>' . "\n";
		$html .= '      <svg width="100" height="100" class="svg-icon">' . "\n";
		$html .= '        <circle cx="50" cy="50" r="40" stroke="black" stroke-width="2" fill="red" />' . "\n";
		$html .= '      </svg>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Canvas Element:</div>' . "\n";
		$html .= '      <canvas id="test-canvas" width="200" height="100" class="canvas-element"></canvas>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate code section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_code_section(): string {
		$html = '  <section id="code">' . "\n";
		$html .= '    <h2>Code Elements</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Inline Code:</div>' . "\n";
		$html .= '      <p>This paragraph contains <code class="inline-code">inline code</code> elements.</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Block-level Code:</div>' . "\n";
		$html .= '      <code class="block-code" style="display: block; padding: 10px; background: #f0f0f0;">' . "\n";
		$html .= 'function example() {' . "\n";
		$html .= '  return "Hello World";' . "\n";
		$html .= '}' . "\n";
		$html .= '      </code>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Preformatted Text:</div>' . "\n";
		$html .= '      <pre class="code-block">' . "\n";
		$html .= 'function example() {' . "\n";
		$html .= '    console.log("Hello World");' . "\n";
		$html .= '    return true;' . "\n";
		$html .= '}' . "\n";
		$html .= '      </pre>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Pre with Code:</div>' . "\n";
		$html .= '      <pre><code class="language-php">' . "\n";
		$html .= '&lt;?php' . "\n";
		$html .= 'echo "Hello World";' . "\n";
		$html .= '?&gt;' . "\n";
		$html .= '      </code></pre>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate quotes section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_quotes_section(): string {
		$html = '  <section id="quotes">' . "\n";
		$html .= '    <h2>Quotes</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Blockquote:</div>' . "\n";
		$html .= '      <blockquote class="quote-block" cite="https://example.com">' . "\n";
		$html .= '        <p>This is a blockquote with citation.</p>' . "\n";
		$html .= '        <cite>— Author Name</cite>' . "\n";
		$html .= '      </blockquote>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Inline Quote:</div>' . "\n";
		$html .= '      <p>As the saying goes, <q cite="https://example.com">Practice makes perfect</q>.</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate block elements section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_block_elements_section(): string {
		$html = '  <section id="block-elements">' . "\n";
		$html .= '    <h2>Block Elements</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Div Elements:</div>' . "\n";
		$html .= '      <div class="container" id="main-container">' . "\n";
		$html .= '        <div class="inner-div">Inner div content</div>' . "\n";
		$html .= '      </div>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Address Element:</div>' . "\n";
		$html .= '      <address class="contact-info">' . "\n";
		$html .= '        123 Main Street<br />' . "\n";
		$html .= '        City, State 12345' . "\n";
		$html .= '      </address>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Horizontal Rule:</div>' . "\n";
		$html .= '      <hr class="divider" />' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Line Break:</div>' . "\n";
		$html .= '      <p>Line 1<br />Line 2<br class="custom-break" />Line 3</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}

	/**
	 * Generate edge cases section.
	 *
	 * @return string HTML section.
	 */
	private static function generate_edge_cases_section(): string {
		$html = '  <section id="edge-cases">' . "\n";
		$html .= '    <h2>Edge Cases</h2>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Empty Elements:</div>' . "\n";
		$html .= '      <p></p>' . "\n";
		$html .= '      <div class="empty-div"></div>' . "\n";
		$html .= '      <ul><li></li></ul>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Elements with Only Whitespace:</div>' . "\n";
		$html .= '      <p>   </p>' . "\n";
		$html .= '      <div>   </div>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Deeply Nested Structures:</div>' . "\n";
		$html .= '      <div>' . "\n";
		$html .= '        <div>' . "\n";
		$html .= '          <div>' . "\n";
		$html .= '            <p>Deeply nested paragraph</p>' . "\n";
		$html .= '          </div>' . "\n";
		$html .= '        </div>' . "\n";
		$html .= '      </div>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Mixed Content (Text + Elements):</div>' . "\n";
		$html .= '      <p>Text before <strong>bold</strong> text in middle <em>italic</em> text after.</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '    <div class="element-group">' . "\n";
		$html .= '      <div class="element-label">Special Characters and Entities:</div>' . "\n";
		$html .= '      <p>&amp; &lt; &gt; &quot; &apos; &copy; &reg; &trade; &nbsp; &mdash; &ndash;</p>' . "\n";
		$html .= '    </div>' . "\n";
		$html .= '  </section>' . "\n";
		return $html;
	}
}
