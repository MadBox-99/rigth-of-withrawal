# Elállási funkció WordPress plugin — Terv (Design Spec)

**Dátum:** 2026-06-10
**Státusz:** Jóváhagyott terv (implementáció előtt)

## 1. Cél és kontextus

Önállóan terjesztett (nem WordPress.org), fizetős WordPress plugin, amely a
**45/2014. (II. 26.) Korm. rendelet 2026. június 19-én hatályba lépő** módosításához
biztosítja a kötelező **online elállási funkciót** magyar webshopoknak.

A jogszabály lényege: a webshopnak nem elegendő tájékoztatnia az elállás lehetőségéről,
hanem tényleges, online elállási űrlapot kell biztosítania, amely az elállási időszak
teljes tartama alatt folyamatosan elérhető, „Elállás a szerződéstől" megjelöléssel.
Az elállás véglegesítése külön megerősítő funkcióval történik, majd a webshop
indokolatlan késedelem nélkül, tartós adathordozón átvételi elismervényt küld.

### Terjesztési és licenc modell

- **Nincs WordPress.org közzététel** — saját oldalról / piactérről értékesítve.
- **Egy plugin**, amelyben a Pro funkciókat **API-token (licenckulcs)** nyitja ki.
- A licenc-token kiállítását/ellenőrzését egy **külön licenc-szerver projekt** végzi;
  ez a spec a plugin oldalán definiálja az API-klienst és az elvárt végpont-szerződést.

### Free / Pro határ

| Réteg | Funkció |
|-------|---------|
| **Free (token nélkül)** | Működő, „Elállás a szerződéstől" feliratú online elállási űrlap (shortcode + Gutenberg blokk); kétlépcsős megerősítés; rekord mentése; admin lista. |
| **Pro (érvényes token)** | Automatikus visszaigazoló e-mail (tartós adathordozó: elállás lényege + dátum/időpont); WooCommerce integráció (rendelésazonosító és ügyfél-e-mail felismerése, rendeléshez kötés); admin dashboard extrák. |

> **Fontos kommunikációs megkötés:** a free réteg önmagában nem teszi a boltot teljesen
> jogszabály-konformmá, mert a kötelező visszaigazoló e-mail a Pro rétegben van.
> A plugin ezt a Pro felé egyértelműen jelzi, hogy ne legyen félrevezető.

## 2. Architektúra és komponensek

Egy plugin, tokennel kapcsolt Pro funkciókkal. Elkülönített, egy-felelősségű egységek,
jól definiált interfészeken kommunikálva:

- **Core / Plugin bootstrap** — betöltés, aktiváció/deaktiváció, DB-séma telepítés,
  hook-regisztráció, beállítások betöltése.
- **Form renderer** — az elállási űrlap kirajzolása shortcode (`[elallasi_urlap]`) és
  Gutenberg blokk formájában. Egységes, akadálymentes, jól olvasható sablon.
- **Submission handler** — beküldés feldolgozása: validáció, nonce/CSRF védelem,
  rate-limiting, kétlépcsős megerősítés kezelése, rekord mentése a repository-n keresztül.
- **Withdrawal repository** — adatréteg (saját DB-tábla CRUD), a tárolás részleteit
  elrejtő interfész.
- **Admin UI** — beérkezett elállások listája és részletei; beállítások (feliratok,
  értesítendő admin e-mail, szövegsablonok, token megadása).
- **Licensing client** — token aktiválás/ellenőrzés a külső licenc-szerver felé;
  WP update-integráció (token-hitelesített frissítés).
- **Pro: Mailer** — visszaigazoló e-mail összeállítása és küldése (tartós adathordozó).
- **Pro: WooCommerce bridge** — rendelés-keresés, ügyfél-e-mail előtöltés, elállás
  rendeléshez kötése, opcionális rendelés-jegyzet.

A Pro modulok csak akkor töltődnek be / aktívak, ha a Licensing client érvényes tokent
jelez (`is_pro_active()`). WooCommerce hiányában a Woo-bridge csendben kikapcsol.

## 3. Adatmodell és adatfolyam

### Tárolás

Saját DB-tábla: `wp_elallas_requests` (a `$wpdb->prefix` szerinti prefixszel).
Tisztább, mint egy CPT, és jól kezeli a személyes adatokat (GDPR export/törlés).

| Mező | Típus | Leírás |
|------|-------|--------|
| `id` | BIGINT PK | azonosító |
| `created_at` | DATETIME | beérkezés időpontja |
| `status` | VARCHAR | `received` / `confirmed` |
| `consumer_name` | VARCHAR | fogyasztó neve |
| `contact_email` | VARCHAR | visszaigazolási elérhetőség |
| `order_reference` | VARCHAR | szerződés/rendelés azonosító (kézi) |
| `wc_order_id` | BIGINT NULL | WooCommerce rendelés (Pro, ha összekötve) |
| `intent_text` | TEXT | elállási szándék szövege |
| `confirmation_token` | VARCHAR | kétlépcsős megerősítéshez |
| `confirmed_at` | DATETIME NULL | véglegesítés időpontja |
| `receipt_sent_at` | DATETIME NULL | visszaigazoló e-mail elküldve (Pro) |
| `ip_hash` | VARCHAR | hashelt IP (visszaélés-szűrés) |
| `lang` | VARCHAR | nyelv |

### Adatfolyam

1. Vevő megnyitja az űrlapot (oldal/blokk) → kitölti (szándék, név, rendelésazonosító,
   e-mail) → „Tovább".
2. Megerősítő képernyő (összegzés) → külön **„Elállás véglegesítése"** gomb — ez a
   jogszabály szerinti külön megerősítő funkció.
3. Submission handler validál, rekordot ment `received` státusszal.
4. **Pro:** azonnal visszaigazoló e-mail a vevőnek (átvételi elismervény: elállás
   lényege + dátum/időpont) és értesítő a boltnak; státusz frissül, `receipt_sent_at` kitöltve.
5. **Free:** rekord mentve, admin a felületen látja; visszaigazoló e-mail nincs.
6. **Pro + WooCommerce:** a megadott rendelésazonosító alapján a rendelés megtalálva és
   összekötve (`wc_order_id`), ügyfél-e-mail előtölthető.

### GDPR

A plugin beépül a WordPress személyes-adat exporter és eraser API-jába, hogy a tárolt
elállási rekordok exportálhatók/törölhetők legyenek.

## 4. Licencelés és frissítés

- **Aktiválás:** admin beállításokban beírt token → Licensing client POST a licenc-szerverre
  (`/activate`); válasz: érvényes / lejárt / site-limit túllépve. Eredmény cache-elve
  (transient), időszakos újraellenőrzéssel.
- **Pro kapuzás:** minden Pro funkció `is_pro_active()` ellenőrzés mögött.
- **Frissítés:** a plugin bekötődik a WordPress update API-ba; verzió-ellenőrzés és
  csomag-letöltés token-hitelesítéssel a licenc-szerverről (mivel nincs .org közzététel).
- **Grace period:** ha a licenc-szerver elérhetetlen, az utolsó ismert érvényes állapot
  marad érvényben egy türelmi ideig; a free funkciók ettől függetlenül sosem törnek el.

### Elvárt licenc-szerver szerződés (külön projekt)

| Végpont | Kérés | Válasz |
|---------|-------|--------|
| `POST /activate` | token, site_url | status, expires_at, site_limit |
| `POST /validate` | token, site_url | status, expires_at |
| `GET /update-check` | token, current_version | latest_version, package_url, changelog |

## 5. Jogi megfelelés (követelmény → komponens)

| Jogszabályi követelmény | Megvalósító komponens |
|-------------------------|------------------------|
| „Elállás a szerződéstől" felirat, jól olvasható űrlap | Form renderer |
| Folyamatos elérhetőség az elállási időszak alatt | Shortcode/blokk bármely oldalon |
| Szándék, név, szerződés-azonosító, e-mail megadása | Form renderer + Submission handler |
| Külön megerősítő funkció a véglegesítéshez | Kétlépcsős megerősítés (2. lépcső gomb) |
| Tartós adathordozós átvételi elismervény (lényeg + dátum/időpont) | Pro: Mailer |
| Indokolatlan késedelem nélküli visszaigazolás | Pro: azonnali e-mail a beküldéskor |

## 6. Hibakezelés

- Validációs hibák érthető, lokalizált üzenettel jelennek meg.
- E-mail küldési hiba esetén a rekord akkor is megmarad; admin riasztás + manuális
  újraküldés gomb.
- Licenc-szerver elérhetetlensége esetén az utolsó ismert állapot a cache-ből (grace period).
- A free funkciók működése sosem függ a licenc-szerver elérhetőségétől.

## 7. Nemzetköziesítés (i18n)

- Teljes fordíthatóság (`.pot` sablon), magyar és angol fordítás alapból.
- A későbbi (akár 6 nyelvű) bővítésre felkészített szövegkezelés; a rekord `lang` mezője
  rögzíti a beküldés nyelvét.

## 8. Tesztelés

- **PHPUnit (WP test suite):** submission handler, withdrawal repository, licenc-logika.
- Kétlépcsős megerősítés és e-mail-trigger lefedve.
- WooCommerce integráció külön teszttel (aktív/inaktív Woo eset).
- Validáció és GDPR exporter/eraser tesztek.

## Nyitott kérdések / későbbi projektek

- A **licenc-szerver** önálló spec és projekt (a fenti végpont-szerződés alapján).
- A 6 nyelvű fordítás tartalmi feltöltése későbbi ütemben.
