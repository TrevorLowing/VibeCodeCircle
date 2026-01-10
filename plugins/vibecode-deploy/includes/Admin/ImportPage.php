<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Staging;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Services\ClassPrefixDetector;
use VibeCode\Deploy\Services\EnvService;
use VibeCode\Deploy\Services\ManifestService;
use VibeCode\Deploy\Services\RollbackService;

defined( 'ABSPATH' ) || exit;

final class ImportPage {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_vibecode_deploy_rollback_last_deploy', array( __CLASS__, 'rollback_last_deploy' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			__( 'Deploy', 'vibecode-deploy' ),
			__( 'Deploy', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-import',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$notice = '';
		$error = '';
		$preflight = null;
		$import_result = null;
		$selected_fingerprint = '';
		$build_root = '';
		$failed = isset( $_GET['failed'] ) ? sanitize_text_field( (string) $_GET['failed'] ) : '';
		$rolled_back_deleted = isset( $_GET['rolled_back_deleted'] ) ? (int) $_GET['rolled_back_deleted'] : 0;
		$rolled_back_restored = isset( $_GET['rolled_back_restored'] ) ? (int) $_GET['rolled_back_restored'] : 0;

		// Debug: Log all POST data when preflight button is clicked
		if ( isset( $_POST['vibecode_deploy_preflight'] ) ) {
			Logger::info( 'Preflight POST detected.', array(
				'post_keys' => array_keys( $_POST ),
				'has_fingerprint' => isset( $_POST['vibecode_deploy_fingerprint'] ),
				'fingerprint_value' => isset( $_POST['vibecode_deploy_fingerprint'] ) ? sanitize_text_field( (string) $_POST['vibecode_deploy_fingerprint'] ) : '',
				'has_nonce' => isset( $_POST['vibecode_deploy_preflight_nonce'] ),
				'project_slug' => (string) $settings['project_slug'],
			), (string) $settings['project_slug'] );
		}

		if ( isset( $_POST['vibecode_deploy_upload_zip'] ) ) {
			check_admin_referer( 'vibecode_deploy_upload_zip', 'vibecode_deploy_nonce' );

			// Validate class prefix format if set
			if ( $settings['class_prefix'] !== '' && ! preg_match( '/^[a-z0-9-]+-$/', (string) $settings['class_prefix'] ) ) {
				$error = __( 'Class Prefix is invalid.', 'vibecode-deploy' );
				Logger::error( 'Upload blocked: invalid class prefix.', array( 'project_slug' => (string) $settings['project_slug'], 'class_prefix' => (string) $settings['class_prefix'] ), (string) $settings['project_slug'] );
			} elseif ( empty( $_FILES['vibecode_deploy_zip']['tmp_name'] ) || ! is_uploaded_file( (string) $_FILES['vibecode_deploy_zip']['tmp_name'] ) ) {
				$error = 'No zip file uploaded.';
				Logger::error( 'Upload blocked: no zip file uploaded.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} else {
				$file = $_FILES['vibecode_deploy_zip'];
				$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
				if ( $size <= 0 ) {
					$error = 'Uploaded zip is empty.';
					Logger::error( 'Upload blocked: zip size is empty.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
				} elseif ( $size > Staging::ZIP_MAX_BYTES ) {
					$error = 'Uploaded zip exceeds max size.';
					Logger::error( 'Upload blocked: zip exceeds max size.', array( 'project_slug' => (string) $settings['project_slug'], 'size' => $size, 'max' => Staging::ZIP_MAX_BYTES ), (string) $settings['project_slug'] );
				} else {
					$upload = wp_handle_upload(
						$file,
						array(
							'test_form' => false,
							'mimes' => array( 'zip' => 'application/zip' ),
						)
					);

					if ( ! is_array( $upload ) || isset( $upload['error'] ) ) {
						$error = is_array( $upload ) ? (string) ( $upload['error'] ?? 'Upload failed.' ) : 'Upload failed.';
						Logger::error( 'Upload failed.', array( 'project_slug' => (string) $settings['project_slug'], 'error' => $error ), (string) $settings['project_slug'] );
					} else {
						// Auto-detect project_slug from JSON file if not set
						$project_slug_to_use = (string) $settings['project_slug'];
						if ( $project_slug_to_use === '' ) {
							$detected_slug = self::detect_project_slug_from_zip( (string) $upload['file'] );
							if ( $detected_slug !== '' ) {
								// Update settings with detected project slug
								$updated_settings = $settings;
								$updated_settings['project_slug'] = $detected_slug;
								update_option( Settings::OPTION_NAME, $updated_settings );
								$settings = Settings::get_all(); // Refresh settings
								$project_slug_to_use = $detected_slug;
								
								$notice = 'Project slug auto-detected from staging zip: <code>' . esc_html( $detected_slug ) . '</code>.';
								Logger::info( 'Project slug auto-detected from staging zip.', array( 'detected_slug' => $detected_slug ), $detected_slug );
							} else {
								$error = __( 'Project Slug is required. Could not auto-detect from staging zip. Please set it in Vibe Code Deploy → Configuration.', 'vibecode-deploy' );
								Logger::error( 'Upload blocked: missing project slug and auto-detection failed.', array(), '' );
								@unlink( (string) $upload['file'] );
							}
						}
						
						if ( ! isset( $error ) ) {
							Logger::info( 'Zip uploaded; extracting to staging.', array( 'project_slug' => $project_slug_to_use ), $project_slug_to_use );
							$result = Staging::extract_zip_to_staging( (string) $upload['file'], $project_slug_to_use );
							@unlink( (string) $upload['file'] );

							if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
								$error = is_array( $result ) ? (string) ( $result['error'] ?? 'Extraction failed.' ) : 'Extraction failed.';
								Logger::error( 'Extraction failed.', array( 'project_slug' => $project_slug_to_use, 'error' => $error ), $project_slug_to_use );
							} else {
								$selected_fingerprint = (string) $result['fingerprint'];
								
								// Auto-detect class prefix if not set
								if ( $settings['class_prefix'] === '' ) {
									$build_root = BuildService::build_root_path( $project_slug_to_use, $selected_fingerprint );
									$detected_prefix = ClassPrefixDetector::detect_from_staging( $build_root );
									
									if ( $detected_prefix !== '' ) {
										// Update settings with detected prefix
										$updated_settings = $settings;
										$updated_settings['class_prefix'] = $detected_prefix;
										update_option( Settings::OPTION_NAME, $updated_settings );
										$settings = Settings::get_all(); // Refresh settings
										
										$notice = ( isset( $notice ) ? $notice . ' ' : '' ) . 'Staging uploaded: ' . esc_html( $selected_fingerprint ) . ' (' . esc_html( (string) $result['files'] ) . ' files). Class prefix auto-detected: <code>' . esc_html( $detected_prefix ) . '</code>';
										Logger::info( 'Class prefix auto-detected from staging files.', array( 'project_slug' => $project_slug_to_use, 'detected_prefix' => $detected_prefix ), $project_slug_to_use );
									} else {
										$notice = ( isset( $notice ) ? $notice . ' ' : '' ) . 'Staging uploaded: ' . esc_html( $selected_fingerprint ) . ' (' . esc_html( (string) $result['files'] ) . ' files). <strong>Warning:</strong> Class prefix could not be auto-detected. Please set it manually in Settings.';
										Logger::warning( 'Class prefix auto-detection failed.', array( 'project_slug' => $project_slug_to_use ), $project_slug_to_use );
									}
								} else {
									$notice = ( isset( $notice ) ? $notice . ' ' : '' ) . 'Staging uploaded: ' . esc_html( $selected_fingerprint ) . ' (' . esc_html( (string) $result['files'] ) . ' files)';
								}
								
								Logger::info( 'Staging extracted.', array( 'project_slug' => $project_slug_to_use, 'fingerprint' => $selected_fingerprint, 'files' => (int) ( $result['files'] ?? 0 ) ), $project_slug_to_use );
							}
						}
					}
				}
			}
		}

		if ( isset( $_POST['vibecode_deploy_preflight'] ) ) {
			Logger::info( 'Preflight POST processing started.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			
			// Verify nonce - this will wp_die() if invalid, so if we get here, nonce is valid
			check_admin_referer( 'vibecode_deploy_preflight', 'vibecode_deploy_preflight_nonce' );
			Logger::info( 'Preflight nonce verified.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			
			$selected_fingerprint = isset( $_POST['vibecode_deploy_fingerprint'] ) ? sanitize_text_field( (string) $_POST['vibecode_deploy_fingerprint'] ) : '';
			Logger::info( 'Preflight fingerprint extracted.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint ), (string) $settings['project_slug'] );
			
			if ( $settings['project_slug'] === '' ) {
				$error = 'Project Slug is required.';
				Logger::error( 'Preflight blocked: missing project slug.', array(), '' );
			} elseif ( $selected_fingerprint === '' ) {
				$error = 'Select a staging build.';
				Logger::error( 'Preflight blocked: missing fingerprint selection.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} else {
				$build_root = BuildService::build_root_path( (string) $settings['project_slug'], $selected_fingerprint );
				Logger::info( 'Preflight build_root calculated.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'build_root' => $build_root, 'build_root_exists' => is_dir( $build_root ) ), (string) $settings['project_slug'] );
				
				if ( ! is_dir( $build_root ) ) {
					$error = 'Build directory not found. Please ensure the staging zip was uploaded successfully.';
					Logger::error( 'Preflight blocked: build directory not found.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'build_root' => $build_root ), (string) $settings['project_slug'] );
				} else {
					Logger::info( 'Preflight started.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'build_root' => $build_root ), (string) $settings['project_slug'] );
					try {
						$preflight = Importer::preflight( (string) $settings['project_slug'], $build_root );
						Logger::info( 'Preflight method returned.', array( 'project_slug' => (string) $settings['project_slug'], 'is_array' => is_array( $preflight ), 'has_pages_total' => isset( $preflight['pages_total'] ), 'pages_total' => isset( $preflight['pages_total'] ) ? (int) $preflight['pages_total'] : null ), (string) $settings['project_slug'] );
						
						// Ensure preflight is always an array, even on error
						if ( ! is_array( $preflight ) ) {
							$preflight = array(
								'pages_total' => 0,
								'items' => array(),
								'errors' => array( 'Preflight returned invalid result. Check logs for details.' ),
							);
							Logger::error( 'Preflight returned invalid result.', array( 'preflight' => $preflight, 'build_root' => $build_root ), (string) $settings['project_slug'] );
						}
						// Always log preflight completion, even if no pages found
						// The preflight result will be displayed below regardless of pages_total
						if ( empty( $preflight['pages_total'] ) ) {
							Logger::warning( 'Preflight found no pages.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'preflight' => $preflight, 'build_root' => $build_root, 'pages_dir' => Importer::pages_dir( $build_root ), 'pages_dir_exists' => is_dir( Importer::pages_dir( $build_root ) ) ), (string) $settings['project_slug'] );
						} else {
							Logger::info( 'Preflight complete.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'pages_total' => (int) ( $preflight['pages_total'] ?? 0 ) ), (string) $settings['project_slug'] );
						}
					} catch ( \Exception $e ) {
						$preflight = array(
							'pages_total' => 0,
							'items' => array(),
							'errors' => array( 'Preflight error: ' . $e->getMessage() ),
						);
						Logger::error( 'Preflight exception.', array( 'exception' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'build_root' => $build_root ), (string) $settings['project_slug'] );
					}
				}
			}
			Logger::info( 'Preflight POST processing complete.', array( 'project_slug' => (string) $settings['project_slug'], 'preflight_set' => ( $preflight !== null ), 'error_set' => ( $error !== '' ) ), (string) $settings['project_slug'] );
		}

		if ( isset( $_POST['vibecode_deploy_run_import'] ) ) {
			check_admin_referer( 'vibecode_deploy_run_import', 'vibecode_deploy_run_import_nonce' );
			$selected_fingerprint = isset( $_POST['vibecode_deploy_fingerprint'] ) ? sanitize_text_field( (string) $_POST['vibecode_deploy_fingerprint'] ) : '';
			$set_front_page = ! empty( $_POST['vibecode_deploy_set_front_page'] );
			$force_claim_unowned = ! empty( $_POST['vibecode_deploy_force_claim_unowned'] );
			$deploy_template_parts = ! isset( $_POST['vibecode_deploy_deploy_template_parts'] ) ? true : ( ! empty( $_POST['vibecode_deploy_deploy_template_parts'] ) );
			$generate_404_template = ! isset( $_POST['vibecode_deploy_generate_404_template'] ) ? true : ( ! empty( $_POST['vibecode_deploy_generate_404_template'] ) );
			$force_claim_templates = ! empty( $_POST['vibecode_deploy_force_claim_templates'] );
			$validate_cpt_shortcodes = ! empty( $_POST['vibecode_deploy_validate_cpt_shortcodes'] );
			
			// Auto-detect class prefix if still empty (before validation)
			if ( $settings['class_prefix'] === '' && $selected_fingerprint !== '' && $settings['project_slug'] !== '' ) {
				$build_root = BuildService::build_root_path( (string) $settings['project_slug'], $selected_fingerprint );
				$detected_prefix = ClassPrefixDetector::detect_from_staging( $build_root );
				
				if ( $detected_prefix !== '' ) {
					// Update settings with detected prefix
					$updated_settings = $settings;
					$updated_settings['class_prefix'] = $detected_prefix;
					update_option( Settings::OPTION_NAME, $updated_settings );
					$settings = Settings::get_all(); // Refresh settings
					
					$notice = 'Class prefix auto-detected: <code>' . esc_html( $detected_prefix ) . '</code>';
					Logger::info( 'Class prefix auto-detected from staging files during import.', array( 'project_slug' => (string) $settings['project_slug'], 'detected_prefix' => $detected_prefix ), (string) $settings['project_slug'] );
				}
			}

			if ( $settings['project_slug'] === '' ) {
				$error = 'Project Slug is required.';
				Logger::error( 'Import blocked: missing project slug.', array(), '' );
			} elseif ( $settings['class_prefix'] !== '' && ! preg_match( '/^[a-z0-9-]+-$/', (string) $settings['class_prefix'] ) ) {
				$error = 'Class Prefix is invalid.';
				Logger::error( 'Import blocked: invalid class prefix.', array( 'project_slug' => (string) $settings['project_slug'], 'class_prefix' => (string) $settings['class_prefix'] ), (string) $settings['project_slug'] );
			} elseif ( $selected_fingerprint === '' ) {
				$error = 'Select a staging build.';
				Logger::error( 'Import blocked: missing fingerprint selection.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} else {
				// Get selected items from form
				$selected_pages = array();
				if ( isset( $_POST['vibecode_deploy_selected_pages'] ) && is_array( $_POST['vibecode_deploy_selected_pages'] ) ) {
					$selected_pages = array_map( 'sanitize_key', $_POST['vibecode_deploy_selected_pages'] );
					$selected_pages = array_filter( $selected_pages );
				}
				
				$selected_css = array();
				if ( isset( $_POST['vibecode_deploy_selected_css'] ) && is_array( $_POST['vibecode_deploy_selected_css'] ) ) {
					$selected_css = array_map( 'sanitize_file_name', $_POST['vibecode_deploy_selected_css'] );
					$selected_css = array_filter( $selected_css );
				}
				
				$selected_js = array();
				if ( isset( $_POST['vibecode_deploy_selected_js'] ) && is_array( $_POST['vibecode_deploy_selected_js'] ) ) {
					$selected_js = array_map( 'sanitize_file_name', $_POST['vibecode_deploy_selected_js'] );
					$selected_js = array_filter( $selected_js );
				}
				
				$selected_templates = array();
				if ( isset( $_POST['vibecode_deploy_selected_templates'] ) && is_array( $_POST['vibecode_deploy_selected_templates'] ) ) {
					$selected_templates = array_map( 'sanitize_key', $_POST['vibecode_deploy_selected_templates'] );
					$selected_templates = array_filter( $selected_templates );
				}
				
				$selected_template_parts = array();
				if ( isset( $_POST['vibecode_deploy_selected_template_parts'] ) && is_array( $_POST['vibecode_deploy_selected_template_parts'] ) ) {
					$selected_template_parts = array_map( 'sanitize_key', $_POST['vibecode_deploy_selected_template_parts'] );
					$selected_template_parts = array_filter( $selected_template_parts );
				}
				
				$selected_theme_files = array();
				if ( isset( $_POST['vibecode_deploy_selected_theme_files'] ) && is_array( $_POST['vibecode_deploy_selected_theme_files'] ) ) {
					$selected_theme_files = array_map( 'sanitize_file_name', $_POST['vibecode_deploy_selected_theme_files'] );
					$selected_theme_files = array_filter( $selected_theme_files );
				}
				
				$build_root = BuildService::build_root_path( (string) $settings['project_slug'], $selected_fingerprint );
				Logger::info( 'Import started.', array( 
					'project_slug' => (string) $settings['project_slug'], 
					'fingerprint' => $selected_fingerprint, 
					'set_front_page' => (bool) $set_front_page, 
					'force_claim_unowned' => (bool) $force_claim_unowned, 
					'deploy_template_parts' => (bool) $deploy_template_parts, 
					'generate_404_template' => (bool) $generate_404_template, 
					'force_claim_templates' => (bool) $force_claim_templates, 
					'validate_cpt_shortcodes' => (bool) $validate_cpt_shortcodes,
					'selected_pages' => count( $selected_pages ),
					'selected_css' => count( $selected_css ),
					'selected_js' => count( $selected_js ),
					'selected_templates' => count( $selected_templates ),
					'selected_template_parts' => count( $selected_template_parts ),
					'selected_theme_files' => count( $selected_theme_files ),
				), (string) $settings['project_slug'] );
				$import_result = Importer::run_import( (string) $settings['project_slug'], $selected_fingerprint, $build_root, $set_front_page, $force_claim_unowned, $deploy_template_parts, $generate_404_template, $force_claim_templates, $validate_cpt_shortcodes, $selected_pages, $selected_css, $selected_js, $selected_templates, $selected_template_parts, $selected_theme_files );
				if ( is_array( $import_result ) && (int) ( $import_result['errors'] ?? 0 ) === 0 ) {
					BuildService::set_active_fingerprint( (string) $settings['project_slug'], $selected_fingerprint );
				}
				$notice = __( 'Deploy complete.', 'vibecode-deploy' );
				Logger::info( 'Import complete.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'result' => $import_result ), (string) $settings['project_slug'] );
			}
		}

		$rolled_back = isset( $_GET['rolled_back'] ) ? sanitize_text_field( (string) $_GET['rolled_back'] ) : '';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();

		if ( $rolled_back !== '' ) {
			/* translators: %1$s: Fingerprint, %2$s: Restored count, %3$s: Deleted count */
			echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Rollback complete: %1$s. Restored: %2$s. Deleted: %3$s.', 'vibecode-deploy' ), '<code>' . esc_html( $rolled_back ) . '</code>', '<strong>' . esc_html( (string) $rolled_back_restored ) . '</strong>', '<strong>' . esc_html( (string) $rolled_back_deleted ) . '</strong>' ) . '</p></div>';
		} elseif ( $failed !== '' ) {
			/* translators: %s: Error message */
			echo '<div class="notice notice-error"><p>' . sprintf( esc_html__( 'Rollback failed: %s. Check', 'vibecode-deploy' ), '<code>' . esc_html( $failed ) . '</code>' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Logs', 'vibecode-deploy' ) . '.</p></div>';
		} elseif ( $error !== '' ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		} elseif ( $notice !== '' ) {
			echo '<div class="notice notice-success"><p>' . esc_html( $notice ) . '</p></div>';
		}

		// Debug: Show if preflight was attempted but result is null
		if ( isset( $_POST['vibecode_deploy_preflight'] ) && $preflight === null && $error === '' ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Preflight Warning:', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'Preflight was attempted but no result was returned. Check the logs for details.', 'vibecode-deploy' ) . '</p></div>';
		}

		$logs_url = admin_url( 'admin.php?page=vibecode-deploy-logs' );
		$builds_url = admin_url( 'admin.php?page=vibecode-deploy-builds' );
		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Workflow', 'vibecode-deploy' ) . '</h2>';
		echo '<ol>'; 
		echo '<li>' . esc_html__( 'Upload staging zip', 'vibecode-deploy' ) . '</li>';
		echo '<li>' . esc_html__( 'Select build', 'vibecode-deploy' ) . '</li>';
		echo '<li>' . esc_html__( 'Preflight (review changes)', 'vibecode-deploy' ) . '</li>';
		echo '<li>' . esc_html__( 'Deploy (writes pages and template content)', 'vibecode-deploy' ) . '</li>';
		echo '</ol>';
		/* translators: %1$s: Logs link, %2$s: Builds link */
		echo '<p>' . sprintf( esc_html__( 'If something fails, check %1$s. To manage builds, use %2$s.', 'vibecode-deploy' ), '<a href="' . esc_url( $logs_url ) . '">' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Logs', 'vibecode-deploy' ) . '</a>', '<a href="' . esc_url( $builds_url ) . '">' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Builds', 'vibecode-deploy' ) . '</a>' ) . '</p>';
		echo '<details><summary>Staging zip requirements</summary>';
		echo '<p class="description">Your zip must contain a top-level <code>vibecode-deploy-staging/</code> folder with these subfolders:</p>';
		echo '<ul style="list-style: disc; padding-left: 22px;">';
		echo '<li><code>vibecode-deploy-staging/pages/*.html</code> (required)</li>';
		echo '<li><code>vibecode-deploy-staging/template-parts/*.html</code> (optional; e.g. <code>header.html</code>, <code>footer.html</code>, <code>header-404.html</code>, <code>footer-404.html</code>)</li>';
		echo '<li><code>vibecode-deploy-staging/templates/*.html</code> (optional; block templates like <code>archive-advisory.html</code>)</li>';
		echo '<li><code>vibecode-deploy-staging/css/</code> (optional)</li>';
		echo '<li><code>vibecode-deploy-staging/js/</code> (optional)</li>';
		echo '<li><code>vibecode-deploy-staging/resources/</code> (optional)</li>';
		echo '</ul>';
		echo '</details>';
		echo '</div>';

		$project_slug_for_rollback = (string) $settings['project_slug'];
		$last_deploy_fp = $project_slug_for_rollback !== '' ? ManifestService::get_last_deploy_fingerprint( $project_slug_for_rollback ) : '';
		$can_rollback_last = ( $project_slug_for_rollback !== '' && $last_deploy_fp !== '' && ManifestService::has_manifest( $project_slug_for_rollback, $last_deploy_fp ) );
		if ( $can_rollback_last ) {
			$rollback_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=vibecode_deploy_rollback_last_deploy' ),
				'vibecode_deploy_rollback_last_deploy'
			);
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<h2 class="title">' . esc_html__( 'Rollback', 'vibecode-deploy' ) . '</h2>';
			/* translators: %s: Fingerprint */
			echo '<p class="description">' . sprintf( esc_html__( 'Last deploy fingerprint: %s', 'vibecode-deploy' ), '<code>' . esc_html( $last_deploy_fp ) . '</code>' ) . '</p>';
			$rollback_confirm = esc_js( __( 'Rollback the last deploy? This will restore updated pages and delete pages created by that deploy.', 'vibecode-deploy' ) );
			echo '<p><a class="button" href="' . esc_url( $rollback_url ) . '" onclick="return confirm(\'' . $rollback_confirm . '\');">' . esc_html__( 'Rollback Last Deploy', 'vibecode-deploy' ) . '</a></p>';
			echo '</div>';
		}

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">1) ' . esc_html__( 'Upload Staging Zip', 'vibecode-deploy' ) . '</h2>';
		echo '<form method="post" enctype="multipart/form-data">';
		wp_nonce_field( 'vibecode_deploy_upload_zip', 'vibecode_deploy_nonce' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Zip file', 'vibecode-deploy' ) . '</th><td><input type="file" name="vibecode_deploy_zip" accept=".zip" required /><p class="description">' . esc_html__( 'Upload a staging bundle exported from your local build.', 'vibecode-deploy' ) . '</p></td></tr>';
		echo '</table>';
		echo '<p><input type="submit" class="button button-primary" name="vibecode_deploy_upload_zip" value="' . esc_attr__( 'Upload Staging Zip', 'vibecode-deploy' ) . '" /></p>';
		echo '</form>';
		/* translators: %s: Max size in MB */
		echo '<p class="description">' . sprintf( esc_html__( 'Max zip size: %s', 'vibecode-deploy' ), esc_html( (string) (int) ( Staging::ZIP_MAX_BYTES / 1024 / 1024 ) ) . 'MB' ) . '</p>';
		echo '</div>';

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">2) ' . esc_html__( 'Select Build', 'vibecode-deploy' ) . '</h2>';

		$project_slug = (string) $settings['project_slug'];
		$fingerprints = $project_slug !== '' ? BuildService::list_build_fingerprints( $project_slug ) : array();
		$active_fingerprint = $project_slug !== '' ? BuildService::get_active_fingerprint( $project_slug ) : '';
		if ( $selected_fingerprint === '' ) {
			if ( $active_fingerprint !== '' ) {
				$selected_fingerprint = $active_fingerprint;
			} elseif ( ! empty( $fingerprints ) ) {
				$selected_fingerprint = (string) $fingerprints[0];
			}
		}

		if ( $project_slug === '' ) {
			echo '<p><strong>' . esc_html__( 'Project Slug is required.', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'Set it in', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Configuration', 'vibecode-deploy' ) . ' ' . esc_html__( 'before deploying.', 'vibecode-deploy' ) . '</p>';
		} elseif ( empty( $fingerprints ) ) {
			echo '<p>' . esc_html__( 'No staging builds found yet. Upload a staging zip above.', 'vibecode-deploy' ) . '</p>';
		} else {
			if ( $active_fingerprint !== '' ) {
				/* translators: %s: Fingerprint */
				echo '<p>' . sprintf( esc_html__( 'Active build: %s', 'vibecode-deploy' ), '<code>' . esc_html( $active_fingerprint ) . '</code>' ) . '</p>';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=vibecode-deploy-import' ) ) . '">';
			wp_nonce_field( 'vibecode_deploy_preflight', 'vibecode_deploy_preflight_nonce' );
			echo '<table class="form-table" role="presentation">';
			echo '<tr><th scope="row">' . esc_html__( 'Staging build', 'vibecode-deploy' ) . '</th><td><select name="vibecode_deploy_fingerprint">';
			foreach ( $fingerprints as $fp ) {
				$selected = ( $fp === $selected_fingerprint ) ? ' selected' : '';
				$label = (string) $fp;
				if ( $active_fingerprint !== '' && $fp === $active_fingerprint ) {
					$label .= ' (' . esc_html__( 'Active', 'vibecode-deploy' ) . ')';
				}
				echo '<option value="' . esc_attr( $fp ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></td></tr>';
			echo '</table>';
			echo '<p><input type="submit" class="button" name="vibecode_deploy_preflight" value="' . esc_attr__( 'Run Preflight', 'vibecode-deploy' ) . '" /></p>';
			echo '</form>';

			// Show preflight results if available (even if items array is empty)
			// Note: $preflight is set during POST processing above, so it persists for this page render
			if ( is_array( $preflight ) ) {
				// Check for critical errors first (always show, even if items is empty)
				if ( isset( $preflight['errors'] ) && is_array( $preflight['errors'] ) && ! empty( $preflight['errors'] ) ) {
					echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Deployment blocked:', 'vibecode-deploy' ) . '</strong></p>';
					echo '<ul style="list-style: disc; padding-left: 22px;">';
					foreach ( $preflight['errors'] as $error ) {
						echo '<li>' . esc_html( $error ) . '</li>';
					}
					echo '</ul>';
					echo '<p>' . esc_html__( 'Please fix these issues before attempting to deploy.', 'vibecode-deploy' ) . '</p></div>';
				}

				// Show success/error notice
				if ( isset( $preflight['pages_total'] ) && $preflight['pages_total'] > 0 ) {
					echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Preflight completed successfully.', 'vibecode-deploy' ) . '</strong> ' . sprintf( esc_html__( 'Found %d page(s) to process.', 'vibecode-deploy' ), (int) $preflight['pages_total'] ) . '</p></div>';
				} elseif ( isset( $preflight['pages_total'] ) && $preflight['pages_total'] === 0 ) {
					echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Preflight completed, but no pages were found.', 'vibecode-deploy' ) . '</strong></p></div>';
				} else {
					// Preflight ran but pages_total is not set (shouldn't happen, but show feedback)
					echo '<div class="notice notice-info is-dismissible"><p><strong>' . esc_html__( 'Preflight completed.', 'vibecode-deploy' ) . '</strong></p></div>';
				}

				// Show message if preflight completed but no items to display
				if ( empty( $preflight['items'] ) && isset( $preflight['pages_total'] ) && $preflight['pages_total'] > 0 ) {
					echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__( 'Preflight completed, but no page details are available.', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'This may indicate an issue with the staging build structure.', 'vibecode-deploy' ) . '</p></div>';
				}

				// Show preflight details if items exist
				if ( ! empty( $preflight['items'] ) ) {
					$create = 0;
				$update = 0;
				$skip = 0;
				$warnings_total = 0;
				foreach ( $preflight['items'] as $it ) {
					$act = (string) ( $it['action'] ?? '' );
					if ( $act === 'create' ) {
						$create++;
					} elseif ( $act === 'update' ) {
						$update++;
					} elseif ( $act === 'skip' ) {
						$skip++;
					}
					$warnings_total += (int) ( $it['warnings_count'] ?? 0 );
				}

				echo '<h3>' . esc_html__( 'Preflight Result', 'vibecode-deploy' ) . '</h3>';
				/* translators: %1$s: Creates count, %2$s: Updates count, %3$s: Skips count, %4$s: Warnings count */
				echo '<p>' . sprintf( esc_html__( 'Creates: %1$s Updates: %2$s Skips (unowned): %3$s Warnings: %4$s', 'vibecode-deploy' ), '<strong>' . esc_html( (string) $create ) . '</strong>', '<strong>' . esc_html( (string) $update ) . '</strong>', '<strong>' . esc_html( (string) $skip ) . '</strong>', '<strong>' . esc_html( (string) $warnings_total ) . '</strong>' ) . '</p>';

				
				$template_parts = isset( $preflight['template_parts'] ) && is_array( $preflight['template_parts'] ) ? $preflight['template_parts'] : array();
				$template_items = isset( $template_parts['items'] ) && is_array( $template_parts['items'] ) ? $template_parts['items'] : array();
				if ( ! empty( $template_items ) ) {
					$tp_create = 0;
					$tp_update = 0;
					$tp_skip = 0;
					foreach ( $template_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$act = (string) ( $it['action'] ?? '' );
						if ( $act === 'create' ) {
							$tp_create++;
						} elseif ( $act === 'update' ) {
							$tp_update++;
						} elseif ( $act === 'skip' ) {
							$tp_skip++;
						}
					}
					echo '<p>Template Parts — Creates: <strong>' . esc_html( (string) $tp_create ) . '</strong> Updates: <strong>' . esc_html( (string) $tp_update ) . '</strong> Skips (unowned): <strong>' . esc_html( (string) $tp_skip ) . '</strong></p>';
					
					echo '<details><summary>Select Template Parts to Deploy</summary>';
					echo '<p>';
					echo '<button type="button" class="button" id="vibecode-deploy-select-all-template-parts">' . esc_html__( 'Select All', 'vibecode-deploy' ) . '</button> ';
					echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-template-parts">' . esc_html__( 'Deselect All', 'vibecode-deploy' ) . '</button>';
					echo '</p>';
					echo '<table class="widefat striped" style="max-width: 900px;">';
					echo '<thead><tr><th style="width: 30px;"><input type="checkbox" id="vibecode-deploy-template-parts-select-all" checked /></th><th>Slug</th><th>Action</th><th>Source</th></tr></thead><tbody>';
					foreach ( $template_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$slug = (string) ( $it['slug'] ?? '' );
						$act = (string) ( $it['action'] ?? '' );
						$file = (string) ( $it['file'] ?? '' );
						echo '<tr>';
						echo '<td><input type="checkbox" name="vibecode_deploy_selected_template_parts[]" value="' . esc_attr( $slug ) . '" class="vibecode-deploy-template-part-checkbox" checked /></td>';
						echo '<td>' . esc_html( $slug ) . '</td>';
						echo '<td>' . esc_html( $act ) . '</td>';
						echo '<td><code>' . esc_html( wp_basename( $file ) ) . '</code></td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
					echo '</details>';
				}

				$templates = isset( $preflight['templates'] ) && is_array( $preflight['templates'] ) ? $preflight['templates'] : array();
				$templates_items = isset( $templates['items'] ) && is_array( $templates['items'] ) ? $templates['items'] : array();
				if ( ! empty( $templates_items ) ) {
					$t_create = 0;
					$t_update = 0;
					$t_skip = 0;
					foreach ( $templates_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$act = (string) ( $it['action'] ?? '' );
						if ( $act === 'create' ) {
							$t_create++;
						} elseif ( $act === 'update' ) {
							$t_update++;
						} elseif ( $act === 'skip' ) {
							$t_skip++;
						}
					}
					echo '<p>Templates — Creates: <strong>' . esc_html( (string) $t_create ) . '</strong> Updates: <strong>' . esc_html( (string) $t_update ) . '</strong> Skips (unowned): <strong>' . esc_html( (string) $t_skip ) . '</strong></p>';
					
					echo '<details><summary>Select Templates to Deploy</summary>';
					echo '<p>';
					echo '<button type="button" class="button" id="vibecode-deploy-select-all-templates">' . esc_html__( 'Select All', 'vibecode-deploy' ) . '</button> ';
					echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-templates">' . esc_html__( 'Deselect All', 'vibecode-deploy' ) . '</button>';
					echo '</p>';
					echo '<table class="widefat striped" style="max-width: 900px;">';
					echo '<thead><tr><th style="width: 30px;"><input type="checkbox" id="vibecode-deploy-templates-select-all" checked /></th><th>Slug</th><th>Action</th><th>Source</th></tr></thead><tbody>';
					foreach ( $templates_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$slug = (string) ( $it['slug'] ?? '' );
						$act = (string) ( $it['action'] ?? '' );
						$file = (string) ( $it['file'] ?? '' );
						echo '<tr>';
						echo '<td><input type="checkbox" name="vibecode_deploy_selected_templates[]" value="' . esc_attr( $slug ) . '" class="vibecode-deploy-template-checkbox" checked /></td>';
						echo '<td>' . esc_html( $slug ) . '</td>';
						echo '<td>' . esc_html( $act ) . '</td>';
						echo '<td><code>' . esc_html( wp_basename( $file ) ) . '</code></td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
					echo '</details>';
				}

				$auto_parts = isset( $preflight['auto_template_parts'] ) && is_array( $preflight['auto_template_parts'] ) ? $preflight['auto_template_parts'] : array();
				$auto_items = isset( $auto_parts['items'] ) && is_array( $auto_parts['items'] ) ? $auto_parts['items'] : array();
				if ( ! empty( $auto_items ) ) {
					$ap_create = 0;
					$ap_update = 0;
					$ap_skip = 0;
					foreach ( $auto_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$act = (string) ( $it['action'] ?? '' );
						if ( $act === 'create' ) {
							$ap_create++;
						} elseif ( $act === 'update' ) {
							$ap_update++;
						} elseif ( $act === 'skip' ) {
							$ap_skip++;
						}
					}
					echo '<p>Auto Template Parts (from home.html) — Creates: <strong>' . esc_html( (string) $ap_create ) . '</strong> Updates: <strong>' . esc_html( (string) $ap_update ) . '</strong> Skips (unowned): <strong>' . esc_html( (string) $ap_skip ) . '</strong></p>';
					
					echo '<details><summary>Show auto template part actions</summary>';
					echo '<table class="widefat striped" style="max-width: 900px;">';
					echo '<thead><tr><th>Slug</th><th>Action</th><th>Source</th></tr></thead><tbody>';
					foreach ( $auto_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$slug = (string) ( $it['slug'] ?? '' );
						$act = (string) ( $it['action'] ?? '' );
						$file = (string) ( $it['file'] ?? '' );
						echo '<tr>';
						echo '<td>' . esc_html( $slug ) . '</td>';
						echo '<td>' . esc_html( $act ) . '</td>';
						echo '<td><code>' . esc_html( $file ) . '</code></td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
					echo '</details>';
				}

				echo '<details open><summary>Select Pages to Deploy</summary>';
				echo '<p>';
				echo '<button type="button" class="button" id="vibecode-deploy-select-all-pages">' . esc_html__( 'Select All', 'vibecode-deploy' ) . '</button> ';
				echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-pages">' . esc_html__( 'Deselect All', 'vibecode-deploy' ) . '</button> ';
				echo '<span id="vibecode-deploy-page-count" style="margin-left: 10px; font-weight: bold;">' . esc_html__( '0 pages selected', 'vibecode-deploy' ) . '</span>';
				echo '</p>';
				echo '<table class="widefat striped" style="max-width: 900px;">';
				echo '<thead><tr><th style="width: 30px;"><input type="checkbox" id="vibecode-deploy-pages-select-all" checked /></th><th>Slug</th><th>Action</th><th>Warnings</th><th>Source</th></tr></thead><tbody>';
				foreach ( $preflight['items'] as $it ) {
					$slug = (string) ( $it['slug'] ?? '' );
					$act = (string) ( $it['action'] ?? '' );
					$file = (string) ( $it['file'] ?? '' );
					$warnings = isset( $it['warnings'] ) && is_array( $it['warnings'] ) ? $it['warnings'] : array();
					$warnings_count = (int) ( $it['warnings_count'] ?? 0 );
					echo '<tr>';
					echo '<td><input type="checkbox" name="vibecode_deploy_selected_pages[]" value="' . esc_attr( $slug ) . '" class="vibecode-deploy-page-checkbox" checked /></td>';
					echo '<td>' . esc_html( $slug ) . '</td>';
					echo '<td>' . esc_html( $act ) . '</td>';
					echo '<td>';
					if ( $warnings_count > 0 ) {
						echo '<details><summary>' . esc_html( (string) $warnings_count ) . '</summary>';
						echo '<ul style="list-style: disc; padding-left: 22px;">';
						foreach ( $warnings as $w ) {
							if ( ! is_string( $w ) || $w === '' ) {
								continue;
							}
							echo '<li>' . esc_html( $w ) . '</li>';
						}
						echo '</ul>';
						echo '</details>';
					} else {
						echo '0';
					}
					echo '</td>';
					echo '<td><code>' . esc_html( wp_basename( $file ) ) . '</code></td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
				echo '</details>';

				// CSS/JS File Selection
				$css_files = isset( $preflight['css_files'] ) && is_array( $preflight['css_files'] ) ? $preflight['css_files'] : array();
				$js_files = isset( $preflight['js_files'] ) && is_array( $preflight['js_files'] ) ? $preflight['js_files'] : array();
				if ( ! empty( $css_files ) || ! empty( $js_files ) ) {
					echo '<h3 style="margin-top: 24px;">' . esc_html__( 'Select CSS/JS Files to Deploy', 'vibecode-deploy' ) . '</h3>';
					if ( ! empty( $css_files ) ) {
						echo '<details open><summary>CSS Files (' . count( $css_files ) . ')</summary>';
						echo '<p>';
						echo '<button type="button" class="button" id="vibecode-deploy-select-all-css">' . esc_html__( 'Select All CSS', 'vibecode-deploy' ) . '</button> ';
						echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-css">' . esc_html__( 'Deselect All CSS', 'vibecode-deploy' ) . '</button>';
						echo '</p>';
						echo '<ul style="list-style: none; padding-left: 0;">';
						foreach ( $css_files as $css_file ) {
							$css_file = (string) $css_file;
							echo '<li style="margin-bottom: 5px;">';
							echo '<label><input type="checkbox" name="vibecode_deploy_selected_css[]" value="' . esc_attr( $css_file ) . '" class="vibecode-deploy-css-checkbox" checked /> ';
							echo '<code>' . esc_html( $css_file ) . '</code></label>';
							echo '</li>';
						}
						echo '</ul>';
						echo '</details>';
					}
					if ( ! empty( $js_files ) ) {
						echo '<details open><summary>JS Files (' . count( $js_files ) . ')</summary>';
						echo '<p>';
						echo '<button type="button" class="button" id="vibecode-deploy-select-all-js">' . esc_html__( 'Select All JS', 'vibecode-deploy' ) . '</button> ';
						echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-js">' . esc_html__( 'Deselect All JS', 'vibecode-deploy' ) . '</button>';
						echo '</p>';
						echo '<ul style="list-style: none; padding-left: 0;">';
						foreach ( $js_files as $js_file ) {
							$js_file = (string) $js_file;
							echo '<li style="margin-bottom: 5px;">';
							echo '<label><input type="checkbox" name="vibecode_deploy_selected_js[]" value="' . esc_attr( $js_file ) . '" class="vibecode-deploy-js-checkbox" checked /> ';
							echo '<code>' . esc_html( $js_file ) . '</code></label>';
							echo '</li>';
						}
						echo '</ul>';
						echo '</details>';
					}
				}

				// Theme File Selection
				$theme_files = isset( $preflight['theme_files'] ) && is_array( $preflight['theme_files'] ) ? $preflight['theme_files'] : array();
				if ( ! empty( $theme_files ) ) {
					echo '<h3 style="margin-top: 24px;">' . esc_html__( 'Select Theme Files to Deploy', 'vibecode-deploy' ) . '</h3>';
					echo '<details open><summary>Theme Files (' . count( $theme_files ) . ')</summary>';
					echo '<p>';
					echo '<button type="button" class="button" id="vibecode-deploy-select-all-theme-files">' . esc_html__( 'Select All', 'vibecode-deploy' ) . '</button> ';
					echo '<button type="button" class="button" id="vibecode-deploy-deselect-all-theme-files">' . esc_html__( 'Deselect All', 'vibecode-deploy' ) . '</button>';
					echo '</p>';
					echo '<ul style="list-style: none; padding-left: 0;">';
					$has_functions = false;
					$acf_files = array();
					foreach ( $theme_files as $theme_file ) {
						$theme_file = (string) $theme_file;
						if ( $theme_file === 'functions.php' ) {
							$has_functions = true;
						} elseif ( strpos( $theme_file, 'acf-json/' ) === 0 ) {
							$acf_files[] = $theme_file;
						}
					}
					if ( $has_functions ) {
						echo '<li style="margin-bottom: 5px;">';
						echo '<label><input type="checkbox" name="vibecode_deploy_selected_theme_files[]" value="functions.php" class="vibecode-deploy-theme-file-checkbox" checked /> ';
						echo '<code>functions.php</code></label>';
						echo '</li>';
					}
					if ( ! empty( $acf_files ) ) {
						echo '<li style="margin-bottom: 5px;">';
						echo '<label><input type="checkbox" name="vibecode_deploy_selected_theme_files[]" value="acf-json/*.json" class="vibecode-deploy-theme-file-checkbox" checked /> ';
						echo '<code>acf-json/*.json</code> (' . count( $acf_files ) . ' files)</label>';
						echo '</li>';
					}
					echo '</ul>';
					echo '</details>';
				}

				echo '<h2 class="title" style="margin-top: 24px;">3) Deploy Build</h2>';
				echo '<form method="post" id="vibecode-deploy-import-form">';
				wp_nonce_field( 'vibecode_deploy_run_import', 'vibecode_deploy_run_import_nonce' );
				echo '<input type="hidden" name="vibecode_deploy_fingerprint" value="' . esc_attr( $selected_fingerprint ) . '" />';
				echo '<label style="display:block; margin: 8px 0;">';
				echo '<input type="checkbox" name="vibecode_deploy_set_front_page" value="1" checked /> Set Home page as front page (if present)';
				echo '</label>';
				echo '<label style="display:block; margin: 8px 0;">';
				echo '<input type="hidden" name="vibecode_deploy_deploy_template_parts" value="0" />';
				echo '<input type="checkbox" name="vibecode_deploy_deploy_template_parts" value="1" checked /> Extract header/footer from home.html into template parts (recommended for block themes)';
				echo '</label>';
				echo '<label style="display:block; margin: 8px 0;">';
				echo '<input type="hidden" name="vibecode_deploy_generate_404_template" value="0" />';
				echo '<input type="checkbox" name="vibecode_deploy_generate_404_template" value="1" checked /> Generate/update 404 template (uses header/footer above; optional header-404/footer-404 if present)';
				echo '</label>';
				echo '<label style="display:block; margin: 8px 0;">';
				echo '<input type="checkbox" name="vibecode_deploy_force_claim_unowned" value="1" /> Force claim/overwrite existing pages that are not owned by this project (writes Vibe Code Deploy meta)';
				echo '</label>';
				echo '<label style="display:block; margin: 8px 0;">';
				echo '<input type="checkbox" name="vibecode_deploy_force_claim_templates" value="1" /> Force claim/overwrite template parts/templates that are not owned by this project (default off)';
				echo '</label>';
				echo '<label style="display:block; margin: 8px 0;">';
				echo '<input type="checkbox" name="vibecode_deploy_validate_cpt_shortcodes" value="1" /> Optional: Validate CPT shortcode coverage (recommended sections only; may warn or fail based on strict mode)';
				echo '</label>';
				echo '<p><input type="submit" class="button button-primary" name="vibecode_deploy_run_import" value="Deploy Build" id="vibecode-deploy-submit" /></p>';
				echo '</form>';
				
				// JavaScript for selection management
				?>
				<script>
				(function() {
					'use strict';
					
					function updatePageCount() {
						var checkboxes = document.querySelectorAll('.vibecode-deploy-page-checkbox:checked');
						var count = checkboxes.length;
						var total = document.querySelectorAll('.vibecode-deploy-page-checkbox').length;
						var countEl = document.getElementById('vibecode-deploy-page-count');
						if (countEl) {
							countEl.textContent = count + ' of ' + total + ' pages selected';
						}
					}
					
					// Page selection
					var pagesSelectAll = document.getElementById('vibecode-deploy-pages-select-all');
					var pagesSelectAllBtn = document.getElementById('vibecode-deploy-select-all-pages');
					var pagesDeselectAllBtn = document.getElementById('vibecode-deploy-deselect-all-pages');
					
					if (pagesSelectAll) {
						pagesSelectAll.addEventListener('change', function() {
							document.querySelectorAll('.vibecode-deploy-page-checkbox').forEach(function(cb) {
								cb.checked = pagesSelectAll.checked;
							});
							updatePageCount();
						});
					}
					
					if (pagesSelectAllBtn) {
						pagesSelectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-page-checkbox').forEach(function(cb) {
								cb.checked = true;
							});
							if (pagesSelectAll) pagesSelectAll.checked = true;
							updatePageCount();
						});
					}
					
					if (pagesDeselectAllBtn) {
						pagesDeselectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-page-checkbox').forEach(function(cb) {
								cb.checked = false;
							});
							if (pagesSelectAll) pagesSelectAll.checked = false;
							updatePageCount();
						});
					}
					
					document.querySelectorAll('.vibecode-deploy-page-checkbox').forEach(function(cb) {
						cb.addEventListener('change', updatePageCount);
					});
					
					// Template selection
					var templatesSelectAll = document.getElementById('vibecode-deploy-templates-select-all');
					var templatesSelectAllBtn = document.getElementById('vibecode-deploy-select-all-templates');
					var templatesDeselectAllBtn = document.getElementById('vibecode-deploy-deselect-all-templates');
					
					if (templatesSelectAll) {
						templatesSelectAll.addEventListener('change', function() {
							document.querySelectorAll('.vibecode-deploy-template-checkbox').forEach(function(cb) {
								cb.checked = templatesSelectAll.checked;
							});
						});
					}
					
					if (templatesSelectAllBtn) {
						templatesSelectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-template-checkbox').forEach(function(cb) {
								cb.checked = true;
							});
							if (templatesSelectAll) templatesSelectAll.checked = true;
						});
					}
					
					if (templatesDeselectAllBtn) {
						templatesDeselectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-template-checkbox').forEach(function(cb) {
								cb.checked = false;
							});
							if (templatesSelectAll) templatesSelectAll.checked = false;
						});
					}
					
					// Template part selection
					var templatePartsSelectAll = document.getElementById('vibecode-deploy-template-parts-select-all');
					var templatePartsSelectAllBtn = document.getElementById('vibecode-deploy-select-all-template-parts');
					var templatePartsDeselectAllBtn = document.getElementById('vibecode-deploy-deselect-all-template-parts');
					
					if (templatePartsSelectAll) {
						templatePartsSelectAll.addEventListener('change', function() {
							document.querySelectorAll('.vibecode-deploy-template-part-checkbox').forEach(function(cb) {
								cb.checked = templatePartsSelectAll.checked;
							});
						});
					}
					
					if (templatePartsSelectAllBtn) {
						templatePartsSelectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-template-part-checkbox').forEach(function(cb) {
								cb.checked = true;
							});
							if (templatePartsSelectAll) templatePartsSelectAll.checked = true;
						});
					}
					
					if (templatePartsDeselectAllBtn) {
						templatePartsDeselectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-template-part-checkbox').forEach(function(cb) {
								cb.checked = false;
							});
							if (templatePartsSelectAll) templatePartsSelectAll.checked = false;
						});
					}
					
					// CSS selection
					var cssSelectAllBtn = document.getElementById('vibecode-deploy-select-all-css');
					var cssDeselectAllBtn = document.getElementById('vibecode-deploy-deselect-all-css');
					
					if (cssSelectAllBtn) {
						cssSelectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-css-checkbox').forEach(function(cb) {
								cb.checked = true;
							});
						});
					}
					
					if (cssDeselectAllBtn) {
						cssDeselectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-css-checkbox').forEach(function(cb) {
								cb.checked = false;
							});
						});
					}
					
					// JS selection
					var jsSelectAllBtn = document.getElementById('vibecode-deploy-select-all-js');
					var jsDeselectAllBtn = document.getElementById('vibecode-deploy-deselect-all-js');
					
					if (jsSelectAllBtn) {
						jsSelectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-js-checkbox').forEach(function(cb) {
								cb.checked = true;
							});
						});
					}
					
					if (jsDeselectAllBtn) {
						jsDeselectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-js-checkbox').forEach(function(cb) {
								cb.checked = false;
							});
						});
					}
					
					// Theme file selection
					var themeFilesSelectAllBtn = document.getElementById('vibecode-deploy-select-all-theme-files');
					var themeFilesDeselectAllBtn = document.getElementById('vibecode-deploy-deselect-all-theme-files');
					
					if (themeFilesSelectAllBtn) {
						themeFilesSelectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-theme-file-checkbox').forEach(function(cb) {
								cb.checked = true;
							});
						});
					}
					
					if (themeFilesDeselectAllBtn) {
						themeFilesDeselectAllBtn.addEventListener('click', function() {
							document.querySelectorAll('.vibecode-deploy-theme-file-checkbox').forEach(function(cb) {
								cb.checked = false;
							});
						});
					}
					
					// Initial page count
					updatePageCount();
				})();
				</script>
				<?php
			}

			if ( is_array( $import_result ) ) {
				echo '<h3>Deploy Result</h3>';
				echo '<p>';
				echo 'Created: <strong>' . esc_html( (string) ( $import_result['created'] ?? 0 ) ) . '</strong> '; 
				echo 'Updated: <strong>' . esc_html( (string) ( $import_result['updated'] ?? 0 ) ) . '</strong> '; 
				echo 'Skipped: <strong>' . esc_html( (string) ( $import_result['skipped'] ?? 0 ) ) . '</strong> '; 
				echo 'Errors: <strong>' . esc_html( (string) ( $import_result['errors'] ?? 0 ) ) . '</strong>'; 
				echo '</p>';

				$template_result = isset( $import_result['template_result'] ) && is_array( $import_result['template_result'] ) ? $import_result['template_result'] : array();
				$debug = isset( $template_result['debug'] ) && is_array( $template_result['debug'] ) ? $template_result['debug'] : array();
				if ( ! empty( $template_result ) ) {
					echo '<div class="card" style="max-width: 1100px;">';
					echo '<h2 class="title">Template deploy diagnostics</h2>';
					$supported = ! empty( $debug['supported'] );
					$theme = isset( $debug['theme'] ) && is_string( $debug['theme'] ) ? $debug['theme'] : '';
					echo '<p>Supported: <strong>' . esc_html( $supported ? 'Yes' : 'No' ) . '</strong></p>';
					if ( $theme !== '' ) {
						echo '<p>Theme (stylesheet): <code>' . esc_html( $theme ) . '</code></p>';
					}
					if ( array_key_exists( 'post_type_wp_template', $debug ) ) {
						echo '<p>post_type_exists(wp_template): <code>' . esc_html( ! empty( $debug['post_type_wp_template'] ) ? 'true' : 'false' ) . '</code></p>';
					}
					if ( array_key_exists( 'post_type_wp_template_part', $debug ) ) {
						echo '<p>post_type_exists(wp_template_part): <code>' . esc_html( ! empty( $debug['post_type_wp_template_part'] ) ? 'true' : 'false' ) . '</code></p>';
					}

					if ( $supported ) {
						$tpl_parts_url = admin_url( 'edit.php?post_type=wp_template_part' );
						$tpl_url = admin_url( 'edit.php?post_type=wp_template' );
						echo '<p>';
						echo '<a class="button" href="' . esc_url( $tpl_parts_url ) . '">View Template Parts (posts)</a> ';
						echo '<a class="button" href="' . esc_url( $tpl_url ) . '">View Templates (posts)</a>';
						echo '</p>';
					} else {
						echo '<div class="notice notice-warning"><p>This site does not appear to support block templates. If you are using a classic theme (no Site Editor), templates/template parts will not appear.</p></div>';
					}

					$error_messages = isset( $template_result['error_messages'] ) && is_array( $template_result['error_messages'] ) ? $template_result['error_messages'] : array();
					if ( ! empty( $error_messages ) ) {
						echo '<details><summary>Template errors</summary>';
						echo '<ul style="list-style: disc; padding-left: 22px;">';
						foreach ( $error_messages as $m ) {
							if ( ! is_string( $m ) || $m === '' ) {
								continue;
							}
							echo '<li>' . esc_html( $m ) . '</li>';
						}
						echo '</ul>';
						echo '</details>';
					}

					echo '</div>';
				}
			}
			}
		}
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Detect project_slug from JSON file inside zip without extracting.
	 *
	 * @param string $zip_path Path to zip file.
	 * @return string Detected project_slug or empty string if not found.
	 */
	private static function detect_project_slug_from_zip( string $zip_path ): string {
		if ( ! file_exists( $zip_path ) || ! class_exists( '\\ZipArchive' ) ) {
			return '';
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $zip_path );
		if ( $opened !== true ) {
			return '';
		}

		$config_filename = 'vibecode-deploy-shortcodes.json';
		$possible_paths = array(
			$config_filename,
			'vibecode-deploy-staging/' . $config_filename,
		);

		$project_slug = '';
		foreach ( $possible_paths as $path ) {
			$index = $zip->locateName( $path );
			if ( $index !== false ) {
				$content = $zip->getFromIndex( $index );
				if ( is_string( $content ) && $content !== '' ) {
					$decoded = json_decode( $content, true );
					if ( is_array( $decoded ) && isset( $decoded['project_slug'] ) && is_string( $decoded['project_slug'] ) ) {
						$project_slug = sanitize_key( trim( $decoded['project_slug'] ) );
						break;
					}
				}
			}
		}

		$zip->close();
		return $project_slug;
	}

	public static function rollback_last_deploy(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}

		check_admin_referer( 'vibecode_deploy_rollback_last_deploy' );

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		if ( $project_slug === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-import&failed=missing_project' ) );
			exit;
		}

		$fingerprint = ManifestService::get_last_deploy_fingerprint( $project_slug );
		if ( $fingerprint === '' || ! ManifestService::has_manifest( $project_slug, $fingerprint ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-import&failed=no_manifest' ) );
			exit;
		}

		$result = RollbackService::rollback_deploy( $project_slug, $fingerprint );
		$ok = is_array( $result ) && ! empty( $result['ok'] );
		if ( ! $ok ) {
			Logger::error( 'Rollback last deploy failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'result' => $result ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-import&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$deleted = (int) ( is_array( $result ) ? ( $result['deleted'] ?? 0 ) : 0 );
		$restored = (int) ( is_array( $result ) ? ( $result['restored'] ?? 0 ) : 0 );

		Logger::info( 'Rollback last deploy complete.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'result' => $result ), $project_slug );
		wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-import&rolled_back=' . rawurlencode( $fingerprint ) . '&rolled_back_deleted=' . (string) $deleted . '&rolled_back_restored=' . (string) $restored ) );
		exit;
	}
}
