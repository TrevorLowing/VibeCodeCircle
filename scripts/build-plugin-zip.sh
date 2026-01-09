#!/usr/bin/env bash
set -euo pipefail

# Build plugin zip file for WordPress installation
# Output: dist/vibecode-deploy.zip and dist/vibecode-deploy-{version}.zip

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${REPO_ROOT}/plugins/vibecode-deploy"
PLUGIN_FILE="${PLUGIN_DIR}/vibecode-deploy.php"
DIST_DIR="${REPO_ROOT}/dist"
ZIP_PATH="${DIST_DIR}/vibecode-deploy.zip"

if [ ! -d "${PLUGIN_DIR}" ]; then
  echo "Error: Plugin directory not found at ${PLUGIN_DIR}" >&2
  exit 1
fi

if [ ! -f "${PLUGIN_FILE}" ]; then
  echo "Error: Plugin file not found at ${PLUGIN_FILE}" >&2
  exit 1
fi

mkdir -p "${DIST_DIR}"

# Extract version from plugin file
VERSION=$(grep "Version:" "${PLUGIN_FILE}" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)

if [ -z "${VERSION}" ]; then
  echo "Error: Could not extract version from ${PLUGIN_FILE}" >&2
  exit 1
fi

VERSIONED_ZIP_PATH="${DIST_DIR}/vibecode-deploy-${VERSION}.zip"

# Remove old zips if they exist
rm -f "${ZIP_PATH}"
rm -f "${VERSIONED_ZIP_PATH}"

echo "Building plugin zip from ${PLUGIN_DIR}..."
echo "Version: ${VERSION}"

# Create zip from plugin directory (WordPress expects plugin folder at zip root)
(
  cd "${PLUGIN_DIR}/.."
  
  # Create zip excluding test files, git files, and other non-essential files
  # WordPress requires the plugin folder to be at the zip root (not in plugins/ subdirectory)
  zip -r "${ZIP_PATH}" "vibecode-deploy" \
    -x "*.DS_Store" \
    -x "__MACOSX/*" \
    -x "*/__MACOSX/*" \
    -x "._*" \
    -x "*.git*" \
    -x "*/.git/*" \
    -x "*/tests/*" \
    -x "*/test-*.php" \
    -x "*/phpunit.xml.dist" \
    -x "*/README-TESTING.md" \
    -x "*/bin/install-wp-tests.sh" \
    -x "*.log" \
    >/dev/null
)

# Copy to versioned filename
cp "${ZIP_PATH}" "${VERSIONED_ZIP_PATH}"

if [ -f "${ZIP_PATH}" ] && [ -f "${VERSIONED_ZIP_PATH}" ]; then
  SIZE=$(du -h "${ZIP_PATH}" | cut -f1)
  echo "✓ Plugin zip built successfully:"
  echo "  - ${ZIP_PATH} (${SIZE})"
  echo "  - ${VERSIONED_ZIP_PATH} (${SIZE})"
else
  echo "Error: Failed to create plugin zip" >&2
  exit 1
fi
