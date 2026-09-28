#!/usr/bin/env bash
#
# Build a WordPress-installable zip for a plugin version and publish it.
#
# Usage: tools/build-zip.sh <version> [options]
#   Options:
#     --no-upload    Skip uploading to the public repository and GitHub releases.
#     --upload-only  Skip zip build; upload the existing package in versions/<version>/.
#     --force        Rebuild and overwrite versions/<version>/ package if it already exists.
#
# The zip is built from committed HEAD only, so commit your changes first.
# Output: versions/<version>/sidrena-cijena-eu.zip + SHA256SUMS.txt
# Public repo: https://github.com/administrakt0r/sidrena-cijena-eu
#
# Version policy:
#   Every version, including patch releases, is committed and pushed to GitHub.
set -euo pipefail

VERSION=""
DO_UPLOAD=true
UPLOAD_ONLY=false
FORCE=false

for arg in "$@"; do
	case "$arg" in
		--no-upload|--skip-upload)
			DO_UPLOAD=false
			;;
		--upload-only)
			UPLOAD_ONLY=true
			;;
		--force)
			FORCE=true
			;;
		-*)
			echo "Unknown option: $arg" >&2
			echo "Usage: tools/build-zip.sh <version> [--no-upload] [--upload-only] [--force]" >&2
			exit 1
			;;
		*)
			if [ -z "$VERSION" ]; then
				VERSION="$arg"
			else
				echo "Unexpected argument: $arg" >&2
				exit 1
			fi
			;;
	esac
done

if [ -z "$VERSION" ]; then
	echo "Usage: tools/build-zip.sh <version> [--no-upload] [--upload-only] [--force]" >&2
	exit 1
fi

if ! [[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "error: version must look like 0.1.1, got '$VERSION'" >&2
	exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PUBLIC_REPO="https://github.com/administrakt0r/sidrena-cijena-eu.git"
PUBLIC_REPO_NAME="administrakt0r/sidrena-cijena-eu"

HEADER="$(grep -m1 "SIDRENA_CIJENA_VERSION" "$ROOT/sidrena-cijena.php" | grep -o "'[0-9][^']*'" | tr -d "'")"

if [ "$HEADER" != "$VERSION" ]; then
	echo "error: sidrena-cijena.php declares $HEADER but you asked for $VERSION." >&2
	echo "Bump the header, Stable tag, changelog and package.json first." >&2
	exit 1
fi

OUT="$ROOT/versions/$VERSION"

if [ "$UPLOAD_ONLY" = true ]; then
	if [ ! -f "$OUT/sidrena-cijena-eu.zip" ]; then
		echo "error: $OUT/sidrena-cijena-eu.zip not found to upload." >&2
		exit 1
	fi
	echo "Using existing package at $OUT/sidrena-cijena-eu.zip"
else
	DIRT="$(git -C "$ROOT" status --porcelain | grep -v "^?? versions/" || true)"

	if [ -n "$DIRT" ]; then
		echo "error: working tree is not clean - commit first, then build:" >&2
		echo "$DIRT" >&2
		exit 1
	fi

	if [ -e "$OUT/sidrena-cijena-eu.zip" ] && [ "$FORCE" != true ]; then
		echo "error: versions/$VERSION already has a package; use --force to overwrite or --upload-only to upload." >&2
		exit 1
	fi

	mkdir -p "$OUT"

	git -C "$ROOT" archive --format=zip --prefix=sidrena-cijena-eu/ HEAD \
		sidrena-cijena.php \
		uninstall.php \
		readme.txt \
		LICENSE \
		src \
		assets \
		templates \
		languages \
		-o "$OUT/sidrena-cijena-eu.zip"

	ENTRIES="$(unzip -l "$OUT/sidrena-cijena-eu.zip" | awk 'NR>3' | head -n -2)"
	LEAKS="$(printf '%s\n' "$ENTRIES" | grep -E "sidrena-cijena-eu/(tests|specs|tools|AGENTARDS|docs|\.opencode|\.specify|node_modules|vendor|versions|PROMPT\.md|completion\.md)" || true)"

	if [ -n "$LEAKS" ]; then
		echo "error: disallowed or dev-only files leaked into the zip:" >&2
		echo "$LEAKS" >&2
		exit 1
	fi

	( cd "$OUT" && sha256sum sidrena-cijena-eu.zip > SHA256SUMS.txt )

	echo "Built $OUT/sidrena-cijena-eu.zip ($(du -h "$OUT/sidrena-cijena-eu.zip" | cut -f1))"
fi

if [ "$DO_UPLOAD" = true ]; then
	echo "Uploading release $VERSION to public repository ($PUBLIC_REPO)..."

	TMP_DIR="$(mktemp -d)"
	cleanup() {
		rm -rf "$TMP_DIR"
	}
	trap cleanup EXIT

	# 1. Clone public repo master branch
	git clone --depth 1 -b master "$PUBLIC_REPO" "$TMP_DIR"

	# 2. Copy release zip and checksum
	mkdir -p "$TMP_DIR/versions/$VERSION"
	cp "$OUT/sidrena-cijena-eu.zip" "$TMP_DIR/versions/$VERSION/"
	cp "$OUT/SHA256SUMS.txt" "$TMP_DIR/versions/$VERSION/"

	# 3. Update version strings and badges in public README.md
	if [ -f "$TMP_DIR/README.md" ]; then
		sed -i -E \
			-e "s/verzija-[0-9]+\.[0-9]+\.[0-9]+/verzija-$VERSION/g" \
			-e "s/Verzija [0-9]+\.[0-9]+\.[0-9]+/Verzija $VERSION/g" \
			-e "s/Preuzmi dodatak [0-9]+\.[0-9]+\.[0-9]+/Preuzmi dodatak $VERSION/g" \
			"$TMP_DIR/README.md"
	fi

	# 4. Commit and push changes to public repo master
	(
		cd "$TMP_DIR"
		GIT_USER_NAME="$(git -C "$ROOT" config user.name || echo "administrakt0r")"
		GIT_USER_EMAIL="$(git -C "$ROOT" config user.email || echo "markoes3@gmail.com")"
		git config user.name "$GIT_USER_NAME"
		git config user.email "$GIT_USER_EMAIL"

		git add "versions/$VERSION" README.md
		if ! git diff --cached --quiet; then
			git commit -m "Release Sidrena Cijena EU $VERSION"
			git push origin master
			echo "Pushed release package to $PUBLIC_REPO (master)"
		else
			echo "Public repo master is already up to date."
		fi
	)

	# 5. Create or update GitHub release via gh CLI
	if command -v gh >/dev/null 2>&1; then
		TAG="v$VERSION"
		TITLE="Sidrena Cijena EU $VERSION"
		SHA="$(awk '{print $1}' "$OUT/SHA256SUMS.txt")"

		CHANGELOG_NOTES="$(sed -n "/^= ${VERSION//./\\.} =/,/^= [0-9]/p" "$ROOT/readme.txt" | grep -v '^=' | sed '/^[[:space:]]*$/d' || true)"
		if [ -z "$CHANGELOG_NOTES" ]; then
			CHANGELOG_NOTES="* Release $VERSION"
		fi

		NOTES=$(cat <<EOF
## Sidrena Cijena EU $VERSION

Croatian WordPress and WooCommerce plugin for anchor price compliance. Track the lowest price from the previous 30 days, publish current price lists as CSV/XML, and show dated publications in a public HTML archive.

### Highlights

$CHANGELOG_NOTES

### WordPress upload package

Download \`sidrena-cijena-eu.zip\` below and upload it at the WordPress.org plugin submission page https://wordpress.org/plugins/developers/add/. The SHA-256 file is attached for verification.

* **SHA-256:** \`$SHA\`
* **Main site:** https://sidrenacijena.eu/
* **Demo shop:** https://wordpress.sidrenacijena.eu/
EOF
)

		if gh release view "$TAG" --repo "$PUBLIC_REPO_NAME" >/dev/null 2>&1; then
			echo "Updating GitHub release $TAG on $PUBLIC_REPO_NAME..."
			gh release upload "$TAG" "$OUT/sidrena-cijena-eu.zip" "$OUT/SHA256SUMS.txt" --repo "$PUBLIC_REPO_NAME" --clobber
			gh release edit "$TAG" --repo "$PUBLIC_REPO_NAME" --title "$TITLE" --notes "$NOTES"
		else
			echo "Creating GitHub release $TAG on $PUBLIC_REPO_NAME..."
			gh release create "$TAG" "$OUT/sidrena-cijena-eu.zip" "$OUT/SHA256SUMS.txt" \
				--repo "$PUBLIC_REPO_NAME" \
				--title "$TITLE" \
				--notes "$NOTES" \
				--target master
		fi
		echo "GitHub release published: https://github.com/$PUBLIC_REPO_NAME/releases/tag/$TAG"
	else
		echo "Notice: gh CLI not found or not in PATH; skipped GitHub release creation."
	fi

	cleanup
	trap - EXIT

	# 6. Fetch updated public remote refs locally if configured
	if git -C "$ROOT" remote get-url github-public >/dev/null 2>&1; then
		git -C "$ROOT" fetch github-public master --tags >/dev/null 2>&1 || true
	fi
fi

echo "Done. Package: $OUT/sidrena-cijena-eu.zip"
