#!/usr/bin/env bash
#
# stoplocal.sh - stop the local demo webshop started by ./local.sh.
#
# Containers are stopped but data is kept, so ./local.sh resumes where
# you left off. For a full reset (delete everything and start clean):
#   npx wp-env destroy
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

npx wp-env stop

echo "Stopped. Data is kept; run ./local.sh to resume."
echo "Full reset: npx wp-env destroy"
