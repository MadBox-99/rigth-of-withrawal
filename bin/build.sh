#!/usr/bin/env bash
#
# build.sh — telepíthető, verziózott plugin ZIP készítése.
#
# Verzió-forrás sorrendje:  --bump  >  --version=  >  legutóbbi git tag  >  header.
# Ezért a sima './bin/build.sh' paraméter nélkül is működik.
#
#   0) Csak csomagolás — a plugin-header verzióját (Version: X.Y.Z) használja,
#      a git-hez NEM nyúl:
#        ./bin/build.sh          # vagy: composer build
#        → dist/elallasi-funkcio-<header-verzió>.zip
#
#   1) Meglévő tagből / adott verzióból csomagol:
#        git tag v0.2.0 && ./bin/build.sh
#        ./bin/build.sh --version=0.2.0
#
#   2) Tag létrehozása + build (release egy lépésben, tiszta fa kell):
#        ./bin/build.sh --version=0.2.0 --tag
#
#   3) TELJES release: verzió-emelés + forrás-commit + tag + build [+ push]:
#        ./bin/build.sh --bump            # minor: 0.1.0 → 0.2.0 (alapértelmezett)
#        ./bin/build.sh --bump=patch      # 0.1.0 → 0.1.1
#        ./bin/build.sh --bump=major      # 0.1.0 → 1.0.0
#        ./bin/build.sh --bump --push     # a fentit az origin-ra is feltolja
#
# Kapcsolók:
#   --version=X.Y.Z   adott verzió (a --bump-pal nem kombinálható)
#   --bump[=szint]    verzió emelése (patch|minor|major; alap: minor), majd
#                     a forrásfájlba írja, commitolja és tag-eli
#   --tag             a --version verzióból annotált git tag-et hoz létre
#   --push            a build után pushol: aktuális ág + a release-tag
#   -h, --help        ez a súgó
#
# A --bump / --tag / --push tiszta munkamappát igényel, és nem ír felül létező tag-et.
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

# Verzió beírása egy plugin-fájlba (header 'Version:' + ELALLAS_VERSION konstans).
# A verziót környezeti változón át (adatként) adjuk a perlnek — NEM a
# programszövegbe interpolálva —, hogy semmilyen érték ne tudjon kódba törni.
stamp_version() {
  local file="$1" version="$2"
  VER="$version" perl -0pi -e 's/^(\s*\*\s*Version:\s*).*$/$1$ENV{VER}/m' "$file"
  VER="$version" perl -0pi -e "s/(define\(\s*'ELALLAS_VERSION'\s*,\s*')[^']*('\s*\))/\${1}\$ENV{VER}\${2}/" "$file"
}

# A jelenlegi verzió kiolvasása a plugin-header 'Version:' sorából.
read_header_version() {
  sed -nE 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*([0-9][^[:space:]]*).*/\1/p' \
    "${ROOT_DIR}/${MAIN_FILE}" | head -n1
}

# --- argumentumok ------------------------------------------------------------
VERSION=""
BUMP_LEVEL=""
DO_TAG=0
DO_PUSH=0
for arg in "$@"; do
  case "$arg" in
    --version=*) VERSION="${arg#--version=}" ;;
    --bump)      BUMP_LEVEL="minor" ;;
    --bump=*)    BUMP_LEVEL="${arg#--bump=}" ;;
    --tag)       DO_TAG=1 ;;
    --push)      DO_PUSH=1 ;;
    -h|--help)
      grep '^#' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) err "Ismeretlen argumentum: $arg"; exit 1 ;;
  esac
done

# Konfliktusok / validáció
if [[ -n "$BUMP_LEVEL" && -n "$VERSION" ]]; then
  err "A --bump és a --version= együtt nem használható (a --bump maga számol)."
  exit 1
fi
if [[ -n "$BUMP_LEVEL" && ! "$BUMP_LEVEL" =~ ^(patch|minor|major)$ ]]; then
  err "Érvénytelen --bump szint: '${BUMP_LEVEL}' (patch|minor|major)."
  exit 1
fi

# --- előfeltételek -----------------------------------------------------------
for tool in zip composer git perl; do
  command -v "$tool" >/dev/null 2>&1 || { err "Hiányzó eszköz: ${tool}"; exit 1; }
done

# --- verzió meghatározása ----------------------------------------------------
if [[ -n "$BUMP_LEVEL" ]]; then
  CURRENT="$(read_header_version)"
  if [[ ! "$CURRENT" =~ ^[0-9]+\.[0-9]+\.[0-9]+ ]]; then
    err "Nem olvasható ki érvényes jelenlegi verzió a headerből: '${CURRENT}'"
    exit 1
  fi
  IFS=. read -r MAJ MIN PAT <<< "${CURRENT%%[-+]*}"
  MAJ="${MAJ:-0}"; MIN="${MIN:-0}"; PAT="${PAT:-0}"
  case "$BUMP_LEVEL" in
    major) MAJ=$((MAJ + 1)); MIN=0; PAT=0 ;;
    minor) MIN=$((MIN + 1)); PAT=0 ;;
    patch) PAT=$((PAT + 1)) ;;
  esac
  VERSION="${MAJ}.${MIN}.${PAT}"
  info "Verzió emelése (${BUMP_LEVEL}): ${CURRENT} → ${VERSION}"
elif [[ -z "$VERSION" ]]; then
  if VERSION="$(git -C "${ROOT_DIR}" describe --tags --abbrev=0 2>/dev/null)"; then
    VERSION="${VERSION#v}"   # 'v0.2.0' → '0.2.0'
  else
    VERSION="$(read_header_version)"
    if [[ -n "$VERSION" ]]; then
      info "Nincs git tag — a plugin-header verziója használva: ${VERSION}"
    else
      err "Nem sikerült verziót megállapítani (nincs tag, és a header sem olvasható)."
      err "Add meg kézzel: ./bin/build.sh --version=X.Y.Z"
      exit 1
    fi
  fi
else
  VERSION="${VERSION#v}"   # '--version=v0.2.0' is elfogadott
fi

# Szigorú formátum: X.Y.Z, opcionális semver-szerű utótaggal. Szándékosan NEM
# enged meta-karaktereket (a VERSION lentebb perl-be és zip-névbe kerül).
if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.]+)?$ ]]; then
  err "A verzió formátuma érvénytelen: '${VERSION}' (várt: X.Y.Z)"
  exit 1
fi

TAG="v${VERSION}"

# --- release-előfeltételek (--bump / --tag) ----------------------------------
TREE_DIRTY=""
[[ -n "$(git -C "${ROOT_DIR}" status --porcelain)" ]] && TREE_DIRTY=1

NEED_TAG=0
[[ -n "$BUMP_LEVEL" || "$DO_TAG" == "1" ]] && NEED_TAG=1

if [[ "$NEED_TAG" == "1" ]]; then
  if [[ -n "$TREE_DIRTY" ]]; then
    err "A munkamappa nem tiszta — release (--bump/--tag) csak tiszta fán futtatható."
    err "Commitold a változásokat, majd futtasd újra."
    exit 1
  fi
  if git -C "${ROOT_DIR}" rev-parse -q --verify "refs/tags/${TAG}" >/dev/null; then
    err "A(z) '${TAG}' tag már létezik. Válassz másik verziót, vagy build-elj tag nélkül:"
    err "  ./bin/build.sh --version=${VERSION}"
    exit 1
  fi
fi
if [[ "$DO_PUSH" == "1" ]] && ! git -C "${ROOT_DIR}" remote get-url origin >/dev/null 2>&1; then
  err "--push kérve, de nincs 'origin' távoli repó beállítva."
  exit 1
fi

# --- verzió-emelés: forrás-commit (--bump) -----------------------------------
if [[ -n "$BUMP_LEVEL" ]]; then
  info "Új verzió beírása a forrásfájlba és commit…"
  stamp_version "${ROOT_DIR}/${MAIN_FILE}" "${VERSION}"
  git -C "${ROOT_DIR}" add "${MAIN_FILE}"
  git -C "${ROOT_DIR}" commit -q -m "chore(release): ${TAG}"
fi

# --- git tag (--bump / --tag) ------------------------------------------------
if [[ "$NEED_TAG" == "1" ]]; then
  info "Annotált git tag létrehozása: ${TAG}"
  git -C "${ROOT_DIR}" tag -a "${TAG}" -m "Release ${TAG}"
elif [[ -n "$TREE_DIRTY" ]]; then
  warn "A munkamappa nem tiszta — a build a commitált állapotból dolgozik,"
  warn "de release-hez érdemes előbb commitolni (lásd: --bump / --tag)."
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

# --- verzió-stamp (a csomagban; --bump nélkül is garantálja a helyes verziót) -
info "Verzió beírása a csomagolt plugin-fájlba…"
stamp_version "${PKG_DIR}/${MAIN_FILE}" "${VERSION}"

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

# --- push (--push) -----------------------------------------------------------
if [[ "$DO_PUSH" == "1" ]]; then
  BRANCH="$(git -C "${ROOT_DIR}" rev-parse --abbrev-ref HEAD)"
  info "Push az origin-ra: ${BRANCH} ág…"
  git -C "${ROOT_DIR}" push origin "${BRANCH}"
  if git -C "${ROOT_DIR}" rev-parse -q --verify "refs/tags/${TAG}" >/dev/null 2>&1; then
    info "Push: ${TAG} tag…"
    git -C "${ROOT_DIR}" push origin "${TAG}"
  fi
fi
