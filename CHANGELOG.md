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
- **Semantic Block Conversion (v0.1.57+)**: Automatic conversion of semantic HTML elements (paragraphs, lists, images, blockquotes, code, tables) to editable Gutenberg blocks, reducing CORE/HTML blocks and making content fully editable in EtchWP IDE
- **Image URL Conversion (v0.1.63+)**: Enhanced image handling with automatic URL conversion during block conversion, ensuring image blocks always have absolute plugin URLs for proper EtchWP IDE compatibility
- **Comprehensive HTML Test Page (v0.1.60+)**: Generate test pages with full HTML4/HTML5 element coverage for testing block conversion accuracy
- **Audit Report Generation (v0.1.61+)**: Generate compliance audit reports analyzing block conversion accuracy, etchData coverage, and EtchWP editability
- **Media Library Attachment Cleanup (v0.1.64+)**: Automatic tracking and cleanup of Media Library attachments during rollback and nuclear operations
  - Attachment tracking in deployment manifests (`created_attachments`, `updated_attachments`)
  - Orphaned attachment detection (attachments not referenced in post content)
  - Automatic cleanup of orphaned attachments during rollback
  - Optional Media Library attachment deletion in nuclear operations with mode selection (all vs orphaned only)

### Fixed
- Asset 404 errors by checking both root and /assets subfolder
- Preflight silently failing when Etch theme not active
- ThemeSetupService lint errors
- Plugin zip structure for WordPress installation
- CFA-specific constant replaced with configurable prefix
- TODO in DeployService resolved (env errors mode now configurable)
- Architecture.md renamed to EXTERNAL_TOOLS.md, new Architecture.md created
- **Image URL conversion (v0.1.63)**: Fixed images not working by ensuring relative asset paths are converted to full plugin URLs during block conversion, ensuring proper EtchWP IDE compatibility
- **List block conversion (v0.1.62)**: Fixed lists showing as code passthrough by keeping list items as raw HTML inside list blocks instead of separate nested blocks

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
