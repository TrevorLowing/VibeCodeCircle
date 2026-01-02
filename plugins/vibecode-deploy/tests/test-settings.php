<?php
/**
 * Tests for Settings class.
 *
 * @package VibeCode\Deploy\Tests
 */

use VibeCode\Deploy\Settings;

/**
 * Settings test case.
 */
class Test_Settings extends WP_UnitTestCase {
	/**
	 * Test default settings.
	 */
	public function test_defaults() {
		$defaults = Settings::defaults();
		
		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'project_slug', $defaults );
		$this->assertArrayHasKey( 'class_prefix', $defaults );
		$this->assertArrayHasKey( 'staging_dir', $defaults );
		$this->assertArrayHasKey( 'placeholder_prefix', $defaults );
		$this->assertArrayHasKey( 'env_errors_mode', $defaults );
		$this->assertEquals( 'vibecode-deploy-staging', $defaults['staging_dir'] );
		$this->assertEquals( 'VIBECODE_SHORTCODE', $defaults['placeholder_prefix'] );
		$this->assertEquals( 'warn', $defaults['env_errors_mode'] );
	}

	/**
	 * Test get_all returns defaults when no settings exist.
	 */
	public function test_get_all_returns_defaults() {
		// Clear any existing settings
		delete_option( Settings::OPTION_NAME );
		
		$settings = Settings::get_all();
		
		$this->assertIsArray( $settings );
		$this->assertEquals( Settings::defaults(), $settings );
	}

	/**
	 * Test sanitize function.
	 */
	public function test_sanitize() {
		$input = array(
			'project_slug' => 'test-project',
			'class_prefix' => 'test-prefix-',
			'staging_dir' => 'test-staging',
			'placeholder_prefix' => 'TEST_PREFIX',
			'env_errors_mode' => 'fail',
		);
		
		$sanitized = Settings::sanitize( $input );
		
		$this->assertEquals( 'test-project', $sanitized['project_slug'] );
		$this->assertEquals( 'test-prefix-', $sanitized['class_prefix'] );
		$this->assertEquals( 'test-staging', $sanitized['staging_dir'] );
		$this->assertEquals( 'TEST_PREFIX', $sanitized['placeholder_prefix'] );
		$this->assertEquals( 'fail', $sanitized['env_errors_mode'] );
	}

	/**
	 * Test sanitize with invalid input.
	 */
	public function test_sanitize_invalid() {
		$input = array(
			'project_slug' => 'Invalid Project Slug!',
			'class_prefix' => 'INVALID_PREFIX',
			'env_errors_mode' => 'invalid',
		);
		
		$sanitized = Settings::sanitize( $input );
		
		// Should sanitize to valid values
		$this->assertEquals( 'invalid-project-slug', $sanitized['project_slug'] );
		$this->assertEquals( 'warn', $sanitized['env_errors_mode'] );
	}
}
