#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../../../" && pwd)"
SRC_CFA="${REPO_ROOT}/../CFA"
DEST="${REPO_ROOT}/testing/plugins/vibecode-deploy/data/fixtures/cfa-root"

if [ ! -d "${SRC_CFA}" ]; then
  echo "CFA repo not found at: ${SRC_CFA}" 1>&2
  echo "Expected CFA to be a sibling of VibeCodeCircle." 1>&2
  exit 1
fi

mkdir -p "${DEST}"

rsync -a --delete \
  --include='/*.html' \
  --include='/css/***' \
  --include='/js/***' \
  --include='/resources/***' \
  --exclude='/*' \
  "${SRC_CFA}/" \
  "${DEST}/"

echo "Synced fixtures to: ${DEST}"
