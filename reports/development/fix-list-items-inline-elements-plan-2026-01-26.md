# Fix List Items and Inline Elements - Implementation Plan

## Problem

User reports seeing CORE/HTML blocks for:
- **List items (`<li>`)**: Not converted to `wp:list-item` blocks
- **Spans and other inline elements**: Converted to separate `wp:html` blocks instead of staying inline

## Root Cause

1. **List Items**: No handler for `<li>` elements - they fall through to default handler
2. **Inline Elements**: Current handler converts ALL inline elements to `wp:html` blocks, even when nested inside paragraphs/headings

## Solution

### Fix 1: Add List Item Handler

Add handler to convert `<li>` to `wp:list-item` blocks WITH etchData.

### Fix 2: Keep Inline Elements Inline

Inline elements should NOT be converted to blocks when nested. They should return raw HTML strings that get included in parent blocks.

**Exception:** Only convert to blocks if truly standalone (direct child of body/html with block-level display).

## Implementation

### Step 1: Add List Item Handler

**Location:** After paragraph handler, before list handler (around line 608)

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

### Step 2: Update Inline Elements Handler

**Location:** Line 806

**Change from:** Convert all inline elements to `wp:html` blocks

**Change to:** Keep inline elements as raw HTML (return HTML string, not block)

```php
// Handle inline elements - keep as raw HTML (not blocks) so they stay inline in parent blocks
if ( in_array( $tag, $inline_elements, true ) ) {
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
		
		// Return as raw HTML string (not a block) - will be included in parent block
		return $element_html;
	}
}
```

**Rationale:** Inline elements like `<span>`, `<strong>`, `<em>` should remain inline within their parent blocks (paragraphs, headings, list items). Converting them to separate blocks breaks the flow and creates too many CORE/HTML blocks.

## Expected Results

### Before
- List items: `wp:html` blocks (CORE/HTML)
- Spans: Separate `wp:html` blocks (CORE/HTML)
- Inline elements: Separate blocks breaking flow

### After
- List items: `wp:list-item` blocks WITH etchData ✅
- Spans: Inline HTML within parent blocks ✅
- Inline elements: Inline HTML within parent blocks ✅

## Files to Modify

1. **`VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`**
   - Add list item handler (after paragraph handler)
   - Update inline elements handler (line 806)
