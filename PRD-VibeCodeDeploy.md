# Product Requirements Document: Vibe Code Deploy Plugin

## 1. Overview

### 1.1 Purpose
Vibe Code Deploy is a WordPress plugin that converts static HTML websites into Gutenberg-based WordPress sites. It automates the process of importing HTML pages, converting them to blocks, managing assets, and setting up block templates for headers and footers.

### 1.2 Target Audience
- Web developers migrating static sites to WordPress
- Agencies managing multiple website deployments
- WordPress users who need to import HTML designs
- Developers working with EtchWP preprocessor

### 1.3 Key Value Propositions
- One-click HTML to Gutenberg conversion
- Automatic asset management and URL rewriting
- Template part extraction for headers/footers
- Rollback capability for failed deployments
- Preflight validation to prevent errors
- CLI support for automated deployments

## 2. Functional Requirements

### 2.1 Core Features

#### 2.1.1 Staging Zip Upload
- Accept ZIP files containing HTML pages and assets
- Validate ZIP structure before processing
- Extract to WordPress uploads directory
- Support versioned builds with fingerprints

#### 2.1.2 HTML to Block Conversion
- Convert HTML pages to Gutenberg blocks
- Preserve semantic HTML structure
- Handle custom CSS classes and styling
- Support for WordPress block patterns

#### 2.1.3 Asset Management
- Copy CSS, JS, and resource files to plugin assets folder
- Rewrite asset URLs to point to plugin location
- Support for local assets only (css/, js/, resources/)
- Exclude external URLs and CDN resources

#### 2.1.4 Template Part Extraction
- Automatically extract header from home.html
- Automatically extract footer from home.html
- Create WordPress template parts (wp_template_part)
- Support for block theme template hierarchy

#### 2.1.5 Page Management
- Create/update/skip existing pages based on ownership
- Support for front page setting
- Maintain page hierarchy and navigation
- Custom post type support with shortcode validation

#### 2.1.6 Preflight Validation
- Check for missing assets before deployment
- Validate HTML structure
- Detect existing template conflicts
- Environment requirement checks

### 2.2 Advanced Features

#### 2.2.1 Template Management UI
- List all plugin-owned template parts
- List all plugin-owned templates
- Separate purge buttons for parts vs templates
- Bulk cleanup capability

#### 2.2.2 Environment Validation
- Check Etch plugin installation and activation
- Validate Etch theme presence
- Verify block template support
- Warning system for incompatible configurations

#### 2.2.3 Theme Auto-Configuration
- Create required theme files (index.php, page.php)
- Set up block templates (index.html, page.html)
- Update functions.php for asset enqueueing
- Enable Etch mode automatically

#### 2.2.4 Rollback System
- Save deployment manifests
- Rollback to previous build versions
- Maintain deployment history
- One-click rollback functionality

#### 2.2.5 CLI Support
- WP-CLI commands for all operations
- Automated deployment scripts
- CI/CD integration support

#### 2.2.6 Rules Pack Generation
- Export project rules as downloadable pack
- Include README and configuration files
- Support for project-specific rules

### 2.3 Validation Features

#### 2.3.1 Shortcode Validation
- Configurable placeholder requirements per page
- CPT shortcode validation
- Warning/fail modes for validation
- Auto-seeding options for required shortcodes

#### 2.3.2 Link Validation
- Internal link rewriting
- Page slug mapping
- Resource URL handling
- 404 template generation

## 3. Technical Requirements

### 3.1 System Requirements
- WordPress 6.0 or higher
- PHP 8.0 or higher
- EtchWP plugin (recommended for full functionality)
- Etch theme or child theme (recommended for full functionality)
- Block template support (required for template parts)

### 3.2 File Structure
```
wp-content/
├── plugins/
│   └── vibecode-deploy/
│       ├── vibecode-deploy.php
│       ├── rules.md
│       └── includes/
├── themes/
│   └── etch-theme/
│       └── (child themes)
└── uploads/
    └── vibecode-deploy/
        └── staging/
            └── {project}/
                └── {fingerprint}/
```

### 3.3 Staging Zip Structure (CRITICAL)
The staging zip MUST have this exact structure:
```
staging-zip.zip
└── vibecode-deploy-staging/
    ├── pages/
    │   ├── home.html (required)
    │   ├── about.html
    │   └── ...
    ├── css/
    │   ├── styles.css
    │   └── icons.css
    ├── js/
    │   └── main.js
    └── resources/
        └── images/
```

**Note:** Do NOT upload the plugin zip as a staging zip. They are different files with different purposes.

### 3.4 Database Schema
- Post meta for project ownership tracking
- Options for settings and manifests
- Custom post types: wp_template, wp_template_part
- Build fingerprints and deployment history

### 3.5 API Hooks
- Filters for HTML processing
- Actions for deployment events
- Hooks for custom validation
- Integration points for third-party tools

## 4. User Interface Requirements

### 4.1 Admin Menu Structure
- Vibe Code Deploy (main)
  - Import Build
  - Builds
  - Templates
  - Settings
  - Rules Pack
  - Logs

### 4.2 Import Page
- File upload interface
- Build selection dropdown
- Preflight results display
- Deployment options
- Progress indicators

### 4.3 Templates Page
- Template parts table
- Templates table
- Purge controls
- Ownership indicators
- Status badges

### 4.4 Settings Page
- Project slug configuration
- Class prefix settings
- Validation options
- Default deployment preferences

## 5. Security Requirements

### 5.1 File Upload Security
- ZIP file validation
- Path traversal prevention
- File type restrictions
- Size limitations

### 5.2 Code Execution Safety
- Sanitized HTML processing
- Escaped output
- Capability checks
- Nonce verification

### 5.3 Data Privacy
- No external API calls
- Local processing only
- Optional telemetry
- User data protection

## 6. Performance Requirements

### 6.1 Processing Limits
- Maximum ZIP size: 100MB
- Maximum pages per build: 100
- Concurrent deployments: 1 per site
- Memory limit: 256MB

### 6.2 Optimization
- Efficient DOM parsing
- Batch database operations
- Asset compression
- Caching strategies

## 7. Compatibility Requirements

### 7.1 WordPress Compatibility
- Support for latest WordPress version
- Backward compatibility to WordPress 6.0
- Multisite support
- Multi-language compatibility

### 7.2 Theme Compatibility
- Etch theme (primary)
- Etch child themes
- Block themes
- Classic themes (limited functionality)

### 7.3 Plugin Compatibility
- EtchWP preprocessor
- Popular page builders (limited)
- SEO plugins
- Caching plugins

## 8. Testing Requirements

### 8.1 Unit Testing
- All service classes
- Utility functions
- Data validation
- Error handling

### 8.2 Integration Testing
- Full deployment workflow
- Template creation
- Asset copying
- Rollback functionality

### 8.3 User Acceptance Testing
- Admin interface usability
- Error message clarity
- Documentation accuracy
- Feature completeness

## 9. Documentation Requirements

### 9.1 User Documentation
- Installation guide
- Quick start tutorial
- Feature reference
- Troubleshooting guide

### 9.2 Developer Documentation
- API reference
- Hook documentation
- Extension guide
- Code examples

### 9.3 System Documentation
- Architecture overview
- Database schema
- File structure
- Configuration options

## 10. Deployment Requirements

### 10.1 Distribution
- WordPress.org repository
- GitHub releases
- Direct download
- Partner channels

### 10.2 Updates
- Automatic updates
- Migration scripts
- Backward compatibility
- Update notifications

## 11. Success Metrics

### 11.1 Usage Metrics
- Number of deployments
- Success rate
- Error frequency
- Feature adoption

### 11.2 Performance Metrics
- Deployment time
- Memory usage
- Error rates
- User satisfaction

## 12. Future Enhancements

### 12.1 Planned Features
- Multi-site management
- Cloud storage integration
- Advanced validation rules
- Visual diff viewer

### 12.2 Integration Opportunities
- CI/CD pipelines
- Development workflows
- Design tools
- Analytics platforms

## 13. Risk Assessment

### 13.1 Technical Risks
- **WordPress core changes**: Plugin relies on block template system
- **Plugin conflicts**: May conflict with other deployment or theme plugins
- **Performance issues**: Large ZIP files may cause timeouts
- **Security vulnerabilities**: File upload and extraction risks
- **Theme dependencies**: Requires specific theme for full functionality
- **Staging zip structure**: Incorrect structure causes silent failures

### 13.2 Business Risks
- **Market adoption**: Niche audience (developers migrating static sites)
- **Competition**: Other migration tools exist
- **Maintenance costs**: Ongoing WordPress compatibility updates
- **User support**: Complex setup process may increase support burden
- **Documentation**: Critical for user success

### 13.3 Mitigation Strategies
- **Comprehensive testing**: Test with various WordPress versions and themes
- **Clear documentation**: Detailed setup and troubleshooting guides
- **Graceful degradation**: Warnings instead of blocking when possible
- **Error handling**: Clear error messages and logs
- **Backup features**: Rollback capability for failed deployments

## 14. Timeline

### 14.1 Development Phases
1. Core functionality (completed)
2. Advanced features (completed)
3. Documentation (in progress)
4. Testing and QA
5. Release preparation

### 14.2 Milestones
- MVP release
- Beta testing
- Public launch
- Version 1.1
- Long-term roadmap
