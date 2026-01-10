# Vibe Code Deploy — Current Status & Next Steps

## Goal (restated)
You want to turn the existing **EtchVibe** plugin into a new plugin:

- **Name:** `Vibe Code Deploy`
- **Slug:** `vibecode-deploy`
- **Namespace:** `VibeCode\\Deploy`
- **Behavior change:** **Gutenberg-default** (plain WP block markup first), with an **optional “Etch conversion mode”**.

You also want it to live in a **new repo** (`VibeCodeCircle`) with **Option A** layout: the repo contains `plugins/<plugin>`.

---

## What has been completed successfully

### 1) New repo cloned
- Repo exists at:
  - `/Users/tlowing/CascadeProjects/windsurf-project/VibeCodeCircle`
- Repo contains `.git/`.

### 2) Plugin scaffolded into the new repo
- We copied the EtchVibe plugin from the CFA project into the new repo.

### 3) Workspace access resolved
- `VibeCodeCircle` has been added as an IDE workspace, so code-aware tools can operate on it.

### 4) Filesystem renames completed (manual)
Critical filesystem renames were completed:

- Plugin folder is now:
  - `VibeCodeCircle/plugins/vibecode-deploy/`
- Plugin entrypoint is now:
  - `VibeCodeCircle/plugins/vibecode-deploy/vibecode-deploy.php`

This means the plugin now has the **desired on-disk shape**.

---

## What is NOT done yet (why it still “looks like EtchVibe”)

Even though the folder and entrypoint names are correct, the plugin **code identity** is still largely EtchVibe.

### Current state (observed)
The entrypoint `vibecode-deploy.php` still contains:

- **Plugin header**
  - `Plugin Name: EtchVibe`
  - `Description: ... EtchWP ...`
  - `Author: EtchVibe`

- **Constants**
  - `ETCHVIBE_PLUGIN_FILE`
  - `ETCHVIBE_PLUGIN_DIR`
  - `ETCHVIBE_PLUGIN_VERSION`

- **Bootstrap init**
  - `EtchVibe\Bootstrap::init();`

- **Namespaces across the codebase**
  - `namespace EtchVibe;`
  - `namespace EtchVibe\Services;`
  - `namespace EtchVibe\Admin;`

- **Admin page slugs/nonces/actions**
  - Many `etchvibe-...` and `etchvibe_...`

Until these are renamed, WordPress will still think of it as EtchVibe internally.

---

## Why execution got “stuck” previously

### 1) Workspace/tooling constraints earlier
Initially, tools could only run in the CFA workspace, which blocked codewide operations in the new repo until `VibeCodeCircle` was added.

### 2) Terminal commands were unreliable / canceled
Some attempts relied on terminal commands that:

- ran with an unexpected working directory (`pwd` showed `.../CFA` at least once)
- or were canceled mid-run, leaving no verified state transition

You unblocked the filesystem part by doing the folder/entrypoint renames manually.

---

## What needs to be done next (clear checklist)

### Phase 1 — Code-level identity rename (mandatory before Gutenberg refactor)
Target folder:
- `VibeCodeCircle/plugins/vibecode-deploy/`

1) **Update plugin header** in `vibecode-deploy.php`
   - Plugin Name → `Vibe Code Deploy`
   - Description → remove Etch-only language
   - Author → `Vibe Code Deploy`

2) **Rename constants**
   - `ETCHVIBE_PLUGIN_*` → `VIBECODE_DEPLOY_PLUGIN_*`

3) **Update bootstrap include/init call**
   - `EtchVibe\Bootstrap::init();` → `VibeCode\Deploy\Bootstrap::init();`

4) **Rename PHP namespaces everywhere**
   - `EtchVibe` → `VibeCode\Deploy`
   - `EtchVibe\Services` → `VibeCode\Deploy\Services`
   - `EtchVibe\Admin` → `VibeCode\Deploy\Admin`
   - Update all fully-qualified references like `\EtchVibe\Admin\ImportPage` accordingly.

5) **Rename admin slugs + actions + nonces**
   - Replace `etchvibe-...` → `vibecode-deploy-...`
   - Replace `etchvibe_...` → `vibecode_deploy_...`

**Result after Phase 1:** plugin loads as a distinct identity and no longer references EtchVibe namespaces/constants.

---

### Phase 2 — Gutenberg-default behavior (product change)
Once identity is clean, implement the Gutenberg-first approach:

1) **Add conversion modes**
   - default: passthrough when input already contains Gutenberg blocks (`<!-- wp:`)
   - optional: HTML → blocks conversion (your current converter)
   - fallback: wrap as `core/html`

2) **Make “Etch Mode” optional**
   - current `html_to_etch_blocks()` and any `metadata.etchData` behavior should be behind a setting/flag.

3) **Make HTML constraints configurable**
   - Keep constraints that reduce risk, but convert them into:
     - strict / warn / fallback
   - Document tradeoffs for each constraint.

---

## Recommended “start next time” sequence

1) Run repo-wide searches in `plugins/vibecode-deploy` for:
   - `EtchVibe`
   - `ETCHVIBE_`
   - `etchvibe`

2) Apply the rename in a controlled series:
   - entrypoint header/constants/init
   - `Bootstrap.php` namespace + requires
   - Admin + Services + Importer namespaces

3) Confirm:
   - no remaining `namespace EtchVibe`
   - no remaining `ETCHVIBE_`

4) Only then start Gutenberg-default refactor.

---

## Current status summary

- **Repo:** cloned ✅
- **Plugin copied into repo:** ✅
- **Folder + entrypoint renamed:** ✅
- **Code identity rename (EtchVibe → Vibe Code Deploy):** not done yet ⏳
- **Gutenberg-default behavior:** not started yet ⏳
