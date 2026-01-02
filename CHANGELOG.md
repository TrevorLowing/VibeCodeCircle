# Changelog

All notable changes to Vibe Code Deploy will be documented in this file.

## [Unreleased]
### Added
- Comprehensive Help page with system status checker
- ThemeSetupService for automatic theme configuration
- Asset copying fixes for root-level assets
- Preflight warnings instead of blocking for missing theme
- Configurable placeholder prefix (default: VIBECODE_SHORTCODE)
- Environment errors mode setting (warn/fail)
- PHPDoc comments to service classes
- Architecture documentation

### Fixed
- Asset 404 errors by checking both root and /assets subfolder
- Preflight silently failing when Etch theme not active
- ThemeSetupService lint errors
- Plugin zip structure for WordPress installation
- CFA-specific constant replaced with configurable prefix
- TODO in DeployService resolved (env errors mode now configurable)
- Architecture.md renamed to EXTERNAL_TOOLS.md, new Architecture.md created

### Changed
- Preflight shows warnings instead of blocking when theme requirements not met (configurable)
- Improved error handling and logging
- Updated documentation with troubleshooting guides
- Placeholder prefix now configurable in settings (was hardcoded CFA_SHORTCODE)
- Environment errors handling now respects settings (was hardcoded to warn)

### Security
- Added file validation for staging zip uploads
- Improved path traversal prevention

## [1.0.0] - 2024-01-01
### Added
- Initial release of Vibe Code Deploy plugin
- HTML to Gutenberg block conversion
- Asset management and URL rewriting
- Template part extraction (header/footer)
- Preflight validation system
- Rollback functionality
- CLI support
- Template management UI
- Environment requirement checks
- Rules pack generation
- Build fingerprinting
- CPT shortcode validation

### Features
- Convert static HTML to WordPress blocks
- Automatic CSS/JS asset copying
- Header/footer template extraction
- Block template support
- Deployment manifests
- Multi-project support
- Force-claim options
- 404 template generation

### Requirements
- WordPress 6.0+
- PHP 8.0+
- EtchWP plugin (recommended)
- Etch theme or child theme (recommended)

### Known Issues
- Preflight blocks deployment if Etch theme not active
- Theme auto-configuration may cause conflicts
- Help page disabled in current release
