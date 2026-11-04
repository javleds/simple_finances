#!/usr/bin/env bash
set -Eeuo pipefail

VITE_BUILD_OUT_DIR="$PWD/storage/app/deploy-assets/build"
export VITE_BUILD_OUT_DIR
npm ci
npm run build
test -s "$VITE_BUILD_OUT_DIR/manifest.json"
