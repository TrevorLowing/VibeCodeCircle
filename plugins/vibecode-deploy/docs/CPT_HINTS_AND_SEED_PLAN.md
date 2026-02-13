# CPT Content: Intentional Hints + Separate Seed Data (Plan)

## Problem

- **Selector-based extraction is fragile**: Config in `vibecode-deploy-shortcodes.json` (`extract_cpt_pages` with CSS selectors per page) is decoupled from the HTML. Class renames or structure changes break extraction silently; no single source of truth.
- **Insights (and similar) don’t work**: BGP Insights use core `post`; there is no extraction config for them and no seed data, so the list is empty after deploy. Other CPTs that “should” have data often fail for the same reasons (wrong selectors, missing config, or CPT not registered in time).
- **Unclear intent**: In the HTML it’s not obvious what is “static layout” vs “CPT content to be extracted.” Authors and tooling can’t reliably tell what will become posts.

## Proposed Design: Two Separate Processes

| Process | Purpose | Source of truth | When it runs |
|--------|---------|------------------|---------------|
| **A. HTML hints (intentional CPT blocks)** | Mark in the **HTML** which blocks represent one CPT item. Only those blocks are parsed for extraction. | Comments (and optional data attributes) in the static HTML. | During deploy, after theme is loaded (same as today’s extraction, but only for hinted blocks). |
| **B. CPT seed data** | Create/update CPT (and optionally core `post`) content from **structured data files**, not from the DOM. | JSON (or YAML) files in staging, e.g. `seed/bgp_products.json`, `seed/posts.json`. | During deploy (or a dedicated “Import seed” step); no HTML parsing. |

**Principles**

- **Static by default**: If there’s no hint and no seed, nothing is extracted/imported. Layout stays static.
- **Intent in code**: Comments (and optional attributes) make “this is CPT content” explicit in the repo.
- **Seed data is first-class**: Representative content lives in versioned, editable seed files instead of being inferred from selectors.

---

## Process A: Intentional Hints in Static HTML

### 1. Comment-based block marker

In the HTML, wrap (or immediately precede) each block that represents **one CPT item** with a comment that identifies post type and optional taxonomy/term:

```html
<!-- VIBECODE_CPT_BLOCK cpt="bgp_product" taxonomy="bgp_product_type" term="accessory" -->
<div class="bgp-accessory-card">
    <h3 class="bgp-accessory-title">Desulfurizer Unit</h3>
    <p class="bgp-accessory-text">Remove H₂S for cleaner burning. Extends stove life.</p>
    <p class="bgp-accessory-price">8,500 KES</p>
    ...
</div>
<!-- /VIBECODE_CPT_BLOCK -->
```

- **Parser behavior**: During deploy, the plugin looks only for blocks between `VIBECODE_CPT_BLOCK` and `/VIBECODE_CPT_BLOCK` (or the next same-level block). No extraction from other DOM nodes.
- **Field mapping**: Either (1) a small, per-CPT schema in config (e.g. “for `bgp_product`, title = .bgp-accessory-title, content = .bgp-accessory-text, meta price = .bgp-accessory-price”), or (2) data attributes on the block for field names so the HTML is self-describing.

### 2. Optional: data attributes for self-describing blocks

To avoid config for selectors, the block can declare field mapping:

```html
<!-- VIBECODE_CPT_BLOCK cpt="bgp_product" taxonomy="bgp_product_type" term="accessory" -->
<div class="bgp-accessory-card"
     data-cpt-title=".bgp-accessory-title"
     data-cpt-content=".bgp-accessory-text"
     data-cpt-meta-price=".bgp-accessory-price">
    <h3 class="bgp-accessory-title">Desulfurizer Unit</h3>
    ...
</div>
<!-- /VIBECODE_CPT_BLOCK -->
```

Plugin uses the first element matching the selector inside the block for each field. Config can still define defaults per CPT so attributes are only needed when the structure differs.

### 3. What stays static

- Any section **without** a `VIBECODE_CPT_BLOCK` wrapper is never parsed for CPT extraction. Shortcode placeholders (e.g. `<!-- VIBECODE_SHORTCODE bgp_products type="system" -->`) remain as shortcodes; static copy and layout stay as-is.
- Clear rule: **only explicitly hinted blocks** are considered for extraction.

---

## Process B: Separate CPT Seed Data

### 1. Staging layout

Seed data lives next to (or inside) the staging bundle, not inside HTML:

```
vibecode-deploy-staging/
├── pages/
│   ├── products.html
│   ├── faqs.html
│   └── ...
├── seed/
│   ├── bgp_products.json
│   ├── bgp_faqs.json
│   ├── bgp_case_studies.json
│   └── posts.json          # optional: core "post" for Insights
├── vibecode-deploy-shortcodes.json
└── ...
```

### 2. Seed file format (per CPT or per post type)

One file per post type (or one file with a key per type). Example for products:

**seed/bgp_products.json**

```json
{
  "post_type": "bgp_product",
  "items": [
    {
      "post_title": "Desulfurizer Unit",
      "post_content": "Remove H₂S for cleaner burning. Extends stove life.",
      "meta": { "price": "8,500 KES" },
      "terms": { "bgp_product_type": ["accessory"] }
    },
    {
      "post_title": "Dual-Burner Stove",
      "post_content": "Heavy-duty steel stove. Works on biogas or LPG.",
      "meta": { "price": "12,000 KES" },
      "terms": { "bgp_product_type": ["accessory"] }
    }
  ]
}
```

For FAQs (with category term):

**seed/bgp_faqs.json**

```json
{
  "post_type": "bgp_faq",
  "items": [
    {
      "post_title": "How long does installation take?",
      "post_content": "<p><strong>Household systems take 1–2 days</strong>, depending on site prep. We handle digging, connecting, and testing. Commercial systems take <strong>5–10 days</strong>. We schedule installation at your convenience and handle all permits if needed.</p>",
      "terms": { "bgp_faq_category": ["installation"] }
    },
    {
      "post_title": "What maintenance does the system need?",
      "post_content": "<p>Very little! Monthly tasks include checking the digester level and cleaning the stove. We recommend <strong>quarterly inspections</strong> (3 months) to check piping and seals. Most homeowners do monthly checks themselves—takes 15 minutes.</p>",
      "terms": { "bgp_faq_category": ["installation"] }
    }
  ]
}
```

For Insights (core `post`), so the Insights page and “Recent posts” widgets have content:

**seed/posts.json**

```json
{
  "post_type": "post",
  "items": [
    {
      "post_title": "Getting started with household biogas",
      "post_content": "How to size your system and what to expect in the first months.",
      "post_excerpt": "How to size your system and what to expect in the first months.",
      "terms": { "category": ["Insights"] }
    },
    {
      "post_title": "Bio-slurry: turn waste into fertilizer",
      "post_content": "Using digestate to improve soil and crop yields on your farm.",
      "post_excerpt": "Using digestate to improve soil and crop yields on your farm.",
      "terms": { "category": ["Insights"] }
    },
    {
      "post_title": "Savings that pay back your system",
      "post_content": "Real numbers from Kenyan homes and small farms.",
      "post_excerpt": "Real numbers from Kenyan homes and small farms.",
      "terms": { "category": ["Insights"] }
    }
  ]
}
```

### 3. Deploy behavior for seed data

- **When**: During deploy, after theme (and CPTs) are loaded. Can run in the same follow-up request as today’s extraction, or in a dedicated “import seed” phase.
- **How**: Plugin reads `seed/*.json` (or a list in config), validates `post_type` and structure, then creates/updates posts (e.g. match by title + post type + terms to avoid duplicates).
- **Idempotency**: Same rules as current extraction: match existing by title (+ type + terms); update or create. Re-deploying the same zip should not create duplicates.

---

## What BGP Would Look Like End-to-End

### 1. Products page: hybrid (shortcode + hinted blocks)

- **Systems**: Remain shortcode-only (no static cards); shortcode pulls from `bgp_product` with term `system` (can be seeded via seed file or left for manual entry).
- **Accessories**: Either
  - **Option A (hints)**: In `products.html`, wrap each accessory card in `VIBECODE_CPT_BLOCK` so deploy extracts them into `bgp_product` with term `accessory`, or
  - **Option B (seed only)**: Remove static cards and use only `<!-- VIBECODE_SHORTCODE bgp_products type="accessory" -->`; seed `seed/bgp_products.json` with accessory items so the shortcode has content.

Example **Option A** in `products.html`:

```html
<!-- ACCESSORIES & UPGRADES -->
<section class="bgp-section bgp-accessories-section" id="accessories">
    ...
    <div class="bgp-accessories-grid">
        <!-- VIBECODE_CPT_BLOCK cpt="bgp_product" taxonomy="bgp_product_type" term="accessory" -->
        <div class="bgp-accessory-card">
            <div class="bgp-accessory-icon">🔧</div>
            <h3 class="bgp-accessory-title">Desulfurizer Unit</h3>
            <p class="bgp-accessory-text">Remove H₂S for cleaner burning. Extends stove life.</p>
            <p class="bgp-accessory-price">8,500 KES</p>
            <a href="..." class="bgp-btn ...">Order Now</a>
        </div>
        <!-- /VIBECODE_CPT_BLOCK -->
        <!-- VIBECODE_CPT_BLOCK cpt="bgp_product" taxonomy="bgp_product_type" term="accessory" -->
        <div class="bgp-accessory-card">
            ...
        </div>
        <!-- /VIBECODE_CPT_BLOCK -->
        ...
    </div>
</section>
```

Config would define, once per CPT, the default field mapping (e.g. `post_title` ← `.bgp-accessory-title`, `post_content` ← `.bgp-accessory-text`, `price_meta` ← `.bgp-accessory-price`) so the comment doesn’t need to repeat it.

### 2. FAQs page: hybrid (shortcodes + one section with hints or seed)

- **Cost, operation, environment, safety**: Remain shortcode-only; content comes from CPT populated by seed (or hints elsewhere).
- **Installation**: Today this section has static `.bgp-faq-item` blocks. Either:
  - **Option A**: Wrap each in `VIBECODE_CPT_BLOCK cpt="bgp_faq" taxonomy="bgp_faq_category" term="installation"` and extract from HTML, or
  - **Option B**: Replace static items with `<!-- VIBECODE_SHORTCODE bgp_faqs category="installation" -->` and add `seed/bgp_faqs.json` with the same Q&A pairs.

Example **Option A** (one item) in `faqs.html`:

```html
<div class="bgp-faq-category" id="installation">
    <h2 class="bgp-faq-category-title">🔧 Installation & Maintenance</h2>
    <div class="bgp-faq-accordion">
        <!-- VIBECODE_CPT_BLOCK cpt="bgp_faq" taxonomy="bgp_faq_category" term="installation" -->
        <div class="bgp-faq-item bgp-faq-item--primary">
            <button class="bgp-faq-question" ...>How long does installation take?</button>
            <div class="bgp-faq-answer" ...><p><strong>Household systems take 1–2 days</strong>...</p></div>
        </div>
        <!-- /VIBECODE_CPT_BLOCK -->
        ...
    </div>
</div>
```

Default field mapping for `bgp_faq`: `post_title` ← `.bgp-faq-question` (text), `post_content` ← `.bgp-faq-answer` (inner HTML).

### 3. Insights: seed only (no extraction)

- Insights use core `post` and shortcodes `bgp_recent_posts` / `bgp_posts_list`. There are no CPT blocks to extract; the static cards are placeholders.
- Add **seed/posts.json** (or `seed/insights.json` if we name it by purpose) with 3–5 sample posts. On deploy, plugin imports these as `post` with category “Insights” (or whatever the theme expects). Then Insights and “Recent posts” show real content.

### 4. Config changes for BGP

- **Remove or deprecate** `extract_cpt_pages` with page-specific CSS selectors. Replace with:
  - **Option 1**: Global “CPT block extraction” enabled when the plugin finds any `VIBECODE_CPT_BLOCK` in staged HTML, plus a single **per-CPT field map** in config (or in plugin defaults for known CPTs like `bgp_product` / `bgp_faq`).
  - **Option 2**: No extraction config; only comment + (optional) data attributes in HTML; plugin infers from block structure and a small convention (e.g. first h2/h3 = title, first content block = content, known meta keys from ACF).
- **Add** a “seed” section or convention: e.g. `seed_enabled: true` and plugin reads all `seed/*.json`; or an explicit list `seed_files: ["bgp_products.json", "bgp_faqs.json", "posts.json"]` in config.

---

## Implementation Phases (Suggested)

1. **Phase 1 – Seed data only**
   - Add `seed/` support: read JSON, create/update posts by post_type; run after theme deploy (same as current extraction timing). No change to HTML.
   - BGP: add `seed/bgp_products.json`, `seed/bgp_faqs.json`, `seed/posts.json` and wire deploy to import them. Insights and other CPTs start working from day one.

2. **Phase 2 – HTML hints (optional extraction)**
   - Parser for `VIBECODE_CPT_BLOCK` / `VIBECODE_CPT_BLOCK`; extract only those blocks; use per-CPT field mapping (config or convention). Deprecate or remove selector-based `extract_cpt_pages`.
   - BGP: add hints to products and faqs where we want “this block → one CPT post.” Keep seed as alternative or supplement.

3. **Phase 3 – Documentation and defaults**
   - Document the two processes in STRUCTURAL_RULES; add BGP (and optionally CFA) examples. Decide default field maps for common CPTs so authors need minimal config.

---

## Summary Table (BGP)

| Content type      | Today                         | After (hints + seed) |
|-------------------|-------------------------------|-----------------------|
| Products (systems)| Shortcode only                | Unchanged; seed optional for systems. |
| Products (accessories) | Selector-based extraction from DOM | **Hinted blocks** in HTML and/or **seed/bgp_products.json**. |
| FAQs (installation)    | Selector-based extraction     | **Hinted blocks** and/or **seed/bgp_faqs.json**. |
| FAQs (other categories) | Shortcode only                | Shortcode + content from seed (or hinted elsewhere). |
| Insights           | Shortcode; no posts → empty   | **seed/posts.json** (core `post`); shortcodes show real posts. |
| Case studies      | Shortcode only                | Optional **seed/bgp_case_studies.json** later. |

Result: **Static is intentional** (no hint = not extracted). **CPT content is intentional** (hints in HTML or explicit seed files). Insights and other CPTs work via seed; extraction from HTML is limited to explicitly marked blocks and is optional when seed is used instead.
