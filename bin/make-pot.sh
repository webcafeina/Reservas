#!/usr/bin/env bash
# Regenerate languages/reservas-aldealab.pot using wp-cli's i18n command.
#
# Requires wp-cli on PATH. On macOS: `brew install wp-cli`. On Debian/Ubuntu:
# see https://wp-cli.org/#installing.
#
# Run from the plugin root:
#   bin/make-pot.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v wp >/dev/null 2>&1; then
    echo "wp-cli not found. Install it: https://wp-cli.org/" >&2
    exit 1
fi

wp i18n make-pot \
    "$ROOT" \
    "$ROOT/languages/reservas-aldealab.pot" \
    --domain=reservas-aldealab \
    --package-name="Reservas Aldealab" \
    --exclude=node_modules,vendor,assets/dist,frontend,tests,_legacy_assets \
    --headers='{"Last-Translator":"Webcafeína <info@webcafeina.com>","Language-Team":"Webcafeína <info@webcafeina.com>","Report-Msgid-Bugs-To":"https://webcafeina.com/"}'

echo "POT written to languages/reservas-aldealab.pot"
