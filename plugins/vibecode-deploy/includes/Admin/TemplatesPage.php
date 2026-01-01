<?php

namespace VibeCode\Deploy\Admin;

use VibeCode\Deploy\Importer;
use VibeCode\Deploy\Settings;
use VibeCode\Deploy\Services\TemplateService;
use VibeCode\Deploy\Logger;

defined( 'ABSPATH' ) || exit;

final class TemplatesPage {
	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
	}

	public static function add_admin_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			'Templates',
			'Templates',
			'manage_options',
			'vibecode-deploy-templates',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden.' );
		}
		?>
		<div class="wrap">
			<h1>Vibe Code Deploy Templates</h1>
			<?php
			if ( ( isset( $_POST['vibecode_deploy_purge_templates'] ) || isset( $_POST['vibecode_deploy_purge_template_parts'] ) ) && check_admin_referer( 'vibecode_deploy_purge_templates' ) ) {
				self::handle_purge();
			}
			?>
			<div class="card" style="max-width: 1100px;">
				<h2 class="title">Manage Plugin-Owned Templates</h2>
				<p>This page lists all block templates and template parts owned by Vibe Code Deploy for the current project. You can purge them to clean up before a fresh deploy.</p>
			</div>

			<?php self::render_template_parts_table(); ?>
			<?php self::render_templates_table(); ?>
		</div>
		<?php
	}

	private static function render_template_parts_table(): void {
		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		if ( $project_slug === '' ) {
			echo '<div class="notice notice-warning"><p>Project Slug not set. Configure it in Vibe Code Deploy → Settings.</p></div>';
			return;
		}

		global $wpdb;
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_name, p.post_title, p.post_status, pm.meta_value as fingerprint 
				FROM {$wpdb->posts} p 
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s 
				WHERE p.post_type = %s AND p.post_status != 'trash' 
				AND EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm2 
					WHERE pm2.post_id = p.ID AND pm2.meta_key = %s AND pm2.meta_value = %s
				) 
				ORDER BY p.post_name",
				Importer::META_FINGERPRINT,
				'wp_template_part',
				Importer::META_PROJECT_SLUG,
				$project_slug
			)
		);

		if ( empty( $posts ) ) {
			echo '<div class="card"><p>No plugin-owned template parts found.</p></div>';
			return;
		}

		echo '<div class="card">';
		echo '<h2 class="title">Template Parts (wp_template_part)</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'vibecode_deploy_purge_templates' );
		echo '<p><input type="submit" name="vibecode_deploy_purge_template_parts" class="button button-secondary" value="Purge All Template Parts" onclick="return confirm(\'Delete all template parts owned by this project? This cannot be undone.\');" /></p>';
		echo '</form>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>ID</th><th>Slug</th><th>Title</th><th>Status</th><th>Fingerprint</th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			echo '<tr>';
			echo '<td>' . (int) $p->ID . '</td>';
			echo '<td><code>' . esc_html( $p->post_name ) . '</code></td>';
			echo '<td>' . esc_html( $p->post_title ) . '</td>';
			echo '<td>' . esc_html( $p->post_status ) . '</td>';
			echo '<td><code>' . esc_html( $p->fingerprint ?? '' ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	private static function render_templates_table(): void {
		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		if ( $project_slug === '' ) {
			return;
		}

		global $wpdb;
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.post_name, p.post_title, p.post_status, pm.meta_value as fingerprint 
				FROM {$wpdb->posts} p 
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s 
				WHERE p.post_type = %s AND p.post_status != 'trash' 
				AND EXISTS (
					SELECT 1 FROM {$wpdb->postmeta} pm2 
					WHERE pm2.post_id = p.ID AND pm2.meta_key = %s AND pm2.meta_value = %s
				) 
				ORDER BY p.post_name",
				Importer::META_FINGERPRINT,
				'wp_template',
				Importer::META_PROJECT_SLUG,
				$project_slug
			)
		);

		if ( empty( $posts ) ) {
			echo '<div class="card"><p>No plugin-owned templates found.</p></div>';
			return;
		}

		echo '<div class="card">';
		echo '<h2 class="title">Templates (wp_template)</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'vibecode_deploy_purge_templates' );
		echo '<p><input type="submit" name="vibecode_deploy_purge_templates" class="button button-secondary" value="Purge All Templates" onclick="return confirm(\'Delete all templates owned by this project? This cannot be undone.\');" /></p>';
		echo '</form>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>ID</th><th>Slug</th><th>Title</th><th>Status</th><th>Fingerprint</th></tr></thead><tbody>';
		foreach ( $posts as $p ) {
			echo '<tr>';
			echo '<td>' . (int) $p->ID . '</td>';
			echo '<td><code>' . esc_html( $p->post_name ) . '</code></td>';
			echo '<td>' . esc_html( $p->post_title ) . '</td>';
			echo '<td>' . esc_html( $p->post_status ) . '</td>';
			echo '<td><code>' . esc_html( $p->fingerprint ?? '' ) . '</code></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '</div>';
	}

	private static function handle_purge(): void {
		$settings = Settings::get_all();
		$project_slug = (string) $settings['project_slug'];
		if ( $project_slug === '' ) {
			echo '<div class="notice notice-error"><p>Project Slug not set.</p></div>';
			return;
		}

		global $wpdb;
		$deleted_parts = 0;
		$deleted_templates = 0;

		// Determine which purge action was requested
		$purge_parts = isset( $_POST['vibecode_deploy_purge_template_parts'] );
		$purge_templates = isset( $_POST['vibecode_deploy_purge_templates'] );

		// Purge template parts only if requested
		if ( $purge_parts ) {
			$part_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p 
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s 
					WHERE p.post_type = %s AND p.post_status != 'trash'",
					Importer::META_PROJECT_SLUG,
					$project_slug,
					'wp_template_part'
				)
			);
			if ( ! empty( $part_ids ) ) {
				foreach ( $part_ids as $id ) {
					$res = wp_delete_post( (int) $id, true );
					if ( $res !== false && $res !== null ) {
						$deleted_parts++;
					}
				}
			}
		}

		// Purge templates only if requested
		if ( $purge_templates ) {
			$tpl_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT p.ID FROM {$wpdb->posts} p 
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s AND pm.meta_value = %s 
					WHERE p.post_type = %s AND p.post_status != 'trash'",
					Importer::META_PROJECT_SLUG,
					$project_slug,
					'wp_template'
				)
			);
			if ( ! empty( $tpl_ids ) ) {
				foreach ( $tpl_ids as $id ) {
					$res = wp_delete_post( (int) $id, true );
					if ( $res !== false && $res !== null ) {
						$deleted_templates++;
					}
				}
			}
		}

		Logger::info( 'Templates purged by user.', array(
			'project_slug' => $project_slug,
			'deleted_parts' => $deleted_parts,
			'deleted_templates' => $deleted_templates,
			'purge_parts' => $purge_parts,
			'purge_templates' => $purge_templates,
		), $project_slug );

		$msg = array();
		if ( $purge_parts ) {
			$msg[] = 'Purged ' . (int) $deleted_parts . ' template parts.';
		}
		if ( $purge_templates ) {
			$msg[] = 'Purged ' . (int) $deleted_templates . ' templates.';
		}
		echo '<div class="notice notice-success"><p>' . esc_html( implode( ' ', $msg ) ) . '</p></div>';
	}
}
