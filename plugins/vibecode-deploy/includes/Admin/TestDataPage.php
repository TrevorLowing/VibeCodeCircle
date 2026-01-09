<?php
/**
 * Test Data Admin Page
 *
 * Provides UI for seeding test data for CPTs.
 *
 * @package VibeCode\Deploy
 */

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Services\TestDataService;
use VibeCode\Deploy\Logger;

/**
 * Test Data Admin Page
 */
class TestDataPage {

	/**
	 * Initialize the admin page.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
	}

	/**
	 * Register admin menu page.
	 *
	 * @return void
	 */
	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			__( 'Test Data', 'vibecode-deploy' ),
			__( 'Test Data', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-test-data',
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Render the test data page.
	 *
	 * @return void
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle rewrite flush
		if ( isset( $_POST['vibecode_deploy_flush_rewrite'] ) && check_admin_referer( 'vibecode_deploy_flush_rewrite', 'vibecode_deploy_flush_rewrite_nonce' ) ) {
			flush_rewrite_rules( false ); // Soft flush
			echo '<div class="notice notice-success is-dismissible"><p>';
			echo esc_html__( 'Rewrite rules flushed successfully!', 'vibecode-deploy' );
			echo '</p></div>';
		}

		// Handle form submission
		if ( isset( $_POST['vibecode_deploy_seed_test_data'] ) && check_admin_referer( 'vibecode_deploy_seed_test_data', 'vibecode_deploy_seed_test_data_nonce' ) ) {
			$selected_cpts = isset( $_POST['selected_cpts'] ) && is_array( $_POST['selected_cpts'] ) ? array_map( 'sanitize_key', $_POST['selected_cpts'] ) : array();
			$results = TestDataService::seed_test_data( $selected_cpts );

			Logger::info(
				'Test data seeded.',
				array(
					'created' => $results['created'],
					'skipped' => $results['skipped'],
					'errors' => $results['errors'],
				)
			);

			// Show success/error messages
			if ( ! empty( $results['created'] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html__( 'Test data created successfully!', 'vibecode-deploy' );
				echo '</p></div>';
			}

			if ( ! empty( $results['errors'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Some errors occurred while creating test data.', 'vibecode-deploy' );
				echo '</p></div>';
			}

			if ( ! empty( $results['skipped'] ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				echo esc_html__( 'Some CPTs were skipped.', 'vibecode-deploy' );
				echo '</p></div>';
			}
		}

		// Get current CPT status
		$cpts = array(
			'advisory',
			'investigation',
			'evidence_record',
			'foia_request',
			'foia_update',
			'survey',
		);

		$cpt_status = array();
		foreach ( $cpts as $cpt ) {
			$exists = post_type_exists( $cpt );
			$counts = $exists ? wp_count_posts( $cpt ) : null;
			$cpt_status[ $cpt ] = array(
				'exists' => $exists,
				'published' => $exists ? (int) $counts->publish : 0,
				'total' => $exists ? (int) $counts->publish + (int) $counts->draft + (int) $counts->pending : 0,
			);
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Test Data', 'vibecode-deploy' ); ?></h1>
			<p><?php echo esc_html__( 'Create example posts for Custom Post Types to help with testing and development.', 'vibecode-deploy' ); ?></p>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Current Status', 'vibecode-deploy' ); ?></h2>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php echo esc_html__( 'CPT', 'vibecode-deploy' ); ?></th>
							<th><?php echo esc_html__( 'Status', 'vibecode-deploy' ); ?></th>
							<th><?php echo esc_html__( 'Published Posts', 'vibecode-deploy' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $cpt_status as $cpt => $status ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $cpt ); ?></strong></td>
								<td>
									<?php if ( $status['exists'] ) : ?>
										<span style="color: green;">✓ Registered</span>
									<?php else : ?>
										<span style="color: red;">✗ Not Registered</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( $status['exists'] ) : ?>
										<?php echo esc_html( $status['published'] ); ?>
										<?php if ( $status['published'] === 0 ) : ?>
											<span style="color: orange;">(no posts)</span>
										<?php endif; ?>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Seed Test Data', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'Select which CPTs to seed with test data. CPTs that already have published posts will be skipped.', 'vibecode-deploy' ); ?></p>

				<form method="post" action="">
					<?php wp_nonce_field( 'vibecode_deploy_seed_test_data', 'vibecode_deploy_seed_test_data_nonce' ); ?>

					<table class="form-table">
						<tbody>
							<?php foreach ( $cpt_status as $cpt => $status ) : ?>
								<?php if ( ! $status['exists'] ) : ?>
									<tr>
										<th scope="row">
											<label>
												<input type="checkbox" name="selected_cpts[]" value="<?php echo esc_attr( $cpt ); ?>" disabled />
												<?php echo esc_html( $cpt ); ?>
											</label>
										</th>
										<td>
											<span style="color: red;">CPT not registered</span>
										</td>
									</tr>
								<?php elseif ( $status['published'] > 0 ) : ?>
									<tr>
										<th scope="row">
											<label>
												<input type="checkbox" name="selected_cpts[]" value="<?php echo esc_attr( $cpt ); ?>" />
												<?php echo esc_html( $cpt ); ?>
											</label>
										</th>
										<td>
											<span style="color: orange;">Will be skipped (already has <?php echo esc_html( $status['published'] ); ?> published posts)</span>
										</td>
									</tr>
								<?php else : ?>
									<tr>
										<th scope="row">
											<label>
												<input type="checkbox" name="selected_cpts[]" value="<?php echo esc_attr( $cpt ); ?>" checked />
												<?php echo esc_html( $cpt ); ?>
											</label>
										</th>
										<td>
											<span style="color: green;">Ready to seed</span>
										</td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>

					<p class="submit">
						<input type="submit" name="vibecode_deploy_seed_test_data" class="button button-primary" value="<?php echo esc_attr__( 'Seed Test Data', 'vibecode-deploy' ); ?>" />
					</p>
				</form>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Fix 404 Errors', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'If CPT single pages are showing 404 errors, flush rewrite rules to rebuild permalink structure.', 'vibecode-deploy' ); ?></p>
				<form method="post" action="" style="margin-top: 10px;">
					<?php wp_nonce_field( 'vibecode_deploy_flush_rewrite', 'vibecode_deploy_flush_rewrite_nonce' ); ?>
					<input type="submit" name="vibecode_deploy_flush_rewrite" class="button button-secondary" value="<?php echo esc_attr__( 'Flush Rewrite Rules', 'vibecode-deploy' ); ?>" />
				</form>
				<p style="margin-top: 10px; color: #666;">
					<strong><?php echo esc_html__( 'Note:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Rewrite rules are automatically flushed after theme deployment. Use this button if you need to manually refresh them.', 'vibecode-deploy' ); ?>
				</p>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'About Test Data', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'Test data includes:', 'vibecode-deploy' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php echo esc_html__( 'Advisory: 3 example advisories with different classifications', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'Investigation: 2 example investigations (if none exist)', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'Evidence Record: 3 example evidence records', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'FOIA Request: 2 example FOIA requests (if none exist)', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'FOIA Update: 2 example updates (if none exist)', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'Survey: 2 example surveys with active dates', 'vibecode-deploy' ); ?></li>
				</ul>
				<p><strong><?php echo esc_html__( 'Note:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'CPTs that already have published posts will be skipped to avoid creating duplicate test data.', 'vibecode-deploy' ); ?></p>
			</div>
		</div>
		<?php
	}
}
