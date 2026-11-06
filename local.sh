#!/usr/bin/env bash
#
# local.sh - one-command local demo webshop for the sidrena-cijena plugin.
#
# Starts wp-env (WordPress + WooCommerce), activates the plugin, sets
# Europe/Zagreb + EUR, creates demo products with anchors and a sale,
# seeds the 17 compliance scenarios, generates the price lists and prints
# everything you need to see the plugin in action.
#
# Usage: ./local.sh
# Stop:  ./stoplocal.sh   (full reset: npx wp-env destroy)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

SITE="http://localhost:8888"
WPENV="npx wp-env"

need() {
	command -v "$1" >/dev/null 2>&1 || { echo "error: $1 is required but not installed." >&2; exit 1; }
}

need docker
need node
need npx
need curl

if ! docker info >/dev/null 2>&1; then
	systemctl start docker.socket 2>/dev/null || true
fi

if ! docker info >/dev/null 2>&1; then
	echo "error: Docker daemon is not running - start Docker first (e.g. systemctl start docker.socket)." >&2
	exit 1
fi

[ -d node_modules ] || npm install
[ -d vendor ] || composer install

echo "==> Starting wp-env (first run downloads WordPress + WooCommerce, may take a while)..."
$WPENV start

echo "==> Waiting for WordPress to respond at $SITE ..."
for i in $(seq 1 60); do
	if curl -fs -o /dev/null "$SITE/wp-admin/install.php" 2>/dev/null || curl -fs -o /dev/null "$SITE/wp-login.php" 2>/dev/null; then
		break
	fi
	sleep 5
	if [ "$i" -eq 60 ]; then
		echo "error: WordPress did not come up in time." >&2
		exit 1
	fi
done

CLI="$WPENV run cli wp"

echo "==> Base configuration (permalinks, timezone, currency)..."
$CLI rewrite structure '/%postname%/' --hard >/dev/null
$CLI option update timezone_string 'Europe/Zagreb' >/dev/null
$CLI option update woocommerce_currency 'EUR' >/dev/null
$CLI option update woocommerce_price_num_decimals 2 >/dev/null
# WooCommerce ships in "coming soon" mode: logged-out visitors (and the
# storefront tests) would never reach the products.
$CLI option update woocommerce_coming_soon no >/dev/null
$CLI option update woocommerce_store_pages_only no >/dev/null
PLUGIN_NAME="$($CLI plugin list --field=name 2>/dev/null | grep -E "titanworks|sidrena" | head -1)"
$CLI plugin activate "${PLUGIN_NAME:-titanworks-sidrena-bazna-cijena}" woocommerce >/dev/null

# The test suites can leave the shop without an active theme (WordPress then
# renders a blank storefront), so make sure the demo always has one.
if ! $CLI theme is-active >/dev/null 2>&1; then
	$CLI theme activate twentytwentyfive >/dev/null 2>&1 || true
fi

echo "==> Seeding demo products, anchors and a sale..."
PLUGIN_DIR="$($CLI eval 'echo dirname( array_merge( glob( WP_PLUGIN_DIR . "/*/titanworks-sidrena-bazna-cijena.php" ) ?: array(), glob( WP_PLUGIN_DIR . "/*/sidrena-cijena.php" ) ?: array() )[0] ?? ( WP_PLUGIN_DIR . "/titanworks-sidrena-bazna-cijena" ) );')"
$CLI eval-file "$PLUGIN_DIR/tools/local-seed.php"

echo "==> Seeding the 17 compliance test scenarios..."
$CLI sidrena-cijena seed-test-data --yes >/dev/null

echo "==> Generating price lists..."
$CLI sidrena-cijena generate --force >/dev/null

echo "==> Compliance status:"
$CLI sidrena-cijena status || true

cat <<EOF

=============================================================
 Demo webshop is ready: $SITE
-------------------------------------------------------------
 Shop:        $SITE/shop/
 Admin:       $SITE/wp-admin/  (admin / password)
 REST prices: $SITE/wp-json/sidrena-cijena/v1/prices
 Price lists: Sidrena cijena → Price lists (admin), files under
              wp-content/uploads/sidrena-cijena/
-------------------------------------------------------------
 What to look at:
 - A product page shows the current price + "Sidrena cijena".
 - "Maslinovo ulje" is on akcija: sale price, anchor AND the
   30-day lowest price are shown with distinct labels.
 - Compliance check lists every finding (e.g. items without a
   barcode); an ERROR finding blocks a new price list until it
   is resolved, so remove an anchor to see FR-007 in action.
 - Sidrena cijena → Compliance check lists every finding.
 - WP-CLI: $WPENV run cli wp sidrena-cijena status
=============================================================
EOF
