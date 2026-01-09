<?php

namespace VibeCode\Deploy\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Starter Pack Service
 *
 * Generates downloadable project starter pack with build scripts and templates.
 *
 * @package VibeCode\Deploy\Services
 */
final class StarterPackService {
	/**
	 * Generate README markdown for starter pack.
	 *
	 * @return string README content.
	 */
	public static function generate_readme_markdown(): string {
		$out = '';
		$out .= "# Vibe Code Deploy Project Starter Pack\n\n";
		$out .= "This starter pack contains build scripts and templates for creating deployment packages from your static HTML project.\n\n";
		$out .= "## Contents\n\n";
		$out .= "- `build-deployment-package.sh` - Main build script\n";
		$out .= "- `generate-manifest.php` - Manifest generator\n";
		$out .= "- `generate-functions-php.php` - Functions.php generator\n";
		$out .= "- `README.md` - This file\n";
		$out .= "- `.cursorrules.template` - Template for project rules\n";
		$out .= "- `example-structure/` - Example project structure\n\n";
		$out .= "## Quick Start\n\n";
		$out .= "1. Copy the build scripts to your project's `scripts/` directory\n";
		$out .= "2. Review and customize `.cursorrules.template` for your project\n";
		$out .= "3. Run `./scripts/build-deployment-package.sh` to create your deployment package\n";
		$out .= "4. Upload the generated ZIP file via Vibe Code Deploy → Import Build\n\n";
		$out .= "## Build Script Usage\n\n";
		$out .= "```bash\n";
		$out .= "./scripts/build-deployment-package.sh\n";
		$out .= "```\n\n";
		$out .= "This will:\n";
		$out .= "- Create a simplified deployment package structure\n";
		$out .= "- Generate `manifest.json` with package metadata\n";
		$out .= "- Generate `config.json` with deployment settings\n";
		$out .= "- Package everything as a ZIP file\n\n";
		$out .= "## Project Structure\n\n";
		$out .= "Your project should have this structure:\n\n";
		$out .= "```\n";
		$out .= "your-project/\n";
		$out .= "├── *.html              # HTML pages\n";
		$out .= "├── css/                 # CSS files\n";
		$out .= "├── js/                   # JavaScript files\n";
		$out .= "├── resources/            # Images and assets\n";
		$out .= "├── scripts/              # Build scripts (from this pack)\n";
		$out .= "└── wp-content/themes/your-theme/  # Theme files (if applicable)\n";
		$out .= "    ├── functions.php\n";
		$out .= "    └── acf-json/\n";
		$out .= "```\n\n";
		$out .= "## Deployment Package Structure\n\n";
		$out .= "The build script creates:\n\n";
		$out .= "```\n";
		$out .= "{project-name}-deployment/\n";
		$out .= "├── manifest.json        # Package metadata and checksums\n";
		$out .= "├── config.json          # Deployment settings\n";
		$out .= "├── pages/               # HTML pages\n";
		$out .= "├── assets/               # All assets in one place\n";
		$out .= "│   ├── css/\n";
		$out .= "│   ├── js/\n";
		$out .= "│   └── images/\n";
		$out .= "└── theme/                # Theme files\n";
		$out .= "    ├── functions.php\n";
		$out .= "    └── acf-json/\n";
		$out .= "```\n\n";
		$out .= "## For More Information\n\n";
		$out .= "See the Vibe Code Deploy plugin documentation for detailed deployment instructions.\n";
		return $out;
	}

	/**
	 * Build starter pack ZIP file.
	 *
	 * @return array Result with 'ok', 'tmp_path', 'filename' keys.
	 */
	public static function build_starter_pack_zip(): array {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			return array( 'ok' => false, 'error' => 'ZipArchive not available.' );
		}

		$plugin_dir = defined( 'VIBECODE_DEPLOY_PLUGIN_DIR' ) ? rtrim( (string) VIBECODE_DEPLOY_PLUGIN_DIR, '/\\' ) : '';
		if ( $plugin_dir === '' ) {
			return array( 'ok' => false, 'error' => 'Plugin directory not found.' );
		}

		$starter_pack_dir = $plugin_dir . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'starter-pack';
		if ( ! is_dir( $starter_pack_dir ) ) {
			return array( 'ok' => false, 'error' => 'Starter pack directory not found.' );
		}

		$tmp = wp_tempnam( 'vibecode-deploy-starter-pack.zip' );
		if ( ! is_string( $tmp ) || $tmp === '' ) {
			return array( 'ok' => false, 'error' => 'Unable to create temporary file.' );
		}

		$zip = new \ZipArchive();
		$opened = $zip->open( $tmp, \ZipArchive::OVERWRITE );
		if ( $opened !== true ) {
			@unlink( $tmp );
			return array( 'ok' => false, 'error' => 'Unable to create zip.' );
		}

		// Add README
		$readme = self::generate_readme_markdown();
		$zip->addFromString( 'README.md', $readme );

		// Add files from starter-pack directory
		$files_to_add = array(
			'build-deployment-package.sh',
			'generate-manifest.php',
			'generate-functions-php.php',
			'.cursorrules.template',
		);

		foreach ( $files_to_add as $filename ) {
			$file_path = $starter_pack_dir . DIRECTORY_SEPARATOR . $filename;
			if ( is_file( $file_path ) && is_readable( $file_path ) ) {
				$content = file_get_contents( $file_path );
				if ( is_string( $content ) ) {
					$zip->addFromString( $filename, $content );
				}
			}
		}

		// Add example structure directory if it exists
		$example_dir = $starter_pack_dir . DIRECTORY_SEPARATOR . 'example-structure';
		if ( is_dir( $example_dir ) ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $example_dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $iterator as $item ) {
				$file_path = $item->getPathname();
				$relative_path = str_replace( $example_dir . DIRECTORY_SEPARATOR, '', $file_path );
				$relative_path = str_replace( '\\', '/', $relative_path );

				if ( $item->isDir() ) {
					$zip->addEmptyDir( 'example-structure/' . $relative_path . '/' );
				} elseif ( $item->isFile() ) {
					$content = file_get_contents( $file_path );
					if ( is_string( $content ) ) {
						$zip->addFromString( 'example-structure/' . $relative_path, $content );
					}
				}
			}
		}

		$zip->close();

		$filename = 'vibecode-deploy-starter-pack-' . gmdate( 'Ymd-His' ) . '.zip';
		return array(
			'ok' => true,
			'tmp_path' => $tmp,
			'filename' => $filename,
		);
	}
}
