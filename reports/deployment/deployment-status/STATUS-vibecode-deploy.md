# Vibe Code Deploy — Current Status & Next Steps

## Goal (restated)
Turn the existing **EtchVibe** plugin into a new plugin:

- **Name:** `Vibe Code Deploy`
- **Slug:** `vibecode-deploy`
- **Namespace:** `VibeCode\Deploy`
- **Behavior change (future):** **Gutenberg-default** (plain WP block markup first), with an **optional “Etch conversion mode”**.

The plugin lives in **VibeCodeCircle** with layout: `plugins/vibecode-deploy/`.

---

## What has been completed

### 1) Repo and plugin structure
- Repo: `/Users/tlowing/CascadeProjects/windsurf-project/VibeCodeCircle`
- Plugin: `plugins/vibecode-deploy/`
- Entrypoint: `vibecode-deploy.php`

### 2) Code-level identity rename (Phase 1) — DONE
- **Plugin header:** `Plugin Name: Vibe Code Deploy`, Author: Vibe Code Deploy, Etch-only language removed
- **Constants:** `VIBECODE_DEPLOY_PLUGIN_FILE`, `VIBECODE_DEPLOY_PLUGIN_DIR`, `VIBECODE_DEPLOY_PLUGIN_VERSION`
- **Bootstrap:** `VibeCode\Deploy\Bootstrap::init()`
- **Namespaces:** `VibeCode\Deploy`, `VibeCode\Deploy\Services`, `VibeCode\Deploy\Admin` (no EtchVibe remaining)
- **Admin slugs / nonces / actions:** `vibecode-deploy-...`, `vibecode_deploy_...`

No remaining `EtchVibe`, `ETCHVIBE_`, or `etchvibe` references in the codebase.

### 3) Plugin behavior
- HTML → Gutenberg block conversion
- Asset management, template extraction, preflight, rollback
- CLI support, starter pack, health check
- Current version: **0.1.54** (in `vibecode-deploy.php`)

### 4) Build
- `scripts/build-plugin-zip.sh` produces `dist/vibecode-deploy-{version}.zip` (versioned only; `dist/*.zip` gitignored).
- Run from repo root: `./scripts/build-plugin-zip.sh`.

---

## What is NOT done yet

### Phase 2 — Gutenberg-default behavior (product change)
Planned once Phase 1 was complete:

1) **Conversion modes**
   - Default: passthrough when input already contains Gutenberg blocks (`<!-- wp:`)
   - Optional: HTML → blocks (current converter)
   - Fallback: wrap as `core/html`

2) **“Etch Mode” optional**
   - `html_to_etch_blocks()` and `metadata.etchData`-style behavior behind a setting/flag

3) **Configurable HTML constraints**
   - strict / warn / fallback, with documented tradeoffs

---

## Current status summary

| Item | Status |
|------|--------|
| Repo / plugin layout | Done |
| Phase 1: EtchVibe → Vibe Code Deploy rename | Done |
| Phase 2: Gutenberg-default behavior | Not started |
| Plugin zip in `dist/` | Build on demand via `./scripts/build-plugin-zip.sh` |

---

## Recommended next steps

1. **When shipping:** Run `./scripts/build-plugin-zip.sh` and use `dist/vibecode-deploy-{version}.zip` for WordPress.
2. **For Phase 2:** Implement Gutenberg-default conversion modes and make Etch conversion optional, per the sections above.
