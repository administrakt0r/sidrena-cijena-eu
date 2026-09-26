#!/usr/bin/env bash
#
# One-command verification for AI coding agents and humans.
#
# Runs every host gate and reports PASS/FAIL per gate with the test counts, so
# any agent can prove a change without rediscovering the command list from
# docs. Usage:
#
#   tools/verify.sh                     # unit + PHPCS + PHPStan + ESLint + i18n
#   tools/verify.sh --with-integration  # + PHPUnit integration suite (wp-env)
#   tools/verify.sh --with-e2e          # + Playwright E2E (test env, port 8889)
#
# Exit code 0 means every requested gate is green.
#
# Baselines (v1.0.13) — treat counts below these as a regression:
#   unit 301 tests / 1078 assertions, integration 76+ tests, E2E 28 tests,
#   632 catalog strings with 0 untranslated.
#
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
RUN_INTEGRATION=0
RUN_E2E=0
FAILURES=0
RESULTS=()

MIN_UNIT_TESTS=301
MIN_E2E_TESTS=28

for arg in "$@"; do
	case "$arg" in
		--with-integration) RUN_INTEGRATION=1 ;;
		--with-e2e) RUN_E2E=1 ;;
		*)
			echo "usage: tools/verify.sh [--with-integration] [--with-e2e]" >&2
			exit 2
			;;
	esac
done

if [ -n "${WP_BASE_URL:-}" ] && [[ "$WP_BASE_URL" == *":8888"* ]]; then
	echo "error: WP_BASE_URL points at port 8888 (the demo shop). E2E must run on 8889." >&2
	exit 2
fi

record() {
	local name="$1" status="$2" detail="${3:-}"
	RESULTS+=("$status|$name|$detail")
	if [ "$status" != "PASS" ]; then
		FAILURES=$(( FAILURES + 1 ))
	fi
}

run_gate() {
	local name="$1"
	shift
	local output
	echo "==> $name"
	if output="$("$@" 2>&1)"; then
		record "$name" "PASS"
		return 0
	fi
	echo "$output" | tail -20
	record "$name" "FAIL"
	return 1
}

cd "$ROOT"

# 1. Unit suite (host, no WordPress needed).
UNIT_OUT="$(composer test:unit 2>&1)"
if echo "$UNIT_OUT" | grep -q "^OK ("; then
	UNIT_LINE="$(echo "$UNIT_OUT" | grep -o "OK ([0-9]* tests*, [0-9]* assertions*)" | head -1)"
	UNIT_TESTS="$(echo "$UNIT_LINE" | grep -o "[0-9]*" | head -1)"
	record "unit tests" "PASS" "$UNIT_LINE"
	if [ "${UNIT_TESTS:-0}" -lt "$MIN_UNIT_TESTS" ]; then
		record "unit test floor" "WARN" "expected at least $MIN_UNIT_TESTS tests, got ${UNIT_TESTS:-0}"
	fi
else
	echo "$UNIT_OUT" | tail -20
	record "unit tests" "FAIL"
fi

# 2. Code style.
run_gate "PHPCS" composer lint >/dev/null || true

# 3. Static analysis.
run_gate "PHPStan" composer analyse >/dev/null || true

# 4. JavaScript lint.
run_gate "ESLint" npm run lint:js >/dev/null || true

# 5. Croatian catalogue completeness. This regenerates languages/; timestamp-only
#    churn is restored so the tree stays release-clean, real changes are kept.
I18N_OUT="$(php tools/i18n/make-pot.php --locale=hr_HR 2>&1)"
echo "$I18N_OUT" | grep "po (" || true
if echo "$I18N_OUT" | grep -q "0 untranslated"; then
	record "i18n (0 untranslated)" "PASS"
else
	echo "$I18N_OUT"
	record "i18n (0 untranslated)" "FAIL" "add the missing translations to tools/i18n/hr_HR.php"
fi

if ! git -C "$ROOT" diff --quiet -- languages 2>/dev/null; then
	if git -C "$ROOT" diff -- languages | grep -E '^[+-][^+-]' | grep -vE 'POT-Creation-Date|PO-Revision-Date' >/dev/null 2>&1; then
		record "i18n catalogs" "WARN" "languages/ has real changes - review and commit them"
	else
		git -C "$ROOT" checkout -- languages
	fi
fi

# 6. Integration suite (inside wp-env; WP_TESTS_DIR lives in the container).
#    Note: plain `npx wp-env` only — wp-env 10.39 silently no-ops `--config`.
if [ "$RUN_INTEGRATION" -eq 1 ]; then
	echo "==> integration tests (wp-env)"
	INT_OUT="$(npx wp-env run tests-cli wp-content/plugins/sidrena-cijena/vendor/bin/phpunit -c wp-content/plugins/sidrena-cijena/phpunit.xml.dist --testsuite=integration 2>&1)"
	if echo "$INT_OUT" | grep -q "^OK ("; then
		record "integration tests" "PASS" "$(echo "$INT_OUT" | grep -o "OK ([0-9]* tests*, [0-9]* assertions*)" | head -1)"
	else
		echo "$INT_OUT" | tail -20
		record "integration tests" "FAIL"
	fi
fi

# 7. E2E suite (Playwright against the test environment, port 8889).
if [ "$RUN_E2E" -eq 1 ]; then
	echo "==> E2E tests (Playwright, port 8889)"
	E2E_OUT="$(npm run test:e2e 2>&1)"
	E2E_PASSED="$(echo "$E2E_OUT" | grep -oE '[0-9]+ passed' | grep -oE '[0-9]+' | tail -1)"
	if echo "$E2E_OUT" | grep -qE '[0-9]+ passed' && ! echo "$E2E_OUT" | grep -qE '[0-9]+ failed'; then
		record "E2E tests" "PASS" "${E2E_PASSED:-?} passed"
		if [ "${E2E_PASSED:-0}" -lt "$MIN_E2E_TESTS" ]; then
			record "E2E test floor" "WARN" "expected at least $MIN_E2E_TESTS tests, got ${E2E_PASSED:-0}"
		fi
	else
		echo "$E2E_OUT" | tail -25
		record "E2E tests" "FAIL"
	fi
fi

echo
echo "================ verification summary ================"
for line in "${RESULTS[@]}"; do
	status="${line%%|*}"
	rest="${line#*|}"
	name="${rest%%|*}"
	detail="${rest#*|}"
	printf '%-4s %-24s %s\n' "$status" "$name" "$detail"
done
echo "======================================================"

if [ "$FAILURES" -gt 0 ]; then
	echo "$FAILURES gate(s) failed." >&2
	exit 1
fi

echo "All gates green."
