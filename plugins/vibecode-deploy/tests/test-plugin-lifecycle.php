<?php
/**
 * Tests for plugin lifecycle hooks (activation, deactivation, uninstall).
 *
 * @package VibeCode\Deploy\Tests
 */

/**
 * Plugin lifecycle test case.
 */
class Test_Plugin_Lifecycle extends WP_UnitTestCase {
	/**
	 * Test activation hook sets default options.
	 */
	public function test_activation_sets_defaults() {
		// Clear any existing options
		delete_option( 'vibecode_deploy_version' );
		delete_option( \VibeCode\Deploy\Settings::OPTION_NAME );
		
		// Run activation hook
		vibecode_deploy_activate();
		
		// Check version option was set
		$version = get_option( 'vibecode_deploy_version' );
		$this->assertNotEmpty( $version );
		$this->assertEquals( VIBECODE_DEPLOY_PLUGIN_VERSION, $version );
		
		// Check settings option was set
		$settings = get_option( \VibeCode\Deploy\Settings::OPTION_NAME );
		$this->assertIsArray( $settings );
		$this->assertEquals( \VibeCode\Deploy\Settings::defaults(), $settings );
	}

	/**
	 * Test activation doesn't overwrite existing settings.
	 */
	public function test_activation_preserves_existing_settings() {
		// Set existing settings
		$existing_settings = array(
			'project_slug' => 'existing-project',
			'class_prefix' => 'existing-',
		);
		update_option( \VibeCode\Deploy\Settings::OPTION_NAME, $existing_settings );
		
		// Run activation hook
		vibecode_deploy_activate();
		
		// Settings should be preserved
		$settings = get_option( \VibeCode\Deploy\Settings::OPTION_NAME );
		$this->assertEquals( 'existing-project', $settings['project_slug'] );
		$this->assertEquals( 'existing-', $settings['class_prefix'] );
	}

	/**
	 * Test deactivation hook.
	 */
	public function test_deactivation() {
		// Run deactivation hook
		vibecode_deploy_deactivate();
		
		// Should not throw errors
		$this->assertTrue( true );
	}
}
