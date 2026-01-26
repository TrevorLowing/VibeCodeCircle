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
use VibeCode\Deploy\Services\HtmlTestPageService;
use VibeCode\Deploy\Services\HtmlTestPageAuditService;
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

		// Handle HTML test page generation/download
		if ( isset( $_POST['generate_html_test_page'] ) && check_admin_referer( 'vibecode_deploy_generate_html_test_page', 'vibecode_deploy_generate_html_test_page_nonce' ) ) {
			$action = isset( $_POST['test_page_action'] ) ? sanitize_text_field( (string) $_POST['test_page_action'] ) : 'download';
			
			if ( $action === 'download' ) {
				// Generate and download HTML file
				$html_content = HtmlTestPageService::generate_test_page_html();
				
				header( 'Content-Type: text/html; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="html-test-page.html"' );
				header( 'Content-Length: ' . strlen( $html_content ) );
				
				echo $html_content;
				exit;
			} elseif ( $action === 'deploy' ) {
				// Deploy to WordPress
				$page_id = HtmlTestPageService::deploy_test_page_to_wordpress();
				
				if ( $page_id && is_numeric( $page_id ) ) {
					$page_url = get_permalink( $page_id );
					echo '<div class="notice notice-success is-dismissible"><p>';
					echo esc_html__( 'HTML test page deployed successfully!', 'vibecode-deploy' );
					echo ' <a href="' . esc_url( $page_url ) . '" target="_blank">' . esc_html__( 'View Page', 'vibecode-deploy' ) . '</a>';
					echo ' | <a href="' . esc_url( admin_url( 'post.php?post=' . $page_id . '&action=edit' ) ) . '">' . esc_html__( 'Edit Page', 'vibecode-deploy' ) . '</a>';
					echo '</p></div>';
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>';
					echo esc_html__( 'Failed to deploy HTML test page. Please check logs for details.', 'vibecode-deploy' );
					echo '</p></div>';
				}
			}
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

			// Show success/error messages with details
			if ( ! empty( $results['created'] ) ) {
				$total_created = 0;
				foreach ( $results['created'] as $cpt => $post_ids ) {
					$total_created += count( $post_ids );
				}
				echo '<div class="notice notice-success is-dismissible"><p>';
				echo esc_html( sprintf( 
					__( 'Test data created successfully! Created %d posts across %d CPT(s).', 'vibecode-deploy' ),
					$total_created,
					count( $results['created'] )
				) );
				echo '</p></div>';
			}

			if ( ! empty( $results['errors'] ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>';
				echo esc_html__( 'Some errors occurred while creating test data:', 'vibecode-deploy' );
				echo '<ul style="margin-left: 20px;">';
				foreach ( $results['errors'] as $error ) {
					$cpt = isset( $error['cpt'] ) ? $error['cpt'] : 'unknown';
					$msg = isset( $error['error'] ) ? $error['error'] : 'Unknown error';
					echo '<li><strong>' . esc_html( $cpt ) . ':</strong> ' . esc_html( $msg ) . '</li>';
				}
				echo '</ul></p></div>';
			}

			if ( ! empty( $results['skipped'] ) ) {
				echo '<div class="notice notice-warning is-dismissible"><p>';
				echo esc_html__( 'Some CPTs were skipped:', 'vibecode-deploy' );
				echo '<ul style="margin-left: 20px;">';
				foreach ( $results['skipped'] as $skip ) {
					$cpt = isset( $skip['cpt'] ) ? $skip['cpt'] : 'unknown';
					$reason = isset( $skip['reason'] ) ? $skip['reason'] : 'Unknown reason';
					echo '<li><strong>' . esc_html( $cpt ) . ':</strong> ' . esc_html( $reason ) . '</li>';
				}
				echo '</ul></p></div>';
			}

			// Show message if nothing was created and no errors
			if ( empty( $results['created'] ) && empty( $results['errors'] ) && ! empty( $results['skipped'] ) ) {
				echo '<div class="notice notice-info is-dismissible"><p>';
				echo esc_html__( 'No test data was created. All selected CPTs were skipped (they may already have published posts or are not registered).', 'vibecode-deploy' );
				echo '</p></div>';
			}
		}

		// Handle audit report generation
		if ( isset( $_POST['generate_audit_report'] ) && check_admin_referer( 'vibecode_deploy_generate_audit_report', 'vibecode_deploy_generate_audit_report_nonce' ) ) {
			$audit_source = isset( $_POST['audit_source'] ) ? sanitize_text_field( (string) $_POST['audit_source'] ) : 'wordpress';
			
			if ( $audit_source === 'wordpress' ) {
				// Analyze deployed WordPress page
				$page_slug = 'html-test-page';
				$page = get_page_by_path( $page_slug );
				
				if ( ! $page ) {
					echo '<div class="notice notice-error is-dismissible"><p>';
					echo esc_html__( 'HTML test page not found. Please deploy the test page first.', 'vibecode-deploy' );
					echo '</p></div>';
				} else {
					$audit_results = HtmlTestPageAuditService::analyze_wordpress_page( $page->ID );
					
					if ( isset( $audit_results['error'] ) ) {
						echo '<div class="notice notice-error is-dismissible"><p>';
						echo esc_html( $audit_results['error'] );
						echo '</p></div>';
					} else {
						// Generate markdown report
						$report = HtmlTestPageAuditService::generate_markdown_report( $audit_results );
						
						// Download as file
						$filename = 'html-test-page-audit-report-' . date( 'Y-m-d-His' ) . '.md';
						header( 'Content-Type: text/markdown; charset=utf-8' );
						header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
						header( 'Content-Length: ' . strlen( $report ) );
						
						echo $report;
						exit;
					}
				}
			} elseif ( $audit_source === 'html_file' ) {
				// Analyze from uploaded HTML file
				if ( isset( $_FILES['html_file'] ) && $_FILES['html_file']['error'] === UPLOAD_ERR_OK ) {
					$html_content = file_get_contents( $_FILES['html_file']['tmp_name'] );
					
					if ( $html_content ) {
						$audit_results = HtmlTestPageAuditService::analyze_test_page( $html_content, 'html_file' );
						$report = HtmlTestPageAuditService::generate_markdown_report( $audit_results );
						
						// Download as file
						$filename = 'html-test-page-audit-report-' . date( 'Y-m-d-His' ) . '.md';
						header( 'Content-Type: text/markdown; charset=utf-8' );
						header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
						header( 'Content-Length: ' . strlen( $report ) );
						
						echo $report;
						exit;
					} else {
						echo '<div class="notice notice-error is-dismissible"><p>';
						echo esc_html__( 'Failed to read uploaded HTML file.', 'vibecode-deploy' );
						echo '</p></div>';
					}
				} else {
					echo '<div class="notice notice-error is-dismissible"><p>';
					echo esc_html__( 'Please select an HTML file to analyze.', 'vibecode-deploy' );
					echo '</p></div>';
				}
			}
		}

		// Get all registered custom post types (exclude built-in types)
		$all_cpts = get_post_types( array(
			'public' => true,
			'_builtin' => false,
		), 'names' );

		// Also include non-public CPTs that have show_ui enabled
		$non_public_cpts = get_post_types( array(
			'public' => false,
			'show_ui' => true,
			'_builtin' => false,
		), 'names' );

		$all_cpts = array_merge( $all_cpts, $non_public_cpts );
		$all_cpts = array_unique( $all_cpts );

		// Sort alphabetically for consistent display
		sort( $all_cpts );

		$cpt_status = array();
		foreach ( $all_cpts as $cpt ) {
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
				<h2><?php echo esc_html__( 'HTML Test Page', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'Generate a comprehensive HTML test page covering all HTML4 and HTML5 elements for testing block conversion accuracy and EtchWP IDE editability.', 'vibecode-deploy' ); ?></p>
				
				<form method="post" action="">
					<?php wp_nonce_field( 'vibecode_deploy_generate_html_test_page', 'vibecode_deploy_generate_html_test_page_nonce' ); ?>
					
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label>
										<input type="radio" name="test_page_action" value="download" checked />
										<?php echo esc_html__( 'Generate HTML File (Download)', 'vibecode-deploy' ); ?>
									</label>
								</th>
								<td>
									<?php echo esc_html__( 'Download a standalone HTML file for local testing.', 'vibecode-deploy' ); ?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label>
										<input type="radio" name="test_page_action" value="deploy" />
										<?php echo esc_html__( 'Deploy to WordPress (Create Page)', 'vibecode-deploy' ); ?>
									</label>
								</th>
								<td>
									<?php echo esc_html__( 'Create a WordPress page with the test content. The page will be converted to Gutenberg blocks automatically.', 'vibecode-deploy' ); ?>
								</td>
							</tr>
						</tbody>
					</table>
					
					<p class="submit">
						<input type="submit" name="generate_html_test_page" class="button button-primary" value="<?php echo esc_attr__( 'Generate Test Page', 'vibecode-deploy' ); ?>" />
					</p>
				</form>
				
				<div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 4px;">
					<h3><?php echo esc_html__( 'What\'s Included:', 'vibecode-deploy' ); ?></h3>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php echo esc_html__( 'All HTML4 elements (headings, lists, tables, forms, etc.)', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'All HTML5 elements (semantic, media, interactive)', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Various attributes and use cases', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Edge cases (nested structures, empty elements, etc.)', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Organized sections for easy navigation', 'vibecode-deploy' ); ?></li>
					</ul>
					<p style="margin-top: 10px;"><strong><?php echo esc_html__( 'Use Cases:', 'vibecode-deploy' ); ?></strong></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php echo esc_html__( 'Test block conversion accuracy', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Verify etchData on all block types', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Test EtchWP IDE editability', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Regression testing', 'vibecode-deploy' ); ?></li>
					</ul>
				</div>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'Generate Audit Report', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'Analyze the test page and generate a compliance audit report showing block conversion accuracy, etchData compliance, and EtchWP IDE editability.', 'vibecode-deploy' ); ?></p>
				
				<form method="post" action="" enctype="multipart/form-data">
					<?php wp_nonce_field( 'vibecode_deploy_generate_audit_report', 'vibecode_deploy_generate_audit_report_nonce' ); ?>
					
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row">
									<label>
										<input type="radio" name="audit_source" value="wordpress" checked />
										<?php echo esc_html__( 'Analyze Deployed WordPress Page', 'vibecode-deploy' ); ?>
									</label>
								</th>
								<td>
									<?php
									$page_slug = 'html-test-page';
									$page = get_page_by_path( $page_slug );
									if ( $page ) {
										echo '<span style="color: green;">✓ Test page found (ID: ' . $page->ID . ')</span><br />';
										echo '<a href="' . esc_url( get_permalink( $page->ID ) ) . '" target="_blank">' . esc_html__( 'View Page', 'vibecode-deploy' ) . '</a>';
									} else {
										echo '<span style="color: orange;">⚠ Test page not found. Deploy the test page first.</span>';
									}
									?>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label>
										<input type="radio" name="audit_source" value="html_file" />
										<?php echo esc_html__( 'Analyze from HTML File (Upload)', 'vibecode-deploy' ); ?>
									</label>
								</th>
								<td>
									<input type="file" name="html_file" accept=".html,.htm" />
									<p class="description"><?php echo esc_html__( 'Upload an HTML file to analyze (e.g., downloaded test page).', 'vibecode-deploy' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					
					<p class="submit">
						<input type="submit" name="generate_audit_report" class="button button-primary" value="<?php echo esc_attr__( 'Generate Audit Report', 'vibecode-deploy' ); ?>" />
					</p>
				</form>
				
				<div style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 4px;">
					<h3><?php echo esc_html__( 'Report Includes:', 'vibecode-deploy' ); ?></h3>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php echo esc_html__( 'Executive summary with compliance scores', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Element-by-element analysis (expected vs actual)', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Block type coverage statistics', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Issues and warnings (categorized by severity)', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Compliance metrics (etchData coverage, block type accuracy, editability)', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Actionable recommendations', 'vibecode-deploy' ); ?></li>
					</ul>
					<p style="margin-top: 10px;"><strong><?php echo esc_html__( 'Use Cases:', 'vibecode-deploy' ); ?></strong></p>
					<ul style="list-style: disc; margin-left: 20px;">
						<li><?php echo esc_html__( 'Support: Quickly identify conversion issues', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Compliance: Verify structural rules adherence', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Documentation: Show conversion accuracy to stakeholders', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Debugging: Element-by-element analysis', 'vibecode-deploy' ); ?></li>
						<li><?php echo esc_html__( 'Regression testing: Track quality over time', 'vibecode-deploy' ); ?></li>
					</ul>
				</div>
			</div>

			<div class="card" style="max-width: 800px; margin-top: 20px;">
				<h2><?php echo esc_html__( 'About Test Data', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'Test data is automatically generated for all registered Custom Post Types using lorem ipsum content.', 'vibecode-deploy' ); ?></p>
				<ul style="list-style: disc; margin-left: 20px;">
					<li><?php echo esc_html__( 'Each CPT will receive 3 sample posts with lorem ipsum content', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'Generic meta fields are added using the pattern: {cpt_slug}_test_field_{number}', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'A test date field is added: {cpt_slug}_test_date', 'vibecode-deploy' ); ?></li>
					<li><?php echo esc_html__( 'All posts are published immediately', 'vibecode-deploy' ); ?></li>
				</ul>
				<p><strong><?php echo esc_html__( 'Note:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'CPTs that already have published posts will be skipped to avoid creating duplicate test data.', 'vibecode-deploy' ); ?></p>
			</div>
		</div>
		<?php
	}
}
