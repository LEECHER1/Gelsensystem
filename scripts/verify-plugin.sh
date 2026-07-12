#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN="$ROOT/gelsendiele-reservierungsdashboard"
ENTRY="$PLUGIN/gelsendiele-reservierungsdashboard.php"

test -f "$ENTRY"
test -f "$PLUGIN/includes/class-gelsendiele-settings.php"
test -f "$PLUGIN/includes/class-gelsendiele-availability.php"
test -f "$PLUGIN/includes/class-gelsensystem-email.php"
test -f "$PLUGIN/includes/class-gelsendiele-migrator.php"
test -f "$PLUGIN/includes/class-gelsendiele-admin.php"

VERSION="$(sed -n 's/^ \* Version: \([0-9][0-9.]*\)$/\1/p' "$ENTRY" | head -n 1)"
CONSTANT_VERSION="$(sed -n "s/.*define( 'GELSENDIELE_VERSION', '\([^']*\)' ).*/\1/p" "$ENTRY" | head -n 1)"
if [[ -z "$VERSION" || "$VERSION" != "$CONSTANT_VERSION" ]]; then
  echo "Plugin-Header und GELSENDIELE_VERSION stimmen nicht überein." >&2
  exit 1
fi

if ! grep -q '^ \* Plugin Name: Gelsensystem$' "$ENTRY"; then
  echo "Der sichtbare Pluginname muss Gelsensystem lauten." >&2
  exit 1
fi

if grep -Fq "class_exists( 'GDG_Plugin', false )" "$PLUGIN/modules/gastro/gelsendiele-gastro-system.php"; then
  echo "Das integrierte Gastro-Modul würde sich beim Laden selbst überspringen." >&2
  exit 1
fi

if grep -Fq 'gdrf-brand-logo' "$PLUGIN/includes/class-gd-reservation-engine.php"; then
  echo "Das Reservierungsformular darf kein eigenes Markenlogo ausgeben." >&2
  exit 1
fi

if ! grep -Fq '.gdrf-theme-light' "$PLUGIN/assets/reservation-form.css"; then
  echo "Der explizite helle Formularstil fehlt." >&2
  exit 1
fi

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    php -l "$file" >/dev/null
  done < <(find "$PLUGIN" -type f -name '*.php' -print0)
  php "$ROOT/tests/availability-smoke.php" >/dev/null
  php "$ROOT/tests/email-template-smoke.php" >/dev/null
  php "$ROOT/tests/gastro-fullscreen-smoke.php" >/dev/null
else
  echo "Hinweis: PHP ist lokal nicht verfügbar; PHP-Lint wird in GitHub Actions ausgeführt." >&2
fi

if command -v node >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    node --check "$file" >/dev/null
  done < <(find "$PLUGIN" -type f -name '*.js' -print0)
fi

if command -v rg >/dev/null 2>&1; then
  SECRET_MATCHES="$(rg -n --hidden -g '!*.png' -g '!*.zip' 'BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|ghp_[A-Za-z0-9]+' "$ROOT" || true)"
else
  SECRET_MATCHES="$(grep -RInE --exclude='*.png' --exclude='*.zip' --exclude-dir='.git' 'BEGIN (RSA|OPENSSH|EC) PRIVATE KEY|ghp_[A-Za-z0-9]+' "$ROOT" || true)"
fi
if [[ -n "$SECRET_MATCHES" ]]; then
  echo "$SECRET_MATCHES"
  echo "Mögliche Zugangsdaten im Repository gefunden." >&2
  exit 1
fi

echo "Gelsensystem $VERSION erfolgreich geprüft."
