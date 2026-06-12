#!/usr/bin/env bash
#
# build.sh — telepíthető, verziózott plugin ZIP készítése.
#
# A verzió egyetlen igazságforrása a git tag:
#
#     git tag v0.2.0
#     ./bin/build.sh          # vagy: composer build
#     → dist/elallasi-funkcio-0.2.0.zip
#
# A tag nélküli buildhez (fejlesztés) a verzió kézzel megadható:
#
#     ./bin/build.sh --version=9.9.9
#
set -euo pipefail

# --- konstansok --------------------------------------------------------------
SLUG="elallasi-funkcio"
MAIN_FILE="${SLUG}.php"

# A csomagba kerülő útvonalak (whitelist). A production vendor-t külön kezeljük.
INCLUDE_PATHS=(
  "${MAIN_FILE}"
  "uninstall.php"
  "src"
  "templates"
  "assets"
  "languages"
)

# --- helyek ------------------------------------------------------------------
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist"

err()  { printf '\033[31mHIBA:\033[0m %s\n' "$*" >&2; }
info() { printf '\033[32m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[33mFIGYELEM:\033[0m %s\n' "$*" >&2; }

# --- argumentumok ------------------------------------------------------------
VERSION=""
for arg in "$@"; do
  case "$arg" in
    --version=*) VERSION="${arg#--version=}" ;;
    -h|--help)
      grep '^#' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) err "Ismeretlen argumentum: $arg"; exit 1 ;;
  esac
done

# --- előfeltételek -----------------------------------------------------------
for tool in zip composer git; do
  command -v "$tool" >/dev/null 2>&1 || { err "Hiányzó eszköz: ${tool}"; exit 1; }
done

# --- verzió meghatározása ----------------------------------------------------
if [[ -z "$VERSION" ]]; then
  if ! VERSION="$(git -C "${ROOT_DIR}" describe --tags --abbrev=0 2>/dev/null)"; then
    err "Nincs elérhető git tag. Hozz létre egyet (pl. 'git tag v0.2.0'),"
    err "vagy add meg kézzel: ./bin/build.sh --version=X.Y.Z"
    exit 1
  fi
fi
VERSION="${VERSION#v}"   # 'v0.2.0' → '0.2.0'

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-].+)?$ ]]; then
  err "A verzió formátuma érvénytelen: '${VERSION}' (várt: X.Y.Z)"
  exit 1
fi

# --- tiszta-fa figyelmeztetés ------------------------------------------------
if [[ -n "$(git -C "${ROOT_DIR}" status --porcelain)" ]]; then
  warn "A munkamappa nem tiszta — a build a commitált állapotból dolgozik,"
  warn "de release-hez érdemes előbb commitolni/tagelni."
fi

# --- staging -----------------------------------------------------------------
STAGING="$(mktemp -d)"
trap 'rm -rf "${STAGING}"' EXIT
PKG_DIR="${STAGING}/${SLUG}"      # a ZIP gyökér-almappája (WP konvenció)
mkdir -p "${PKG_DIR}"

info "Build verzió: ${VERSION}"
info "Fájlok másolása a staging mappába…"
for path in "${INCLUDE_PATHS[@]}"; do
  if [[ -e "${ROOT_DIR}/${path}" ]]; then
    cp -R "${ROOT_DIR}/${path}" "${PKG_DIR}/"
  else
    warn "Kihagyva (nem létezik): ${path}"
  fi
done

# A composer.json a production függőségek telepítéséhez kell a staging-ben,
# de a kész csomagból utána eltávolítjuk.
cp "${ROOT_DIR}/composer.json" "${PKG_DIR}/"
[[ -f "${ROOT_DIR}/composer.lock" ]] && cp "${ROOT_DIR}/composer.lock" "${PKG_DIR}/"

# --- production függőségek ---------------------------------------------------
info "Production függőségek telepítése (composer install --no-dev)…"
(
  cd "${PKG_DIR}"
  composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --quiet
)

# composer fájlok eltávolítása a csomagból (futásidőben nem kellenek)
rm -f "${PKG_DIR}/composer.json" "${PKG_DIR}/composer.lock"

# --- verzió-stamp (csak a csomagban) -----------------------------------------
info "Verzió beírása a csomagolt plugin-fájlba…"
STAMPED="${PKG_DIR}/${MAIN_FILE}"
# Plugin-header 'Version:' sora
perl -0pi -e "s/^(\s*\*\s*Version:\s*).*$/\${1}${VERSION}/m" "${STAMPED}"
# ELALLAS_VERSION konstans
perl -0pi -e "s/(define\(\s*'ELALLAS_VERSION'\s*,\s*')[^']*('\s*\))/\${1}${VERSION}\${2}/" "${STAMPED}"

# --- ZIP ---------------------------------------------------------------------
mkdir -p "${DIST_DIR}"
ZIP_PATH="${DIST_DIR}/${SLUG}-${VERSION}.zip"
rm -f "${ZIP_PATH}"
info "ZIP készítése…"
(
  cd "${STAGING}"
  zip -rq "${ZIP_PATH}" "${SLUG}"
)

info "Kész: ${ZIP_PATH}"
printf '    %s\n' "$(cd "${DIST_DIR}" && ls -lh "$(basename "${ZIP_PATH}")" | awk '{print $5, $9}')"
