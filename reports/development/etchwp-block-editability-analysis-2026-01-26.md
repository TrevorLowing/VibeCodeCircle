# EtchWP Block Editability Analysis - January 26, 2026

## Problem Statement

After implementing semantic block conversion, content is still showing as CORE/HTML blocks in EtchWP IDE instead of being editable. The user wants to maximize EtchWP-editable blocks.

## Key Discovery

### What Makes Blocks Editable in EtchWP IDE

**Critical Finding:** Blocks need `etchData` metadata to be editable in EtchWP IDE, regardless of block type.

**Evidence from EtchWP Reference Files:**

1. **Editable Paragraph Block:**
```json
{
  "blockName": "core/paragraph",
  "attrs": {
    "metadata": {
      "name": "Excerpt",
      "etchData": {
        "origin": "etch",
        "attributes": { "class": "blog-card__excerpt" },
        "block": { "type": "html", "tag": "p" }
      }
    }
  }
}
```

2. **Editable Heading Block:**
```json
{
  "blockName": "core/heading",
  "attrs": {
    "metadata": {
      "name": "Heading",
      "etchData": {
        "origin": "etch",
        "attributes": { "class": "blog-card__heading" },
        "block": { "type": "html", "tag": "h1" }
      }
    },
    "level": 1
  }
}
```

3. **Even wp:html Blocks Are Editable WITH etchData:**
```html
<!-- wp:html {"metadata":{"etchData":{"origin":"etch","attributes":{"xmlns":"http://www.w3.org/2000/svg"},"block":{"type":"html","tag":"svg"}}}} -->
<svg>...</svg>
<!-- /wp:html -->
```

4. **Blocks WITHOUT etchData Show as CORE/HTML:**
```html
<!-- wp:paragraph --><p>Text</p><!-- /wp:paragraph -->
<!-- This shows as CORE/HTML in EtchWP IDE (not editable) -->
```

## Current Implementation Analysis

### What We Currently Do

**File:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`

**Current Behavior:**

1. **Text Nodes (Line 437):**
   - ✅ Converts to `wp:paragraph` WITH `etchData`
   - ✅ Editable in EtchWP IDE

2. **Headings (Line 514):**
   - ✅ Converts to `wp:heading` blocks
   - ❌ NO `etchData` metadata
   - ❌ Shows as CORE/HTML, not editable

3. **Block-Level Elements (div, section, p, ul, ol, etc.) (Line 560):**
   - ✅ Converts to `wp:group` blocks WITH `etchData`
   - ✅ Editable in EtchWP IDE
   - ⚠️ BUT: Wrong block type (should be wp:paragraph, wp:list, etc.)

4. **Images (Line 475):**
   - ❌ Wrapped in `wp:html` blocks WITHOUT `etchData`
   - ❌ Shows as CORE/HTML, not editable

5. **Inline Elements (Line 542):**
   - ❌ Wrapped in `wp:html` blocks WITHOUT `etchData`
   - ❌ Shows as CORE/HTML, not editable

6. **Unsupported Elements (Line 632):**
   - ❌ Wrapped in `wp:html` blocks WITHOUT `etchData`
   - ❌ Shows as CORE/HTML, not editable

### The Problem

**Two Issues:**

1. **Wrong Block Types:** Semantic elements (p, ul, ol, img) are converted to `wp:group` blocks instead of their proper semantic blocks (wp:paragraph, wp:list, wp:image)

2. **Missing etchData:** Even when converted to correct block types, they lack `etchData` metadata, making them non-editable in EtchWP IDE

## Solution: Convert to Semantic Blocks WITH etchData

### Strategy

**Principle:** Convert semantic HTML elements to their proper Gutenberg blocks AND add `etchData` metadata to make them editable in EtchWP IDE.

### etchData Structure

**Required Structure:**
```php
'metadata' => array(
    'name' => 'Block Name',  // Optional: Human-readable name
    'etchData' => array(
        'origin' => 'etch',
        'attributes' => array(
            'class' => 'class-name',
            'id' => 'element-id',
            // ... all HTML attributes preserved
        ),
        'block' => array(
            'type' => 'html',  // 'html' for most elements, 'text' for text-only
            'tag' => 'p',      // HTML tag: 'p', 'ul', 'img', 'h2', etc.
        ),
    ),
)
```

### Block Conversion Matrix

| HTML Element | Current | Target | etchData Required |
|--------------|---------|--------|-------------------|
| `<p>` | `wp:group` + etchData | `wp:paragraph` + etchData | ✅ Yes |
| `<ul>`, `<ol>` | `wp:group` + etchData | `wp:list` + etchData | ✅ Yes |
| `<img>` | `wp:html` (no etchData) | `wp:image` + etchData | ✅ Yes |
| `<blockquote>` | `wp:group` + etchData | `wp:quote` + etchData | ✅ Yes |
| `<pre>` | `wp:group` + etchData | `wp:preformatted` + etchData | ✅ Yes |
| `<code>` | `wp:html` (no etchData) | `wp:code` + etchData | ✅ Yes |
| `<table>` | `wp:group` + etchData | `wp:table` + etchData | ✅ Yes |
| `<h1-h6>` | `wp:heading` (no etchData) | `wp:heading` + etchData | ✅ Yes |
| `<div>`, `<section>` | `wp:group` + etchData | `wp:group` + etchData | ✅ Yes (keep) |
| Text nodes | `wp:paragraph` + etchData | `wp:paragraph` + etchData | ✅ Yes (keep) |
| Custom HTML | `wp:html` (no etchData) | `wp:html` + etchData | ✅ Yes |

## Implementation Plan

### Phase 1: Add Helper Method

**File:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`

**Add method:**
```php
/**
 * Build etchData structure for a block.
 *
 * @param string $tag HTML tag name (e.g., 'p', 'ul', 'img').
 * @param array  $attrs HTML attributes.
 * @param string $block_type Block type ('html' for most, 'text' for text-only blocks).
 * @return array etchData array.
 */
private static function build_etch_data( string $tag, array $attrs, string $block_type = 'html' ): array {
    $attrs_for_json = empty( $attrs ) ? new \stdClass() : $attrs;
    
    return array(
        'origin' => 'etch',
        'attributes' => $attrs_for_json,
        'block' => array(
            'type' => $block_type,
            'tag' => $tag,
        ),
    );
}
```

### Phase 2: Add Semantic Block Handlers WITH etchData

**Add handlers BEFORE block-level elements check (around line 513):**

1. **Paragraph Handler:**
   - Convert `<p>` to `wp:paragraph` block
   - Add `metadata.etchData` using `build_etch_data('p', $attrs)`
   - Preserve classes via `className` attribute
   - Preserve other attributes in HTML and etchData

2. **List Handler:**
   - Convert `<ul>`, `<ol>` to `wp:list` block
   - Add `metadata.etchData` using `build_etch_data('ul' or 'ol', $attrs)`
   - Extract list-specific attributes (type, start) for block attributes
   - Preserve other attributes in etchData

3. **Image Handler:**
   - Remove `img` from `$void_elements` array
   - Convert `<img>` to `wp:image` block
   - Add `metadata.etchData` using `build_etch_data('img', $attrs)`
   - Extract image attributes (src, alt, width, height) for block attributes
   - Preserve other attributes in etchData

4. **Blockquote Handler:**
   - Convert `<blockquote>` to `wp:quote` block
   - Add `metadata.etchData` using `build_etch_data('blockquote', $attrs)`
   - Extract citation if present

5. **Preformatted Handler:**
   - Convert `<pre>` to `wp:preformatted` block
   - Add `metadata.etchData` using `build_etch_data('pre', $attrs)`
   - Preserve raw HTML content

6. **Code Handler:**
   - Convert block-level `<code>` to `wp:code` block
   - Add `metadata.etchData` using `build_etch_data('code', $attrs)`
   - Handle inline code separately (keep as inline)

7. **Table Handler:**
   - Convert `<table>` to `wp:table` block
   - Add `metadata.etchData` using `build_etch_data('table', $attrs)`
   - Preserve table structure

### Phase 3: Update Existing Handlers

**Headings Handler (Line 514):**
- Currently creates `wp:heading` without etchData
- **Add:** `metadata.etchData` using `build_etch_data($tag, $attrs)`
- Keep existing level and className attributes

### Phase 4: Update Fallback Strategy

**Unsupported Elements Handler (Line 632):**
- Currently creates `wp:html` blocks without etchData
- **Change:** Add `metadata.etchData` using `build_etch_data($tag, $attrs)`
- Makes even custom HTML editable in EtchWP IDE

**Inline Elements Handler (Line 542):**
- Currently creates `wp:html` blocks without etchData
- **Change:** Add `metadata.etchData` using `build_etch_data($tag, $attrs)`
- Makes inline elements editable in EtchWP IDE

### Phase 5: Update Block Elements Array

**Remove semantic content elements from `$block_elements` array (Line 508):**
- Remove: `'ul'`, `'ol'`, `'li'`, `'blockquote'`, `'pre'`, `'table'`, `'thead'`, `'tbody'`, `'tfoot'`, `'tr'`, `'td'`, `'th'`
- Keep: Only structural containers: `'div'`, `'section'`, `'article'`, `'main'`, `'header'`, `'footer'`, `'aside'`, `'nav'`, `'form'`, `'dl'`, `'dt'`, `'dd'`, `'figure'`, `'figcaption'`, `'address'`

## Example Implementations

### Paragraph Block with etchData

```php
if ( $tag === 'p' ) {
    $inner = self::convert_dom_children( $el );
    
    $paragraph_attrs = array();
    if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
        $paragraph_attrs['className'] = $attrs['class'];
    }
    
    // Add etchData for EtchWP IDE editability
    $paragraph_attrs['metadata'] = array(
        'name' => 'Paragraph',
        'etchData' => self::build_etch_data( 'p', $attrs ),
    );
    
    // Build HTML attributes (id, data-*, etc.)
    $element_attrs = '';
    foreach ( $attrs as $key => $value ) {
        if ( $key === 'class' ) {
            continue; // Already in className
        }
        if ( is_string( $value ) && $value !== '' ) {
            $element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
        }
    }
    
    return self::block_open( 'paragraph', $paragraph_attrs ) . "\n" .
        '<p' . $element_attrs . '>' . $inner . '</p>' . "\n" .
        self::block_close( 'paragraph' ) . "\n";
}
```

### Heading Block with etchData

```php
// Update existing heading handler (Line 514)
if ( preg_match( '/^h[1-6]$/', $tag ) ) {
    $level = (int) substr( $tag, 1 );
    $inner = self::convert_dom_children( $el );
    $inner_text = trim( strip_tags( $inner ) );
    
    $heading_attrs = array( 'level' => $level );
    if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
        $heading_attrs['className'] = $attrs['class'];
    }
    
    // ADD etchData for EtchWP IDE editability
    $heading_attrs['metadata'] = array(
        'name' => 'Heading',
        'etchData' => self::build_etch_data( $tag, $attrs ),
    );
    
    // Build HTML attributes
    $element_attrs = '';
    foreach ( $attrs as $key => $value ) {
        if ( $key === 'class' ) {
            continue;
        }
        if ( is_string( $value ) && $value !== '' ) {
            $element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
        }
    }
    
    return self::block_open( 'heading', $heading_attrs ) . "\n" .
        '<' . $tag . $element_attrs . '>' . $inner_text . '</' . $tag . '>' . "\n" .
        self::block_close( 'heading' ) . "\n";
}
```

### Image Block with etchData

```php
if ( $tag === 'img' ) {
    $image_attrs = array();
    if ( isset( $attrs['src'] ) && is_string( $attrs['src'] ) ) {
        $image_attrs['url'] = $attrs['src'];
    }
    if ( isset( $attrs['alt'] ) && is_string( $attrs['alt'] ) ) {
        $image_attrs['alt'] = $attrs['alt'];
    }
    if ( isset( $attrs['width'] ) && is_numeric( $attrs['width'] ) ) {
        $image_attrs['width'] = (int) $attrs['width'];
    }
    if ( isset( $attrs['height'] ) && is_numeric( $attrs['height'] ) ) {
        $image_attrs['height'] = (int) $attrs['height'];
    }
    if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
        $image_attrs['className'] = $attrs['class'];
    }
    
    // Add etchData for EtchWP IDE editability
    $image_attrs['metadata'] = array(
        'name' => 'Image',
        'etchData' => self::build_etch_data( 'img', $attrs ),
    );
    
    // Build HTML attributes
    $element_attrs = '';
    foreach ( $attrs as $key => $value ) {
        if ( in_array( $key, array( 'class', 'src', 'alt', 'width', 'height' ), true ) ) {
            continue; // Already handled
        }
        if ( is_string( $value ) && $value !== '' ) {
            $element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
        }
    }
    
    return self::block_open( 'image', $image_attrs ) . "\n" .
        '<img' . $element_attrs . ' />' . "\n" .
        self::block_close( 'image' ) . "\n";
}
```

### Fallback Handler with etchData

```php
// Update default handler (Line 632)
// Default: For any other elements, preserve as-is in wp:html block WITH etchData
$dom = $el->ownerDocument;
if ( $dom instanceof \DOMDocument ) {
    $inner_html = self::convert_dom_children( $el );
    $element_html = '<' . $tag;
    foreach ( $attrs as $key => $value ) {
        if ( is_string( $value ) && $value !== '' ) {
            $element_html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
        }
    }
    $element_html .= '>' . $inner_html . '</' . $tag . '>';
    
    // Add etchData to make wp:html blocks editable in EtchWP IDE
    $html_attrs = array(
        'metadata' => array(
            'name' => strtoupper( $tag ),
            'etchData' => self::build_etch_data( $tag, $attrs ),
        ),
    );
    
    return self::block_open( 'html', $html_attrs ) . "\n" .
        $element_html . "\n" .
        self::block_close( 'html' ) . "\n";
}
```

## Expected Results

### Before (Current State)

**Paragraphs:**
```
<!-- wp:group {"metadata":{"etchData":{...}}} -->
<div class="wp-block-group">
  <p>Text</p>
</div>
<!-- /wp:group -->
```
- ✅ Editable (has etchData)
- ❌ Wrong block type (should be wp:paragraph)

**Headings:**
```
<!-- wp:heading {"level":2} -->
<h2>Heading</h2>
<!-- /wp:heading -->
```
- ❌ Not editable (no etchData)
- ✅ Correct block type

**Images:**
```
<!-- wp:html -->
<img src="logo.png" alt="Logo" />
<!-- /wp:html -->
```
- ❌ Not editable (no etchData)
- ❌ Wrong block type (should be wp:image)

### After (Target State)

**Paragraphs:**
```
<!-- wp:paragraph {"metadata":{"name":"Paragraph","etchData":{"origin":"etch","attributes":{"class":"intro"},"block":{"type":"html","tag":"p"}}}} -->
<p class="intro">Text</p>
<!-- /wp:paragraph -->
```
- ✅ Editable (has etchData)
- ✅ Correct block type (wp:paragraph)

**Headings:**
```
<!-- wp:heading {"level":2,"metadata":{"name":"Heading","etchData":{"origin":"etch","attributes":{"class":"title"},"block":{"type":"html","tag":"h2"}}}} -->
<h2 class="title">Heading</h2>
<!-- /wp:heading -->
```
- ✅ Editable (has etchData)
- ✅ Correct block type (wp:heading)

**Images:**
```
<!-- wp:image {"url":"logo.png","alt":"Logo","metadata":{"name":"Image","etchData":{"origin":"etch","attributes":{"class":"site-logo"},"block":{"type":"html","tag":"img"}}}} -->
<img class="site-logo" />
<!-- /wp:image -->
```
- ✅ Editable (has etchData)
- ✅ Correct block type (wp:image)

## Benefits

1. **Fully Editable in EtchWP IDE:** All blocks have etchData, making them editable
2. **Semantically Correct:** Blocks use proper Gutenberg block types (wp:paragraph, wp:list, etc.)
3. **Preserved Attributes:** All HTML attributes preserved in etchData.attributes
4. **Better UX:** Content creators can edit directly in EtchWP IDE
5. **Compatible:** Still works in standard Gutenberg editor (etchData is just metadata)

## Files to Modify

1. **`VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`**
   - Add `build_etch_data()` helper method
   - Add semantic block handlers (paragraph, list, image, blockquote, pre, code, table) WITH etchData
   - Update headings handler to include etchData
   - Update fallback handlers to include etchData
   - Update inline elements handler to include etchData
   - Remove semantic elements from `$block_elements` array
   - Remove `img` from `$void_elements` array

## Testing Checklist

- [ ] All semantic blocks have etchData metadata
- [ ] Block types are correct (wp:paragraph, wp:list, wp:image, etc.)
- [ ] Blocks are editable in EtchWP IDE (not showing as CORE/HTML)
- [ ] Attributes are preserved in both block attrs and etchData
- [ ] Content can be edited directly in EtchWP IDE
- [ ] Blocks still work in standard Gutenberg editor
- [ ] Frontend rendering is correct
- [ ] CSS classes are preserved and functional
