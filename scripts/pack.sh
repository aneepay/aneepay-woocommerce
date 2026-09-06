#!/usr/bin/env bash
#
# pack.sh — собирает (упаковывает) плагин AneePay под конкретную CMS.
#
# Использование:
#   ./scripts/pack.sh <platform> [version]
#
#   platform  wc | oc | ps         (или "all" для всех трёх)
#   version   необязательно; по умолчанию берётся из ./VERSION
#
# Примеры:
#   ./scripts/pack.sh wc                       # → dist/wc/aneepay.zip
#   ./scripts/pack.sh oc 1.3.0                 # → dist/oc/aneepay-1.3.0.ocmod.zip
#   ./scripts/pack.sh ps                       # → dist/ps/ps_aneepay.zip
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PLATFORM="${1:-}"
VERSION="${2:-$(tr -d '[:space:]' < VERSION 2>/dev/null || echo 'dev')}"
DIST="$ROOT/dist"

fail() { echo "ERROR: $*" >&2; exit 1; }
warn() { echo "WARN: $*" >&2; }

# ---------------------------------------------------------------------------
# helpers
# ---------------------------------------------------------------------------

php_lint() {
  local base="$1"
  command -v php >/dev/null 2>&1 || { warn "php не найден — линт пропущен"; return 0; }
  local found=0 errors=0 f
  while IFS= read -r -d '' f; do
    found=1
    if ! php -l "$f" >/dev/null 2>&1; then
      php -l "$f" || true
      errors=$((errors + 1))
    fi
  done < <(find "$base" -type f -name '*.php' -print0)
  [ "$found" = 1 ] || warn "нет .php файлов в $base"
  [ "$errors" -eq 0 ] || fail "php -l: $errors ошибок в $base"
}

check_platform() {
  local p="$1" src="$2"
  case "$p" in
    wc)
      [ -f "$src/aneepay.php" ] || fail "нет $src/aneepay.php"
      grep -qE '^ \* Version:' "$src/aneepay.php" || warn "нет WP-header 'Version:' в aneepay.php"
      ;;
    oc)
      [ -f "$src/install.xml" ] \
        || warn "нет install.xml (для ocmod/загрузки расширения OC 3.x оба формата валидны)"
      ;;
    ps)
      local dir
      while IFS= read -r -d '' dir; do
        [ -f "$dir/index.php" ] || fail "нет index.php (требование PrestaShop): $dir"
      done < <(find "$src" -type d -print0)
      [ -f "$src/logo.png" ] || warn "нет logo.png в $src"
      ;;
  esac
}

make_zip() {
  # $1 — исходная папка, $2 — путь к итоговому .zip, $3 — корневая папка в архиве
  local src="$1" out="$2" rootname="$3" tmp
  tmp="$(mktemp -d)"
  mkdir -p "$tmp/$rootname"
  cp -a "$src/." "$tmp/$rootname/"
  ( cd "$tmp" && zip -qr "$out" "$rootname" )
  rm -rf "$tmp"
}

# ---------------------------------------------------------------------------
# main
# ---------------------------------------------------------------------------

[ -n "$PLATFORM" ] || fail "укажи платформу: wc | oc | ps | all"

platforms=("$PLATFORM")
[ "$PLATFORM" = "all" ] && platforms=(wc oc ps)

mkdir -p "$DIST"

for p in "${platforms[@]}"; do
  case "$p" in
    wc) src="plugins/woocommerce/aneepay";    base="aneepay";     fname="aneepay.zip" ;;
    oc) src="plugins/opencart/aneepay";       base="aneepay";     fname="aneepay-${VERSION}.ocmod.zip" ;;
    ps) src="plugins/prestashop/ps_aneepay";  base="ps_aneepay";  fname="ps_aneepay.zip" ;;
    *) fail "неизвестная платформа: $p" ;;
  esac

  [ -d "$src" ] || fail "нет каталога $src"

  echo "==> Упаковка: $p (v$VERSION)"
  php_lint "$src"
  check_platform "$p" "$src"

  dest="$DIST/$p"
  mkdir -p "$dest"

  make_zip "$src" "$dest/$fname" "$base"
  echo "   -> $dest/$fname"
done

echo "Готово. Артефакты в $DIST"
