# Rules Pack

This file is included with the Vibe Code Deploy plugin install as the **rules pack content**.

It focuses on the rules that most directly impact **Vibe-coded HTML/CSS/JS packaging** and **WordPress/Gutenberg deployment**.

---

## 🚨 CRITICAL: Plugin Must Be Project-Agnostic

**NEVER hardcode project-specific values in plugin code.**

**What NOT to do:**
- ❌ Hardcode class prefixes (e.g., `:not([class*="cfa-"])`, `:not([class*="bgp-"])`)
- ❌ Hardcode project slugs or identifiers
- ❌ Hardcode project-specific CSS selectors
- ❌ Hardcode project-specific file paths
- ❌ Add project-specific logic or conditionals

**What TO do:**
- ✅ Use generic selectors that work for all projects
- ✅ Let projects handle their own component styling in project CSS
- ✅ Use configuration/settings for project-specific values when needed
- ✅ Keep plugin code generic and reusable

**Why this matters:**
- Plugin is used by multiple projects (CFA, BGP, future projects)
- Hardcoding breaks the plugin for other projects
- Violates separation of concerns (plugin = generic, projects = specific)
- Creates maintenance burden (must update plugin for each new project)

**Examples:**
```css
/* ❌ WRONG - Hardcoded project prefix */
.wp-block-group:not([class*="cfa-"]):not([class*="bgp-"]) {
    margin: 0;
}

/* ✅ CORRECT - Generic, project-agnostic */
.wp-block-group:not([class*="wp-block"]):not([class*="is-layout"]) {
    margin: 0;
}
/* Projects override in their own CSS: .my-project-hero.wp-block-group { margin: 2rem 0; } */
```

**When reviewing plugin code changes:**
- Ask: "Does this work for any project, or only specific ones?"
- If project-specific, move it to project CSS or make it configurable
- Never assume only current projects will use the plugin

---

## 🚨 CRITICAL: Always Deploy and Verify Changes Locally

**NEVER claim fixes work without deploying and verifying them.**

**Required Workflow:**
1. ✅ Make code changes
2. ✅ Build staging zip (if needed)
3. ✅ **Deploy to local Docker WordPress** (automated via script)
4. ✅ **Run comprehensive visual audit** (automated)
5. ✅ **Fix any issues found** (repeat until audit passes)
6. ✅ **Only then** report that fixes are complete

**What NOT to do:**
- ❌ Claim fixes work without deploying
- ❌ Skip verification step
- ❌ Assume changes will work
- ❌ Report completion without running audit

**What TO do:**
- ✅ Always run `./scripts/auto-deploy-verify.sh` after making changes
- ✅ Fix all issues found in audit before reporting completion
- ✅ Verify pages are actually deployed (not just CSS loading)
- ✅ Check that content renders, not just that files exist

**Automation:**
- Projects should have `scripts/auto-deploy-verify.sh` that:
  - Deploys staging zip to local Docker WordPress
  - Verifies pages are deployed and accessible
  - Runs comprehensive visual audit
  - Reports all issues found
- Run this script automatically after any changes

**Why this matters:**
- Prevents wasting time on fixes that don't actually work
- Catches deployment issues early
- Ensures changes are verified before reporting completion
- Eliminates manual back-and-forth

---

---

## Files + organization (required)

### External files only (no inline code)

- CSS
- Do not use inline `<style>` tags
- Do not use `style=""` attributes
- Put styles in external CSS files (project convention: `css/styles.css`)

- JavaScript
- Do not use inline `<script>` tags
- Do not use `onclick=""` or other inline handlers
- Put scripts in external JS files (project convention: `js/main.js`)
- Prefer modern ES6+ and event delegation

### Script/style include structure

```html
<head>
    <link rel="stylesheet" href="css/icons.css">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- content -->
    <script src="js/icons.js"></script>
    <script src="js/main.js"></script>
</body>
```

---

## HTML standards (required)

### Page wrapper + semantic structure

All pages must use a single wrapper and semantic regions. **Important:** The skip link must be the first element, and any elements that appear on EVERY page header should be INSIDE the header element.

```html
<body>
    <!-- Skip link (first element, before header) -->
    <a class="{project-prefix}-skip-link" href="#main" aria-label="Skip to main content">Skip to main content</a>
    
    <div class="{project-prefix}-page-content">
        <!-- Header (extracted as template part) -->
        <header class="{project-prefix}-header" role="banner">
            <!-- Elements repeated on every page (e.g., top bar, announcements) -->
            <div class="{project-prefix}-top-bar">
                <div class="{project-prefix}-top-bar__container">
                    <span>Established 2024</span>
                    <div class="{project-prefix}-top-bar__links">
                        <a href="about" class="{project-prefix}-top-bar__link">About</a>
                        <a href="services" class="{project-prefix}-top-bar__link">Services</a>
                        <a href="/wp-login.php" class="{project-prefix}-top-bar__link">Login</a>
                        <a href="contact" class="{project-prefix}-top-bar__link">Contact</a>
                    </div>
                </div>
            </div>
            
            <!-- Main header content -->
            <div class="header__container {project-prefix}-header__container">
                <!-- header content -->
            </div>
        </header>

        <!-- Main content (imported as page content) -->
        <main id="main" class="{project-prefix}-main">
            <!-- page content -->
        </main>

        <!-- Footer (extracted as template part) -->
        <footer class="{project-prefix}-footer" role="contentinfo">
            <!-- footer content -->
        </footer>
    </div>

    <script src="js/icons.js"></script>
    <script src="js/main.js"></script>
</body>
```

**Key points:**
- Skip link is the VERY FIRST element inside `body`
- Elements repeated on every page header should be INSIDE `<header>` (part of header template part)
- Only `<header>` and `<footer>` are extracted as template parts
- Only `<main>` content is imported as page content

### BEM naming + WordPress-safe naming

- Use **BEM** (Block / Element / Modifier)
- Avoid generic class names that collide with WordPress core/themes/plugins

Examples:

- Avoid
- `.nav`
- `.content`
- `.sidebar`

- Prefer
- `.{project-prefix}-site-header__nav`
- `.{project-prefix}-page-content`
- `.{project-prefix}-widget-sidebar`

---

## CSS standards (required)

### Use CSS custom properties

- Prefer `var(--token)` usage instead of hard-coded colors

### Mobile-first

- Define mobile defaults first, then layer `@media (min-width: ...)` overrides

### Never use `!important`

- `!important` breaks maintainability and commonly conflicts with WordPress utility frameworks

---

## JavaScript standards (required)

### Required: IIFE encapsulation

All scripts must be wrapped to avoid global scope pollution:

```js
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // code here
    });
})();
```

---

## Accessibility (required)

- Provide `alt` text for images
- Use semantic elements: `header`, `nav`, `main`, `section`, `footer`
- Maintain heading order: `h1` → `h2` → `h3` (do not skip levels)
- Use `aria-label` where needed
- Use `aria-expanded` for toggles and keep it in sync with UI state
- Ensure keyboard navigability

---

## Header + footer standardization (required)

- Header and footer must be **consistent across pages** so they can be extracted into template parts during deployment.
- Required header container: `header__container` (not `container`).

---

## Dynamic content placeholders (shortcodes)

If a page needs dynamic WordPress content, do **not** paste raw `[shortcode]` text into HTML.

Use an HTML comment placeholder that the deploy pipeline converts into a Gutenberg `core/shortcode` block:

- Placeholder example
- `<!-- VIBECODE_SHORTCODE my_content_index paginate="1" per_page="20" -->`

Converted to:

- `<!-- wp:shortcode -->[my_content_index paginate="1" per_page="20"]<!-- /wp:shortcode -->`

---

## Session hygiene (required)

### Always document changed files

When you make changes, list files changed (created/modified/deleted) relative to project root:

```md
## Files Changed:
- `home.html` - Modified: Added login button
- `css/styles.css` - Modified: Updated header styles
- `js/main.js` - Modified: Added form handler
```
