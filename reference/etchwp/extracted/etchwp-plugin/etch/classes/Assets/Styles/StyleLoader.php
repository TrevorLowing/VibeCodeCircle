<?php
/**
 * Custom style loader for Etch styles.
 *
 * @package Etch
 * @subpackage Assets
 */

declare(strict_types=1);

namespace Etch\Assets\Styles;

use Etch\Helpers\Flag;

/**
 * Class CustomStyleLoader
 *
 * Handles loading of custom CSS files.
 */
class StyleLoader {
	/**
	 * CSS files to load.
	 *
	 * @var array<string>
	 */
	private array $css_files = array(
		'etch-defaults.css',
		/* 'etch-props.css', */
		'etch-reset.css',
	);

	/**
	 * Gutenberg overwrite CSS files to load.
	 *
	 * @var array<string>
	 */
	private array $gutenberg_css_files = array(
		'etch-gutenberg-overwrites.css',
	);

	/**
	 * Directory for CSS files.
	 *
	 * @var string
	 */
	private const CSS_DIR = ETCH_PLUGIN_DIR . 'assets/css/';

	/**
	 * Initialization
	 *
	 * @return void
	 */
	public function init(): void {
		if ( ! is_admin() && isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		} else {
			add_action( 'wp_head', array( $this, 'inline_style' ) );
		}

		if ( is_admin() ) {
			add_action( 'enqueue_block_assets', array( $this, 'enqueue_styles' ) );
			add_action( 'enqueue_block_assets', array( $this, 'enqueue_gutenberg_overwrites' ) );
		}
	}

	/**
	 * Inline custom CSS files.
	 *
	 * @return void
	 */
	public function inline_style(): void {
		foreach ( $this->css_files as $file ) {
			$file_path = self::CSS_DIR . $file;
			if ( ! file_exists( $file_path ) ) {
				continue;
			}

			$css_content = file_get_contents( $file_path );

			if ( ! $css_content ) {
				continue;
			}
			$css_content = str_ireplace( array( '<style>', '</style>' ), '', $css_content );
			?>
			<style id="<?php echo esc_attr( str_replace( '.', '-', $file ) ); ?>-styles">
				<?php echo $css_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</style>
			<?php
		}
	}

	/**
	 * Enqueue custom CSS files.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		foreach ( $this->css_files as $file ) {
			$this->enqueue_style( $file );
		}
	}

	/**
	 * Enqueue Gutenberg overwrite CSS files.
	 *
	 * @return void
	 */
	public function enqueue_gutenberg_overwrites(): void {
		foreach ( $this->gutenberg_css_files as $file ) {
			$this->enqueue_style( $file );
		}
	}

	/**
	 * Enqueue a single CSS file.
	 *
	 * @param string $file_name CSS file name.
	 * @return void
	 */
	private function enqueue_style( string $file_name ): void {
		$file_path = self::CSS_DIR . $file_name;
		$version = file_exists( $file_path ) ? (string) filemtime( $file_path ) : null;

		wp_enqueue_style(
			'etch-' . str_replace( '.css', '', $file_name ),
			ETCH_PLUGIN_URL . 'assets/css/' . $file_name,
			array(),
			$version
		);
	}
}
