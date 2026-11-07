#!/usr/bin/env bash
# A failed final attempt must fail the job instead of falling through successfully.
set -euo pipefail

for attempt in 1 2 3; do
	echo "Starting wp-env (attempt $attempt)..."
	if ./node_modules/.bin/wp-env start; then
		wp_env_path="$(./node_modules/.bin/wp-env install-path)"
		project="$(basename "$wp_env_path")"
		container="$(docker ps --filter "label=com.docker.compose.project=$project" --filter 'label=com.docker.compose.service=tests-cli' --format '{{.ID}}')"
		if [ -z "$container" ] || [[ "$container" == *$'\n'* ]]; then
			echo "Expected exactly one tests-cli container for $project" >&2
			exit 1
		fi
		# The test helper uses this exact service/user, bypassing repeated Node/Compose setup.
		printf 'export TWBCE_WP_CLI_CONTAINER=%q\n' "$container" >> "$BASH_ENV"
		exit 0
	fi
	if [ "$attempt" -lt 3 ]; then
		sleep 5
	fi
done

echo 'wp-env failed to start after three attempts.' >&2
exit 1
