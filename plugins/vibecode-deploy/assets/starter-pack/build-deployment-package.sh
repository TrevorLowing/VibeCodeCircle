#!/usr/bin/env bash
set -euo pipefail

# Build deployment package script for Vibe Code Deploy
# Creates a simplified deployment package from your static HTML project

# Get project root directory (parent of scripts directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PROJECT_NAME="$(basename "${PROJECT_ROOT}")"

# Output directory and zip file
DEPLOYMENT_DIR="${PROJECT_ROOT}/${PROJECT_NAME}-deployment"
ZIP_FILE="${PROJECT_ROOT}/${PROJECT_NAME}-deployment.zip"

# Clean up old deployment directory and zip
rm -rf "${DEPLOYMENT_DIR}"
rm -f "${ZIP_FILE}"

echo "Building deployment package for ${PROJECT_NAME}..."

# Create deployment directory structure
mkdir -p "${DEPLOYMENT_DIR}/pages"
mkdir -p "${DEPLOYMENT_DIR}/assets/css"
mkdir -p "${DEPLOYMENT_DIR}/assets/js"
mkdir -p "${DEPLOYMENT_DIR}/assets/images"
mkdir -p "${DEPLOYMENT_DIR}/theme/acf-json"

# Copy HTML pages
if [ -d "${PROJECT_ROOT}" ]; then
    find "${PROJECT_ROOT}" -maxdepth 1 -name "*.html" -type f | while read -r html_file; do
        cp "${html_file}" "${DEPLOYMENT_DIR}/pages/"
        echo "  Copied: $(basename "${html_file}")"
    done
fi

# Copy CSS files
if [ -d "${PROJECT_ROOT}/css" ]; then
    cp -r "${PROJECT_ROOT}/css"/* "${DEPLOYMENT_DIR}/assets/css/" 2>/dev/null || true
    echo "  Copied CSS files"
fi

# Copy JS files
if [ -d "${PROJECT_ROOT}/js" ]; then
    cp -r "${PROJECT_ROOT}/js"/* "${DEPLOYMENT_DIR}/assets/js/" 2>/dev/null || true
    echo "  Copied JS files"
fi

# Copy resources/images
if [ -d "${PROJECT_ROOT}/resources" ]; then
    cp -r "${PROJECT_ROOT}/resources"/* "${DEPLOYMENT_DIR}/assets/images/" 2>/dev/null || true
    echo "  Copied resources"
fi

# Generate manifest.json
if [ -f "${SCRIPT_DIR}/generate-manifest.php" ]; then
    php "${SCRIPT_DIR}/generate-manifest.php" "${DEPLOYMENT_DIR}" > "${DEPLOYMENT_DIR}/manifest.json"
    echo "  Generated manifest.json"
else
    echo "  Warning: generate-manifest.php not found, creating basic manifest"
    cat > "${DEPLOYMENT_DIR}/manifest.json" <<EOF
{
    "version": "1.0.0",
    "build_date": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
    "project_name": "${PROJECT_NAME}",
    "package_type": "deployment"
}
EOF
fi

# Generate config.json
PROJECT_SLUG=$(echo "${PROJECT_NAME}" | tr '[:upper:]' '[:lower:]')
cat > "${DEPLOYMENT_DIR}/config.json" <<EOF
{
    "project_slug": "${PROJECT_SLUG}",
    "extract_header_footer": true,
    "validate_shortcodes": false,
    "force_claim_pages": false,
    "force_claim_templates": false
}
EOF
echo "  Generated config.json"

# Copy theme files if they exist
THEME_FUNCTIONS=$(find "${PROJECT_ROOT}/wp-content/themes" -name "functions.php" -type f 2>/dev/null | head -1)
if [ -n "${THEME_FUNCTIONS}" ] && [ -f "${THEME_FUNCTIONS}" ]; then
    if [ -n "${THEME_FUNCTIONS}" ]; then
        # Generate functions.php using generator if available
        if [ -f "${SCRIPT_DIR}/generate-functions-php.php" ]; then
            php "${SCRIPT_DIR}/generate-functions-php.php" "${THEME_FUNCTIONS}" > "${DEPLOYMENT_DIR}/theme/functions.php"
            echo "  Generated theme/functions.php"
        else
            cp "${THEME_FUNCTIONS}" "${DEPLOYMENT_DIR}/theme/functions.php"
            echo "  Copied theme/functions.php"
        fi
    fi
fi

# Copy ACF JSON files if they exist
ACF_JSON_DIR=$(find "${PROJECT_ROOT}/wp-content/themes" -type d -name "acf-json" 2>/dev/null | head -1)
if [ -n "${ACF_JSON_DIR}" ] && [ -d "${ACF_JSON_DIR}" ]; then
    if [ -n "${ACF_JSON_DIR}" ]; then
        cp -r "${ACF_JSON_DIR}"/* "${DEPLOYMENT_DIR}/theme/acf-json/" 2>/dev/null || true
        echo "  Copied ACF JSON files"
    fi
fi

# Create ZIP file
echo "Creating ZIP file..."
cd "${PROJECT_ROOT}"
zip -r "${ZIP_FILE}" "$(basename "${DEPLOYMENT_DIR}")" \
    -x "*.DS_Store" \
    -x "__MACOSX/*" \
    -x "*/__MACOSX/*" \
    -x "._*" \
    >/dev/null 2>&1

if [ -f "${ZIP_FILE}" ]; then
    SIZE=$(du -h "${ZIP_FILE}" | cut -f1)
    echo "✓ Deployment package created successfully:"
    echo "  - ${ZIP_FILE} (${SIZE})"
    echo ""
    echo "Next steps:"
    echo "  1. Upload ${ZIP_FILE} via Vibe Code Deploy → Import Build"
    echo "  2. Run preflight to validate the package"
    echo "  3. Deploy to your WordPress site"
else
    echo "Error: Failed to create deployment package ZIP" >&2
    exit 1
fi
