#!/usr/bin/env bash
#
# Build a WordPress-installable zip for a plugin version.
#
# Usage: tools/build-zip.sh <version>   (e.g. tools/build-zip.sh 0.1.1)
#
# The zip is built from committed HEAD only, so commit your changes first.
# Output: versions/<version>/sidrena-cijena.zip + SHA256SUMS.txt
#
# Version policy:
#   Every version, including patch releases, is committed and pushed to GitHub.
set -euo pipefail

VERSION="${1:-}"

if [ -z "$VERSION" ]; then
	echo "Usage: tools/build-zip.sh <version>" >&2
	exit 1
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "error: version must look like 0.1.1, got '$VERSION'" >&2
	exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"

DIRT="$(git -C "$ROOT" status --porcelain | grep -v "^?? versions/" || true)"

if [ -n "$DIRT" ]; then
	echo "error: working tree is not clean - commit first, then build:" >&2
	echo "$DIRT" >&2
	exit 1
fi

HEADER="$(grep -m1 "SIDRENA_CIJENA_VERSION" "$ROOT/sidrena-cijena.php" | grep -o "'[0-9][^']*'" | tr -d "'")"

if [ "$HEADER" != "$VERSION" ]; then
	echo "error: sidrena-cijena.php declares $HEADER but you asked for $VERSION." >&2
	echo "Bump the header, Stable tag, changelog and package.json first." >&2
	exit 1
fi

OUT="$ROOT/versions/$VERSION"
if [ -e "$OUT/sidrena-cijena.zip" ]; then
	echo "error: versions/$VERSION already has a package; bump the version instead of replacing it." >&2
	exit 1
fi
mkdir -p "$OUT"

git -C "$ROOT" archive --format=zip --prefix=sidrena-cijena/ HEAD \
	sidrena-cijena.php \
	uninstall.php \
	readme.txt \
	README.md \
	LICENSE \
	src \
	assets \
	templates \
	languages \
	-o "$OUT/sidrena-cijena.zip"

ENTRIES="$(unzip -l "$OUT/sidrena-cijena.zip" | awk 'NR>3' | head -n -2)"
LEAKS="$(printf '%s\n' "$ENTRIES" | grep -E "sidrena-cijena/(tests|specs|tools|AGENTARD|docs|\.opencode|\.specify|node_modules|vendor|versions|PROMPT\.md|completion\.md)" || true)"

if [ -n "$LEAKS" ]; then
	echo "error: dev-only files leaked into the zip:" >&2
	echo "$LEAKS" >&2
	exit 1
fi

( cd "$OUT" && sha256sum sidrena-cijena.zip > SHA256SUMS.txt )

echo "Built $OUT/sidrena-cijena.zip ($(du -h "$OUT/sidrena-cijena.zip" | cut -f1))"

echo "Release: commit versions/$VERSION/ and push."
