<?php
/**
 * Tests for security features (nonces, capability checks, sanitization).
 *
 * @package VibeCode\Deploy\Tests
 */

/**
 * Security test case.
 */
class Test_Security extends WP_UnitTestCase {
	/**
	 * Test that all PHP files have ABSPATH check.
	 */
	public function test_abspath_checks() {
		$plugin_dir = dirname( dirname( __FILE__ ) );
		$php_files = $this->get_php_files( $plugin_dir . '/includes' );
		
		foreach ( $php_files as $file ) {
			$content = file_get_contents( $file );
			$this->assertStringContainsString(
				"defined( 'ABSPATH' )",
				$content,
				"File $file is missing ABSPATH check"
			);
		}
	}

	/**
	 * Test Settings sanitization.
	 */
	public function test_settings_sanitization() {
		$input = array(
			'project_slug' => '<script>alert("xss")</script>',
			'class_prefix' => '../../etc/passwd',
			'placeholder_prefix' => 'DROP TABLE users;',
		);
		
		$sanitized = \VibeCode\Deploy\Settings::sanitize( $input );
		
		// Should sanitize dangerous input
		$this->assertNotContains( '<script>', $sanitized['project_slug'] );
		$this->assertNotContains( '../', $sanitized['class_prefix'] );
		$this->assertNotContains( 'DROP', $sanitized['placeholder_prefix'] );
	}

	/**
	 * Get all PHP files in a directory recursively.
	 *
	 * @param string $dir Directory path.
	 * @return array Array of file paths.
	 */
	private function get_php_files( string $dir ): array {
		$files = array();
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir )
		);
		
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && $file->getExtension() === 'php' ) {
				$files[] = $file->getPathname();
			}
		}
		
		return $files;
	}
}
