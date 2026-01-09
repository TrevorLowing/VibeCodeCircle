<?php
/**
 * Tests for RollbackService
 *
 * @package VibeCode\Deploy\Tests
 */

use VibeCode\Deploy\Services\RollbackService;
use VibeCode\Deploy\Services\ManifestService;
use VibeCode\Deploy\Importer;

class Test_Rollback_Service extends WP_UnitTestCase {

	/**
	 * Test project slug
	 */
	private $project_slug = 'test_project';

	/**
	 * Set up test fixtures
	 */
	public function setUp(): void {
		parent::setUp();
		$this->project_slug = 'test_project';
	}

	/**
	 * Clean up after tests
	 */
	public function tearDown(): void {
		// Clean up test data
		$posts = get_posts( array(
			'post_type' => 'any',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => Importer::META_PROJECT_SLUG,
					'value' => $this->project_slug,
					'compare' => '=',
				),
			),
		) );
		foreach ( $posts as $post ) {
			wp_delete_post( $post->ID, true );
		}
		parent::tearDown();
	}

	/**
	 * Test restore_post_snapshot with missing post (should return skippable error)
	 */
	public function test_restore_post_snapshot_missing_post(): void {
		$non_existent_id = 99999;
		$before = array(
			'post_content' => 'Test content',
			'post_title' => 'Test Title',
			'post_status' => 'publish',
		);
		$before_meta = array();

		// Use reflection to call private method
		$reflection = new ReflectionClass( RollbackService::class );
		$method = $reflection->getMethod( 'restore_post_snapshot' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, array( $non_existent_id, $before, $before_meta ) );

		$this->assertFalse( $result['success'] );
		$this->assertTrue( $result['skippable'] );
		$this->assertEquals( 'post_not_found', $result['error_code'] );
		$this->assertStringContainsString( 'does not exist', $result['error'] );
	}

	/**
	 * Test restore_post_snapshot with existing post (should succeed)
	 */
	public function test_restore_post_snapshot_existing_post(): void {
		// Create a test post
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Original Title',
			'post_content' => 'Original content',
			'post_status' => 'publish',
		) );

		// Add project meta
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $this->project_slug );

		$before = array(
			'post_content' => 'Restored content',
			'post_title' => 'Restored Title',
			'post_status' => 'publish',
		);
		$before_meta = array(
			Importer::META_PROJECT_SLUG => $this->project_slug,
		);

		// Use reflection to call private method
		$reflection = new ReflectionClass( RollbackService::class );
		$method = $reflection->getMethod( 'restore_post_snapshot' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, array( $post_id, $before, $before_meta ) );

		$this->assertTrue( $result['success'] );

		// Verify post was restored
		$restored_post = get_post( $post_id );
		$this->assertEquals( 'Restored Title', $restored_post->post_title );
		$this->assertEquals( 'Restored content', $restored_post->post_content );
	}

	/**
	 * Test rollback_deploy separates warnings from errors
	 */
	public function test_rollback_deploy_separates_warnings_from_errors(): void {
		// Create a manifest with missing posts (should be warnings)
		$fingerprint = 'test-' . time();
		$manifest = array(
			'created_pages' => array(),
			'created_template_parts' => array(),
			'created_templates' => array(),
			'updated_pages' => array(
				array(
					'post_id' => 99999, // Non-existent post
					'before' => array(
						'post_content' => 'Test',
						'post_title' => 'Test',
						'post_status' => 'publish',
					),
					'before_meta' => array(),
				),
			),
			'updated_template_parts' => array(),
			'updated_templates' => array(),
			'front_before' => array(),
			'active_before' => '',
		);

		// Save manifest
		$manifest_dir = wp_upload_dir()['basedir'] . '/vibecode-deploy/manifests/' . $this->project_slug;
		if ( ! is_dir( $manifest_dir ) ) {
			wp_mkdir_p( $manifest_dir );
		}
		file_put_contents( $manifest_dir . '/' . $fingerprint . '.json', wp_json_encode( $manifest ) );

		// Run rollback
		$result = RollbackService::rollback_deploy( $this->project_slug, $fingerprint );

		// Should have 0 errors (missing post is skippable)
		$this->assertEquals( 0, $result['errors'] );
		$this->assertTrue( $result['ok'] );

		// Should have warnings
		$this->assertNotEmpty( $result['warnings'] );
		$this->assertGreaterThan( 0, count( $result['warnings'] ) );

		// Should have no actual errors
		$this->assertEmpty( $result['actual_errors'] );

		// All messages should be in error_messages
		$this->assertNotEmpty( $result['error_messages'] );
		$this->assertGreaterThan( 0, count( $result['error_messages'] ) );

		// Clean up manifest
		@unlink( $manifest_dir . '/' . $fingerprint . '.json' );
	}

	/**
	 * Test rollback_deploy with actual error (post exists but restore fails)
	 */
	public function test_rollback_deploy_with_actual_error(): void {
		// Create a post that exists but can't be restored (invalid data)
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test',
			'post_content' => 'Test',
			'post_status' => 'publish',
		) );

		// Add project meta
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $this->project_slug );

		$fingerprint = 'test-' . time();
		$manifest = array(
			'created_pages' => array(),
			'created_template_parts' => array(),
			'created_templates' => array(),
			'updated_pages' => array(
				array(
					'post_id' => $post_id,
					'before' => array(
						'post_content' => 'Test',
						'post_title' => 'Test',
						'post_status' => 'invalid_status', // Invalid status should cause error
					),
					'before_meta' => array(),
				),
			),
			'updated_template_parts' => array(),
			'updated_templates' => array(),
			'front_before' => array(),
			'active_before' => '',
		);

		// Save manifest
		$manifest_dir = wp_upload_dir()['basedir'] . '/vibecode-deploy/manifests/' . $this->project_slug;
		if ( ! is_dir( $manifest_dir ) ) {
			wp_mkdir_p( $manifest_dir );
		}
		file_put_contents( $manifest_dir . '/' . $fingerprint . '.json', wp_json_encode( $manifest ) );

		// Run rollback
		$result = RollbackService::rollback_deploy( $this->project_slug, $fingerprint );

		// Should have errors (invalid status)
		$this->assertGreaterThan( 0, $result['errors'] );
		$this->assertFalse( $result['ok'] );

		// Should have actual errors (not warnings)
		$this->assertNotEmpty( $result['actual_errors'] );
		$this->assertGreaterThan( 0, count( $result['actual_errors'] ) );

		// Clean up manifest
		@unlink( $manifest_dir . '/' . $fingerprint . '.json' );
	}

	/**
	 * Test rollback_selection error counting (should only count actual errors)
	 */
	public function test_rollback_selection_error_counting(): void {
		// Create manifest with missing posts (warnings) and one actual error
		$fingerprint = 'test-' . time();
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test',
			'post_content' => 'Test',
			'post_status' => 'publish',
		) );
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $this->project_slug );

		$manifest = array(
			'created_pages' => array(),
			'created_template_parts' => array(),
			'created_templates' => array(),
			'updated_pages' => array(
				array(
					'post_id' => 99999, // Missing post (warning)
					'before' => array(
						'post_content' => 'Test',
						'post_title' => 'Test',
						'post_status' => 'publish',
					),
					'before_meta' => array(),
				),
				array(
					'post_id' => $post_id, // Existing post with invalid status (error)
					'before' => array(
						'post_content' => 'Test',
						'post_title' => 'Test',
						'post_status' => 'invalid_status',
					),
					'before_meta' => array(),
				),
			),
			'updated_template_parts' => array(),
			'updated_templates' => array(),
			'front_before' => array(),
			'active_before' => '',
		);

		// Save manifest
		$manifest_dir = wp_upload_dir()['basedir'] . '/vibecode-deploy/manifests/' . $this->project_slug;
		if ( ! is_dir( $manifest_dir ) ) {
			wp_mkdir_p( $manifest_dir );
		}
		file_put_contents( $manifest_dir . '/' . $fingerprint . '.json', wp_json_encode( $manifest ) );

		// Run rollback_selection
		$result = RollbackService::rollback_selection( $this->project_slug, $fingerprint, 'everything', array(), array() );

		// Should have warnings
		$this->assertNotEmpty( $result['warnings'] );
		$this->assertGreaterThan( 0, count( $result['warnings'] ) );

		// Should have actual errors
		$this->assertNotEmpty( $result['actual_errors'] );
		$this->assertGreaterThan( 0, count( $result['actual_errors'] ) );

		// All messages should be in errors array
		$this->assertNotEmpty( $result['errors'] );
		$total_messages = count( $result['warnings'] ) + count( $result['actual_errors'] );
		$this->assertEquals( $total_messages, count( $result['errors'] ) );

		// Clean up manifest
		@unlink( $manifest_dir . '/' . $fingerprint . '.json' );
	}

	/**
	 * Test delete_owned_post with non-owned post (should fail)
	 */
	public function test_delete_owned_post_non_owned(): void {
		// Create a post without project meta
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test',
			'post_content' => 'Test',
			'post_status' => 'publish',
		) );

		// Use reflection to call private method
		$reflection = new ReflectionClass( RollbackService::class );
		$method = $reflection->getMethod( 'delete_owned_post' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, array( $post_id, $this->project_slug ) );

		$this->assertFalse( $result );

		// Post should still exist
		$post = get_post( $post_id );
		$this->assertNotNull( $post );
	}

	/**
	 * Test delete_owned_post with owned post (should succeed)
	 */
	public function test_delete_owned_post_owned(): void {
		// Create a post with project meta
		$post_id = $this->factory->post->create( array(
			'post_title' => 'Test',
			'post_content' => 'Test',
			'post_status' => 'publish',
		) );
		update_post_meta( $post_id, Importer::META_PROJECT_SLUG, $this->project_slug );

		// Use reflection to call private method
		$reflection = new ReflectionClass( RollbackService::class );
		$method = $reflection->getMethod( 'delete_owned_post' );
		$method->setAccessible( true );

		$result = $method->invokeArgs( null, array( $post_id, $this->project_slug ) );

		$this->assertTrue( $result );

		// Post should be deleted
		$post = get_post( $post_id );
		$this->assertNull( $post );
	}
}
