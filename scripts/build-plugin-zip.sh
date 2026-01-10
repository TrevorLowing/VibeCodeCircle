#!/usr/bin/env bash
set -euo pipefail

# Build plugin zip file for WordPress installation
# Output: dist/vibecode-deploy-{version}.zip (versioned only, auto-incremented)

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="${REPO_ROOT}/plugins/vibecode-deploy"
PLUGIN_FILE="${PLUGIN_DIR}/vibecode-deploy.php"
DIST_DIR="${REPO_ROOT}/dist"

if [ ! -d "${PLUGIN_DIR}" ]; then
  echo "Error: Plugin directory not found at ${PLUGIN_DIR}" >&2
  exit 1
fi

if [ ! -f "${PLUGIN_FILE}" ]; then
  echo "Error: Plugin file not found at ${PLUGIN_FILE}" >&2
  exit 1
fi

mkdir -p "${DIST_DIR}"

# Extract current version from plugin file
CURRENT_VERSION=$(grep "Version:" "${PLUGIN_FILE}" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)

if [ -z "${CURRENT_VERSION}" ]; then
  echo "Error: Could not extract version from ${PLUGIN_FILE}" >&2
  exit 1
fi

# Auto-increment patch version (e.g., 0.1.1 -> 0.1.2)
IFS='.' read -r -a VERSION_PARTS <<< "${CURRENT_VERSION}"
MAJOR="${VERSION_PARTS[0]}"
MINOR="${VERSION_PARTS[1]}"
PATCH="${VERSION_PARTS[2]}"
NEW_PATCH=$((PATCH + 1))
NEW_VERSION="${MAJOR}.${MINOR}.${NEW_PATCH}"

echo "Building plugin zip from ${PLUGIN_DIR}..."
echo "Current version: ${CURRENT_VERSION}"
echo "New version: ${NEW_VERSION}"

# Update version in plugin file (both Version: header and VIBECODE_DEPLOY_PLUGIN_VERSION constant)
if [[ "$OSTYPE" == "darwin"* ]]; then
  # macOS sed requires -i '' for in-place editing
  sed -i '' "s/Version: ${CURRENT_VERSION}/Version: ${NEW_VERSION}/" "${PLUGIN_FILE}"
  sed -i '' "s/define( 'VIBECODE_DEPLOY_PLUGIN_VERSION', '${CURRENT_VERSION}' );/define( 'VIBECODE_DEPLOY_PLUGIN_VERSION', '${NEW_VERSION}' );/" "${PLUGIN_FILE}"
else
  # Linux sed
  sed -i "s/Version: ${CURRENT_VERSION}/Version: ${NEW_VERSION}/" "${PLUGIN_FILE}"
  sed -i "s/define( 'VIBECODE_DEPLOY_PLUGIN_VERSION', '${CURRENT_VERSION}' );/define( 'VIBECODE_DEPLOY_PLUGIN_VERSION', '${NEW_VERSION}' );/" "${PLUGIN_FILE}"
fi

VERSIONED_ZIP_PATH="${DIST_DIR}/vibecode-deploy-${NEW_VERSION}.zip"

# Remove old versioned zip if it exists (shouldn't, but just in case)
rm -f "${VERSIONED_ZIP_PATH}"

# Create zip from plugin directory (WordPress expects plugin folder at zip root)
(
  cd "${PLUGIN_DIR}/.."
  
  # Create zip excluding test files, git files, and other non-essential files
  # WordPress requires the plugin folder to be at the zip root (not in plugins/ subdirectory)
  zip -r "${VERSIONED_ZIP_PATH}" "vibecode-deploy" \
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

if [ -f "${VERSIONED_ZIP_PATH}" ]; then
  SIZE=$(du -h "${VERSIONED_ZIP_PATH}" | cut -f1)
  echo "✓ Plugin zip built successfully:"
  echo "  - ${VERSIONED_ZIP_PATH} (${SIZE})"
  echo "✓ Version updated in ${PLUGIN_FILE} to ${NEW_VERSION}"
else
  echo "Error: Failed to create plugin zip" >&2
  exit 1
fi
