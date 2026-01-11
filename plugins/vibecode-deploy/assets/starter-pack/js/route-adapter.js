/**
 * Standard Route Adapter for Vibe Code Deploy Projects
 * 
 * This is the canonical route adapter used across all Vibe Code Deploy projects.
 * It handles URL conversion between local development (.html files) and WordPress production (extensionless URLs).
 * 
 * Usage:
 * 1. Copy this file to your project's js/ directory
 * 2. Include in all HTML pages: <script src="js/route-adapter.js" defer></script>
 * 3. Update productionHosts array with your production domain(s)
 * 
 * Behavior:
 * - Local development: Converts extensionless links (e.g., "home") to .html (e.g., "home.html")
 * - WordPress production: Skips URLs ending with / (WordPress permalink format like "/home/")
 * - Production hosts: Bypasses conversion entirely (uses extensionless URLs)
 */

(function() {
    'use strict';

    // Route adapter for local development
    // Converts extensionless URLs to .html for local file serving
    // Production hosts bypass this conversion (use extensionless URLs in production)

    // TODO: Update this array with your production domain(s)
    const productionHosts = [
        'yourdomain.com',
        'www.yourdomain.com'
    ];

    function isProduction() {
        return productionHosts.includes(window.location.hostname);
    }

    function adaptLink(link) {
        if (!link) return;
        const href = link.getAttribute('href');

        if (!href) return;

        // Skip external links, anchors, and already-html links
        if (href.startsWith('http') || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.includes('.html')) {
            return;
        }

        // Convert root to home.html
        if (href === '/' || href === '') {
            link.setAttribute('href', 'home.html');
            return;
        }

        // Add .html extension if missing
        // CRITICAL: Skip URLs ending with / (WordPress-style URLs like /home/, /products/)
        // These are already correct for WordPress and should not be converted
        if (!href.includes('.') && !href.endsWith('/')) {
            link.setAttribute('href', href + '.html');
        }
    }

    function adaptLinks() {
        if (isProduction()) return;

        const links = document.querySelectorAll('a[href]');
        links.forEach(function(link) {
            adaptLink(link);
        });
    }

    function observeAndAdaptNewLinks() {
        if (isProduction()) return;
        if (!('MutationObserver' in window)) return;

        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'href') {
                    adaptLink(mutation.target);
                    return;
                }

                mutation.addedNodes.forEach(function(node) {
                    if (!node || node.nodeType !== 1) return;

                    if (node.matches && node.matches('a[href]')) {
                        adaptLink(node);
                    }

                    if (node.querySelectorAll) {
                        const anchors = node.querySelectorAll('a[href]');
                        anchors.forEach(function(anchor) {
                            adaptLink(anchor);
                        });
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['href'] });
    }

    // Handle browser navigation
    window.addEventListener('popstate', function() {
        adaptLinks();
    });

    // Initialize on load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', adaptLinks);
        document.addEventListener('DOMContentLoaded', observeAndAdaptNewLinks);
    } else {
        adaptLinks();
        observeAndAdaptNewLinks();
    }
})();
