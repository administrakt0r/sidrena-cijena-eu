#!/usr/bin/env bash
#
# Install repository git hooks into .git/hooks/
#
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$DIR/.." && pwd)"
HOOKS_DIR="$ROOT/.git/hooks"

if [ ! -d "$HOOKS_DIR" ]; then
	echo "error: .git/hooks directory not found at $HOOKS_DIR. Is this a git repository?" >&2
	exit 1
fi

chmod +x "$ROOT/tools/git-hooks/post-merge" "$ROOT/tools/git-hooks/post-commit" 2>/dev/null || true

cp "$ROOT/tools/git-hooks/post-merge" "$HOOKS_DIR/post-merge"
cp "$ROOT/tools/git-hooks/post-commit" "$HOOKS_DIR/post-commit"

chmod +x "$HOOKS_DIR/post-merge" "$HOOKS_DIR/post-commit"

echo "Git hooks installed successfully into .git/hooks/:"
echo " - post-merge  (auto-builds release zip when master branch merges/pulls)"
echo " - post-commit (auto-builds release zip on version bump commits on master)"
