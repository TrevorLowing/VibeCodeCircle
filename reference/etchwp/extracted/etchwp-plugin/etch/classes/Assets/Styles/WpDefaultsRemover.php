<?php
/**
 * WordPress Defaults Remover.
 *
 * @package Etch
 * @subpackage Assets
 */

declare(strict_types=1);

namespace Etch\Assets\Styles;

use Etch\Helpers\Flag;
use Etch\Helpers\Logger;

/**
 * Class WpDefaultsRemover
 *
 * Handles removal of WordPress default CSS and scripts styles.
 */
class WpDefaultsRemover {

	/**
	 * Initialize the CSS remover.
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! Flag::is_on( 'REMOVE_WP_DEFAULT_CSS' ) ) {
			return;
		}

		$this->remove_emoji_support();
		$this->remove_wp_default_css();
	}

	/**
	 * Remove emoji support.
	 *
	 * @return void
	 */
	private function remove_emoji_support(): void {
		$this->remove_emoji_styles();
		$this->remove_emoji_scripts();
	}

	/**
	 * Remove emoji styles.
	 *
	 * @return void
	 */
	public function remove_emoji_styles(): void {
		add_action( 'wp_enqueue_scripts', fn() => wp_dequeue_style( 'wp-emoji-release' ), 20 );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Remove emoji script.
	 *
	 * @return void
	 */
	public function remove_emoji_scripts(): void {
		add_action( 'wp_enqueue_scripts', fn() => wp_dequeue_script( 'wp-emoji' ) );
		add_action( 'admin_enqueue_scripts', fn() => wp_dequeue_script( 'wp-emoji' ), 20 );
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_tinymce' ) );
		add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_remove_dns_prefetch' ), 10, 2 );
		add_filter( 'option_use_smilies', '__return_false' );
	}

	   /**
		* Filter function used to remove the tinymce emoji plugin.
		*
		* @param array<int, string> $plugins List of TinyMCE plugins.
		* @return array<int, string> Difference between the two arrays
		*/
	public function disable_emojis_tinymce( array $plugins ): array {
		return array_diff( $plugins, array( 'wpemoji' ) );
	}

	   /**
		* Remove emoji CDN hostname from DNS prefetching hints.
		*
		* @param array<int, string> $urls URLs to print for resource hints.
		* @param string             $relation_type The relation type the URLs are printed for.
		* @return array<int, string> Difference between the two arrays.
		*/
	public function disable_emojis_remove_dns_prefetch( array $urls, string $relation_type ): array {
		if ( 'dns-prefetch' === $relation_type ) {
			/** This filter is documented in wp-includes/formatting.php */
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' );
			$urls = array_diff( $urls, array( $emoji_svg_url ) );
		}
		return $urls;
	}

	/**
	 * Remove WordPress default CSS styles.
	 *
	 * @return void
	 */
	private function remove_wp_default_css(): void {
		// Use output buffering to remove specific inline styles
		add_action( 'wp_head', array( $this, 'start_output_buffering' ), 1 );
		add_action( 'wp_footer', array( $this, 'end_output_buffering' ), 999 );
	}

	/**
	 * Start output buffering to capture and filter HTML.
	 *
	 * @return void
	 */
	public function start_output_buffering(): void {
		ob_start( array( $this, 'filter_output' ) );
	}

	/**
	 * End output buffering.
	 *
	 * @return void
	 */
	public function end_output_buffering(): void {
		if ( ob_get_level() ) {
			ob_end_flush();
		}
	}

	/**
	 * Filter the output to remove specific inline styles.
	 *
	 * @param string $html The HTML output.
	 * @return string
	 */
	public function filter_output( string $html ): string {
		// Remove specific WordPress inline styles
		$patterns = array(
			'/<style[^>]*id=[\'"]wp-emoji-styles-inline-css[\'"][^>]*>.*?<\/style>/s',
			'/<style[^>]*id=[\'"]wp-block-library-inline-css[\'"][^>]*>.*?<\/style>/s',
			'/<style[^>]*id=[\'"]global-styles-inline-css[\'"][^>]*>.*?<\/style>/s',
			'/<style[^>]*id=[\'"]wp-block-template-skip-link-inline-css[\'"][^>]*>.*?<\/style>/s',
		);

		$filtered_html = preg_replace( $patterns, '', $html );

		return $filtered_html ?? $html;
	}
}
