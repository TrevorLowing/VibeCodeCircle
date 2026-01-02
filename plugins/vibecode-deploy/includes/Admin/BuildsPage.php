<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Logger;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\BuildService;
use VibeCode\Deploy\Services\EnvService;
use VibeCode\Deploy\Services\ManifestService;
use VibeCode\Deploy\Services\RollbackService;

defined( 'ABSPATH' ) || exit;

final class BuildsPage {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_vibecode_deploy_delete_build', array( __CLASS__, 'delete_build' ) );
		add_action( 'admin_post_vibecode_deploy_download_build', array( __CLASS__, 'download_build' ) );
		add_action( 'admin_post_vibecode_deploy_set_active_build', array( __CLASS__, 'set_active_build' ) );
		add_action( 'admin_post_vibecode_deploy_bulk_delete_builds', array( __CLASS__, 'bulk_delete_builds' ) );
		add_action( 'admin_post_vibecode_deploy_rollback_build', array( __CLASS__, 'rollback_build' ) );
		add_action( 'admin_post_vibecode_deploy_view_manifest', array( __CLASS__, 'view_manifest' ) );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			__( 'Builds', 'vibecode-deploy' ),
			__( 'Builds', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-builds',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fingerprints = $project_slug !== '' ? BuildService::list_build_fingerprints( $project_slug ) : array();
		$active = $project_slug !== '' ? BuildService::get_active_fingerprint( $project_slug ) : '';

		$deleted = isset( $_GET['deleted'] ) ? sanitize_text_field( (string) $_GET['deleted'] ) : '';
		$bulk_deleted = isset( $_GET['bulk_deleted'] ) ? (int) $_GET['bulk_deleted'] : 0;
		$rolled_back = isset( $_GET['rolled_back'] ) ? sanitize_text_field( (string) $_GET['rolled_back'] ) : '';
		$rolled_back_deleted = isset( $_GET['rolled_back_deleted'] ) ? (int) $_GET['rolled_back_deleted'] : 0;
		$rolled_back_restored = isset( $_GET['rolled_back_restored'] ) ? (int) $_GET['rolled_back_restored'] : 0;
		$failed = isset( $_GET['failed'] ) ? sanitize_text_field( (string) $_GET['failed'] ) : '';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';
		EnvService::render_admin_notice();

		if ( $deleted !== '' ) {
			/* translators: %s: Fingerprint */
			echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Build deleted: %s', 'vibecode-deploy' ), '<code>' . esc_html( $deleted ) . '</code>' ) . '</p></div>';
		} elseif ( $bulk_deleted > 0 ) {
			/* translators: %s: Count */
			echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Builds deleted: %s', 'vibecode-deploy' ), '<strong>' . esc_html( (string) $bulk_deleted ) . '</strong>' ) . '</p></div>';
		} elseif ( $rolled_back !== '' ) {
			/* translators: %1$s: Fingerprint, %2$s: Restored count, %3$s: Deleted count */
			echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( 'Rollback complete: %1$s. Restored: %2$s. Deleted: %3$s.', 'vibecode-deploy' ), '<code>' . esc_html( $rolled_back ) . '</code>', '<strong>' . esc_html( (string) $rolled_back_restored ) . '</strong>', '<strong>' . esc_html( (string) $rolled_back_deleted ) . '</strong>' ) . '</p></div>';
		} elseif ( $failed !== '' ) {
			/* translators: %s: Error message */
			echo '<div class="notice notice-error"><p>' . sprintf( esc_html__( 'Build action failed: %s. Check', 'vibecode-deploy' ), '<code>' . esc_html( $failed ) . '</code>' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Logs', 'vibecode-deploy' ) . '.</p></div>';
		}

		if ( $project_slug === '' ) {
			echo '<p><strong>' . esc_html__( 'Project Slug is required.', 'vibecode-deploy' ) . '</strong> ' . esc_html__( 'Set it in', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Configuration', 'vibecode-deploy' ) . '.</p>';
			echo '</div>';
			return;
		}

		if ( empty( $fingerprints ) ) {
			echo '<div class="card" style="max-width: 1100px;">';
			echo '<p>' . esc_html__( 'No staging builds found yet. Upload a staging zip in', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Deploy', 'vibecode-deploy' ) . '.</p>';
			echo '</div>';
			echo '</div>';
			return;
		}

		echo '<div class="card" style="max-width: 1100px;">';
		echo '<h2 class="title">' . esc_html__( 'Builds', 'vibecode-deploy' ) . '</h2>';
		/* translators: %s: Project slug */
		echo '<p>' . sprintf( esc_html__( 'Project: %s', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="vibecode_deploy_bulk_delete_builds" />';
		wp_nonce_field( 'vibecode_deploy_bulk_delete_builds', 'vibecode_deploy_bulk_nonce' );
		$delete_confirm = esc_js( __( 'Delete selected builds? This cannot be undone.', 'vibecode-deploy' ) );
		echo '<p><input type="submit" class="button" value="' . esc_attr__( 'Delete Selected', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $delete_confirm . '\');" /></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th style="width: 26px;"><input type="checkbox" id="vibecode-deploy-builds-select-all" aria-label="' . esc_attr__( 'Select all builds', 'vibecode-deploy' ) . '" /></th><th>' . esc_html__( 'Fingerprint', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Status', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Pages', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Files', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Size', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Actions', 'vibecode-deploy' ) . '</th></tr></thead><tbody>';

		foreach ( $fingerprints as $fp ) {
			$fp = (string) $fp;
			$is_active = ( $active !== '' && $fp === $active );
			$has_manifest = ManifestService::has_manifest( $project_slug, $fp );
			$stats = BuildService::get_build_stats( $project_slug, $fp );
			$pages = (int) ( $stats['pages'] ?? 0 );
			$files = (int) ( $stats['files'] ?? 0 );
			$bytes = (int) ( $stats['bytes'] ?? 0 );
			$download_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=vibecode_deploy_download_build&fingerprint=' . rawurlencode( $fp ) ),
				'vibecode_deploy_download_build_' . $fp
			);
			$delete_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=vibecode_deploy_delete_build&fingerprint=' . rawurlencode( $fp ) ),
				'vibecode_deploy_delete_build_' . $fp
			);
			$set_active_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=vibecode_deploy_set_active_build&fingerprint=' . rawurlencode( $fp ) ),
				'vibecode_deploy_set_active_build_' . $fp
			);
			$rollback_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=vibecode_deploy_rollback_build&fingerprint=' . rawurlencode( $fp ) ),
				'vibecode_deploy_rollback_build_' . $fp
			);
			$view_manifest_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=vibecode_deploy_view_manifest&fingerprint=' . rawurlencode( $fp ) ),
				'vibecode_deploy_view_manifest_' . $fp
			);

			echo '<tr>';
			echo '<td><input type="checkbox" name="fingerprints[]" value="' . esc_attr( $fp ) . '" /></td>';
			echo '<td><code>' . esc_html( $fp ) . '</code></td>';
			echo '<td>' . ( $is_active ? '<strong>' . esc_html__( 'Active', 'vibecode-deploy' ) . '</strong>' : '' ) . '</td>';
			echo '<td>' . esc_html( (string) $pages ) . '</td>';
			echo '<td>' . esc_html( (string) $files ) . '</td>';
			echo '<td>' . esc_html( (string) size_format( $bytes ) ) . '</td>';
			echo '<td>';
			echo '<a class="button" href="' . esc_url( $download_url ) . '">' . esc_html__( 'Download', 'vibecode-deploy' ) . '</a> ';
			if ( ! $is_active ) {
				echo '<a class="button" href="' . esc_url( $set_active_url ) . '">' . esc_html__( 'Set Active', 'vibecode-deploy' ) . '</a> ';
			}
			if ( $has_manifest ) {
				$rollback_confirm = esc_js( __( 'Rollback this deploy? This will restore updated pages and delete pages created by this deploy.', 'vibecode-deploy' ) );
				echo '<a class="button" href="' . esc_url( $rollback_url ) . '" onclick="return confirm(\'' . $rollback_confirm . '\');">' . esc_html__( 'Rollback', 'vibecode-deploy' ) . '</a> ';
				echo '<a class="button" href="' . esc_url( $view_manifest_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View Manifest', 'vibecode-deploy' ) . '</a> ';
			}
			$delete_confirm = esc_js( __( 'Delete this build? This cannot be undone.', 'vibecode-deploy' ) );
			echo '<a class="button" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . $delete_confirm . '\');">' . esc_html__( 'Delete', 'vibecode-deploy' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '<script>(function(){\'use strict\';var a=document.getElementById(\'vibecode-deploy-builds-select-all\');if(!a){return;}var s=document.querySelectorAll(\'input[name="fingerprints[]"]\');var u=function(){var c=0;for(var i=0;i<s.length;i++){if(s[i].checked){c++;}}if(c===0){a.checked=false;a.indeterminate=false;}else if(c===s.length){a.checked=true;a.indeterminate=false;}else{a.checked=false;a.indeterminate=true;}};a.addEventListener(\'change\',function(){for(var i=0;i<s.length;i++){s[i].checked=a.checked;}u();});for(var i=0;i<s.length;i++){s[i].addEventListener(\'change\',u);}u();})();</script>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	public static function rollback_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fingerprint = isset( $_GET['fingerprint'] ) ? sanitize_text_field( (string) $_GET['fingerprint'] ) : '';
		if ( $project_slug === '' || $fingerprint === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=missing' ) );
			exit;
		}

		check_admin_referer( 'vibecode_deploy_rollback_build_' . $fingerprint );

		$result = RollbackService::rollback_deploy( $project_slug, $fingerprint );
		$ok = is_array( $result ) && ! empty( $result['ok'] );
		if ( ! $ok ) {
			Logger::error( 'Rollback failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'result' => $result ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$deleted = (int) ( is_array( $result ) ? ( $result['deleted'] ?? 0 ) : 0 );
		$restored = (int) ( is_array( $result ) ? ( $result['restored'] ?? 0 ) : 0 );
		Logger::info( 'Rollback complete.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'result' => $result ), $project_slug );
		wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&rolled_back=' . rawurlencode( $fingerprint ) . '&rolled_back_deleted=' . (string) $deleted . '&rolled_back_restored=' . (string) $restored ) );
		exit;
	}

	public static function view_manifest(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fingerprint = isset( $_GET['fingerprint'] ) ? sanitize_text_field( (string) $_GET['fingerprint'] ) : '';
		if ( $project_slug === '' || $fingerprint === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=missing' ) );
			exit;
		}

		check_admin_referer( 'vibecode_deploy_view_manifest_' . $fingerprint );

		$manifest = ManifestService::read_manifest( $project_slug, $fingerprint );
		if ( ! is_array( $manifest ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$json = wp_json_encode( $manifest, JSON_PRETTY_PRINT );
		if ( ! is_string( $json ) ) {
			$json = '';
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Manifest', 'vibecode-deploy' ) . '</h1>';
		/* translators: %s: Project slug */
		echo '<p>' . sprintf( esc_html__( 'Project: %s', 'vibecode-deploy' ), '<code>' . esc_html( $project_slug ) . '</code>' ) . '</p>';
		/* translators: %s: Fingerprint */
		echo '<p>' . sprintf( esc_html__( 'Fingerprint: %s', 'vibecode-deploy' ), '<code>' . esc_html( $fingerprint ) . '</code>' ) . '</p>';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=vibecode-deploy-builds' ) ) . '">← ' . esc_html__( 'Back to Builds', 'vibecode-deploy' ) . '</a></p>';
		echo '<pre style="max-width: 1100px; white-space: pre-wrap; word-wrap: break-word;">' . esc_html( $json ) . '</pre>';
		echo '</div>';
		exit;
	}

	public static function bulk_delete_builds(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		check_admin_referer( 'vibecode_deploy_bulk_delete_builds', 'vibecode_deploy_bulk_nonce' );

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fps = isset( $_POST['fingerprints'] ) && is_array( $_POST['fingerprints'] ) ? $_POST['fingerprints'] : array();
		$fps = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $fps ) ) ) );

		if ( $project_slug === '' || empty( $fps ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=missing' ) );
			exit;
		}

		$deleted = 0;
		foreach ( $fps as $fingerprint ) {
			if ( ! is_string( $fingerprint ) || $fingerprint === '' ) {
				continue;
			}

			$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
			$project_dir = BuildService::get_project_staging_dir( $project_slug );

			if ( ! is_dir( $build_root ) ) {
				$deleted++;
				continue;
			}

			$active = BuildService::get_active_fingerprint( $project_slug );
			if ( $active !== '' && $active === $fingerprint ) {
				BuildService::clear_active_fingerprint( $project_slug );
			}

			$build_real = realpath( $build_root );
			$project_real = realpath( $project_dir );
			if ( ! is_string( $build_real ) || ! is_string( $project_real ) || strpos( $build_real, $project_real ) !== 0 ) {
				Logger::error( 'Bulk delete build blocked: path validation failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'build_root' => $build_root ), $project_slug );
				continue;
			}

			$ok = self::delete_dir_recursive( $build_real );
			if ( ! $ok ) {
				Logger::error( 'Bulk delete build failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'build_root' => $build_real ), $project_slug );
				continue;
			}

			$deleted++;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&bulk_deleted=' . (string) (int) $deleted ) );
		exit;
	}

	public static function delete_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fingerprint = isset( $_GET['fingerprint'] ) ? sanitize_text_field( (string) $_GET['fingerprint'] ) : '';
		if ( $project_slug === '' || $fingerprint === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=missing' ) );
			exit;
		}

		check_admin_referer( 'vibecode_deploy_delete_build_' . $fingerprint );

		$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
		$project_dir = BuildService::get_project_staging_dir( $project_slug );

		if ( ! is_dir( $build_root ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&deleted=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$active = BuildService::get_active_fingerprint( $project_slug );
		if ( $active !== '' && $active === $fingerprint ) {
			BuildService::clear_active_fingerprint( $project_slug );
		}

		$build_real = realpath( $build_root );
		$project_real = realpath( $project_dir );
		if ( ! is_string( $build_real ) || ! is_string( $project_real ) || strpos( $build_real, $project_real ) !== 0 ) {
			Logger::error( 'Delete build blocked: path validation failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'build_root' => $build_root ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$ok = self::delete_dir_recursive( $build_real );
		if ( ! $ok ) {
			Logger::error( 'Delete build failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'build_root' => $build_real ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		Logger::info( 'Build deleted.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint ), $project_slug );
		wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&deleted=' . rawurlencode( $fingerprint ) ) );
		exit;
	}

	public static function set_active_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fingerprint = isset( $_GET['fingerprint'] ) ? sanitize_text_field( (string) $_GET['fingerprint'] ) : '';
		if ( $project_slug === '' || $fingerprint === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=missing' ) );
			exit;
		}

		check_admin_referer( 'vibecode_deploy_set_active_build_' . $fingerprint );

		$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
		if ( ! is_dir( $build_root ) ) {
			Logger::error( 'Set active build failed: build not found.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		BuildService::set_active_fingerprint( $project_slug, $fingerprint );
		Logger::info( 'Active build set.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint ), $project_slug );
		wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds' ) );
		exit;
	}

	public static function download_build(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}

		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		$fingerprint = isset( $_GET['fingerprint'] ) ? sanitize_text_field( (string) $_GET['fingerprint'] ) : '';
		if ( $project_slug === '' || $fingerprint === '' ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=missing' ) );
			exit;
		}

		check_admin_referer( 'vibecode_deploy_download_build_' . $fingerprint );

		$build_root = BuildService::build_root_path( $project_slug, $fingerprint );
		$project_dir = BuildService::get_project_staging_dir( $project_slug );

		if ( ! is_dir( $build_root ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$build_real = realpath( $build_root );
		$project_real = realpath( $project_dir );
		if ( ! is_string( $build_real ) || ! is_string( $project_real ) || strpos( $build_real, $project_real ) !== 0 ) {
			Logger::error( 'Download build blocked: path validation failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint, 'build_root' => $build_root ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$tmp = wp_tempnam( 'vibecode-deploy-build-' . $project_slug . '-' . $fingerprint . '.zip' );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			Logger::error( 'Download build failed: unable to create temp file.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		$ok = self::zip_build_root( $build_real, $tmp );
		if ( ! $ok ) {
			@unlink( $tmp );
			Logger::error( 'Download build failed: zip creation failed.', array( 'project_slug' => $project_slug, 'fingerprint' => $fingerprint ), $project_slug );
			wp_safe_redirect( admin_url( 'admin.php?page=vibecode-deploy-builds&failed=' . rawurlencode( $fingerprint ) ) );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="vibecode-deploy-staging-' . rawurlencode( $project_slug ) . '-' . rawurlencode( $fingerprint ) . '.zip"' );
		header( 'Content-Length: ' . (string) filesize( $tmp ) );

		readfile( $tmp );
		@unlink( $tmp );
		exit;
	}

	private static function delete_dir_recursive( string $dir ): bool {
		$dir = rtrim( $dir, '/\\' );
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return true;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			$path = (string) $item->getPathname();
			if ( $item->isDir() ) {
				if ( ! @rmdir( $path ) ) {
					return false;
				}
			} else {
				if ( ! @unlink( $path ) ) {
					return false;
				}
			}
		}

		return @rmdir( $dir );
	}

	private static function zip_build_root( string $build_root, string $zip_path ): bool {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			return false;
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $zip_path, \ZipArchive::OVERWRITE );
		if ( $opened !== true ) {
			return false;
		}

		$build_root = rtrim( $build_root, '/\\' );
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $build_root, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $iterator as $item ) {
			if ( ! $item->isFile() ) {
				continue;
			}

			$full = (string) $item->getPathname();
			$rel = substr( $full, strlen( $build_root ) + 1 );
			$rel = str_replace( '\\', '/', $rel );
			if ( $rel === '' ) {
				continue;
			}

			$zip_rel = 'vibecode-deploy-staging/' . ltrim( $rel, '/' );
			$zip->addFile( $full, $zip_rel );
		}

		$zip->close();
		return true;
	}
}
