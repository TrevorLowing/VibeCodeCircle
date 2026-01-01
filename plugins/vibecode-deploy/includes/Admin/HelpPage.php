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
			'Help & Documentation',
			'Help',
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
			<h1>Vibe Code Deploy - Help & Documentation</h1>
			
			<div class="vcd-help-grid">
				<div class="vcd-help-section">
					<h2>📖 Getting Started</h2>
					<p>Vibe Code Deploy converts static HTML websites into WordPress Gutenberg blocks. Here's how to use it:</p>
					
					<ol class="vcd-steps">
						<li><strong>Prepare your HTML:</strong> Organize your HTML files with CSS, JS, and resources in the correct structure.</li>
						<li><strong>Create a staging ZIP:</strong> Package your HTML files and assets into a ZIP file.</li>
						<li><strong>Upload and Deploy:</strong> Use the Import Build page to upload and deploy your site.</li>
						<li><strong>Verify:</strong> Use the System Status below to ensure everything is working correctly.</li>
					</ol>
				</div>

				<div class="vcd-help-section">
					<h2>⚙️ System Status</h2>
					<p>Check if your system is properly configured for Vibe Code Deploy:</p>
					
					<?php $this->render_system_status(); ?>
				</div>

				<div class="vcd-help-section">
					<h2>📁 Required File Structure</h2>
					<p>Your staging ZIP should follow this structure:</p>
					
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
					<h2>🎨 HTML Structure Guidelines</h2>
					<p>For best results, follow these HTML structure rules:</p>
					
					<div class="vcd-rules">
						<div class="vcd-rule">
							<h4>Skip Link (Required)</h4>
							<code>&lt;a class="cfa-skip-link" href="#main"&gt;Skip to main content&lt;/a&gt;</code>
							<p>Must be the first element in the body.</p>
						</div>
						
						<div class="vcd-rule">
							<h4>Header Structure</h4>
							<code>&lt;header class="cfa-header" role="banner"&gt;</code>
							<p>Elements repeated on every page (like top bars) should be INSIDE the header.</p>
						</div>
						
						<div class="vcd-rule">
							<h4>Main Content</h4>
							<code>&lt;main id="main" class="cfa-main" role="main"&gt;</code>
							<p>Only the content inside main will be imported as page content.</p>
						</div>
						
						<div class="vcd-rule">
							<h4>CSS Classes</h4>
							<p>Use BEM naming convention with project prefix (e.g., <code>cfa-*</code>).</p>
						</div>
					</div>
				</div>

				<div class="vcd-help-section">
					<h2>🔧 Troubleshooting</h2>
					
					<div class="vcd-issues">
						<div class="vcd-issue">
							<h4>❌ Assets not loading (404 errors)</h4>
							<p><strong>Cause:</strong> Assets weren't copied to plugin folder.</p>
							<p><strong>Solution:</strong> Re-run the import. The plugin automatically copies assets to <code>/wp-content/plugins/vibecode-deploy/assets/</code></p>
						</div>
						
						<div class="vcd-issue">
							<h4>❌ Header/Footer not showing</h4>
							<p><strong>Cause:</strong> Template parts not created or theme not configured.</p>
							<p><strong>Solution:</strong> Ensure "Extract header/footer from home.html" is checked during import. The plugin automatically configures the theme.</p>
						</div>
						
						<div class="vcd-issue">
							<h4>❌ Styling not working</h4>
							<p><strong>Cause:</strong> CSS not enqueued by theme.</p>
							<p><strong>Solution:</strong> The plugin automatically updates the theme to enqueue assets. Check if Etch mode is enabled in Appearance → Customize.</p>
						</div>
						
						<div class="vcd-issue">
							<h4>❌ Blank home page</h4>
							<p><strong>Cause:</strong> Front page not set correctly.</p>
							<p><strong>Solution:</strong> Check Settings → Reading. Ensure "Front page displays" is set to "A static page" and Home is selected.</p>
						</div>
					</div>
				</div>

				<div class="vcd-help-section">
					<h2>📋 Feature Reference</h2>
					
					<div class="vcd-features">
						<div class="vcd-feature">
							<h4>Import Build</h4>
							<p>Upload and deploy staging ZIP files. Includes preflight validation and deployment options.</p>
						</div>
						
						<div class="vcd-feature">
							<h4>Templates</h4>
							<p>Manage header/footer template parts and block templates. Purge plugin-owned templates.</p>
						</div>
						
						<div class="vcd-feature">
							<h4>Builds</h4>
							<p>View deployment history, fingerprints, and rollback to previous versions.</p>
						</div>
						
						<div class="vcd-feature">
							<h4>Settings</h4>
							<p>Configure project slug, class prefix, and validation options.</p>
						</div>
						
						<div class="vcd-feature">
							<h4>Rules Pack</h4>
							<p>Download project-specific rules and configuration files.</p>
						</div>
						
						<div class="vcd-feature">
							<h4>Logs</h4>
							<p>View deployment logs, errors, and debugging information.</p>
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
		$checks = array(
			array(
				'label' => 'WordPress Version',
				'status' => version_compare( get_bloginfo('version'), '6.0', '>=' ) ? 'good' : 'warning',
				'message' => get_bloginfo('version') . (version_compare( get_bloginfo('version'), '6.0', '>=' ) ? ' (OK)' : ' (6.0+ recommended)'),
			),
			array(
				'label' => 'PHP Version',
				'status' => version_compare( PHP_VERSION, '8.0', '>=' ) ? 'good' : 'warning',
				'message' => PHP_VERSION . (version_compare( PHP_VERSION, '8.0', '>=' ) ? ' (OK)' : ' (8.0+ recommended)'),
			),
			array(
				'label' => 'Etch Plugin',
				'status' => is_plugin_active('etch/etch.php') ? 'good' : 'warning',
				'message' => is_plugin_active('etch/etch.php') ? 'Active' : 'Not active (recommended)',
			),
			array(
				'label' => 'Etch Theme',
				'status' => get_template() === 'etch-theme' ? 'good' : 'error',
				'message' => get_template() === 'etch-theme' ? 'Active' : 'Not active (required)',
			),
			array(
				'label' => 'Block Template Support',
				'status' => post_type_exists('wp_template') && post_type_exists('wp_template_part') ? 'good' : 'error',
				'message' => post_type_exists('wp_template') && post_type_exists('wp_template_part') ? 'Supported' : 'Not supported',
			),
			array(
				'label' => 'Plugin Assets Folder',
				'status' => is_dir(WP_PLUGIN_DIR . '/vibecode-deploy/assets') ? 'good' : 'warning',
				'message' => is_dir(WP_PLUGIN_DIR . '/vibecode-deploy/assets') ? 'Exists' : 'Not created yet',
			),
			array(
				'label' => 'Uploads Folder Writable',
				'status' => wp_is_writable(WP_CONTENT_DIR . '/uploads') ? 'good' : 'error',
				'message' => wp_is_writable(WP_CONTENT_DIR . '/uploads') ? 'Writable' : 'Not writable',
			),
			array(
				'label' => 'Memory Limit',
				'status' => (int) ini_get('memory_limit') >= 256 ? 'good' : 'warning',
				'message' => ini_get('memory_limit') . ' (' . ((int) ini_get('memory_limit') >= 256 ? 'OK' : '256M recommended') . ')',
			),
		);

		echo '<table class="vcd-status-table">';
		echo '<thead><tr><th>Item</th><th>Status</th><th>Details</th></tr></thead>';
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
		if ($error_count > 0) {
			echo '<p class="vcd-status-error">❌ ' . count($error_count) . ' critical issues found - Deployment may fail</p>';
		} elseif ($warning_count > 0) {
			echo '<p class="vcd-status-warning">⚠️ ' . count($warning_count) . ' warnings - Some features may not work</p>';
		} else {
			echo '<p class="vcd-status-good">✅ All systems ready for deployment</p>';
		}
		echo '</div>';
	}
}
