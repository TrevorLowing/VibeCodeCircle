# File Organization Standard

**Date:** January 10, 2025  
**Status:** Active Standard  
**Applies To:** VibeCodeCircle repository and all plugins

---

## Overview

This document defines the standard folder structure for organizing documentation and diagnostic files in the VibeCodeCircle repository. This standard prevents root directory clutter and makes it easier to find and maintain project files.

## Standard Folder Structure

```
VibeCodeCircle/
├── docs/                          # Core project documentation
│   ├── PRD-VibeCodeDeploy.md      # Product Requirements Document
│   ├── DEPLOYMENT-GUIDE.md        # Deployment guide
│   ├── DEVELOPER_GUIDE.md         # Developer guide
│   ├── API_REFERENCE.md           # API documentation
│   └── ...                        # Other permanent documentation
│
├── reports/                       # Diagnostic reports and audits
│   ├── deployment/                # Deployment-related reports
│   │   ├── compliance-reviews/    # Compliance audit reports
│   │   ├── visual-comparisons/    # Visual comparison reports
│   │   └── deployment-status/     # Deployment readiness docs
│   ├── development/               # Development-related reports
│   │   ├── implementation-summaries/
│   │   ├── code-reviews/
│   │   └── feature-status/
│   └── archive/                   # Archived reports (older than 90 days)
│
├── plugins/
│   └── vibecode-deploy/
│       ├── docs/                  # Plugin-specific documentation
│       │   ├── ARCHITECTURE.md    # Plugin architecture
│       │   ├── README-TESTING.md  # Testing guide
│       │   └── rules.md           # Rules pack (if documentation)
│       └── reports/               # Plugin-specific reports
│
└── {project files}                # Plugin code, scripts, etc.
```

## File Categorization

### `docs/` Folder - Permanent Documentation

**Purpose:** Long-term project documentation that should be version-controlled and maintained.

**Examples:**
- `PRD-VibeCodeDeploy.md` - Product Requirements Document
- `DEPLOYMENT-GUIDE.md` - Deployment procedures
- `DEVELOPER_GUIDE.md` - Developer documentation
- `API_REFERENCE.md` - API documentation
- `WORDPRESS_PLUGIN_BEST_PRACTICES.md` - Best practices guide

**Criteria:**
- Files referenced regularly
- Provide ongoing value
- Should be version-controlled
- Not temporary or diagnostic

### `reports/` Folder - Diagnostic Reports

**Purpose:** Audit reports, implementation summaries, code reviews, and diagnostic documentation.

**Subfolders:**

1. **`reports/deployment/`** - Deployment-related reports
   - `compliance-reviews/` - Compliance audit reports
   - `visual-comparisons/` - Visual comparison reports
   - `deployment-status/` - Deployment readiness documents

2. **`reports/development/`** - Development-related reports
   - Implementation summaries
   - Code review reports
   - Feature implementation status
   - Development progress reports

3. **`reports/archive/`** - Archived reports
   - Reports older than 90 days
   - Completed fix documentation
   - Historical reference material

**Naming Convention:**
- Format: `{category}-{description}-{YYYY-MM-DD}.md`
- Examples:
  - `development-implementation-summary-2025-01-10.md`
  - `deployment-status-report-2025-01-10.md`

**Avoid:**
- ❌ `*_LATEST.md`, `*_FULL.md`, `*_NEW.md` (use dates instead)
- ❌ `*_v2.md`, `*_v3.md` (use dates instead)
- ❌ Multiple versions of the same report in root

### Root Directory - Minimal Files Only

**Allowed:**
- `README.md` (project overview)
- `CHANGELOG.md` (standard location)
- Configuration files (`.cursorrules`, `composer.json`, etc.)
- Essential scripts in `scripts/` folder

**Not Allowed:**
- Diagnostic reports
- Audit documents
- Temporary documentation
- Multiple versions of the same report

## Cleanup and Archival Rules

### 1. 90-Day Rule

Reports older than 90 days should be moved to `reports/archive/`.

**Exception:** Reports marked as "KEEP" in frontmatter or filename.

**Implementation:**
- Monthly review of `reports/` subfolders
- Move files older than 90 days to `reports/archive/`
- Update any references to moved files

### 2. Version Consolidation

When multiple versions of the same report exist:
- Keep only the most recent comprehensive version
- Archive or delete older versions
- Use date-based naming instead of "LATEST", "FULL", etc.

### 3. Completed Fix Documentation

Files documenting completed fixes:
- Move to `reports/archive/` after fixes are verified and deployed
- Keep only if they provide ongoing reference value

### 4. Temporary Diagnostic Files

Files created for one-time diagnostics:
- Delete after issue is resolved
- Or move to `reports/archive/` if they provide historical context

### 5. Quarterly Cleanup

Every quarter, review `reports/archive/` folder:
- Delete files older than 1 year (unless marked as "KEEP")
- Consolidate related reports into single documents
- Update documentation index if maintained

## Maintenance Checklist

### Monthly
- [ ] Review new reports created in root, move to appropriate `reports/` subfolder
- [ ] Check for duplicate reports, consolidate if needed
- [ ] Move reports older than 90 days to `reports/archive/`

### Quarterly
- [ ] Review `reports/archive/` for files older than 1 year
- [ ] Delete or consolidate old archived reports
- [ ] Update documentation index if maintained

### Annually
- [ ] Review entire `reports/archive/` folder
- [ ] Consolidate related reports into comprehensive documents
- [ ] Archive or delete reports no longer needed
- [ ] Update folder structure if needed based on usage patterns

## Implementation Status

### VibeCodeCircle Root
- ✅ Folder structure created
- ✅ Files migrated (2 docs, 10 reports)
- ✅ `.cursorrules` updated with standards

### vibecode-deploy Plugin
- ✅ Folder structure created
- ✅ Files migrated (2 docs)
- ⚠️ `rules.md` kept in plugin root (rules pack content, may be used by plugin)

## References

- **Project Rules:** `.cursorrules` (File Organization Rules section)
- **Standard Reference:** See biogaspros and CFA projects for examples

---

**Last Updated:** January 10, 2025
