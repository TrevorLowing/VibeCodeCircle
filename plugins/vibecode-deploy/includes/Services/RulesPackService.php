<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

final class RulesPackService {
	private static function read_text_file( string $path, int $max_bytes = 524288 ): string {
		if ( $path === '' || ! is_file( $path ) || ! is_readable( $path ) ) {
			return '';
		}
		$data = file_get_contents( $path, false, null, 0, $max_bytes );
		return is_string( $data ) ? $data : '';
	}

	private static function find_upwards_for_file( string $start_dir, string $filename, int $max_levels = 10 ): string {
		$dir = rtrim( $start_dir, '/\\' );
		for ( $i = 0; $i < $max_levels; $i++ ) {
			$candidate = $dir . DIRECTORY_SEPARATOR . $filename;
			if ( is_file( $candidate ) ) {
				return $candidate;
			}
			$parent = dirname( $dir );
			if ( $parent === $dir || $parent === '' ) {
				break;
			}
			$dir = $parent;
		}
		return '';
	}

	public static function generate_staging_guide_markdown( string $project_slug, string $class_prefix ): string {
		$project_slug = sanitize_key( $project_slug );
		$class_prefix = sanitize_text_field( $class_prefix );

		$out = '';
		$out .= "# Vibe Code Deploy Staging + Deploy Guide\n\n";
		$out .= "This rules pack accompanies the Vibe Code Deploy WordPress plugin.\n\n";
		$out .= "## Project Settings\n\n";
		$out .= "- Project slug: `" . $project_slug . "`\n";
		$out .= "- Class prefix: `" . $class_prefix . "`\n\n";
		$out .= "## Staging Bundle Layout\n\n";
		$out .= "Your zip must contain a top-level `vibecode-deploy-staging/` folder.\n\n";
		$out .= "### Zip creation (macOS)\n\n";
		$out .= "Vibe Code Deploy validates staging zips strictly. Finder-created zips on macOS often include `__MACOSX/`, `.DS_Store`, and `._*` entries that can cause the upload to fail.\n\n";
		$out .= "Recommended: create the zip from your repo root using the `zip` command and exclude macOS metadata entries:\n\n";
		$out .= "```bash\n";
		$out .= "zip -r vibecode-deploy-staging.zip vibecode-deploy-staging -x \"*.DS_Store\" \"__MACOSX/*\" \"*/__MACOSX/*\" \"._*\"\n";
		$out .= "```\n\n";
		$out .= "### Pages (required)\n\n";
		$out .= "- `vibecode-deploy-staging/pages/*.html`\n\n";
		$out .= "### Dynamic content placeholders (shortcodes)\n\n";
		$out .= "If a page needs to render WordPress dynamic content (CPT lists, indexes, etc.), do **not** paste raw `[shortcode]` text into the HTML. During import, Vibe Code Deploy escapes text nodes.\n\n";
		$out .= "Instead, use an HTML comment placeholder that Vibe Code Deploy converts into a real Gutenberg `core/shortcode` block during deploy:\n\n";
		$settings = \VibeCode\Deploy\Settings::get_all();
		$prefix = isset( $settings['placeholder_prefix'] ) ? (string) $settings['placeholder_prefix'] : 'VIBECODE_SHORTCODE';
		$out .= "- Example: `<!-- " . $prefix . " cfa_foia_index paginate=\"1\" per_page=\"20\" -->`\n\n";
		$out .= "Vibe Code Deploy will convert that placeholder into:\n\n";
		$out .= "- `<!-- wp:shortcode -->[cfa_foia_index paginate=\"1\" per_page=\"20\"]<!-- /wp:shortcode -->`\n\n";
		$out .= "### Placeholder rules config (optional but recommended)\n\n";
		$out .= "Add `vibecode-deploy-staging/vibecode-deploy-shortcodes.json` to define which page slugs must include which placeholders.\n\n";
		$out .= "- Validation ignores shortcode attributes (attrs are only defaults for insertion).\n";
		$out .= "- Strict mode is controlled by Vibe Code Deploy settings (Warn vs Fail).\n\n";
		$out .= "### Template parts (optional)\n\n";
		$out .= "- `vibecode-deploy-staging/template-parts/header.html`\n";
		$out .= "- `vibecode-deploy-staging/template-parts/footer.html`\n\n";
		$out .= "#### Per-template overrides (optional)\n\n";
		$out .= "- `vibecode-deploy-staging/template-parts/header-404.html`\n";
		$out .= "- `vibecode-deploy-staging/template-parts/footer-404.html`\n\n";
		$out .= "Default behavior: Vibe Code Deploy extracts header/footer from home.html into template parts (unless you provide them). The 404 template will use header-404/footer-404 only if they exist and are owned by the project.\n\n";
		$out .= "### Assets (optional)\n\n";
		$out .= "- `vibecode-deploy-staging/css/`\n";
		$out .= "- `vibecode-deploy-staging/js/`\n";
		$out .= "- `vibecode-deploy-staging/resources/`\n\n";
		$out .= "## Ownership + Force Claim\n\n";
		$out .= "By default, Vibe Code Deploy only updates content it previously created/owns for the active project slug.\n\n";
		$out .= "- Pages: can optionally force-claim unowned pages during deploy (default off).\n";
		$out .= "- Template parts/templates: can optionally force-claim unowned template parts/templates (default off).\n\n";
		$out .= "## Optional CPT validation (deploy-time)\n\n";
		$out .= "CPT templates are managed in WordPress. Vibe Code Deploy can optionally validate CPT shortcode coverage during deploy (off by default).\n\n";
		$out .= "- Enable via the **Deploy Build** checkbox: \"Validate CPT shortcode coverage\".\n";
		$out .= "- Uses `vibecode-deploy-shortcodes.json` `post_types` rules if present.\n\n";
		$out .= "## Rollback\n\n";
		$out .= "Each successful deploy writes a manifest. Rollback will restore updated items and delete items created by that deploy.\n";

		return $out;
	}

	public static function generate_readme_markdown(): string {
		$out = '';
		$out .= "# Vibe Code Deploy Rules Pack\n\n";
		$out .= "This zip contains project rules for use in AI IDE tools (Cursor, Windsurf, VS Code extensions).\n\n";
		$out .= "## Contents\n\n";
		$out .= "- `RULES.md` (project rules)\n\n";
		$out .= "## How to use\n\n";
		$out .= "- Reference `RULES.md` as your project instructions/context in your AI IDE.\n";
		$out .= "\n## Etch disclaimer\n\n";
		$out .= "Vibe Code Deploy is a separate plugin that integrates with Etch (plugin) and etch-theme. Etch and etch-theme are owned and licensed by their respective authors.\n\n";
		$out .= "This rules pack does not include Etch source code. If your workflow uses Etch or Etch theme assets/templates, you are responsible for ensuring your usage complies with Etch's license and terms.\n";
		return $out;
	}

	public static function build_rules_pack_zip( string $project_slug, string $class_prefix ): array {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'ZipArchive not available.' );
		}

		$rules_md_path = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) . DIRECTORY_SEPARATOR . 'RULES.md' : '';
		$rules_md_content = $rules_md_path !== '' ? self::read_text_file( $rules_md_path ) : '';
		if ( $rules_md_content === '' ) {
			return array( 'ok' => false, 'error' => 'Unable to locate RULES.md in this install.' );
		}

		$tmp = wp_tempnam( 'vibecode-deploy-rules-pack.zip' );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			return array( 'ok' => false, 'error' => 'Unable to create temporary file.' );
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $tmp, \ZipArchive::OVERWRITE );
		if ( $opened !== true ) {
			@unlink( $tmp );
			return array( 'ok' => false, 'error' => 'Unable to create zip.' );
		}

		$zip->addFromString( 'RULES.md', $rules_md_content );

		$readme = self::generate_readme_markdown();
		$zip->addFromString( 'README.md', $readme );

		$zip->close();

		$filename = 'vibecode-deploy-rules-pack-' . gmdate( 'Ymd-His' ) . '.zip';
		return array(
			'ok' => true,
			'tmp_path' => $tmp,
			'filename' => $filename,
			'rules_md_path' => $rules_md_path,
		);
	}
}
