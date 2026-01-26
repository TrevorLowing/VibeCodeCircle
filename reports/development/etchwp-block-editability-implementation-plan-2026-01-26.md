# Implementation Plan: Maximize EtchWP-Editable Blocks with etchData Metadata

## Objective

Convert semantic HTML elements to proper Gutenberg blocks AND add `etchData` metadata to ALL blocks to make them fully editable in EtchWP IDE.

## Key Insight

**Blocks need `etchData` metadata to be editable in EtchWP IDE**, regardless of block type. Even `wp:html` blocks are editable if they have `etchData`.

## Current State

**File:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`

**Current Behavior:**
- ✅ Text nodes → `wp:paragraph` WITH `etchData` (editable)
- ❌ Headings → `wp:heading` WITHOUT `etchData` (not editable, shows as CORE/HTML)
- ✅ Block-level elements (p, ul, ol, etc.) → `wp:group` WITH `etchData` (editable, but wrong block type)
- ❌ Images → `wp:html` WITHOUT `etchData` (not editable, shows as CORE/HTML)
- ❌ Inline elements → `wp:html` WITHOUT `etchData` (not editable, shows as CORE/HTML)
- ❌ Unsupported elements → `wp:html` WITHOUT `etchData` (not editable, shows as CORE/HTML)

## Target State

**All blocks should have `etchData` metadata:**
- ✅ Headings → `wp:heading` WITH `etchData` (editable, correct type)
- ✅ Paragraphs → `wp:paragraph` WITH `etchData` (editable, correct type)
- ✅ Lists → `wp:list` WITH `etchData` (editable, correct type)
- ✅ Images → `wp:image` WITH `etchData` (editable, correct type)
- ✅ Blockquotes → `wp:quote` WITH `etchData` (editable, correct type)
- ✅ Code/Pre → `wp:code`/`wp:preformatted` WITH `etchData` (editable, correct type)
- ✅ Tables → `wp:table` WITH `etchData` (editable, correct type)
- ✅ Inline elements → `wp:html` WITH `etchData` (editable)
- ✅ Unsupported elements → `wp:html` WITH `etchData` (editable)

## Implementation Steps

### Step 1: Add Helper Method for etchData

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`

**Add after `convert_dom_children()` method (around line 466):**

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

### Step 2: Update Headings Handler to Include etchData

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 514)

**Current code:**
```php
$heading_attrs = array( 'level' => $level );
if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
	$heading_attrs['className'] = $attrs['class'];
}
```

**Change to:**
```php
$heading_attrs = array( 'level' => $level );
if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
	$heading_attrs['className'] = $attrs['class'];
}

// Add etchData for EtchWP IDE editability
$heading_attrs['metadata'] = array(
	'name' => 'Heading',
	'etchData' => self::build_etch_data( $tag, $attrs ),
);
```

### Step 3: Add Semantic Block Handlers WITH etchData

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`

**Add handlers AFTER headings handler (after line 539) and BEFORE inline elements check (before line 541):**

#### 3.1: Paragraph Handler

```php
// Handle paragraphs - convert to paragraph blocks WITH etchData
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
			continue; // Already handled in className
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

#### 3.2: List Handler

```php
// Handle lists - convert to list blocks WITH etchData
if ( $tag === 'ul' || $tag === 'ol' ) {
	$inner = self::convert_dom_children( $el );
	
	$list_attrs = array();
	if ( $tag === 'ol' ) {
		$list_attrs['ordered'] = true;
		// Extract type attribute (1, a, A, i, I)
		if ( isset( $attrs['type'] ) && is_string( $attrs['type'] ) ) {
			$list_attrs['type'] = $attrs['type'];
		}
		// Extract start attribute
		if ( isset( $attrs['start'] ) && is_numeric( $attrs['start'] ) ) {
			$list_attrs['start'] = (int) $attrs['start'];
		}
	}
	if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
		$list_attrs['className'] = $attrs['class'];
	}
	
	// Add etchData for EtchWP IDE editability
	$list_attrs['metadata'] = array(
		'name' => 'List',
		'etchData' => self::build_etch_data( $tag, $attrs ),
	);
	
	// Build HTML attributes (id, data-*, etc.)
	$element_attrs = '';
	foreach ( $attrs as $key => $value ) {
		if ( in_array( $key, array( 'class', 'type', 'start' ), true ) ) {
			continue; // Already handled
		}
		if ( is_string( $value ) && $value !== '' ) {
			$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	
	return self::block_open( 'list', $list_attrs ) . "\n" .
		'<' . $tag . $element_attrs . '>' . $inner . '</' . $tag . '>' . "\n" .
		self::block_close( 'list' ) . "\n";
}
```

#### 3.3: Image Handler

```php
// Handle images - convert to image blocks WITH etchData
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
	
	// Build HTML attributes (id, data-*, etc.)
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

#### 3.4: Blockquote Handler

```php
// Handle blockquotes - convert to quote blocks WITH etchData
if ( $tag === 'blockquote' ) {
	$inner = self::convert_dom_children( $el );
	
	$quote_attrs = array();
	if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
		$quote_attrs['className'] = $attrs['class'];
	}
	
	// Check for citation (cite attribute or <cite> element)
	$dom = $el->ownerDocument;
	$citation = '';
	if ( $dom instanceof \DOMDocument ) {
		$xpath = new \DOMXPath( $dom );
		$cite_elements = $xpath->query( './/cite', $el );
		if ( $cite_elements && $cite_elements->length > 0 ) {
			$citation = trim( $cite_elements->item( 0 )->textContent ?? '' );
		}
		if ( $citation === '' && isset( $attrs['cite'] ) && is_string( $attrs['cite'] ) ) {
			$citation = $attrs['cite'];
		}
	}
	if ( $citation !== '' ) {
		$quote_attrs['citation'] = $citation;
	}
	
	// Add etchData for EtchWP IDE editability
	$quote_attrs['metadata'] = array(
		'name' => 'Quote',
		'etchData' => self::build_etch_data( 'blockquote', $attrs ),
	);
	
	// Build HTML attributes (id, data-*, etc.)
	$element_attrs = '';
	foreach ( $attrs as $key => $value ) {
		if ( in_array( $key, array( 'class', 'cite' ), true ) ) {
			continue; // Already handled
		}
		if ( is_string( $value ) && $value !== '' ) {
			$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	
	return self::block_open( 'quote', $quote_attrs ) . "\n" .
		'<blockquote' . $element_attrs . '>' . $inner . '</blockquote>' . "\n" .
		self::block_close( 'quote' ) . "\n";
}
```

#### 3.5: Preformatted Handler

```php
// Handle preformatted text - convert to preformatted blocks WITH etchData
if ( $tag === 'pre' ) {
	$dom = $el->ownerDocument;
	if ( $dom instanceof \DOMDocument ) {
		$inner_html = self::inner_html( $dom, $el );
		
		$pre_attrs = array();
		if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
			$pre_attrs['className'] = $attrs['class'];
		}
		
		// Add etchData for EtchWP IDE editability
		$pre_attrs['metadata'] = array(
			'name' => 'Preformatted',
			'etchData' => self::build_etch_data( 'pre', $attrs ),
		);
		
		// Build HTML attributes (id, data-*, etc.)
		$element_attrs = '';
		foreach ( $attrs as $key => $value ) {
			if ( $key === 'class' ) {
				continue; // Already handled
			}
			if ( is_string( $value ) && $value !== '' ) {
				$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}
		
		return self::block_open( 'preformatted', $pre_attrs ) . "\n" .
			'<pre' . $element_attrs . '>' . $inner_html . '</pre>' . "\n" .
			self::block_close( 'preformatted' ) . "\n";
	}
}
```

#### 3.6: Table Handler

```php
// Handle tables - convert to table blocks WITH etchData
if ( $tag === 'table' ) {
	$inner = self::convert_dom_children( $el );
	
	$table_attrs = array();
	if ( isset( $attrs['class'] ) && is_string( $attrs['class'] ) && $attrs['class'] !== '' ) {
		$table_attrs['className'] = $attrs['class'];
	}
	
	// Add etchData for EtchWP IDE editability
	$table_attrs['metadata'] = array(
		'name' => 'Table',
		'etchData' => self::build_etch_data( 'table', $attrs ),
	);
	
	// Build HTML attributes (id, data-*, etc.)
	$element_attrs = '';
	foreach ( $attrs as $key => $value ) {
		if ( $key === 'class' ) {
			continue; // Already handled
		}
		if ( is_string( $value ) && $value !== '' ) {
			$element_attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}
	
	return self::block_open( 'table', $table_attrs ) . "\n" .
		'<table' . $element_attrs . '>' . $inner . '</table>' . "\n" .
		self::block_close( 'table' ) . "\n";
}
```

### Step 4: Remove Semantic Elements from Block Elements Array

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 508)

**Current:**
```php
$block_elements = array( 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'form', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'figure', 'figcaption', 'blockquote', 'pre', 'address' );
```

**Change to:**
```php
// Define block-level elements that should be converted to wp:group blocks
// Note: Semantic content elements (p, ul, ol, blockquote, pre, table, img) are handled separately
// Keep only structural containers: div, section, article, main, header, footer, aside, nav, form, dl, dt, dd, figure, figcaption, address
$block_elements = array( 'div', 'section', 'article', 'main', 'header', 'footer', 'aside', 'nav', 'form', 'dl', 'dt', 'dd', 'figure', 'figcaption', 'address' );
```

### Step 5: Remove img from Void Elements Array

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 475)

**Current:**
```php
$void_elements = array( 'img', 'br', 'hr', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr' );
```

**Change to:**
```php
// Special handling for void elements (br, hr, input, etc.)
// Note: 'img' is handled separately as a semantic block (wp:image)
$void_elements = array( 'br', 'hr', 'input', 'meta', 'link', 'area', 'base', 'col', 'embed', 'source', 'track', 'wbr' );
```

### Step 6: Update Inline Elements Handler to Include etchData

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 542)

**Current:**
```php
// Wrap in wp:html block to preserve the element
return "<!-- wp:html -->\n" . $element_html . "\n<!-- /wp:html -->\n";
```

**Change to:**
```php
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
```

### Step 7: Update Default/Fallback Handler to Include etchData

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 632)

**Current:**
```php
// Wrap in wp:html block to preserve the element
return "<!-- wp:html -->\n" . $element_html . "\n<!-- /wp:html -->\n";
```

**Change to:**
```php
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
```

### Step 8: Update Preserved Elements Handler to Include etchData

**Location:** `VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php` (Line 503)

**Current:**
```php
// Wrap in wp:html block to preserve the element
return "<!-- wp:html -->\n" . $element_html . "\n<!-- /wp:html -->\n";
```

**Change to:**
```php
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
```

## Summary of Changes

1. **Add `build_etch_data()` helper method** - Centralized function to create etchData structure
2. **Update headings handler** - Add etchData to wp:heading blocks
3. **Add 6 semantic block handlers** - Convert p, ul/ol, img, blockquote, pre, table to proper blocks WITH etchData
4. **Update block_elements array** - Remove semantic elements (handled separately)
5. **Update void_elements array** - Remove img (handled separately)
6. **Update inline elements handler** - Add etchData to wp:html blocks
7. **Update default handler** - Add etchData to wp:html blocks
8. **Update preserved elements handler** - Add etchData to wp:html blocks

## Expected Results

### Before
- Headings: `wp:heading` without etchData → CORE/HTML (not editable)
- Paragraphs: `wp:group` with etchData → Editable but wrong type
- Images: `wp:html` without etchData → CORE/HTML (not editable)

### After
- Headings: `wp:heading` WITH etchData → Fully editable, correct type
- Paragraphs: `wp:paragraph` WITH etchData → Fully editable, correct type
- Lists: `wp:list` WITH etchData → Fully editable, correct type
- Images: `wp:image` WITH etchData → Fully editable, correct type
- All blocks: Proper Gutenberg blocks WITH etchData → Fully editable in EtchWP IDE

## Testing Checklist

- [ ] Verify all semantic blocks have etchData metadata
- [ ] Verify block types are correct (wp:paragraph, wp:list, wp:image, etc.)
- [ ] Verify blocks are editable in EtchWP IDE (not showing as CORE/HTML)
- [ ] Verify attributes are preserved in both block attrs and etchData
- [ ] Verify content can be edited directly in EtchWP IDE
- [ ] Verify blocks still work in standard Gutenberg editor
- [ ] Verify frontend rendering is correct
- [ ] Verify CSS classes are preserved and functional
- [ ] Run PHP syntax validation: `php -l includes/Importer.php`

## Files to Modify

1. **`VibeCodeCircle/plugins/vibecode-deploy/includes/Importer.php`**
   - Add `build_etch_data()` helper method
   - Update headings handler
   - Add 6 semantic block handlers (paragraph, list, image, blockquote, pre, table)
   - Update block_elements array
   - Update void_elements array
   - Update inline elements handler
   - Update default handler
   - Update preserved elements handler
