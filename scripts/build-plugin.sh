#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT/gelsendiele-reservierungsdashboard"
ENTRY="$PLUGIN_DIR/gelsendiele-reservierungsdashboard.php"
OUTPUT_DIR="${1:-$ROOT/dist}"
VERSION="$(sed -n 's/^ \* Version: \([0-9][0-9.]*\)$/\1/p' "$ENTRY" | head -n 1)"

if [[ -z "$VERSION" ]]; then
  echo "Plugin-Version konnte nicht ermittelt werden." >&2
  exit 1
fi

"$ROOT/scripts/verify-plugin.sh"
mkdir -p "$OUTPUT_DIR"
ARCHIVE="$OUTPUT_DIR/gelsensystem-v${VERSION}.zip"
rm -f "$ARCHIVE"

cd "$ROOT"
zip -q -r "$ARCHIVE" gelsendiele-reservierungsdashboard \
  -x '*/.DS_Store' '*/.git/*' '*.log'
unzip -t "$ARCHIVE" >/dev/null
echo "$ARCHIVE"
