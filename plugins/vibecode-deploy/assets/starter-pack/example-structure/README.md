# Example Project Structure

This directory shows an example project structure for reference.

## Structure

```
example-project/
├── home.html
├── about.html
├── contact.html
├── css/
│   └── styles.css
├── js/
│   ├── main.js
│   └── icons.js
├── resources/
│   └── logo.svg
├── scripts/
│   ├── build-deployment-package.sh
│   ├── generate-manifest.php
│   └── generate-functions-php.php
└── wp-content/
    └── themes/
        └── example-theme/
            ├── functions.php
            └── acf-json/
                └── group_example.json
```

## Notes

- HTML pages go in the root directory
- CSS files go in `css/`
- JavaScript files go in `js/`
- Images and assets go in `resources/`
- Build scripts go in `scripts/`
- Theme files (if applicable) go in `wp-content/themes/{theme-name}/`
