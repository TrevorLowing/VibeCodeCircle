#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../" && pwd)"
FIXTURES="${REPO_ROOT}/testing/plugins/vibecode-deploy/data/fixtures/cfa-root"
OUT_BASE="${REPO_ROOT}/testing/plugins/vibecode-deploy/data/build"
STAGING_DIR="${OUT_BASE}/vibecode-deploy-staging"
ZIP_PATH="${OUT_BASE}/vibecode-deploy-staging.zip"

if [ ! -d "${FIXTURES}" ]; then
  echo "Fixtures not found at: ${FIXTURES}" 1>&2
  echo "Run: testing/plugins/vibecode-deploy/scripts/sync-from-cfa.sh" 1>&2
  exit 1
fi

mkdir -p "${STAGING_DIR}/pages"

rsync -a --delete \
  --include='/*.html' \
  --exclude='/*' \
  "${FIXTURES}/" \
  "${STAGING_DIR}/pages/"

for d in css js resources; do
  if [ -d "${FIXTURES}/${d}" ]; then
    mkdir -p "${STAGING_DIR}/${d}"
    rsync -a --delete "${FIXTURES}/${d}/" "${STAGING_DIR}/${d}/"
  fi
done

rm -f "${ZIP_PATH}"

(
  cd "${OUT_BASE}"
  zip -r "$(basename "${ZIP_PATH}")" "vibecode-deploy-staging" \
    -x "*.DS_Store" "__MACOSX/*" "*/__MACOSX/*" "._*" >/dev/null
)

echo "Built staging zip: ${ZIP_PATH}"
