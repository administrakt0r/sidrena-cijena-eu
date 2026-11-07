#!/usr/bin/env bash
# Documentation-only branches do not need a WordPress VM. Unknown diffs run everything.
set -euo pipefail

base_ref="${1:-origin/main}"
if ! base_commit="$(git merge-base HEAD "$base_ref" 2>/dev/null)"; then
	echo "Cannot determine base; keeping full integration coverage."
	exit 0
fi

changed=false
while IFS= read -r -d '' file; do
	changed=true
	case "$file" in
		docs/*|README.md|AGENTS.md|LICENSE|.github/ISSUE_TEMPLATE/*|.github/PULL_REQUEST_TEMPLATE*) ;;
		*) echo "Integration required: $file"; exit 0 ;;
	esac
done < <(git diff --name-only -z "$base_commit" HEAD)

if [ "$changed" = false ]; then
	echo "No changes from base; keeping full integration coverage."
	exit 0
fi

echo "Only documentation changed; skipping the WordPress VM job."
exit 10
