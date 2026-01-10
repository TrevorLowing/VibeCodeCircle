<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\CleanupService;
use VibeCode\Deploy\Services\EnvService;
use VibeCode\Deploy\Services\RollbackService;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_vibecode_deploy_purge_uploads', array( __CLASS__, 'purge_uploads' ) );
		add_action( 'admin_post_vibecode_deploy_detach_pages', array( __CLASS__, 'detach_pages' ) );
		add_action( 'admin_post_vibecode_deploy_purge_both', array( __CLASS__, 'purge_both' ) );
		add_action( 'admin_post_vibecode_deploy_nuclear_operation', array( __CLASS__, 'nuclear_operation' ) );
		add_action( 'admin_post_vibecode_deploy_flush_caches', array( __CLASS__, 'flush_caches' ) );
		add_action( 'admin_post_vibecode_deploy_reset_config', array( __CLASS__, 'reset_config' ) );
	}

	public static function register_menu(): void {
		add_menu_page(
			__( 'Vibe Code Deploy', 'vibecode-deploy' ),
			__( 'Vibe Code Deploy', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy',
			array( __CLASS__, 'render' ),
			'dashicons-admin-generic'
		);

		add_submenu_page(
			'vibecode-deploy',
			__( 'Configuration', 'vibecode-deploy' ),
			__( 'Configuration', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy',
			array( __CLASS__, 'render' )
		);
	}

	public static function register_settings(): void {
		register_setting(
			'vibecode_deploy',
			Settings::OPTION_NAME,
			array(
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'type'              => 'array',
				'default'           => Settings::defaults(),
			)
		);

		add_settings_section( 'vibecode_deploy_main', '', '__return_false', 'vibecode_deploy' );

		add_settings_field(
			'vibecode_deploy_project_slug',
			__( 'Project Slug', 'vibecode-deploy' ),
			array( __CLASS__, 'field_project_slug' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_class_prefix',
			__( 'Class Prefix', 'vibecode-deploy' ),
			array( __CLASS__, 'field_class_prefix' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_staging_dir',
			__( 'Staging Folder', 'vibecode-deploy' ),
			array( __CLASS__, 'field_staging_dir' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_placeholder_prefix',
			__( 'Placeholder Prefix', 'vibecode-deploy' ),
			array( __CLASS__, 'field_placeholder_prefix' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_env_errors_mode',
			__( 'Environment Errors Mode', 'vibecode-deploy' ),
			array( __CLASS__, 'field_env_errors_mode' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_missing_required',
			__( 'Placeholder Strict Mode (Required)', 'vibecode-deploy' ),
			array( __CLASS__, 'field_on_missing_required' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_missing_recommended',
			__( 'Placeholder Strict Mode (Recommended)', 'vibecode-deploy' ),
			array( __CLASS__, 'field_on_missing_recommended' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_on_unknown_placeholder',
			__( 'Placeholder Strict Mode (Unknown)', 'vibecode-deploy' ),
			array( __CLASS__, 'field_on_unknown_placeholder' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_prefix_validation_mode',
			__( 'Prefix Validation Mode', 'vibecode-deploy' ),
			array( __CLASS__, 'field_prefix_validation_mode' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);

		add_settings_field(
			'vibecode_deploy_prefix_validation_scope',
			__( 'Prefix Validation Scope', 'vibecode-deploy' ),
			array( __CLASS__, 'field_prefix_validation_scope' ),
			'vibecode_deploy',
			'vibecode_deploy_main'
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();
		settings_errors( Settings::OPTION_NAME );

		$action = isset( $_GET['vibecode_deploy_action'] ) ? sanitize_text_field( (string) $_GET['vibecode_deploy_action'] ) : '';
		$result = isset( $_GET['vibecode_deploy_result'] ) ? sanitize_text_field( (string) $_GET['vibecode_deploy_result'] ) : '';
		$count = isset( $_GET['vibecode_deploy_count'] ) ? (int) $_GET['vibecode_deploy_count'] : 0;
		if ( $action !== '' && $result !== '' ) {
			if ( $result === 'ok' ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $action ) . ' ' . esc_html__( 'complete.', 'vibecode-deploy' );
				if ( $count > 0 ) {
					echo ' ' . esc_html__( 'Affected items:', 'vibecode-deploy' ) . ' <strong>' . esc_html( (string) $count ) . '</strong>.';
				}
				echo '</p></div>';
			} else {
				echo '<div class="notice notice-error"><p>' . esc_html( $action ) . ' ' . esc_html__( 'failed. Check', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Logs', 'vibecode-deploy' ) . '.</p></div>';
			}
		}

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Configuration', 'vibecode-deploy' ) . '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'vibecode_deploy' );
		
		// Important settings at the top
		echo '<h3>' . esc_html__( 'Required Settings', 'vibecode-deploy' ) . '</h3>';
		echo '<table class="form-table" role="presentation">';
		self::field_project_slug();
		self::field_class_prefix();
		echo '</table>';
		
		// Advanced settings in collapsible details
		echo '<details style="margin-top: 1.5rem;">';
		echo '<summary style="cursor: pointer; font-weight: 600; padding: 0.5rem 0;">' . esc_html__( 'Advanced Settings', 'vibecode-deploy' ) . '</summary>';
		echo '<table class="form-table" role="presentation" style="margin-top: 1rem;">';
		self::field_staging_dir();
		self::field_placeholder_prefix();
		self::field_env_errors_mode();
		self::field_on_missing_required();
		self::field_on_missing_recommended();
		self::field_on_unknown_placeholder();
		self::field_prefix_validation_mode();
		self::field_prefix_validation_scope();
		echo '</table>';
		echo '</details>';
		
		submit_button();
		echo '</form>';
		
		// Reset configuration button
		echo '<hr style="margin: 1.5rem 0;" />';
		echo '<h3>' . esc_html__( 'Reset Configuration', 'vibecode-deploy' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Reset all configuration settings to their default values. This will clear Project Slug, Class Prefix, and all advanced settings.', 'vibecode-deploy' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_reset_config" />';
		wp_nonce_field( 'vibecode_deploy_reset_config', 'vibecode_deploy_reset_config_nonce' );
		$reset_confirm = esc_js( __( 'Reset all configuration settings to defaults? This cannot be undone.', 'vibecode-deploy' ) );
		echo '<p><input type="submit" class="button button-secondary" value="' . esc_attr__( 'Reset Configuration', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $reset_confirm . '\');" /></p>';
		echo '</form>';
		
		echo '</div>';

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Danger Zone', 'vibecode-deploy' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'These actions are destructive and cannot be undone.', 'vibecode-deploy' ) . '</p>';

		echo '<h3>' . esc_html__( 'Delete uploads (staging builds + logs)', 'vibecode-deploy' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_purge_uploads" />';
		wp_nonce_field( 'vibecode_deploy_purge_uploads', 'vibecode_deploy_purge_nonce' );
		$confirm_msg = esc_js( __( 'Delete all Vibe Code Deploy uploads (staging builds + logs)?', 'vibecode-deploy' ) );
		echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Purge Vibe Code Deploy Uploads', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $confirm_msg . '\');" /></p>';
		echo '</form>';

		echo '<h3>' . esc_html__( 'Detach pages (stop loading Vibe Code Deploy assets)', 'vibecode-deploy' ) . '</h3>';
		if ( $project_slug === '' ) {
			echo '<p><strong>' . esc_html__( 'Project Slug is required', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'to detach or delete pages.', 'vibecode-deploy' ) . '</p>';
		} else {
			/* translators: %s: Project slug */
			echo '<p class="description">' . sprintf( esc_html__( 'This removes Vibe Code Deploy meta from pages owned by %s. Pages remain, but Vibe Code Deploy will stop enqueuing their build CSS/JS.', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_detach_pages" />';
			wp_nonce_field( 'vibecode_deploy_detach_pages', 'vibecode_deploy_detach_nonce' );
			$detach_confirm = esc_js( __( 'Detach Vibe Code Deploy pages for this project?', 'vibecode-deploy' ) );
			echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Detach Vibe Code Deploy Pages', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $detach_confirm . '\');" /></p>';
			echo '</form>';
		}

		echo '<h3>' . esc_html__( 'Purge uploads + detach pages', 'vibecode-deploy' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_purge_both" />';
		wp_nonce_field( 'vibecode_deploy_purge_both', 'vibecode_deploy_purge_both_nonce' );
		$purge_confirm = esc_js( __( 'Purge uploads and detach pages?', 'vibecode-deploy' ) );
		echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Purge Uploads + Detach Pages', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $purge_confirm . '\');" /></p>';
		echo '</form>';

		echo '<h3>' . esc_html__( 'Nuclear Option: Granular Content Deletion', 'vibecode-deploy' ) . '</h3>';
		if ( $project_slug !== '' ) {
			/* translators: %s: Project slug */
			echo '<p class="description">' . sprintf( esc_html__( 'Delete content owned by %s with granular control. Choose what to delete and whether to restore from previous deployment.', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
			
			// Get project content for selection
			$project_pages = CleanupService::get_project_pages( $project_slug );
			$project_templates = CleanupService::get_project_templates( $project_slug );
			$project_template_parts = CleanupService::get_project_template_parts( $project_slug );
			
			// Get last deploy fingerprint for rollback
			$last_fingerprint = \VibeCode\Deploy\Services\ManifestService::get_last_deploy_fingerprint( $project_slug );
			$has_rollback = $last_fingerprint !== '' && \VibeCode\Deploy\Services\ManifestService::has_manifest( $project_slug, $last_fingerprint );
			
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" id="vibecode-deploy-nuclear-form">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_nuclear_operation" />';
			wp_nonce_field( 'vibecode_deploy_nuclear_operation', 'vibecode_deploy_nuclear_nonce' );
			
			// Scope Selection
			echo '<div style="margin-bottom: 1.5rem;">';
			echo '<h4>' . esc_html__( 'Scope', 'vibecode-deploy' ) . '</h4>';
			echo '<p>';
			echo '<label><input type="radio" name="vibecode_deploy_nuclear_scope" value="everything" checked /> ' . esc_html__( 'Everything (default)', 'vibecode-deploy' ) . '</label><br />';
			echo '<label><input type="radio" name="vibecode_deploy_nuclear_scope" value="by_type" /> ' . esc_html__( 'Choose by Type', 'vibecode-deploy' ) . '</label><br />';
			echo '<label><input type="radio" name="vibecode_deploy_nuclear_scope" value="by_page" /> ' . esc_html__( 'Choose Page By Page Name', 'vibecode-deploy' ) . '</label>';
			echo '</p>';
			echo '</div>';
			
			// Type Selection (shown when "Choose by Type" selected)
			echo '<div id="vibecode-deploy-nuclear-by-type" style="display: none; margin-bottom: 1.5rem;">';
			echo '<h4>' . esc_html__( 'Select Types', 'vibecode-deploy' ) . '</h4>';
			echo '<p>';
			echo '<label><input type="checkbox" name="vibecode_deploy_nuclear_types[]" value="pages" /> ' . esc_html__( 'Pages', 'vibecode-deploy' ) . ' (' . count( $project_pages ) . ')</label><br />';
			echo '<label><input type="checkbox" name="vibecode_deploy_nuclear_types[]" value="templates" /> ' . esc_html__( 'Templates', 'vibecode-deploy' ) . ' (' . count( $project_templates ) . ')</label><br />';
			echo '<label><input type="checkbox" name="vibecode_deploy_nuclear_types[]" value="template_parts" /> ' . esc_html__( 'Template Parts', 'vibecode-deploy' ) . ' (' . count( $project_template_parts ) . ')</label><br />';
			echo '<label><input type="checkbox" name="vibecode_deploy_nuclear_types[]" value="theme_files" /> ' . esc_html__( 'Theme Files (functions.php)', 'vibecode-deploy' ) . '</label><br />';
			echo '<label><input type="checkbox" name="vibecode_deploy_nuclear_types[]" value="acf_json" /> ' . esc_html__( 'ACF JSON Files', 'vibecode-deploy' ) . '</label><br />';
			echo '<label><input type="checkbox" name="vibecode_deploy_nuclear_types[]" value="assets" /> ' . esc_html__( 'CSS/JS Assets', 'vibecode-deploy' ) . '</label>';
			echo '</p>';
			echo '</div>';
			
			// Page Selection (shown when "Choose Page By Page Name" selected)
			echo '<div id="vibecode-deploy-nuclear-by-page" style="display: none; margin-bottom: 1.5rem;">';
			echo '<h4>' . esc_html__( 'Select Pages', 'vibecode-deploy' ) . '</h4>';
			if ( ! empty( $project_pages ) ) {
				echo '<p><button type="button" class="button" id="vibecode-deploy-select-all-pages">' . esc_html__( 'Select All', 'vibecode-deploy' ) . '</button> ';
				echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-pages">' . esc_html__( 'Deselect All', 'vibecode-deploy' ) . '</button></p>';
				echo '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">';
				foreach ( $project_pages as $page ) {
					$page_slug = esc_attr( (string) ( $page['slug'] ?? '' ) );
					$page_title = esc_html( (string) ( $page['title'] ?? $page_slug ) );
					echo '<label style="display: block; margin-bottom: 5px;">';
					echo '<input type="checkbox" name="vibecode_deploy_nuclear_pages[]" value="' . $page_slug . '" /> ';
					echo '<strong>' . $page_title . '</strong> <code>' . $page_slug . '</code>';
					echo '</label>';
				}
				echo '</div>';
			} else {
				echo '<p>' . esc_html__( 'No pages found for this project.', 'vibecode-deploy' ) . '</p>';
			}
			echo '</div>';
			
			// Action Selection
			echo '<div style="margin-bottom: 1.5rem;">';
			echo '<h4>' . esc_html__( 'Action', 'vibecode-deploy' ) . '</h4>';
			echo '<p>';
			echo '<label><input type="radio" name="vibecode_deploy_nuclear_action" value="delete" /> ' . esc_html__( 'No Restore (just delete)', 'vibecode-deploy' ) . '</label><br />';
			$rollback_label = esc_html__( 'Restore Previous / RollBack', 'vibecode-deploy' );
			if ( ! $has_rollback ) {
				$rollback_label .= ' <span style="color: #d63638;">(' . esc_html__( 'No previous deployment found', 'vibecode-deploy' ) . ')</span>';
			}
			echo '<label><input type="radio" name="vibecode_deploy_nuclear_action" value="rollback"' . ( $has_rollback ? ' checked' : '' ) . ( ! $has_rollback ? ' disabled' : '' ) . ' /> ' . $rollback_label . '</label>';
			if ( ! $has_rollback ) {
				echo '<input type="hidden" name="vibecode_deploy_nuclear_action" value="delete" />';
			}
			echo '</p>';
			if ( $has_rollback ) {
				echo '<p class="description">' . sprintf( esc_html__( 'Rollback from fingerprint: %s', 'vibecode-deploy' ), '<code>' . esc_html( $last_fingerprint ) . '</code>' ) . '</p>';
			}
			echo '</div>';
			
			// Confirmation
			echo '<div style="margin-bottom: 1.5rem;">';
			echo '<p><label>' . esc_html__( 'Type', 'vibecode-deploy' ) . ' <code>DELETE</code> ' . esc_html__( 'to confirm', 'vibecode-deploy' ) . '<br />';
			echo '<input type="text" class="regular-text" name="vibecode_deploy_nuclear_confirm" value="" id="vibecode-deploy-nuclear-confirm" /></label></p>';
			echo '</div>';
			
			// Summary (will be populated by JavaScript)
			echo '<div id="vibecode-deploy-nuclear-summary" style="margin-bottom: 1.5rem; padding: 10px; background: #f0f0f1; border-left: 4px solid #2271b1; display: none;"></div>';
			
			echo '<p><input type="submit" class="button button-primary" value="' . esc_attr__( 'Execute Nuclear Operation', 'vibecode-deploy' ) . '" id="vibecode-deploy-nuclear-submit" /></p>';
			echo '</form>';
			
			// Quick Cache Flush option
			echo '<hr />';
			echo '<h3>' . esc_html__( 'Quick Cache Flush', 'vibecode-deploy' ) . '</h3>';
			echo '<p>' . esc_html__( 'Flush all caches without deleting content. Use this before re-importing to ensure fresh deployment.', 'vibecode-deploy' ) . '</p>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="vibecode_deploy_flush_caches" />';
			wp_nonce_field( 'vibecode_deploy_flush_caches', 'vibecode_deploy_flush_caches_nonce' );
			echo '<p><input type="submit" class="button button-secondary" value="' . esc_attr__( 'Flush All Caches', 'vibecode-deploy' ) . '" /></p>';
			echo '</form>';
			
			// JavaScript for UI interactions
			?>
			<script>
			(function() {
				'use strict';
				var form = document.getElementById('vibecode-deploy-nuclear-form');
				if (!form) return;
				
				var scopeRadios = form.querySelectorAll('input[name="vibecode_deploy_nuclear_scope"]');
				var byTypeDiv = document.getElementById('vibecode-deploy-nuclear-by-type');
				var byPageDiv = document.getElementById('vibecode-deploy-nuclear-by-page');
				var summaryDiv = document.getElementById('vibecode-deploy-nuclear-summary');
				var confirmInput = document.getElementById('vibecode-deploy-nuclear-confirm');
				var submitBtn = document.getElementById('vibecode-deploy-nuclear-submit');
				
				function updateUI() {
					var scope = form.querySelector('input[name="vibecode_deploy_nuclear_scope"]:checked')?.value || 'everything';
					byTypeDiv.style.display = (scope === 'by_type') ? 'block' : 'none';
					byPageDiv.style.display = (scope === 'by_page') ? 'block' : 'none';
					updateSummary();
				}
				
				function updateSummary() {
					var scope = form.querySelector('input[name="vibecode_deploy_nuclear_scope"]:checked')?.value || 'everything';
					var action = form.querySelector('input[name="vibecode_deploy_nuclear_action"]:checked')?.value || 'delete';
					var summary = [];
					
					if (scope === 'everything') {
						summary.push('Everything will be deleted');
					} else if (scope === 'by_type') {
						var types = Array.from(form.querySelectorAll('input[name="vibecode_deploy_nuclear_types[]"]:checked')).map(cb => cb.value);
						if (types.length > 0) {
							summary.push('Types: ' + types.join(', '));
						} else {
							summary.push('No types selected');
						}
					} else if (scope === 'by_page') {
						var pages = Array.from(form.querySelectorAll('input[name="vibecode_deploy_nuclear_pages[]"]:checked')).map(cb => cb.value);
						summary.push('Pages: ' + pages.length + ' selected');
					}
					
					summary.push('Action: ' + (action === 'rollback' ? 'Delete and Restore' : 'Delete Only'));
					
					if (summary.length > 0) {
						summaryDiv.innerHTML = '<strong>' + summary.join(' | ') + '</strong>';
						summaryDiv.style.display = 'block';
					} else {
						summaryDiv.style.display = 'none';
					}
				}
				
				function validateForm() {
					var confirm = confirmInput.value.trim();
					var scope = form.querySelector('input[name="vibecode_deploy_nuclear_scope"]:checked')?.value || 'everything';
					var isValid = confirm === 'DELETE';
					
					if (scope === 'by_type') {
						var types = Array.from(form.querySelectorAll('input[name="vibecode_deploy_nuclear_types[]"]:checked'));
						isValid = isValid && types.length > 0;
					} else if (scope === 'by_page') {
						var pages = Array.from(form.querySelectorAll('input[name="vibecode_deploy_nuclear_pages[]"]:checked'));
						isValid = isValid && pages.length > 0;
					}
					
					submitBtn.disabled = !isValid;
					return isValid;
				}
				
				scopeRadios.forEach(function(radio) {
					radio.addEventListener('change', function() {
						updateUI();
						validateForm();
					});
				});
				
				form.querySelectorAll('input[type="checkbox"], input[type="radio"]').forEach(function(input) {
					input.addEventListener('change', function() {
						updateSummary();
						validateForm();
					});
				});
				
				confirmInput.addEventListener('input', validateForm);
				
				document.getElementById('vibecode-deploy-select-all-pages')?.addEventListener('click', function() {
					form.querySelectorAll('input[name="vibecode_deploy_nuclear_pages[]"]').forEach(function(cb) {
						cb.checked = true;
					});
					updateSummary();
					validateForm();
				});
				
				document.getElementById('vibecode-deploy-deselect-all-pages')?.addEventListener('click', function() {
					form.querySelectorAll('input[name="vibecode_deploy_nuclear_pages[]"]').forEach(function(cb) {
						cb.checked = false;
					});
					updateSummary();
					validateForm();
				});
				
				form.addEventListener('submit', function(e) {
					if (!validateForm()) {
						e.preventDefault();
						alert('<?php echo esc_js( __( 'Please complete all required fields and type DELETE to confirm.', 'vibecode-deploy' ) ); ?>');
						return false;
					}
					
					var scope = form.querySelector('input[name="vibecode_deploy_nuclear_scope"]:checked')?.value || 'everything';
					var action = form.querySelector('input[name="vibecode_deploy_nuclear_action"]:checked')?.value || 'delete';
					var summaryText = summaryDiv.textContent || '';
					
					var confirmMsg = '<?php echo esc_js( __( 'Final confirmation: Execute nuclear operation?', 'vibecode-deploy' ) ); ?>\n\n' + summaryText;
					if (!confirm(confirmMsg)) {
						e.preventDefault();
						return false;
					}
				});
				
				// Initial state
				updateUI();
				validateForm();
			})();
			</script>
			<?php
		} else {
			echo '<p><strong>' . esc_html__( 'Project Slug is required', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'to use the nuclear option.', 'vibecode-deploy' ) . '</p>';
		}
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Vibe Code Deploy Disclaimer', 'vibecode-deploy' ) . '</h2>';
		echo '<p>' . esc_html__( 'Vibe Code Deploy is a separate plugin that integrates with other plugins and themes. Other plugins and themes are owned and licensed by their respective authors.', 'vibecode-deploy' ) . '</p>';
		echo '<p>' . esc_html__( 'Vibe Code Deploy does not bundle other plugin or theme source code. If you install/activate other plugins or use other theme assets/templates, you are responsible for ensuring your use complies with their license and terms.', 'vibecode-deploy' ) . '</p>';
		echo '<p>' . esc_html__( 'Vibe Code Deploy does not bundle Etch source code. If you install/activate Etch or use Etch theme assets/templates, you are responsible for ensuring your use complies with Etch\'s license and terms.', 'vibecode-deploy' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	private static function redirect_result( string $action, bool $ok, int $count = 0 ): void {
		$url = add_query_arg(
			array(
				'page' => 'vibecode-deploy',
				'vibecode_deploy_action' => $action,
				'vibecode_deploy_result' => $ok ? 'ok' : 'failed',
				'vibecode_deploy_count' => (string) (int) $count,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public static function purge_uploads(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_purge_uploads', 'vibecode_deploy_purge_nonce' );

		$ok = CleanupService::purge_uploads_root();
		CleanupService::delete_all_active_build_options();
		if ( ! $ok ) {
			Logger::error( 'Purge uploads failed.', array(), '' );
			self::redirect_result( __( 'Purge uploads', 'vibecode-deploy' ), false );
		}

		Logger::info( 'Purge uploads complete.', array(), '' );
		self::redirect_result( __( 'Purge uploads', 'vibecode-deploy' ), true );
	}

	public static function detach_pages(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_detach_pages', 'vibecode_deploy_detach_nonce' );

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		if ( $project_slug === '' ) {
			self::redirect_result( __( 'Detach pages', 'vibecode-deploy' ), false );
		}

		$count = CleanupService::detach_pages_for_project( $project_slug );
		Logger::info( 'Detach pages complete.', array( 'project_slug' => $project_slug, 'count' => $count ), $project_slug );
		self::redirect_result( __( 'Detach pages', 'vibecode-deploy' ), true, $count );
	}

	public static function purge_both(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_purge_both', 'vibecode_deploy_purge_both_nonce' );

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		$ok = CleanupService::purge_uploads_root();
		CleanupService::delete_all_active_build_options();
		if ( ! $ok ) {
			Logger::error( 'Purge both failed: uploads purge failed.', array( 'project_slug' => $project_slug ), $project_slug );
			self::redirect_result( __( 'Purge uploads + detach pages', 'vibecode-deploy' ), false );
		}

		$count = 0;
		if ( $project_slug !== '' ) {
			$count = CleanupService::detach_pages_for_project( $project_slug );
		}

		Logger::info( 'Purge both complete.', array( 'project_slug' => $project_slug, 'detached_pages' => $count ), $project_slug );
		self::redirect_result( __( 'Purge uploads + detach pages', 'vibecode-deploy' ), true, $count );
	}

	public static function nuclear_operation(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_nuclear_operation', 'vibecode_deploy_nuclear_nonce' );

		$confirm = isset( $_POST['vibecode_deploy_nuclear_confirm'] ) ? sanitize_text_field( (string) $_POST['vibecode_deploy_nuclear_confirm'] ) : '';
		if ( $confirm !== 'DELETE' ) {
			self::redirect_result( __( 'Nuclear operation', 'vibecode-deploy' ), false );
			return;
		}

		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		if ( $project_slug === '' ) {
			self::redirect_result( __( 'Nuclear operation', 'vibecode-deploy' ), false );
			return;
		}

		$scope = isset( $_POST['vibecode_deploy_nuclear_scope'] ) ? sanitize_key( (string) $_POST['vibecode_deploy_nuclear_scope'] ) : 'everything';
		// Nuclear operation always deletes for clean slate - action parameter ignored
		$action = 'delete';
		
		$selected_types = array();
		if ( isset( $_POST['vibecode_deploy_nuclear_types'] ) && is_array( $_POST['vibecode_deploy_nuclear_types'] ) ) {
			$selected_types = array_map( 'sanitize_key', $_POST['vibecode_deploy_nuclear_types'] );
			$selected_types = array_filter( $selected_types );
		}
		
		$selected_pages = array();
		if ( isset( $_POST['vibecode_deploy_nuclear_pages'] ) && is_array( $_POST['vibecode_deploy_nuclear_pages'] ) ) {
			$selected_pages = array_map( 'sanitize_key', $_POST['vibecode_deploy_nuclear_pages'] );
			$selected_pages = array_filter( $selected_pages );
		}

		// Validate scope and selections
		if ( $scope === 'by_type' && empty( $selected_types ) ) {
			self::redirect_result( __( 'Nuclear operation', 'vibecode-deploy' ), false );
			return;
		}
		if ( $scope === 'by_page' && empty( $selected_pages ) ) {
			self::redirect_result( __( 'Nuclear operation', 'vibecode-deploy' ), false );
			return;
		}

		// Execute nuclear operation
		// Nuclear operation = clean slate (delete everything, no restore)
		$results = CleanupService::nuclear_operation( $project_slug, $scope, $selected_types, $selected_pages, $action );
		
		// Note: Nuclear operation with 'delete' action provides a clean slate - it deletes everything
		// Rollback is a separate operation that should be run independently if you want to restore
		// We don't combine delete + rollback because nuclear should be a true clean slate

		$total_deleted = (int) ( $results['deleted_pages'] ?? 0 ) + (int) ( $results['deleted_templates'] ?? 0 ) + (int) ( $results['deleted_template_parts'] ?? 0 );
		$total_restored = (int) ( $results['restored_pages'] ?? 0 ) + (int) ( $results['restored_templates'] ?? 0 ) + (int) ( $results['restored_template_parts'] ?? 0 );
		
		// Separate actual errors from warnings
		$actual_errors = isset( $results['actual_errors'] ) && is_array( $results['actual_errors'] ) ? $results['actual_errors'] : array();
		$warnings = isset( $results['warnings'] ) && is_array( $results['warnings'] ) ? $results['warnings'] : array();
		$all_messages = isset( $results['errors'] ) && is_array( $results['errors'] ) ? $results['errors'] : array();
		
		// Count only actual errors (non-skippable), not warnings
		$error_count = count( $actual_errors );
		
		// Log detailed error information if present
		if ( $error_count > 0 || ! empty( $all_messages ) ) {
			Logger::error( 'Nuclear operation had errors.', array(
				'project_slug' => $project_slug,
				'scope' => $scope,
				'action' => $action,
				'error_count' => $error_count,
				'warning_count' => count( $warnings ),
				'errors' => $actual_errors,
				'warnings' => $warnings,
				'all_messages' => $all_messages, // For backward compatibility
				'results' => $results,
			), $project_slug );
		} else {
			Logger::info( 'Nuclear operation complete.', array(
				'project_slug' => $project_slug,
				'scope' => $scope,
				'action' => $action,
				'results' => $results,
			), $project_slug );
		}
		
		$message = sprintf(
			__( 'Deleted: %d items. Restored: %d items.', 'vibecode-deploy' ),
			$total_deleted,
			$total_restored
		);
		
		self::redirect_result( __( 'Nuclear operation', 'vibecode-deploy' ), empty( $results['errors'] ), $total_deleted );
	}

	public static function flush_caches(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_flush_caches', 'vibecode_deploy_flush_caches_nonce' );
		
		$opts = Settings::get_all();
		$project_slug = (string) $opts['project_slug'];
		
		$results = CleanupService::flush_all_caches( $project_slug );
		
		if ( empty( $results['errors'] ) ) {
			Logger::info( 'Cache flush complete.', array(
				'project_slug' => $project_slug,
				'results' => $results,
			), $project_slug );
			self::redirect_result( __( 'Cache flush', 'vibecode-deploy' ), true );
		} else {
			Logger::error( 'Cache flush had errors.', array(
				'project_slug' => $project_slug,
				'errors' => $results['errors'],
			), $project_slug );
			self::redirect_result( __( 'Cache flush', 'vibecode-deploy' ), false );
		}
	}

	public static function field_project_slug(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['project_slug'] );

		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_project_slug">' . esc_html__( 'Project Slug', 'vibecode-deploy' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="' . $name . '_project_slug" name="' . $name . '[project_slug]" value="' . $val . '" />';
		echo '<p class="description">' . esc_html__( 'Used to identify this project for imports, manifests, and rules packs.', 'vibecode-deploy' ) . '</p></td>';
		echo '</tr>';
	}

	public static function field_class_prefix(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['class_prefix'] );

		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_class_prefix">' . esc_html__( 'Class Prefix', 'vibecode-deploy' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="' . $name . '_class_prefix" name="' . $name . '[class_prefix]" value="' . $val . '" />';
		echo '<p class="description">' . esc_html__( 'Must match ^[a-z0-9-]+-$ (lowercase, trailing dash required).', 'vibecode-deploy' ) . '</p></td>';
		echo '</tr>';
	}

	public static function field_staging_dir(): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$val  = esc_attr( (string) $opts['staging_dir'] );

		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_staging_dir">' . esc_html__( 'Staging Folder', 'vibecode-deploy' ) . '</label></th>';
		echo '<td><input type="text" class="regular-text" id="' . $name . '_staging_dir" name="' . $name . '[staging_dir]" value="' . $val . '" />';
		echo '<p class="description">' . esc_html__( 'Local deploy input folder name (default: vibecode-deploy-staging).', 'vibecode-deploy' ) . '</p></td>';
		echo '</tr>';
	}

	private static function render_mode_select( string $field_key, string $description ): void {
		$opts = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$current = isset( $opts[ $field_key ] ) && is_string( $opts[ $field_key ] ) ? (string) $opts[ $field_key ] : 'warn';
		$current = ( $current === 'fail' ) ? 'fail' : 'warn';
		$field = esc_attr( $field_key );

		echo '<select name="' . $name . '[' . $field . ']">';
		echo '<option value="warn"' . selected( $current, 'warn', false ) . '>' . esc_html__( 'Warn (default)', 'vibecode-deploy' ) . '</option>';
		echo '<option value="fail"' . selected( $current, 'fail', false ) . '>' . esc_html__( 'Fail deploy', 'vibecode-deploy' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}

	public static function field_placeholder_prefix(): void {
		$settings = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$value = isset( $settings['placeholder_prefix'] ) ? esc_attr( (string) $settings['placeholder_prefix'] ) : 'VIBECODE_SHORTCODE';
		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_placeholder_prefix">' . esc_html__( 'Placeholder Prefix', 'vibecode-deploy' ) . '</label></th>';
		echo '<td><input type="text" id="' . $name . '_placeholder_prefix" name="' . $name . '[placeholder_prefix]" value="' . $value . '" class="regular-text" pattern="[A-Z0-9_]+" />';
		echo '<p class="description">' . esc_html__( 'Prefix for shortcode placeholder comments in HTML (e.g., VIBECODE_SHORTCODE). Use uppercase letters, numbers, and underscores only.', 'vibecode-deploy' ) . '</p></td>';
		echo '</tr>';
	}

	public static function field_env_errors_mode(): void {
		$name = esc_attr( Settings::OPTION_NAME );
		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_env_errors_mode">' . esc_html__( 'Environment Errors Mode', 'vibecode-deploy' ) . '</label></th>';
		echo '<td>';
		self::render_mode_select( 'env_errors_mode', __( 'How to handle critical environment errors (missing theme, unsupported WordPress version, etc.) during preflight.', 'vibecode-deploy' ) );
		echo '</td>';
		echo '</tr>';
	}

	public static function field_on_missing_required(): void {
		$settings = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_on_missing_required">' . esc_html__( 'Placeholder Strict Mode (Required)', 'vibecode-deploy' ) . '</label></th>';
		echo '<td>';
		/* translators: %s: Placeholder prefix */
		self::render_mode_select( 'on_missing_required', sprintf( __( 'When a page is missing a required %s placeholder (as defined in vibecode-deploy-shortcodes.json).', 'vibecode-deploy' ), $prefix ) );
		echo '</td>';
		echo '</tr>';
	}

	public static function field_on_missing_recommended(): void {
		$settings = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_on_missing_recommended">' . esc_html__( 'Placeholder Strict Mode (Recommended)', 'vibecode-deploy' ) . '</label></th>';
		echo '<td>';
		/* translators: %s: Placeholder prefix */
		self::render_mode_select( 'on_missing_recommended', sprintf( __( 'When a page is missing a recommended %s placeholder (as defined in vibecode-deploy-shortcodes.json).', 'vibecode-deploy' ), $prefix ) );
		echo '</td>';
		echo '</tr>';
	}

	public static function field_on_unknown_placeholder(): void {
		$settings = Settings::get_all();
		$name = esc_attr( Settings::OPTION_NAME );
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_on_unknown_placeholder">' . esc_html__( 'Placeholder Strict Mode (Unknown)', 'vibecode-deploy' ) . '</label></th>';
		echo '<td>';
		/* translators: %s: Placeholder prefix */
		self::render_mode_select( 'on_unknown_placeholder', sprintf( __( 'When an invalid/unparseable %s placeholder is encountered in HTML.', 'vibecode-deploy' ), $prefix ) );
		echo '</td>';
		echo '</tr>';
	}

	public static function field_prefix_validation_mode(): void {
		$settings = Settings::get_all();
		$project_slug = isset( $settings['project_slug'] ) ? (string) $settings['project_slug'] : '';
		$current = isset( $settings['prefix_validation_mode'] ) && is_string( $settings['prefix_validation_mode'] ) ? strtolower( trim( (string) $settings['prefix_validation_mode'] ) ) : 'warn';
		if ( $current !== 'off' && $current !== 'fail' ) {
			$current = 'warn';
		}
		$name = esc_attr( Settings::OPTION_NAME );
		$field = 'prefix_validation_mode';

		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_prefix_validation_mode">' . esc_html__( 'Prefix Validation Mode', 'vibecode-deploy' ) . '</label></th>';
		echo '<td><select id="' . $name . '_prefix_validation_mode" name="' . $name . '[' . $field . ']">';
		echo '<option value="warn"' . selected( $current, 'warn', false ) . '>' . esc_html__( 'Warn (default)', 'vibecode-deploy' ) . '</option>';
		echo '<option value="fail"' . selected( $current, 'fail', false ) . '>' . esc_html__( 'Fail deploy', 'vibecode-deploy' ) . '</option>';
		echo '<option value="off"' . selected( $current, 'off', false ) . '>' . esc_html__( 'Off (disabled)', 'vibecode-deploy' ) . '</option>';
		echo '</select>';
		if ( $project_slug !== '' ) {
			/* translators: %s: Project slug */
			echo '<p class="description">' . sprintf( esc_html__( 'How to handle shortcodes and CPTs that do not match the project prefix "%s". Validates naming conventions to ensure consistency.', 'vibecode-deploy' ), esc_html( $project_slug ) ) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'How to handle shortcodes and CPTs that do not match the project prefix. Set Project Slug first to enable validation.', 'vibecode-deploy' ) . '</p>';
		}
		echo '</td>';
		echo '</tr>';
	}

	public static function field_prefix_validation_scope(): void {
		$settings = Settings::get_all();
		$current = isset( $settings['prefix_validation_scope'] ) && is_string( $settings['prefix_validation_scope'] ) ? strtolower( trim( (string) $settings['prefix_validation_scope'] ) ) : 'all';
		if ( $current !== 'shortcodes' && $current !== 'cpts' ) {
			$current = 'all';
		}
		$name = esc_attr( Settings::OPTION_NAME );
		$field = 'prefix_validation_scope';

		echo '<tr>';
		echo '<th scope="row"><label for="' . $name . '_prefix_validation_scope">' . esc_html__( 'Prefix Validation Scope', 'vibecode-deploy' ) . '</label></th>';
		echo '<td><fieldset>';
		echo '<label><input type="radio" name="' . $name . '[' . $field . ']" value="all"' . checked( $current, 'all', false ) . ' /> ' . esc_html__( 'All (shortcodes and CPTs)', 'vibecode-deploy' ) . '</label><br />';
		echo '<label><input type="radio" name="' . $name . '[' . $field . ']" value="shortcodes"' . checked( $current, 'shortcodes', false ) . ' /> ' . esc_html__( 'Shortcodes only', 'vibecode-deploy' ) . '</label><br />';
		echo '<label><input type="radio" name="' . $name . '[' . $field . ']" value="cpts"' . checked( $current, 'cpts', false ) . ' /> ' . esc_html__( 'CPTs only', 'vibecode-deploy' ) . '</label>';
		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Which items to validate for project prefix compliance. "All" validates both shortcodes and custom post types.', 'vibecode-deploy' ) . '</p>';
		echo '</td>';
		echo '</tr>';
	}

	public static function reset_config(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		check_admin_referer( 'vibecode_deploy_reset_config', 'vibecode_deploy_reset_config_nonce' );
		
		// Delete the option to reset to defaults
		delete_option( Settings::OPTION_NAME );
		
		Logger::info( 'Configuration reset to defaults.', array(), '' );
		
		// Redirect with success message
		$url = admin_url( 'admin.php?page=vibecode-deploy&vibecode_deploy_action=' . urlencode( __( 'Configuration reset', 'vibecode-deploy' ) ) . '&vibecode_deploy_result=ok' );
		wp_safe_redirect( $url );
		exit;
	}
}
