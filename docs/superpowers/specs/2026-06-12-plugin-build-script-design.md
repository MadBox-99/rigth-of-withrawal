# Plugin build script — tervdokumentum

Dátum: 2026-06-12
Plugin: `elallasi-funkcio` (cegem360/elallasi-funkcio)

## Cél

Egyetlen parancs, ami a WordPress plugint telepíthető, verziózott ZIP-be
csomagolja egy `dist/` mappába. A WP-be a felhasználó kézzel tölti fel
(Bővítmények → Új hozzáadása → Bővítmény feltöltése).

Nincs scope-ban: szerverre feltöltés (SSH/FTP), git hook, WordPress.org SVN release.

## Indítás és verzió forrása

A verzió **egyetlen igazságforrása a git tag**. Workflow:

```bash
git tag v0.2.0
composer build        # alias a bin/build.sh-ra
# → dist/elallasi-funkcio-0.2.0.zip
```

- A verziót `git describe --tags --abbrev=0` adja; a `v` prefixet a script levágja.
- Ha nincs elérhető tag: a script hibával leáll, kivéve ha `--version=X.Y.Z`
  kapcsolóval kézzel megadják (escape hatch fejlesztéshez).

## Komponens: `bin/build.sh`

Önálló, futtatható bash script. Lépések sorrendben:

1. **Előfeltételek** — `zip` és `composer` megléte. Hiányzó eszköz → hibás kilépés.
2. **Verzió meghatározás** — tagből (lásd fent), `--version=` felülírja.
3. **Tiszta-fa figyelmeztetés** — `git status --porcelain` nem üres esetén
   figyelmeztetés (nem fatális; release-nél elkerülendő a "piszkos" build).
4. **Staging** — friss ideiglenes mappa (pl. `mktemp -d`). A repó saját
   fejlesztői `vendor/`-ja **érintetlen marad**.
5. **Production függőségek** — a forrás a staging slug-mappába másolva, majd ott
   `composer install --no-dev --optimize-autoloader --no-scripts`.
6. **Whitelist** — csak a futáshoz kellő útvonalak kerülnek a csomagba:
   - `elallasi-funkcio.php`, `uninstall.php`
   - `src/`, `templates/`, `assets/`, `languages/`
   - production `vendor/`
   - **Kihagyva**: `tests/`, `docs/`, `.git/`, `.github/`, `phpunit.xml.dist`,
     `patchwork.json`, `composer.json`, `composer.lock`, `.phpunit.result.cache`,
     `bin/`, `dist/`, `.gitignore`
7. **Verzió-stamp (auto)** — a staging példányban a plugin-header
   `Version:` sora és az `ELALLAS_VERSION` konstans a tag verziójára íródik.
   A repó forrásfájljai **nem módosulnak** — csak a csomag tartalma.
8. **ZIP** — a csomag tartalma egy `elallasi-funkcio/` gyökér-almappa alatt
   (WP konvenció, hogy a kicsomagolás a helyes mappanevet adja). Kimenet:
   `dist/elallasi-funkcio-{verzió}.zip`. Meglévő azonos nevű ZIP felülíródik.
9. **Takarítás** — a staging mappa minden esetben törlődik (trap-pel, hibánál is).

### Hibakezelés

- `set -euo pipefail`; minden külső hívás (composer, zip) hibája megállítja a futást.
- `trap` a staging takarításra EXIT-en.
- Beszédes hibaüzenetek (hiányzó tag, hiányzó eszköz, composer hiba).

## Kísérő változások

- **`composer.json`**: `scripts` blokkba `"build": "bin/build.sh"`.
- **`.gitignore`**: `/dist/` hozzáadása.
- `bin/build.sh` futtathatóvá téve (`chmod +x`).

## Tesztelés / elfogadás

A script nehezen unit-tesztelhető (rendszereszközökre épül), így a verifikáció
manuális, dokumentált lépéssorral:

1. `git tag v0.1.0 && composer build` → létrejön `dist/elallasi-funkcio-0.1.0.zip`.
2. `unzip -l` a ZIP-en → van `elallasi-funkcio/` gyökér, benne `vendor/autoload.php`,
   `src/`, `templates/`, `assets/`, `languages/`; **nincs** `tests/`, `.git/`, dev-vendor.
3. A csomagolt `elallasi-funkcio.php` headerében és `ELALLAS_VERSION`-jében `0.1.0`.
4. A repó saját `vendor/`-ja és forrásfájljai változatlanok a build után.
5. `--version=9.9.9` kapcsolóval a verzió felülírható tag nélkül is.
