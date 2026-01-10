# File Organization Standard - vibecode-deploy Plugin

**Date:** January 10, 2025  
**Status:** Active Standard  
**Applies To:** vibecode-deploy WordPress plugin

---

## Overview

This document defines the standard folder structure for organizing documentation and diagnostic files in the vibecode-deploy plugin directory.

## Standard Folder Structure

```
vibecode-deploy/
├── docs/                          # Plugin documentation
│   ├── ARCHITECTURE.md            # Plugin architecture
│   ├── README-TESTING.md          # Testing guide
│   └── rules.md                   # Rules pack (if documentation)
│
├── reports/                       # Diagnostic reports
│   ├── development/               # Development reports
│   └── archive/                   # Archived reports
│
├── includes/                     # Plugin code
├── assets/                        # Plugin assets
├── tests/                         # Unit tests
├── rules.md                       # Rules pack (if used by plugin)
└── vibecode-deploy.php            # Main plugin file
```

## File Categorization

### `docs/` Folder - Permanent Documentation

**Purpose:** Long-term plugin documentation that should be version-controlled.

**Examples:**
- `ARCHITECTURE.md` - Plugin architecture documentation
- `README-TESTING.md` - Testing procedures
- `rules.md` - Rules pack documentation (if documentation, not config)

**Criteria:**
- Files referenced regularly
- Provide ongoing value
- Should be version-controlled

### `reports/` Folder - Diagnostic Reports

**Purpose:** Development reports, implementation summaries, and diagnostic documentation.

**Subfolders:**
- `development/` - Development-related reports
- `archive/` - Archived reports (older than 90 days)

### Root Directory - Minimal Files Only

**Allowed:**
- `vibecode-deploy.php` (main plugin file)
- `uninstall.php` (uninstall handler)
- `rules.md` (if used as rules pack content by plugin)
- `composer.json`, `phpunit.xml.dist` (configuration files)

**Not Allowed:**
- Diagnostic reports
- Temporary documentation
- Multiple versions of the same report

## Special Case: `rules.md`

The `rules.md` file serves as the **rules pack content** that is included with the plugin install. 

**Decision:**
- If `rules.md` is used by the plugin code (read/processed), keep it in plugin root
- If `rules.md` is only documentation, move to `docs/`

**Current Status:** Kept in plugin root (assumed to be used by plugin)

## Cleanup and Archival Rules

Same as VibeCodeCircle root standard:
- 90-Day Rule for archiving
- Version consolidation
- Quarterly cleanup

## Maintenance Checklist

Same as VibeCodeCircle root standard:
- Monthly review and organization
- Quarterly archive cleanup
- Annual comprehensive review

## References

- **Repository Standard:** `../../docs/FILE_ORGANIZATION_STANDARD.md`
- **Project Rules:** `../../.cursorrules` (File Organization Rules section)

---

**Last Updated:** January 10, 2025
