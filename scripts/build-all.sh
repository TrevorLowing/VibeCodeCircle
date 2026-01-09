#!/usr/bin/env bash
set -euo pipefail

# Build both plugin and staging zip files
# Output: dist/vibecode-deploy.zip and dist/vibecode-deploy-staging.zip

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT_DIR="${REPO_ROOT}/scripts"
TESTING_SCRIPT="${REPO_ROOT}/testing/plugins/vibecode-deploy/scripts/build-staging-zip.sh"

echo "Building all distribution files..."
echo ""

# Build plugin zip
echo "1. Building plugin zip..."
"${SCRIPT_DIR}/build-plugin-zip.sh"
echo ""

# Build staging zip (if testing fixtures exist)
if [ -f "${TESTING_SCRIPT}" ]; then
  echo "2. Building staging zip..."
  "${TESTING_SCRIPT}"
  echo ""
else
  echo "2. Skipping staging zip (testing script not found)"
  echo "   Note: Staging zips are typically built from the CFA project"
  echo ""
fi

echo "✓ All builds complete!"
echo ""
echo "Distribution files are in: ${REPO_ROOT}/dist/"
