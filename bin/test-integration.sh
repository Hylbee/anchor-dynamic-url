#!/usr/bin/env bash
#
# Integration tests in WordPress Playground: WordPress and PHP compiled to
# WebAssembly and run by Node.js (no Docker, no MySQL). Used by CI, runnable locally.
#
# Usage: bin/test-integration.sh [--php 8.4] [--wp latest] [--zip path/to/anchor-dynamic-url.zip] [--port 9400]
#
# Without --zip, the ZIP is built from the working tree (bin/build-zip.php).
# Requires Node.js 20+, PHP 7.4+ with the zip extension, and curl.
# Results and fetched pages are written to build/integration/out/.

set -euo pipefail

PHP_VERSION="8.4"
WP_VERSION="latest"
ZIP=""
PORT="9400"
PLAYGROUND_CLI="@wp-playground/cli@3.1.65"
READY_TIMEOUT=600

while [ $# -gt 0 ]; do
	case "$1" in
		--php)  PHP_VERSION="$2"; shift 2 ;;
		--wp)   WP_VERSION="$2"; shift 2 ;;
		--zip)  ZIP="$2"; shift 2 ;;
		--port) PORT="$2"; shift 2 ;;
		-h|--help) sed -n '2,11p' "$0"; exit 0 ;;
		*) echo "Unknown option: $1" >&2; exit 2 ;;
	esac
done

# Native absolute path (C:/... under Git Bash on Windows), as Node.js expects.
native_path() { (cd "$1" && (pwd -W 2>/dev/null || pwd)); }

ROOT="$(native_path "$(dirname "$0")/..")"
WORK="$ROOT/build/integration"
BASE="http://127.0.0.1:$PORT"

rm -rf "$WORK"
mkdir -p "$WORK/plugin" "$WORK/dist" "$WORK/out"

# 1. The ZIP under test: the CI artifact, or one built from the working tree.
if [ -n "$ZIP" ]; then
	cp "$ZIP" "$WORK/dist/anchor-dynamic-url.zip"
else
	php "$ROOT/bin/build-zip.php" "$WORK/dist/anchor-dynamic-url.zip"
fi

# 2. WordPress runs the plugin extracted from that ZIP, never the source folder
#    (the update test rewrites plugin folders).
php -r '$z = new ZipArchive(); exit( true === $z->open( $argv[1] ) && $z->extractTo( $argv[2] ) ? 0 : 1 );' \
	"$WORK/dist/anchor-dynamic-url.zip" "$WORK/plugin"

# 3. Start WordPress Playground in the background.
echo "Starting WordPress $WP_VERSION / PHP $PHP_VERSION in Playground ($PLAYGROUND_CLI)..."
npx --yes "$PLAYGROUND_CLI" server \
	--php="$PHP_VERSION" \
	--wp="$WP_VERSION" \
	--port="$PORT" \
	--blueprint="$ROOT/tests/integration/blueprint.json" \
	--mount-dir "$WORK/plugin/anchor-dynamic-url" "/wordpress/wp-content/plugins/anchor-dynamic-url" \
	--mount-dir "$ROOT/tests/integration" "/wordpress/adu-tests" \
	--mount-dir "$WORK/dist" "/wordpress/adu-dist" \
	> "$WORK/out/playground.log" 2>&1 &
PLAYGROUND_PID=$!
trap 'kill "$PLAYGROUND_PID" 2>/dev/null || true' EXIT

fail() {
	echo "$1" >&2
	echo "--- Playground log (last 50 lines) ---" >&2
	tail -n 50 "$WORK/out/playground.log" >&2 || true
	exit 1
}

# 4. Wait for the blueprint to finish. Playground already answers requests while the
#    blueprint is still installing plugins, and prints "WordPress is running on" once
#    it is done.
deadline=$(( $(date +%s) + READY_TIMEOUT ))
until grep -q "WordPress is running on" "$WORK/out/playground.log"; do
	kill -0 "$PLAYGROUND_PID" 2>/dev/null || fail "Playground stopped before WordPress was ready."
	[ "$(date +%s)" -lt "$deadline" ] || fail "WordPress was not ready after ${READY_TIMEOUT}s."
	sleep 3
done

# Then check WordPress, Elementor and the plugin are loaded (HTTP 200 only then).
# -L: Playground answers its very first request with an auto-login redirect.
ready_code="$(curl -sL -o "$WORK/out/ready.json" -w '%{http_code}' "$BASE/adu-tests/setup.php?ready" || true)"
[ "$ready_code" = "200" ] || fail "Plugins not loaded (HTTP $ready_code): $(cat "$WORK/out/ready.json" 2>/dev/null || true)"
echo "Ready: $(cat "$WORK/out/ready.json")"

# 5. Create the test content, then check the frontend HTML (on the host).
curl -sSL --fail-with-body "$BASE/adu-tests/setup.php" -o "$WORK/out/setup.json" \
	|| fail "setup.php failed: $(cat "$WORK/out/setup.json" 2>/dev/null || true)"

status=0

echo
echo "== Frontend"
php "$ROOT/tests/integration/assert-frontend.php" "$WORK/out/setup.json" "$WORK/out" || status=1

# 6. Tests running inside WordPress: menus, updater, update end to end, PHP errors.
echo
curl -sSL --fail-with-body "$BASE/adu-tests/run.php" -o "$WORK/out/run.txt" || status=1
cat "$WORK/out/run.txt"

exit $status
