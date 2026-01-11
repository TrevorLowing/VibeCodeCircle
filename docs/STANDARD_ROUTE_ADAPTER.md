# Standard Route Adapter

**Location:** `plugins/vibecode-deploy/assets/starter-pack/js/route-adapter.js`

This is the canonical route adapter used across all Vibe Code Deploy projects. It handles URL conversion between local development (`.html` files) and WordPress production (extensionless URLs).

## Purpose

The route adapter ensures that:
- **Local development** works with `.html` file extensions
- **WordPress production** uses proper permalink format (`/page-slug/`)
- **Production hosts** bypass conversion entirely

## Usage

### 1. Copy to Your Project

Copy the standard route adapter to your project:

```bash
cp plugins/vibecode-deploy/assets/starter-pack/js/route-adapter.js your-project/js/route-adapter.js
```

### 2. Update Production Hosts

Edit `js/route-adapter.js` and update the `productionHosts` array:

```javascript
// TODO: Update this array with your production domain(s)
const productionHosts = [
    'yourdomain.com',
    'www.yourdomain.com'
];
```

### 3. Include in HTML Pages

Add to all HTML pages before the closing `</body>` tag:

```html
<script src="js/route-adapter.js" defer></script>
```

## How It Works

### Local Development
- Converts extensionless links: `home` → `home.html`
- Converts root: `/` → `home.html`
- **Skips WordPress URLs:** `/home/` remains unchanged (already correct)

### WordPress Production
- WordPress generates permalink format: `/home/`, `/products/`
- Route adapter **skips** URLs ending with `/` (WordPress format)
- No conversion needed - links work as-is

### Production Hosts
- If `location.hostname` matches `productionHosts`, adapter exits early
- No conversion performed - uses extensionless URLs directly

## Key Features

### 1. WordPress Permalink Support
```javascript
// CRITICAL: Skip URLs ending with / (WordPress-style URLs like /home/, /products/)
// These are already correct for WordPress and should not be converted
if (!href.includes('.') && !href.endsWith('/')) {
    link.setAttribute('href', href + '.html');
}
```

### 2. MutationObserver for Dynamic Content
Watches for dynamically added links (important for Gutenberg blocks):

```javascript
const observer = new MutationObserver(function(mutations) {
    // Adapts links added after page load
});
```

### 3. Popstate Handler
Handles browser back/forward navigation:

```javascript
window.addEventListener('popstate', function() {
    adaptLinks();
});
```

## Examples

### Source HTML (Extensionless)
```html
<a href="home">Home</a>
<a href="products">Products</a>
<a href="contact">Contact</a>
```

### Local Development (After Route Adapter)
```html
<a href="home.html">Home</a>
<a href="products.html">Products</a>
<a href="contact.html">Contact</a>
```

### WordPress Production (No Conversion)
```html
<a href="/home/">Home</a>
<a href="/products/">Products</a>
<a href="/contact/">Contact</a>
```

## Troubleshooting

### Links Not Working in WordPress
- **Check:** Are links ending with `/`? (WordPress permalink format)
- **Solution:** Route adapter correctly skips these - no action needed

### Links Not Converting Locally
- **Check:** Is `location.hostname` in `productionHosts` array?
- **Solution:** Remove your local domain from `productionHosts` or use `localhost`

### Dynamic Links Not Working
- **Check:** Is `MutationObserver` supported? (Modern browsers only)
- **Solution:** Route adapter falls back gracefully - static links still work

## Version History

- **v1.0** (2026-01-10): Initial standard version
  - WordPress permalink support (`/page-slug/`)
  - MutationObserver for dynamic content
  - Popstate handler for navigation

## Maintenance

**When to Update:**
- WordPress permalink format changes
- New browser APIs for link handling
- Bug fixes or improvements

**Update Process:**
1. Update `plugins/vibecode-deploy/assets/starter-pack/js/route-adapter.js`
2. Projects copy updated version to their `js/` directory
3. Test in both local and WordPress environments

## Related Files

- **Plugin Location:** `plugins/vibecode-deploy/assets/starter-pack/js/route-adapter.js`
- **Documentation:** `docs/STANDARD_ROUTE_ADAPTER.md` (this file)
- **Deployment Guide:** `docs/DEPLOYMENT-GUIDE.md` (references this file)
