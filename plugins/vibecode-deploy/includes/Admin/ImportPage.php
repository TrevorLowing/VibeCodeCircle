<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Staging;
use VibeCode\Deploy\Services\BuildService;
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

		if ( isset( $_POST['vibecode_deploy_upload_zip'] ) ) {
			check_admin_referer( 'vibecode_deploy_upload_zip', 'vibecode_deploy_nonce' );

			if ( $settings['project_slug'] === '' ) {
				$error = __( 'Project Slug is required.', 'vibecode-deploy' );
				Logger::error( 'Upload blocked: missing project slug.', array(), '' );
			} elseif ( $settings['class_prefix'] === '' ) {
				$error = __( 'Class Prefix is required.', 'vibecode-deploy' );
				Logger::error( 'Upload blocked: missing class prefix.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} elseif ( ! preg_match( '/^[a-z0-9-]+-$/', (string) $settings['class_prefix'] ) ) {
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
						Logger::info( 'Zip uploaded; extracting to staging.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
						$result = Staging::extract_zip_to_staging( (string) $upload['file'], (string) $settings['project_slug'] );
						@unlink( (string) $upload['file'] );

						if ( ! is_array( $result ) || empty( $result['ok'] ) ) {
							$error = is_array( $result ) ? (string) ( $result['error'] ?? 'Extraction failed.' ) : 'Extraction failed.';
							Logger::error( 'Extraction failed.', array( 'project_slug' => (string) $settings['project_slug'], 'error' => $error ), (string) $settings['project_slug'] );
						} else {
							$selected_fingerprint = (string) $result['fingerprint'];
							$notice = 'Staging uploaded: ' . esc_html( (string) $result['fingerprint'] ) . ' (' . esc_html( (string) $result['files'] ) . ' files)';
							Logger::info( 'Staging extracted.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => (string) $result['fingerprint'], 'files' => (int) ( $result['files'] ?? 0 ) ), (string) $settings['project_slug'] );
						}
					}
				}
			}
		}

		if ( isset( $_POST['vibecode_deploy_preflight'] ) ) {
			check_admin_referer( 'vibecode_deploy_preflight', 'vibecode_deploy_preflight_nonce' );
			$selected_fingerprint = isset( $_POST['vibecode_deploy_fingerprint'] ) ? sanitize_text_field( (string) $_POST['vibecode_deploy_fingerprint'] ) : '';
			if ( $settings['project_slug'] === '' ) {
				$error = 'Project Slug is required.';
				Logger::error( 'Preflight blocked: missing project slug.', array(), '' );
			} elseif ( $selected_fingerprint === '' ) {
				$error = 'Select a staging build.';
				Logger::error( 'Preflight blocked: missing fingerprint selection.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} else {
				$build_root = BuildService::build_root_path( (string) $settings['project_slug'], $selected_fingerprint );
				Logger::info( 'Preflight started.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint ), (string) $settings['project_slug'] );
				$preflight = Importer::preflight( (string) $settings['project_slug'], $build_root );
				if ( empty( $preflight['pages_total'] ) ) {
					$error = 'No pages found for the selected build.';
					Logger::error( 'Preflight found no pages.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint ), (string) $settings['project_slug'] );
				} else {
					Logger::info( 'Preflight complete.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'pages_total' => (int) ( $preflight['pages_total'] ?? 0 ) ), (string) $settings['project_slug'] );
				}
			}
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

			if ( $settings['project_slug'] === '' ) {
				$error = 'Project Slug is required.';
				Logger::error( 'Import blocked: missing project slug.', array(), '' );
			} elseif ( $settings['class_prefix'] === '' ) {
				$error = 'Class Prefix is required.';
				Logger::error( 'Import blocked: missing class prefix.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} elseif ( ! preg_match( '/^[a-z0-9-]+-$/', (string) $settings['class_prefix'] ) ) {
				$error = 'Class Prefix is invalid.';
				Logger::error( 'Import blocked: invalid class prefix.', array( 'project_slug' => (string) $settings['project_slug'], 'class_prefix' => (string) $settings['class_prefix'] ), (string) $settings['project_slug'] );
			} elseif ( $selected_fingerprint === '' ) {
				$error = 'Select a staging build.';
				Logger::error( 'Import blocked: missing fingerprint selection.', array( 'project_slug' => (string) $settings['project_slug'] ), (string) $settings['project_slug'] );
			} else {
				$build_root = BuildService::build_root_path( (string) $settings['project_slug'], $selected_fingerprint );
				Logger::info( 'Import started.', array( 'project_slug' => (string) $settings['project_slug'], 'fingerprint' => $selected_fingerprint, 'set_front_page' => (bool) $set_front_page, 'force_claim_unowned' => (bool) $force_claim_unowned, 'deploy_template_parts' => (bool) $deploy_template_parts, 'generate_404_template' => (bool) $generate_404_template, 'force_claim_templates' => (bool) $force_claim_templates, 'validate_cpt_shortcodes' => (bool) $validate_cpt_shortcodes ), (string) $settings['project_slug'] );
				$import_result = Importer::run_import( (string) $settings['project_slug'], $selected_fingerprint, $build_root, $set_front_page, $force_claim_unowned, $deploy_template_parts, $generate_404_template, $force_claim_templates, $validate_cpt_shortcodes );
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
			echo '<form method="post">';
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

			if ( is_array( $preflight ) && ! empty( $preflight['items'] ) ) {
				// Check for critical errors first
				if ( isset( $preflight['errors'] ) && is_array( $preflight['errors'] ) && ! empty( $preflight['errors'] ) ) {
					echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Deployment blocked:', 'vibecode-deploy' ) . '</strong></p>';
					echo '<ul style="list-style: disc; padding-left: 22px;">';
					foreach ( $preflight['errors'] as $error ) {
						echo '<li>' . esc_html( $error ) . '</li>';
					}
					echo '</ul>';
					echo '<p>' . esc_html__( 'Please fix these issues before attempting to deploy.', 'vibecode-deploy' ) . '</p></div>';
					return;
				}
				
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
					
					echo '<details><summary>Show template part actions</summary>';
					echo '<table class="widefat striped" style="max-width: 900px;">';
					echo '<thead><tr><th>Slug</th><th>Action</th><th>Source</th></tr></thead><tbody>';
					foreach ( $template_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$slug = (string) ( $it['slug'] ?? '' );
						$act = (string) ( $it['action'] ?? '' );
						$file = (string) ( $it['file'] ?? '' );
						echo '<tr>';
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
					
					echo '<details><summary>Show template actions</summary>';
					echo '<table class="widefat striped" style="max-width: 900px;">';
					echo '<thead><tr><th>Slug</th><th>Action</th><th>Source</th></tr></thead><tbody>';
					foreach ( $templates_items as $it ) {
						if ( ! is_array( $it ) ) {
							continue;
						}
						$slug = (string) ( $it['slug'] ?? '' );
						$act = (string) ( $it['action'] ?? '' );
						$file = (string) ( $it['file'] ?? '' );
						echo '<tr>';
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

				echo '<details><summary>Show page actions</summary>';
				echo '<table class="widefat striped" style="max-width: 900px;">';
				echo '<thead><tr><th>Slug</th><th>Action</th><th>Warnings</th><th>Source</th></tr></thead><tbody>';
				foreach ( $preflight['items'] as $it ) {
					$slug = (string) ( $it['slug'] ?? '' );
					$act = (string) ( $it['action'] ?? '' );
					$file = (string) ( $it['file'] ?? '' );
					$warnings = isset( $it['warnings'] ) && is_array( $it['warnings'] ) ? $it['warnings'] : array();
					$warnings_count = (int) ( $it['warnings_count'] ?? 0 );
					echo '<tr>';
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

				echo '<h2 class="title" style="margin-top: 24px;">3) Deploy Build</h2>';
				echo '<form method="post">';
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
				echo '<p><input type="submit" class="button button-primary" name="vibecode_deploy_run_import" value="Deploy Build" /></p>';
				echo '</form>';
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
		echo '</div>';

		echo '</div>';
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
