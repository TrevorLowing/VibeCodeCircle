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
			__( 'Templates', 'vibecode-deploy' ),
			__( 'Templates', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-templates',
			array( __CLASS__, 'render' )
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden.', 'vibecode-deploy' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php
			if ( ( isset( $_POST['vibecode_deploy_purge_templates'] ) || isset( $_POST['vibecode_deploy_purge_template_parts'] ) ) && check_admin_referer( 'vibecode_deploy_purge_templates' ) ) {
				self::handle_purge();
			}
			?>
			<div class="card" style="max-width: 1100px;">
				<h2 class="title"><?php echo esc_html__( 'Manage Plugin-Owned Templates', 'vibecode-deploy' ); ?></h2>
				<p><?php echo esc_html__( 'This page lists all block templates and template parts owned by Vibe Code Deploy for the current project. You can purge them to clean up before a fresh deploy.', 'vibecode-deploy' ); ?></p>
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
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Project Slug not set. Configure it in', 'vibecode-deploy' ) . ' ' . esc_html__( 'Vibe Code Deploy', 'vibecode-deploy' ) . ' → ' . esc_html__( 'Settings', 'vibecode-deploy' ) . '.</p></div>';
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
			echo '<div class="card"><p>' . esc_html__( 'No plugin-owned template parts found.', 'vibecode-deploy' ) . '</p></div>';
			return;
		}

		echo '<div class="card">';
		echo '<h2 class="title">' . esc_html__( 'Template Parts (wp_template_part)', 'vibecode-deploy' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'vibecode_deploy_purge_templates' );
		$purge_parts_confirm = esc_js( __( 'Delete all template parts owned by this project? This cannot be undone.', 'vibecode-deploy' ) );
		echo '<p><input type="submit" name="vibecode_deploy_purge_template_parts" class="button button-secondary" value="' . esc_attr__( 'Purge All Template Parts', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $purge_parts_confirm . '\');" /></p>';
		echo '</form>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'ID', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Slug', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Title', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Status', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Fingerprint', 'vibecode-deploy' ) . '</th></tr></thead><tbody>';
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
			echo '<div class="card"><p>' . esc_html__( 'No plugin-owned templates found.', 'vibecode-deploy' ) . '</p></div>';
			return;
		}

		echo '<div class="card">';
		echo '<h2 class="title">' . esc_html__( 'Templates (wp_template)', 'vibecode-deploy' ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'vibecode_deploy_purge_templates' );
		$purge_templates_confirm = esc_js( __( 'Delete all templates owned by this project? This cannot be undone.', 'vibecode-deploy' ) );
		echo '<p><input type="submit" name="vibecode_deploy_purge_templates" class="button button-secondary" value="' . esc_attr__( 'Purge All Templates', 'vibecode-deploy' ) . '" onclick="return confirm(\'' . $purge_templates_confirm . '\');" /></p>';
		echo '</form>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'ID', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Slug', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Title', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Status', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Fingerprint', 'vibecode-deploy' ) . '</th></tr></thead><tbody>';
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
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Project Slug not set.', 'vibecode-deploy' ) . '</p></div>';
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
			/* translators: %s: Count */
			$msg[] = sprintf( __( 'Purged %s template parts.', 'vibecode-deploy' ), (int) $deleted_parts );
		}
		if ( $purge_templates ) {
			/* translators: %s: Count */
			$msg[] = sprintf( __( 'Purged %s templates.', 'vibecode-deploy' ), (int) $deleted_templates );
		}
		echo '<div class="notice notice-success"><p>' . esc_html( implode( ' ', $msg ) ) . '</p></div>';
	}
}
