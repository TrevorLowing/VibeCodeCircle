<?php

namespace VibeCode\Deploy\Admin;

defined( 'ABSPATH' ) || exit;

final class HelpPage {
	private static $instance = null;

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	public function add_admin_menu(): void {
		add_submenu_page(
			'vibecode-deploy',
			__( 'Help & Documentation', 'vibecode-deploy' ),
			__( 'Help', 'vibecode-deploy' ),
			'manage_options',
			'vibecode-deploy-help',
			array( $this, 'render' )
		);
	}

	public function enqueue_styles( string $hook ): void {
		if ( 'vibecode-deploy_page_vibecode-deploy-help' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'vibecode-deploy-help', plugins_url( 'assets/css/help.css', VIBECODE_DEPLOY_PLUGIN_FILE ), array(), '1.0.0' );
	}

	public function render(): void {
		?>
		<div class="wrap vibecode-deploy-help">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<div class="vcd-help-grid">
				<div class="vcd-help-section">
					<h2><?php echo esc_html__( '📖 Getting Started', 'vibecode-deploy' ); ?></h2>
					<p><?php echo esc_html__( 'Vibe Code Deploy converts static HTML websites into WordPress Gutenberg blocks. Here\'s how to use it:', 'vibecode-deploy' ); ?></p>
					
					<ol class="vcd-steps">
						<li><strong><?php echo esc_html__( 'Prepare your HTML:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Organize your HTML files with CSS, JS, and resources in the correct structure.', 'vibecode-deploy' ); ?></li>
						<li><strong><?php echo esc_html__( 'Create a staging ZIP:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Package your HTML files and assets into a ZIP file.', 'vibecode-deploy' ); ?></li>
						<li><strong><?php echo esc_html__( 'Upload and Deploy:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Use the Import Build page to upload and deploy your site.', 'vibecode-deploy' ); ?></li>
						<li><strong><?php echo esc_html__( 'Verify:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Use the System Status below to ensure everything is working correctly.', 'vibecode-deploy' ); ?></li>
					</ol>
				</div>

				<div class="vcd-help-section">
					<h2><?php echo esc_html__( '⚙️ System Status', 'vibecode-deploy' ); ?></h2>
					<p><?php echo esc_html__( 'Check if your system is properly configured for Vibe Code Deploy:', 'vibecode-deploy' ); ?></p>
					
					<?php $this->render_system_status(); ?>
				</div>

				<div class="vcd-help-section">
					<h2><?php echo esc_html__( '📁 Required File Structure', 'vibecode-deploy' ); ?></h2>
					<p><?php echo esc_html__( 'Your staging ZIP should follow this structure:', 'vibecode-deploy' ); ?></p>
					
					<pre class="vcd-code-block">
staging-zip/
├── home.html              # Required: Main page
├── about.html             # Other pages
├── services.html
├── css/
│   ├── styles.css         # Main stylesheet
│   └── icons.css          # Icon fonts
├── js/
│   └── main.js            # JavaScript files
├── resources/
│   ├── images/            # Images and other assets
│   └── documents/
└── rules.md               # Optional: Custom rules
					</pre>
				</div>

				<div class="vcd-help-section">
					<h2><?php echo esc_html__( '🎨 HTML Structure Guidelines', 'vibecode-deploy' ); ?></h2>
					<p><?php echo esc_html__( 'For best results, follow these HTML structure rules:', 'vibecode-deploy' ); ?></p>
					
					<div class="vcd-rules">
						<div class="vcd-rule">
							<h4><?php echo esc_html__( 'Skip Link (Required)', 'vibecode-deploy' ); ?></h4>
							<code>&lt;a class="{project-prefix}-skip-link" href="#main"&gt;<?php echo esc_html__( 'Skip to main content', 'vibecode-deploy' ); ?>&lt;/a&gt;</code>
							<p><?php echo esc_html__( 'Must be the first element in the body. Replace', 'vibecode-deploy' ); ?> <code>{project-prefix}</code> <?php echo esc_html__( 'with your configured class prefix.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-rule">
							<h4><?php echo esc_html__( 'Header Structure', 'vibecode-deploy' ); ?></h4>
							<code>&lt;header class="{project-prefix}-header" role="banner"&gt;</code>
							<p><?php echo esc_html__( 'Elements repeated on every page (like top bars) should be INSIDE the header.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-rule">
							<h4><?php echo esc_html__( 'Main Content', 'vibecode-deploy' ); ?></h4>
							<code>&lt;main id="main" class="{project-prefix}-main" role="main"&gt;</code>
							<p><?php echo esc_html__( 'Only the content inside main will be imported as page content.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-rule">
							<h4><?php echo esc_html__( 'CSS Classes', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'Use BEM naming convention with project prefix (e.g.,', 'vibecode-deploy' ); ?> <code>my-site-*</code>). <?php echo esc_html__( 'Configure the prefix in plugin settings.', 'vibecode-deploy' ); ?></p>
						</div>
					</div>
				</div>

				<div class="vcd-help-section">
					<h2><?php echo esc_html__( '🔧 Troubleshooting', 'vibecode-deploy' ); ?></h2>
					
					<div class="vcd-issues">
						<div class="vcd-issue">
							<h4><?php echo esc_html__( '❌ Assets not loading (404 errors)', 'vibecode-deploy' ); ?></h4>
							<p><strong><?php echo esc_html__( 'Cause:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Assets weren\'t copied to plugin folder.', 'vibecode-deploy' ); ?></p>
							<p><strong><?php echo esc_html__( 'Solution:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Re-run the import. The plugin automatically copies assets to', 'vibecode-deploy' ); ?> <code>/wp-content/plugins/vibecode-deploy/assets/</code></p>
						</div>
						
						<div class="vcd-issue">
							<h4><?php echo esc_html__( '❌ Header/Footer not showing', 'vibecode-deploy' ); ?></h4>
							<p><strong><?php echo esc_html__( 'Cause:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Template parts not created or theme not configured.', 'vibecode-deploy' ); ?></p>
							<p><strong><?php echo esc_html__( 'Solution:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Ensure "Extract header/footer from home.html" is checked during import. The plugin automatically configures the theme.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-issue">
							<h4><?php echo esc_html__( '❌ Styling not working', 'vibecode-deploy' ); ?></h4>
							<p><strong><?php echo esc_html__( 'Cause:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'CSS not enqueued by theme.', 'vibecode-deploy' ); ?></p>
							<p><strong><?php echo esc_html__( 'Solution:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'The plugin automatically updates the theme to enqueue assets. Check if Etch mode is enabled in Appearance → Customize.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-issue">
							<h4><?php echo esc_html__( '❌ Blank home page', 'vibecode-deploy' ); ?></h4>
							<p><strong><?php echo esc_html__( 'Cause:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'The plugin does not set the front page automatically. WordPress must have a front page configured.', 'vibecode-deploy' ); ?></p>
							<p><strong><?php echo esc_html__( 'Solution:', 'vibecode-deploy' ); ?></strong> <?php echo esc_html__( 'Go to Settings → Reading. Set "Front page displays" to "A static page" and choose your Home page.', 'vibecode-deploy' ); ?></p>
						</div>
					</div>
				</div>

				<div class="vcd-help-section">
					<h2><?php echo esc_html__( '📋 Feature Reference', 'vibecode-deploy' ); ?></h2>
					
					<div class="vcd-features">
						<div class="vcd-feature">
							<h4><?php echo esc_html__( 'Import Build', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'Upload and deploy staging ZIP files. Includes preflight validation and deployment options.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-feature">
							<h4><?php echo esc_html__( 'Templates', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'Manage header/footer template parts and block templates. Purge plugin-owned templates.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-feature">
							<h4><?php echo esc_html__( 'Builds', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'View deployment history, fingerprints, and rollback to previous versions.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-feature">
							<h4><?php echo esc_html__( 'Settings', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'Configure project slug, class prefix, and validation options.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-feature">
							<h4><?php echo esc_html__( 'Rules Pack', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'Download project-specific rules and configuration files.', 'vibecode-deploy' ); ?></p>
						</div>
						
						<div class="vcd-feature">
							<h4><?php echo esc_html__( 'Logs', 'vibecode-deploy' ); ?></h4>
							<p><?php echo esc_html__( 'View deployment logs, errors, and debugging information.', 'vibecode-deploy' ); ?></p>
						</div>
					</div>
				</div>

				<div class="vcd-help-section">
					<h2>🔗 CLI Commands</h2>
					<p>Use WP-CLI for automated deployments:</p>
					
					<pre class="vcd-code-block">
# List available builds
wp vibecode-deploy list-builds

# Deploy a build
wp vibecode-deploy deploy --project=mysite --fingerprint=abc123

# Rollback to previous build
wp vibecode-deploy rollback --project=mysite --to=previous

# Purge templates
wp vibecode-deploy purge --type=template-parts
					</pre>
				</div>

				<div class="vcd-help-section">
					<h2>💡 Tips & Best Practices</h2>
					
					<ul class="vcd-tips">
						<li><strong>Test locally first:</strong> Always test your HTML structure before deploying.</li>
						<li><strong>Use preflight:</strong> Run preflight checks to catch issues early.</li>
						<li><strong>Backup before deployment:</strong> Always have a recent backup.</li>
						<li><strong>Check environment:</strong> Verify Etch plugin and theme are active.</li>
						<li><strong>Monitor logs:</strong> Check the Logs page for any errors during deployment.</li>
						<li><strong>Use rollback:</strong> If something goes wrong, rollback immediately.</li>
					</ul>
				</div>

				<div class="vcd-help-section">
					<h2>🆘 Support</h2>
					<p>If you need help with Vibe Code Deploy:</p>
					
					<ul>
						<li>Check the <a href="https://github.com/VibeCodeCircle/vibecode-deploy" target="_blank">GitHub repository</a></li>
						<li>Review the <a href="https://github.com/VibeCodeCircle/vibecode-deploy/issues" target="_blank">issue tracker</a></li>
						<li>Enable debug mode in WordPress for detailed error logs</li>
						<li>Check the Logs page in this plugin for deployment-specific errors</li>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_system_status(): void {
		$wp_version = get_bloginfo('version');
		$wp_ok = version_compare( $wp_version, '6.0', '>=' );
		$php_ok = version_compare( PHP_VERSION, '8.0', '>=' );
		$etch_plugin_active = is_plugin_active('etch/etch.php');
		$etch_theme_active = get_template() === 'etch-theme';
		$block_templates_supported = post_type_exists('wp_template') && post_type_exists('wp_template_part');
		$assets_dir_exists = is_dir(WP_PLUGIN_DIR . '/vibecode-deploy/assets');
		$uploads_writable = wp_is_writable(WP_CONTENT_DIR . '/uploads');
		$memory_limit = (int) ini_get('memory_limit');
		$memory_ok = $memory_limit >= 256;

		$checks = array(
			array(
				'label' => __( 'WordPress Version', 'vibecode-deploy' ),
				'status' => $wp_ok ? 'good' : 'warning',
				'message' => $wp_version . ($wp_ok ? ' (' . __( 'OK', 'vibecode-deploy' ) . ')' : ' (' . __( '6.0+ recommended', 'vibecode-deploy' ) . ')'),
			),
			array(
				'label' => __( 'PHP Version', 'vibecode-deploy' ),
				'status' => $php_ok ? 'good' : 'warning',
				'message' => PHP_VERSION . ($php_ok ? ' (' . __( 'OK', 'vibecode-deploy' ) . ')' : ' (' . __( '8.0+ recommended', 'vibecode-deploy' ) . ')'),
			),
			array(
				'label' => __( 'Etch Plugin', 'vibecode-deploy' ),
				'status' => $etch_plugin_active ? 'good' : 'warning',
				'message' => $etch_plugin_active ? __( 'Active', 'vibecode-deploy' ) : __( 'Not active (recommended)', 'vibecode-deploy' ),
			),
			array(
				'label' => __( 'Etch Theme', 'vibecode-deploy' ),
				'status' => $etch_theme_active ? 'good' : 'error',
				'message' => $etch_theme_active ? __( 'Active', 'vibecode-deploy' ) : __( 'Not active (required)', 'vibecode-deploy' ),
			),
			array(
				'label' => __( 'Block Template Support', 'vibecode-deploy' ),
				'status' => $block_templates_supported ? 'good' : 'error',
				'message' => $block_templates_supported ? __( 'Supported', 'vibecode-deploy' ) : __( 'Not supported', 'vibecode-deploy' ),
			),
			array(
				'label' => __( 'Plugin Assets Folder', 'vibecode-deploy' ),
				'status' => $assets_dir_exists ? 'good' : 'warning',
				'message' => $assets_dir_exists ? __( 'Exists', 'vibecode-deploy' ) : __( 'Not created yet', 'vibecode-deploy' ),
			),
			array(
				'label' => __( 'Uploads Folder Writable', 'vibecode-deploy' ),
				'status' => $uploads_writable ? 'good' : 'error',
				'message' => $uploads_writable ? __( 'Writable', 'vibecode-deploy' ) : __( 'Not writable', 'vibecode-deploy' ),
			),
			array(
				'label' => __( 'Memory Limit', 'vibecode-deploy' ),
				'status' => $memory_ok ? 'good' : 'warning',
				'message' => ini_get('memory_limit') . ' (' . ($memory_ok ? __( 'OK', 'vibecode-deploy' ) : __( '256M recommended', 'vibecode-deploy' )) . ')',
			),
		);

		echo '<table class="vcd-status-table">';
		echo '<thead><tr><th>' . esc_html__( 'Item', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Status', 'vibecode-deploy' ) . '</th><th>' . esc_html__( 'Details', 'vibecode-deploy' ) . '</th></tr></thead>';
		echo '<tbody>';
		
		foreach ($checks as $check) {
			$status_icon = $check['status'] === 'good' ? '✅' : ($check['status'] === 'warning' ? '⚠️' : '❌');
			$status_class = 'vcd-status-' . $check['status'];
			
			echo '<tr class="' . esc_attr($status_class) . '">';
			echo '<td>' . esc_html($check['label']) . '</td>';
			echo '<td>' . $status_icon . '</td>';
			echo '<td>' . esc_html($check['message']) . '</td>';
			echo '</tr>';
		}
		
		echo '</tbody></table>';
		
		// Overall status
		$good_count = array_filter($checks, fn($c) => $c['status'] === 'good');
		$warning_count = array_filter($checks, fn($c) => $c['status'] === 'warning');
		$error_count = array_filter($checks, fn($c) => $c['status'] === 'error');
		
		echo '<div class="vcd-overall-status">';
		if (count($error_count) > 0) {
			/* translators: %s: Error count */
			echo '<p class="vcd-status-error">❌ ' . sprintf( esc_html__( '%s critical issues found - Deployment may fail', 'vibecode-deploy' ), count($error_count) ) . '</p>';
		} elseif (count($warning_count) > 0) {
			/* translators: %s: Warning count */
			echo '<p class="vcd-status-warning">⚠️ ' . sprintf( esc_html__( '%s warnings - Some features may not work', 'vibecode-deploy' ), count($warning_count) ) . '</p>';
		} else {
			echo '<p class="vcd-status-good">✅ ' . esc_html__( 'All systems ready for deployment', 'vibecode-deploy' ) . '</p>';
		}
		echo '</div>';
	}
}
