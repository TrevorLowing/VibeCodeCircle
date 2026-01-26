# Fix List Items and Inline Elements - January 26, 2026

## Problem

User reports still seeing CORE/HTML blocks for:
- **List items (`<li>`)**: Not being converted to proper `wp:list-item` blocks
- **Spans (`<span>`)**: Being converted to separate `wp:html` blocks instead of staying inline
- **Other inline elements**: Similar issue

## Root Cause Analysis

### Issue 1: List Items (`<li>`)

**Current Behavior:**
- `<ul>`/`<ol>` are converted to `wp:list` blocks ✅
- But `<li>` elements inside are processed by `convert_dom_children()` which doesn't convert them to `wp:list-item` blocks
- They fall through to default handler → `wp:html` blocks (CORE/HTML)

**Gutenberg List Block Structure:**
- Parent: `wp:list` block
- Children: Each `<li>` must be a `wp:list-item` block (not HTML)

**Solution:** Add handler for `<li>` elements to convert them to `wp:list-item` blocks WITH etchData.

### Issue 2: Inline Elements (`<span>`, etc.)

**Current Behavior:**
- Inline elements are converted to separate `wp:html` blocks
- This creates too many blocks and breaks inline flow

**Expected Behavior:**
- Inline elements should remain inline within their parent blocks (paragraphs, headings, etc.)
- Only standalone inline elements (not nested) should become blocks

**Solution:** 
- Keep inline elements inline when nested in block-level parents
- Only convert standalone inline elements to blocks (if needed)

## Implementation Plan

### Step 1: Add List Item Handler

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`

**Add handler for `<li>` elements BEFORE list handler (around line 610):**

```php
// Handle list items - convert to list-item blocks WITH etchData
if ( $tag === 'li' ) {
	$inner = self::convert_dom_children( $el );
	
	$list_item_attrs = array();
	if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
		$list_item_attrs['className'] = $attrs['class'];
	}
	
	// Add etchData for EtchWP IDE editability
	$list_item_attrs['metadata'] = array(
		'name' => 'List Item',
		'etchData' => self::build_etch_data( 'li', $attrs ),
	);
	
	// Build HTML attributes (id, data-*, etc.)
	$element_attrs = '';
	foreach ( $attrs as $key => $value ) {
		if ( $key === 'class' ) {
			continue; // Already handled in className
		}
		if ( is_string( $value ) && $value !== '' ) {
			$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	
	return self::block_open( 'list-item', $list_item_attrs ) . "\n" .
		'<li' . $element_attrs . '>' . $inner . '</li>' . "\n" .
		self::block_close( 'list-item' ) . "\n";
}
```

### Step 2: Refine Inline Elements Handling

**Current Issue:** Inline elements are always converted to `wp:html` blocks, even when nested.

**Solution:** Only convert inline elements to blocks if they're standalone (direct children of block-level containers), otherwise keep them inline.

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 800+)

**Update inline elements handler:**

```php
// Handle inline elements - preserve as-is in wp:html blocks ONLY if standalone
if ( in_array( $tag, $inline_elements, true ) ) {
	// Check if parent is a block-level element
	$parent = $el->parentNode;
	$is_standalone = false;
	if ( $parent instanceof \DOMElement ) {
		$parent_tag = strtolower( $parent->tagName );
		$block_parents = array( 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'form', 'body', 'html' );
		// If parent is block-level, this is a standalone inline element
		if ( in_array( $parent_tag, $block_parents, true ) ) {
			$is_standalone = true;
		}
	}
	
	// Only convert to block if standalone, otherwise return as-is (will be handled by parent)
	if ( $is_standalone ) {
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
	} else {
		// Nested inline element - return as raw HTML (will be included in parent block)
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
			return $element_html;
		}
	}
}
```

**Alternative Simpler Approach:** Keep inline elements inline by default, only convert to blocks if they have block-level styling or are direct children of body/html.

## Testing Checklist

- [ ] List items convert to `wp:list-item` blocks with etchData
- [ ] Lists show properly in EtchWP IDE (not CORE/HTML)
- [ ] Spans inside paragraphs remain inline (not separate blocks)
- [ ] Standalone spans can still be converted to blocks if needed
- [ ] Other inline elements (strong, em, etc.) remain inline
- [ ] PHP syntax validation passes

## Files to Modify

1. **`VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`**
   - Add list item handler
   - Refine inline elements handler (optional - may keep simple)
