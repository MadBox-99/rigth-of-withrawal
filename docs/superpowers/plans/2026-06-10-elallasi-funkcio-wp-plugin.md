# Elállási funkció WordPress plugin — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Egy önállóan terjesztett, fizetős WordPress plugin, amely a 45/2014. (II. 26.) Korm. rendelet 2026.06.19-i módosítása szerinti kötelező online elállási funkciót biztosítja; a Pro funkciókat API-token nyitja ki.

**Architecture:** Egyetlen plugin, PSR-4 autoloaddal, egy-felelősségű osztályokkal. A `received → confirmed` elállási rekordok saját DB-táblában tárolódnak. A Pro modulok (Mailer, WooCommerce bridge) csak érvényes licenc-token mellett aktívak (`is_pro_active()`). A free réteg (űrlap + kétlépcsős megerősítés + rekord mentés) sosem függ a licenc-szervertől.

**Tech Stack:** PHP 7.4+, WordPress 6.x, WooCommerce (opcionális, Pro), Composer + PSR-4, PHPUnit 9 + Brain Monkey (unit), Gutenberg blokk (@wordpress/scripts), WP i18n.

---

## File Structure

```
elallasi-funkcio/
├── elallasi-funkcio.php              # Plugin header + bootstrap entry
├── composer.json                     # PSR-4 autoload + dev deps
├── uninstall.php                     # Tábla + opciók törlése
├── src/
│   ├── Plugin.php                    # Core bootstrap, hook-regisztráció
│   ├── Activator.php                 # DB-séma telepítés aktiváláskor
│   ├── Repository/
│   │   └── WithdrawalRepository.php  # Adatréteg (wp_elallas_requests CRUD)
│   ├── Model/
│   │   └── Withdrawal.php            # Értékobjektum (rekord)
│   ├── Form/
│   │   ├── FormRenderer.php          # Shortcode + sablon
│   │   └── block.php                 # Gutenberg blokk regisztráció (PHP)
│   ├── Submission/
│   │   ├── SubmissionHandler.php     # Validáció + kétlépcsős flow
│   │   └── Validator.php             # Mezővalidáció
│   ├── Admin/
│   │   ├── AdminPage.php             # Elállások lista + részletek
│   │   └── SettingsPage.php          # Beállítások + token mező
│   ├── Licensing/
│   │   ├── LicenseClient.php         # Licenc-szerver API kliens
│   │   ├── LicenseManager.php        # is_pro_active(), cache, grace
│   │   └── Updater.php               # WP update API integráció
│   ├── Pro/
│   │   ├── Mailer.php                # Visszaigazoló e-mail (tartós adathordozó)
│   │   └── WooCommerceBridge.php     # Rendelés-keresés/összekötés
│   └── Privacy/
│       └── GdprExporter.php          # WP exporter/eraser
├── templates/
│   ├── form.php                      # Űrlap 1. lépcső
│   ├── confirm.php                   # Megerősítő 2. lépcső
│   └── email-receipt.php             # Átvételi elismervény sablon
├── assets/
│   ├── js/block.js                   # Gutenberg blokk (build)
│   └── css/form.css
├── languages/
│   └── elallasi-funkcio.pot
└── tests/
    ├── bootstrap.php
    └── Unit/
        ├── ValidatorTest.php
        ├── WithdrawalRepositoryTest.php
        ├── SubmissionHandlerTest.php
        ├── LicenseManagerTest.php
        ├── MailerTest.php
        └── WooCommerceBridgeTest.php
```

**Konvenciók:** namespace `Elallas\`, szövegdomén `elallasi-funkcio`, opció-prefix `elallas_`, hook-prefix `elallas_`.

---

## Task 1: Plugin scaffold + Composer + teszt-infrastruktúra

**Files:**
- Create: `composer.json`
- Create: `elallasi-funkcio.php`
- Create: `src/Plugin.php`
- Create: `tests/bootstrap.php`
- Create: `phpunit.xml.dist`

- [ ] **Step 1: composer.json létrehozása**

```json
{
  "name": "cegem360/elallasi-funkcio",
  "description": "Kötelező online elállási funkció magyar webshopoknak (45/2014. Korm. rendelet).",
  "type": "wordpress-plugin",
  "require": { "php": ">=7.4" },
  "require-dev": {
    "phpunit/phpunit": "^9.6",
    "brain/monkey": "^2.6",
    "mockery/mockery": "^1.6"
  },
  "autoload": { "psr-4": { "Elallas\\": "src/" } },
  "autoload-dev": { "psr-4": { "Elallas\\Tests\\": "tests/" } },
  "config": { "sort-packages": true }
}
```

- [ ] **Step 2: Függőségek telepítése**

Run: `composer install`
Expected: `vendor/` létrejön, `composer.lock` íródik.

- [ ] **Step 3: phpunit.xml.dist létrehozása**

```xml
<?xml version="1.0"?>
<phpunit bootstrap="tests/bootstrap.php" colors="true" failOnWarning="true">
  <testsuites>
    <testsuite name="unit">
      <directory>tests/Unit</directory>
    </testsuite>
  </testsuites>
</phpunit>
```

- [ ] **Step 4: tests/bootstrap.php létrehozása**

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Brain Monkey biztosítja a WP függvény-stubokat tesztenként.
```

- [ ] **Step 5: Plugin fő fájl (header + bootstrap)**

`elallasi-funkcio.php`:
```php
<?php
/**
 * Plugin Name: Elállási funkció
 * Description: Kötelező online elállási funkció magyar webshopoknak (45/2014. Korm. rendelet).
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: elallasi-funkcio
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; }

define('ELALLAS_VERSION', '0.1.0');
define('ELALLAS_FILE', __FILE__);
define('ELALLAS_DIR', plugin_dir_path(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, [\Elallas\Activator::class, 'activate']);

add_action('plugins_loaded', static function () {
    (new \Elallas\Plugin())->boot();
});
```

- [ ] **Step 6: Üres Plugin osztály (boot no-op egyelőre)**

`src/Plugin.php`:
```php
<?php
namespace Elallas;

class Plugin
{
    public function boot(): void
    {
        // A további taskokban kapcsoljuk be a komponenseket.
    }
}
```

- [ ] **Step 7: Smoke teszt az autoloadra**

`tests/Unit/PluginTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Elallas\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function test_plugin_class_boots_without_error(): void
    {
        $this->expectNotToPerformAssertions();
        (new Plugin())->boot();
    }
}
```

- [ ] **Step 8: Tesztek futtatása**

Run: `vendor/bin/phpunit`
Expected: PASS (1 test).

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist elallasi-funkcio.php src/Plugin.php tests/
git commit -m "chore: plugin scaffold + composer + phpunit/brain-monkey"
```

---

## Task 2: Withdrawal modell + DB-séma (Activator)

**Files:**
- Create: `src/Model/Withdrawal.php`
- Create: `src/Activator.php`
- Test: `tests/Unit/WithdrawalModelTest.php`

- [ ] **Step 1: Failing test a modellre**

`tests/Unit/WithdrawalModelTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Elallas\Model\Withdrawal;
use PHPUnit\Framework\TestCase;

class WithdrawalModelTest extends TestCase
{
    public function test_creates_from_array_and_exposes_fields(): void
    {
        $w = Withdrawal::fromArray([
            'consumer_name' => 'Teszt Elek',
            'contact_email' => 'elek@example.com',
            'order_reference' => 'WC-1001',
            'intent_text' => 'Elállok a szerződéstől.',
            'lang' => 'hu',
        ]);

        $this->assertSame('Teszt Elek', $w->consumerName());
        $this->assertSame('elek@example.com', $w->contactEmail());
        $this->assertSame('received', $w->status());
    }

    public function test_to_db_row_contains_required_columns(): void
    {
        $row = Withdrawal::fromArray([
            'consumer_name' => 'A', 'contact_email' => 'a@b.hu',
            'order_reference' => 'X', 'intent_text' => 'I', 'lang' => 'hu',
        ])->toDbRow('2026-06-10 12:00:00', 'tok123', 'iphash');

        $this->assertSame('received', $row['status']);
        $this->assertSame('tok123', $row['confirmation_token']);
        $this->assertArrayHasKey('created_at', $row);
    }
}
```

- [ ] **Step 2: Teszt fut, megbukik**

Run: `vendor/bin/phpunit --filter WithdrawalModelTest`
Expected: FAIL ("Class ... Withdrawal not found").

- [ ] **Step 3: Withdrawal modell implementáció**

`src/Model/Withdrawal.php`:
```php
<?php
namespace Elallas\Model;

class Withdrawal
{
    private string $consumerName;
    private string $contactEmail;
    private string $orderReference;
    private string $intentText;
    private string $lang;
    private string $status;

    private function __construct(array $d)
    {
        $this->consumerName = (string)($d['consumer_name'] ?? '');
        $this->contactEmail = (string)($d['contact_email'] ?? '');
        $this->orderReference = (string)($d['order_reference'] ?? '');
        $this->intentText = (string)($d['intent_text'] ?? '');
        $this->lang = (string)($d['lang'] ?? 'hu');
        $this->status = (string)($d['status'] ?? 'received');
    }

    public static function fromArray(array $d): self { return new self($d); }

    public function consumerName(): string { return $this->consumerName; }
    public function contactEmail(): string { return $this->contactEmail; }
    public function orderReference(): string { return $this->orderReference; }
    public function intentText(): string { return $this->intentText; }
    public function lang(): string { return $this->lang; }
    public function status(): string { return $this->status; }

    public function toDbRow(string $createdAt, string $confirmationToken, string $ipHash): array
    {
        return [
            'created_at' => $createdAt,
            'status' => $this->status,
            'consumer_name' => $this->consumerName,
            'contact_email' => $this->contactEmail,
            'order_reference' => $this->orderReference,
            'wc_order_id' => null,
            'intent_text' => $this->intentText,
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => null,
            'receipt_sent_at' => null,
            'ip_hash' => $ipHash,
            'lang' => $this->lang,
        ];
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter WithdrawalModelTest`
Expected: PASS.

- [ ] **Step 5: Activator (DB-séma) — implementáció**

`src/Activator.php`:
```php
<?php
namespace Elallas;

class Activator
{
    public const TABLE = 'elallas_requests';

    public static function activate(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'received',
            consumer_name VARCHAR(190) NOT NULL,
            contact_email VARCHAR(190) NOT NULL,
            order_reference VARCHAR(190) NOT NULL,
            wc_order_id BIGINT UNSIGNED NULL,
            intent_text TEXT NULL,
            confirmation_token VARCHAR(64) NOT NULL,
            confirmed_at DATETIME NULL,
            receipt_sent_at DATETIME NULL,
            ip_hash VARCHAR(64) NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'hu',
            PRIMARY KEY (id),
            KEY status (status),
            KEY confirmation_token (confirmation_token)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('elallas_db_version', ELALLAS_VERSION);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add src/Model/Withdrawal.php src/Activator.php tests/Unit/WithdrawalModelTest.php
git commit -m "feat: withdrawal model + activation DB schema"
```

---

## Task 3: Validator (mezővalidáció)

**Files:**
- Create: `src/Submission/Validator.php`
- Test: `tests/Unit/ValidatorTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/ValidatorTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Submission\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('is_email')->alias(fn($e) => (bool)filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : false);
        Functions\when('__')->returnArg(1);
    }

    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_valid_input_returns_no_errors(): void
    {
        $errors = (new Validator())->validate([
            'consumer_name' => 'Teszt Elek',
            'contact_email' => 'elek@example.com',
            'order_reference' => 'WC-1001',
            'intent_text' => 'Elállok.',
        ]);
        $this->assertSame([], $errors);
    }

    public function test_missing_name_and_bad_email_reported(): void
    {
        $errors = (new Validator())->validate([
            'consumer_name' => '',
            'contact_email' => 'not-an-email',
            'order_reference' => 'WC-1001',
            'intent_text' => 'Elállok.',
        ]);
        $this->assertArrayHasKey('consumer_name', $errors);
        $this->assertArrayHasKey('contact_email', $errors);
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter ValidatorTest`
Expected: FAIL ("Class Validator not found").

- [ ] **Step 3: Validator implementáció**

`src/Submission/Validator.php`:
```php
<?php
namespace Elallas\Submission;

class Validator
{
    /** @return array<string,string> field => error message */
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string)($input['consumer_name'] ?? '')) === '') {
            $errors['consumer_name'] = __('A név megadása kötelező.', 'elallasi-funkcio');
        }
        $email = trim((string)($input['contact_email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            $errors['contact_email'] = __('Érvényes e-mail cím megadása kötelező.', 'elallasi-funkcio');
        }
        if (trim((string)($input['order_reference'] ?? '')) === '') {
            $errors['order_reference'] = __('A rendelés/szerződés azonosító megadása kötelező.', 'elallasi-funkcio');
        }
        if (trim((string)($input['intent_text'] ?? '')) === '') {
            $errors['intent_text'] = __('Az elállási szándék megadása kötelező.', 'elallasi-funkcio');
        }

        return $errors;
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter ValidatorTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Submission/Validator.php tests/Unit/ValidatorTest.php
git commit -m "feat: submission field validator"
```

---

## Task 4: WithdrawalRepository (adatréteg)

**Files:**
- Create: `src/Repository/WithdrawalRepository.php`
- Test: `tests/Unit/WithdrawalRepositoryTest.php`

- [ ] **Step 1: Failing test (wpdb mockolva)**

`tests/Unit/WithdrawalRepositoryTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Elallas\Repository\WithdrawalRepository;
use PHPUnit\Framework\TestCase;

class WithdrawalRepositoryTest extends TestCase
{
    public function test_insert_calls_wpdb_and_returns_id(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            public array $lastInsert = [];
            public function insert($table, $data) { $this->lastInsert = [$table, $data]; $this->insert_id = 42; return 1; }
        };
        $repo = new WithdrawalRepository($wpdb);

        $id = $repo->insert([
            'created_at' => '2026-06-10 12:00:00', 'status' => 'received',
            'consumer_name' => 'A', 'contact_email' => 'a@b.hu',
            'order_reference' => 'X', 'wc_order_id' => null, 'intent_text' => 'I',
            'confirmation_token' => 't', 'confirmed_at' => null, 'receipt_sent_at' => null,
            'ip_hash' => 'h', 'lang' => 'hu',
        ]);

        $this->assertSame(42, $id);
        $this->assertSame('wp_elallas_requests', $repo->tableName());
    }

    public function test_mark_confirmed_updates_status(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $lastUpdate = [];
            public function update($t, $data, $where) { $this->lastUpdate = [$t, $data, $where]; return 1; }
        };
        $repo = new WithdrawalRepository($wpdb);
        $ok = $repo->markConfirmed(42, '2026-06-10 12:05:00');

        $this->assertTrue($ok);
        $this->assertSame('confirmed', $wpdb->lastUpdate[1]['status']);
        $this->assertSame(['id' => 42], $wpdb->lastUpdate[2]);
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter WithdrawalRepositoryTest`
Expected: FAIL ("Class WithdrawalRepository not found").

- [ ] **Step 3: Repository implementáció**

`src/Repository/WithdrawalRepository.php`:
```php
<?php
namespace Elallas\Repository;

use Elallas\Activator;

class WithdrawalRepository
{
    /** @var \wpdb|object */
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . Activator::TABLE;
    }

    public function insert(array $row): int
    {
        $this->wpdb->insert($this->tableName(), $row);
        return (int)$this->wpdb->insert_id;
    }

    public function markConfirmed(int $id, string $confirmedAt): bool
    {
        $ok = $this->wpdb->update(
            $this->tableName(),
            ['status' => 'confirmed', 'confirmed_at' => $confirmedAt],
            ['id' => $id]
        );
        return $ok !== false;
    }

    public function markReceiptSent(int $id, string $sentAt): bool
    {
        $ok = $this->wpdb->update(
            $this->tableName(),
            ['receipt_sent_at' => $sentAt],
            ['id' => $id]
        );
        return $ok !== false;
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter WithdrawalRepositoryTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Repository/WithdrawalRepository.php tests/Unit/WithdrawalRepositoryTest.php
git commit -m "feat: withdrawal repository (insert/confirm/receipt)"
```

---

## Task 5: LicenseManager (Pro kapuzás, cache, grace period)

**Files:**
- Create: `src/Licensing/LicenseClient.php`
- Create: `src/Licensing/LicenseManager.php`
- Test: `tests/Unit/LicenseManagerTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/LicenseManagerTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Elallas\Licensing\LicenseManager;
use PHPUnit\Framework\TestCase;

class LicenseManagerTest extends TestCase
{
    public function test_pro_inactive_without_token(): void
    {
        $mgr = new LicenseManager(
            tokenProvider: fn() => '',
            cacheGet: fn($k) => false,
            cacheSet: fn($k, $v, $ttl) => null,
            validator: fn($token, $site) => ['status' => 'valid']
        );
        $this->assertFalse($mgr->isProActive());
    }

    public function test_pro_active_when_validator_returns_valid(): void
    {
        $mgr = new LicenseManager(
            tokenProvider: fn() => 'TOKEN',
            cacheGet: fn($k) => false,
            cacheSet: fn($k, $v, $ttl) => null,
            validator: fn($token, $site) => ['status' => 'valid', 'expires_at' => '2099-01-01']
        );
        $this->assertTrue($mgr->isProActive());
    }

    public function test_grace_period_uses_cache_when_validator_fails(): void
    {
        $mgr = new LicenseManager(
            tokenProvider: fn() => 'TOKEN',
            cacheGet: fn($k) => ['status' => 'valid', 'expires_at' => '2099-01-01'],
            cacheSet: fn($k, $v, $ttl) => null,
            validator: function ($token, $site) { throw new \RuntimeException('server down'); }
        );
        $this->assertTrue($mgr->isProActive());
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter LicenseManagerTest`
Expected: FAIL ("Class LicenseManager not found").

- [ ] **Step 3: LicenseClient implementáció (HTTP a licenc-szerver felé)**

`src/Licensing/LicenseClient.php`:
```php
<?php
namespace Elallas\Licensing;

class LicenseClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** @return array{status:string,expires_at?:string} */
    public function validate(string $token, string $siteUrl): array
    {
        $resp = wp_remote_post($this->baseUrl . '/validate', [
            'timeout' => 10,
            'body' => ['token' => $token, 'site_url' => $siteUrl],
        ]);
        if (is_wp_error($resp)) {
            throw new \RuntimeException($resp->get_error_message());
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            throw new \RuntimeException('Unexpected status ' . $code);
        }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($data) ? $data : ['status' => 'invalid'];
    }
}
```

- [ ] **Step 4: LicenseManager implementáció (injektált closure-ök tesztelhetőséghez)**

`src/Licensing/LicenseManager.php`:
```php
<?php
namespace Elallas\Licensing;

class LicenseManager
{
    private const CACHE_KEY = 'elallas_license_state';
    private const TTL = 12 * HOUR_IN_SECONDS;

    /** @var callable */ private $tokenProvider;
    /** @var callable */ private $cacheGet;
    /** @var callable */ private $cacheSet;
    /** @var callable */ private $validator;

    public function __construct(callable $tokenProvider, callable $cacheGet, callable $cacheSet, callable $validator)
    {
        $this->tokenProvider = $tokenProvider;
        $this->cacheGet = $cacheGet;
        $this->cacheSet = $cacheSet;
        $this->validator = $validator;
    }

    public function isProActive(): bool
    {
        $token = (string)($this->tokenProvider)();
        if ($token === '') {
            return false;
        }

        $cached = ($this->cacheGet)(self::CACHE_KEY);
        if (is_array($cached) && ($cached['status'] ?? '') === 'valid') {
            return true;
        }

        try {
            $state = ($this->validator)($token, $this->siteUrl());
            ($this->cacheSet)(self::CACHE_KEY, $state, self::TTL);
            return ($state['status'] ?? '') === 'valid';
        } catch (\Throwable $e) {
            // Grace period: a szerver elérhetetlen → utolsó ismert állapot.
            return is_array($cached) && ($cached['status'] ?? '') === 'valid';
        }
    }

    private function siteUrl(): string
    {
        return function_exists('home_url') ? home_url() : '';
    }
}
```

> Megjegyzés: a `HOUR_IN_SECONDS` konstanst a teszt bootstrap definiálja, ha nincs WP. Add hozzá a `tests/bootstrap.php`-hez: `if (!defined('HOUR_IN_SECONDS')) define('HOUR_IN_SECONDS', 3600);`

- [ ] **Step 5: Teszt zöld**

Run: `vendor/bin/phpunit --filter LicenseManagerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Licensing/ tests/Unit/LicenseManagerTest.php tests/bootstrap.php
git commit -m "feat: license manager with cache + grace period"
```

---

## Task 6: SubmissionHandler (kétlépcsős flow, free)

**Files:**
- Create: `src/Submission/SubmissionHandler.php`
- Test: `tests/Unit/SubmissionHandlerTest.php`

A handler a validátort, a repository-t és egy `clock` + `tokenFactory` + `ipHasher` callable-t kap injektálva (tesztelhetőség). Pro hook-ot egy `onConfirmed` callback-en keresztül hív.

- [ ] **Step 1: Failing test**

`tests/Unit/SubmissionHandlerTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Submission\SubmissionHandler;
use Elallas\Submission\Validator;
use PHPUnit\Framework\TestCase;

class SubmissionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('is_email')->alias(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : false);
        Functions\when('__')->returnArg(1);
        Functions\when('sanitize_text_field')->returnArg(1);
        Functions\when('sanitize_textarea_field')->returnArg(1);
    }

    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    private function repoSpy(): object
    {
        return new class {
            public array $inserted = [];
            public array $confirmed = [];
            public function insert(array $row): int { $this->inserted[] = $row; return 7; }
            public function markConfirmed(int $id, string $at): bool { $this->confirmed[] = [$id, $at]; return true; }
        };
    }

    public function test_step1_validation_failure_returns_errors_no_insert(): void
    {
        $repo = $this->repoSpy();
        $handler = $this->makeHandler($repo);
        $result = $handler->prepare(['consumer_name' => '', 'contact_email' => 'x', 'order_reference' => '', 'intent_text' => '']);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertSame([], $repo->inserted);
    }

    public function test_step1_valid_input_inserts_received_record(): void
    {
        $repo = $this->repoSpy();
        $handler = $this->makeHandler($repo);
        $result = $handler->prepare([
            'consumer_name' => 'Teszt Elek', 'contact_email' => 'elek@example.com',
            'order_reference' => 'WC-1001', 'intent_text' => 'Elállok.',
        ]);
        $this->assertTrue($result['ok']);
        $this->assertSame('received', $repo->inserted[0]['status']);
        $this->assertSame('TOK', $result['confirmation_token']);
    }

    public function test_step2_confirm_marks_record_and_fires_callback(): void
    {
        $repo = $this->repoSpy();
        $fired = [];
        $handler = $this->makeHandler($repo, function (int $id) use (&$fired) { $fired[] = $id; });
        $ok = $handler->confirm(7);
        $this->assertTrue($ok);
        $this->assertSame([[7, '2026-06-10 12:00:00']], $repo->confirmed);
        $this->assertSame([7], $fired);
    }

    private function makeHandler(object $repo, ?callable $onConfirmed = null): SubmissionHandler
    {
        return new SubmissionHandler(
            new Validator(),
            $repo,
            fn() => '2026-06-10 12:00:00',
            fn() => 'TOK',
            fn() => 'iphash',
            $onConfirmed ?? function (int $id) {}
        );
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter SubmissionHandlerTest`
Expected: FAIL ("Class SubmissionHandler not found").

- [ ] **Step 3: SubmissionHandler implementáció**

`src/Submission/SubmissionHandler.php`:
```php
<?php
namespace Elallas\Submission;

use Elallas\Model\Withdrawal;

class SubmissionHandler
{
    private Validator $validator;
    /** @var object */ private $repo;
    /** @var callable */ private $clock;
    /** @var callable */ private $tokenFactory;
    /** @var callable */ private $ipHasher;
    /** @var callable */ private $onConfirmed;

    public function __construct(Validator $validator, $repo, callable $clock, callable $tokenFactory, callable $ipHasher, callable $onConfirmed)
    {
        $this->validator = $validator;
        $this->repo = $repo;
        $this->clock = $clock;
        $this->tokenFactory = $tokenFactory;
        $this->ipHasher = $ipHasher;
        $this->onConfirmed = $onConfirmed;
    }

    /** 1. lépcső: validálás + received rekord mentése. */
    public function prepare(array $raw): array
    {
        $input = [
            'consumer_name' => sanitize_text_field($raw['consumer_name'] ?? ''),
            'contact_email' => sanitize_text_field($raw['contact_email'] ?? ''),
            'order_reference' => sanitize_text_field($raw['order_reference'] ?? ''),
            'intent_text' => sanitize_textarea_field($raw['intent_text'] ?? ''),
            'lang' => sanitize_text_field($raw['lang'] ?? 'hu'),
        ];

        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $token = (string)($this->tokenFactory)();
        $row = Withdrawal::fromArray($input)->toDbRow(
            (string)($this->clock)(),
            $token,
            (string)($this->ipHasher)()
        );
        $id = $this->repo->insert($row);

        return ['ok' => true, 'errors' => [], 'id' => $id, 'confirmation_token' => $token];
    }

    /** 2. lépcső: külön megerősítő funkció — véglegesítés. */
    public function confirm(int $id): bool
    {
        $ok = $this->repo->markConfirmed($id, (string)($this->clock)());
        if ($ok) {
            ($this->onConfirmed)($id);
        }
        return $ok;
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter SubmissionHandlerTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Submission/SubmissionHandler.php tests/Unit/SubmissionHandlerTest.php
git commit -m "feat: two-step submission handler (prepare + confirm)"
```

---

## Task 7: Pro Mailer (átvételi elismervény)

**Files:**
- Create: `src/Pro/Mailer.php`
- Create: `templates/email-receipt.php`
- Test: `tests/Unit/MailerTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/MailerTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Pro\Mailer;
use PHPUnit\Framework\TestCase;

class MailerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_sends_receipt_with_date_and_intent(): void
    {
        $captured = [];
        Functions\when('wp_mail')->alias(function ($to, $subject, $body, $headers) use (&$captured) {
            $captured = compact('to', 'subject', 'body', 'headers');
            return true;
        });

        $mailer = new Mailer(fn() => '2026-06-10 12:00:00');
        $ok = $mailer->sendReceipt([
            'contact_email' => 'elek@example.com',
            'consumer_name' => 'Teszt Elek',
            'order_reference' => 'WC-1001',
            'intent_text' => 'Elállok a szerződéstől.',
        ]);

        $this->assertTrue($ok);
        $this->assertSame('elek@example.com', $captured['to']);
        $this->assertStringContainsString('2026-06-10 12:00:00', $captured['body']);
        $this->assertStringContainsString('WC-1001', $captured['body']);
        $this->assertContains('Content-Type: text/html; charset=UTF-8', $captured['headers']);
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter MailerTest`
Expected: FAIL ("Class Mailer not found").

- [ ] **Step 3: Email sablon**

`templates/email-receipt.php`:
```php
<?php
/** @var array $data */
?>
<h2><?php echo esc_html__('Átvételi elismervény – Elállás a szerződéstől', 'elallasi-funkcio'); ?></h2>
<p><?php echo esc_html(sprintf(__('Tisztelt %s!', 'elallasi-funkcio'), $data['consumer_name'])); ?></p>
<p><?php echo esc_html__('Visszaigazoljuk, hogy elállási nyilatkozatát átvettük.', 'elallasi-funkcio'); ?></p>
<ul>
  <li><strong><?php echo esc_html__('Rendelés/szerződés azonosító:', 'elallasi-funkcio'); ?></strong> <?php echo esc_html($data['order_reference']); ?></li>
  <li><strong><?php echo esc_html__('Elállás lényege:', 'elallasi-funkcio'); ?></strong> <?php echo esc_html($data['intent_text']); ?></li>
  <li><strong><?php echo esc_html__('Dátum és időpont:', 'elallasi-funkcio'); ?></strong> <?php echo esc_html($data['received_at']); ?></li>
</ul>
```

- [ ] **Step 4: Mailer implementáció**

`src/Pro/Mailer.php`:
```php
<?php
namespace Elallas\Pro;

class Mailer
{
    /** @var callable */ private $clock;

    public function __construct(callable $clock)
    {
        $this->clock = $clock;
    }

    public function sendReceipt(array $data): bool
    {
        $data['received_at'] = (string)($this->clock)();
        $subject = __('Átvételi elismervény – Elállás a szerződéstől', 'elallasi-funkcio');
        $body = $this->renderBody($data);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return (bool)wp_mail($data['contact_email'], $subject, $body, $headers);
    }

    private function renderBody(array $data): string
    {
        ob_start();
        include defined('ELALLAS_DIR') ? ELALLAS_DIR . 'templates/email-receipt.php'
            : __DIR__ . '/../../templates/email-receipt.php';
        return (string)ob_get_clean();
    }
}
```

> Megjegyzés: a sablon `esc_html__`-t használ; a teszt bootstrap-be add hozzá: `if (!function_exists('esc_html__')) { function esc_html__($t, $d=null){return $t;} }`. Élesben a WP biztosítja.

- [ ] **Step 5: Teszt zöld**

Run: `vendor/bin/phpunit --filter MailerTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Pro/Mailer.php templates/email-receipt.php tests/Unit/MailerTest.php tests/bootstrap.php
git commit -m "feat: pro mailer for durable-medium receipt email"
```

---

## Task 8: Pro WooCommerceBridge (rendelés-összekötés)

**Files:**
- Create: `src/Pro/WooCommerceBridge.php`
- Test: `tests/Unit/WooCommerceBridgeTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/WooCommerceBridgeTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Pro\WooCommerceBridge;
use PHPUnit\Framework\TestCase;

class WooCommerceBridgeTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_returns_null_when_woocommerce_inactive(): void
    {
        Functions\when('function_exists')->justReturn(false);
        $bridge = new WooCommerceBridge();
        $this->assertNull($bridge->findOrderEmail('WC-1001'));
    }

    public function test_resolves_order_email_when_order_found(): void
    {
        Functions\when('function_exists')->alias(fn($n) => $n === 'wc_get_order');
        $order = new class { public function get_billing_email() { return 'buyer@example.com'; } };
        Functions\when('wc_get_order')->alias(fn($id) => $id === '1001' ? $order : false);

        $bridge = new WooCommerceBridge();
        $this->assertSame('buyer@example.com', $bridge->findOrderEmail('1001'));
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter WooCommerceBridgeTest`
Expected: FAIL ("Class WooCommerceBridge not found").

- [ ] **Step 3: WooCommerceBridge implementáció**

`src/Pro/WooCommerceBridge.php`:
```php
<?php
namespace Elallas\Pro;

class WooCommerceBridge
{
    public function isAvailable(): bool
    {
        return function_exists('wc_get_order');
    }

    public function findOrderEmail(string $reference): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        $id = preg_replace('/[^0-9]/', '', $reference);
        if ($id === '') {
            return null;
        }
        $order = wc_get_order($id);
        if (!$order) {
            return null;
        }
        $email = $order->get_billing_email();
        return $email !== '' ? $email : null;
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter WooCommerceBridgeTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Pro/WooCommerceBridge.php tests/Unit/WooCommerceBridgeTest.php
git commit -m "feat: pro woocommerce bridge (order email lookup)"
```

---

## Task 9: FormRenderer (shortcode + sablonok)

**Files:**
- Create: `src/Form/FormRenderer.php`
- Create: `templates/form.php`
- Create: `templates/confirm.php`
- Create: `assets/css/form.css`
- Test: `tests/Unit/FormRendererTest.php`

- [ ] **Step 1: Failing test (a kimenet tartalmazza a kötelező feliratot)**

`tests/Unit/FormRendererTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Form\FormRenderer;
use PHPUnit\Framework\TestCase;

class FormRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_html__')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('wp_nonce_field')->justReturn('<input type="hidden" name="_wpnonce" value="x">');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-post.php');
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_form_with_legal_label_and_fields(): void
    {
        $html = (new FormRenderer())->renderForm([]);
        $this->assertStringContainsString('Elállás a szerződéstől', $html);
        $this->assertStringContainsString('name="consumer_name"', $html);
        $this->assertStringContainsString('name="contact_email"', $html);
        $this->assertStringContainsString('name="order_reference"', $html);
        $this->assertStringContainsString('name="intent_text"', $html);
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter FormRendererTest`
Expected: FAIL ("Class FormRenderer not found").

- [ ] **Step 3: Sablon — form.php**

`templates/form.php`:
```php
<?php /** @var array $errors */ $errors = $errors ?? []; ?>
<form class="elallas-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <h2 class="elallas-title"><?php echo esc_html__('Elállás a szerződéstől', 'elallasi-funkcio'); ?></h2>
  <input type="hidden" name="action" value="elallas_prepare">
  <?php echo wp_nonce_field('elallas_prepare', '_wpnonce', true, false); ?>

  <p>
    <label for="elallas_name"><?php echo esc_html__('Név', 'elallasi-funkcio'); ?></label>
    <input id="elallas_name" type="text" name="consumer_name" required>
    <?php if (isset($errors['consumer_name'])): ?><span class="elallas-error"><?php echo esc_html($errors['consumer_name']); ?></span><?php endif; ?>
  </p>
  <p>
    <label for="elallas_email"><?php echo esc_html__('E-mail (visszaigazoláshoz)', 'elallasi-funkcio'); ?></label>
    <input id="elallas_email" type="email" name="contact_email" required>
    <?php if (isset($errors['contact_email'])): ?><span class="elallas-error"><?php echo esc_html($errors['contact_email']); ?></span><?php endif; ?>
  </p>
  <p>
    <label for="elallas_ref"><?php echo esc_html__('Rendelés/szerződés azonosító', 'elallasi-funkcio'); ?></label>
    <input id="elallas_ref" type="text" name="order_reference" required>
    <?php if (isset($errors['order_reference'])): ?><span class="elallas-error"><?php echo esc_html($errors['order_reference']); ?></span><?php endif; ?>
  </p>
  <p>
    <label for="elallas_intent"><?php echo esc_html__('Elállási szándék', 'elallasi-funkcio'); ?></label>
    <textarea id="elallas_intent" name="intent_text" required></textarea>
    <?php if (isset($errors['intent_text'])): ?><span class="elallas-error"><?php echo esc_html($errors['intent_text']); ?></span><?php endif; ?>
  </p>
  <p><button type="submit"><?php echo esc_html__('Tovább', 'elallasi-funkcio'); ?></button></p>
</form>
```

- [ ] **Step 4: Sablon — confirm.php (2. lépcső, külön megerősítő funkció)**

`templates/confirm.php`:
```php
<?php /** @var array $data */ /** @var int $id */ /** @var string $token */ ?>
<form class="elallas-confirm" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
  <h2><?php echo esc_html__('Elállás véglegesítése', 'elallasi-funkcio'); ?></h2>
  <input type="hidden" name="action" value="elallas_confirm">
  <input type="hidden" name="id" value="<?php echo (int)$id; ?>">
  <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">
  <?php echo wp_nonce_field('elallas_confirm', '_wpnonce', true, false); ?>

  <p><?php echo esc_html__('Kérjük, erősítse meg, hogy véglegesen el kíván állni a szerződéstől.', 'elallasi-funkcio'); ?></p>
  <ul>
    <li><?php echo esc_html__('Név:', 'elallasi-funkcio'); ?> <?php echo esc_html($data['consumer_name']); ?></li>
    <li><?php echo esc_html__('Azonosító:', 'elallasi-funkcio'); ?> <?php echo esc_html($data['order_reference']); ?></li>
  </ul>
  <p><button type="submit" class="elallas-finalize"><?php echo esc_html__('Elállás véglegesítése', 'elallasi-funkcio'); ?></button></p>
</form>
```

- [ ] **Step 5: CSS**

`assets/css/form.css`:
```css
.elallas-form, .elallas-confirm { max-width: 640px; }
.elallas-form label { display:block; font-weight:600; margin-bottom:.25rem; }
.elallas-form input, .elallas-form textarea { width:100%; padding:.5rem; }
.elallas-error { color:#b00020; display:block; margin-top:.25rem; }
.elallas-finalize { font-weight:700; }
```

- [ ] **Step 6: FormRenderer implementáció**

`src/Form/FormRenderer.php`:
```php
<?php
namespace Elallas\Form;

class FormRenderer
{
    public function renderForm(array $errors = []): string
    {
        return $this->render('form.php', ['errors' => $errors]);
    }

    public function renderConfirm(array $data, int $id, string $token): string
    {
        return $this->render('confirm.php', ['data' => $data, 'id' => $id, 'token' => $token]);
    }

    private function render(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include defined('ELALLAS_DIR') ? ELALLAS_DIR . 'templates/' . $template
            : __DIR__ . '/../../templates/' . $template;
        return (string)ob_get_clean();
    }
}
```

- [ ] **Step 7: Teszt zöld**

Run: `vendor/bin/phpunit --filter FormRendererTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add src/Form/FormRenderer.php templates/form.php templates/confirm.php assets/css/form.css tests/Unit/FormRendererTest.php
git commit -m "feat: form renderer with two-step templates"
```

---

## Task 10: Wiring — Plugin::boot összekötés (admin-post, shortcode, hookok)

**Files:**
- Modify: `src/Plugin.php`
- Create: `src/Form/block.php`

Ez a task a komponenseket köti össze élő WP hookokra. Unit teszt helyett a `Plugin::boot()` regisztrációkat ellenőrizzük Brain Monkey `expectAdded`-del.

- [ ] **Step 1: Failing test a hook-regisztrációra**

`tests/Unit/PluginBootTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Elallas\Plugin;
use PHPUnit\Framework\TestCase;

class PluginBootTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('load_plugin_textdomain')->justReturn(true);
        Functions\when('plugin_basename')->returnArg(1);
        Functions\when('register_block_type')->justReturn(true);
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_boot_registers_admin_post_actions(): void
    {
        (new Plugin())->boot();
        $this->assertTrue(Actions\has('admin_post_nopriv_elallas_prepare'));
        $this->assertTrue(Actions\has('admin_post_nopriv_elallas_confirm'));
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter PluginBootTest`
Expected: FAIL (no actions registered).

- [ ] **Step 3: Plugin::boot bővítése**

`src/Plugin.php` (teljes csere):
```php
<?php
namespace Elallas;

use Elallas\Form\FormRenderer;
use Elallas\Licensing\LicenseClient;
use Elallas\Licensing\LicenseManager;
use Elallas\Pro\Mailer;
use Elallas\Pro\WooCommerceBridge;
use Elallas\Repository\WithdrawalRepository;
use Elallas\Submission\SubmissionHandler;
use Elallas\Submission\Validator;

class Plugin
{
    private FormRenderer $renderer;

    public function boot(): void
    {
        $this->renderer = new FormRenderer();

        add_action('init', [$this, 'loadTextdomain']);
        add_shortcode('elallasi_urlap', [$this, 'shortcode']);

        add_action('admin_post_nopriv_elallas_prepare', [$this, 'handlePrepare']);
        add_action('admin_post_elallas_prepare', [$this, 'handlePrepare']);
        add_action('admin_post_nopriv_elallas_confirm', [$this, 'handleConfirm']);
        add_action('admin_post_elallas_confirm', [$this, 'handleConfirm']);
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('elallasi-funkcio', false, dirname(plugin_basename(ELALLAS_FILE)) . '/languages');
    }

    public function shortcode($atts = []): string
    {
        return $this->renderer->renderForm();
    }

    private function licenseManager(): LicenseManager
    {
        $client = new LicenseClient((string)get_option('elallas_license_server', ''));
        return new LicenseManager(
            fn() => (string)get_option('elallas_license_token', ''),
            fn($k) => get_transient($k),
            fn($k, $v, $ttl) => set_transient($k, $v, $ttl),
            fn($token, $site) => $client->validate($token, $site)
        );
    }

    private function handler(): SubmissionHandler
    {
        global $wpdb;
        $repo = new WithdrawalRepository($wpdb);
        $proActive = $this->licenseManager()->isProActive();

        return new SubmissionHandler(
            new Validator(),
            $repo,
            fn() => current_time('mysql'),
            fn() => wp_generate_password(32, false),
            fn() => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
            function (int $id) use ($repo, $proActive) {
                if ($proActive) {
                    $this->sendReceiptFor($repo, $id);
                }
            }
        );
    }

    public function handlePrepare(): void
    {
        check_admin_referer('elallas_prepare');
        $result = $this->handler()->prepare($_POST);
        if (!$result['ok']) {
            // hiba esetén az űrlap újrarajzolása a hibákkal — átadás transient útján
            set_transient('elallas_errors_' . $this->clientKey(), $result['errors'], 60);
            wp_safe_redirect(wp_get_referer() ?: home_url());
            exit;
        }
        // megerősítő képernyő megjelenítése
        set_transient('elallas_pending_' . $this->clientKey(), [
            'id' => $result['id'], 'token' => $result['confirmation_token'], 'data' => $_POST,
        ], 900);
        wp_safe_redirect(add_query_arg('elallas_step', 'confirm', wp_get_referer() ?: home_url()));
        exit;
    }

    public function handleConfirm(): void
    {
        check_admin_referer('elallas_confirm');
        $id = (int)($_POST['id'] ?? 0);
        $this->handler()->confirm($id);
        wp_safe_redirect(add_query_arg('elallas_step', 'done', wp_get_referer() ?: home_url()));
        exit;
    }

    private function sendReceiptFor(WithdrawalRepository $repo, int $id): void
    {
        $pending = get_transient('elallas_pending_' . $this->clientKey());
        $data = is_array($pending) ? ($pending['data'] ?? []) : [];
        $mailer = new Mailer(fn() => current_time('mysql'));
        if ($mailer->sendReceipt($data)) {
            $repo->markReceiptSent($id, current_time('mysql'));
        }

        if ((new WooCommerceBridge())->isAvailable()) {
            // opcionális rendelés-összekötés a következő iterációban bővíthető
        }
    }

    private function clientKey(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter PluginBootTest`
Expected: PASS.

- [ ] **Step 5: Gutenberg blokk (szerveroldali render-callback, az űrlapot adja vissza)**

`src/Form/block.php`:
```php
<?php
namespace Elallas\Form;

add_action('init', static function () {
    if (!function_exists('register_block_type')) { return; }
    register_block_type('elallas/urlap', [
        'render_callback' => static function () {
            return (new FormRenderer())->renderForm();
        },
    ]);
});
```

Töltsd be a fő pluginban: `elallasi-funkcio.php` végére add: `require_once ELALLAS_DIR . 'src/Form/block.php';`

- [ ] **Step 6: Teljes tesztsuite**

Run: `vendor/bin/phpunit`
Expected: PASS (minden eddigi teszt).

- [ ] **Step 7: Commit**

```bash
git add src/Plugin.php src/Form/block.php elallasi-funkcio.php tests/Unit/PluginBootTest.php
git commit -m "feat: wire components into WP hooks (admin-post, shortcode, block)"
```

---

## Task 11: Admin felület (elállások lista + beállítások/token)

**Files:**
- Create: `src/Admin/AdminPage.php`
- Create: `src/Admin/SettingsPage.php`
- Modify: `src/Plugin.php` (admin menü regisztráció)
- Test: `tests/Unit/SettingsPageTest.php`

- [ ] **Step 1: Failing test a beállítások mentésére (token sanitizálás)**

`tests/Unit/SettingsPageTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Admin\SettingsPage;
use PHPUnit\Framework\TestCase;

class SettingsPageTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); Monkey\setUp(); Functions\when('sanitize_text_field')->returnArg(1); }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_sanitize_token_trims_and_strips(): void
    {
        $this->assertSame('ABC123', (new SettingsPage())->sanitizeToken('  ABC123 '));
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter SettingsPageTest`
Expected: FAIL ("Class SettingsPage not found").

- [ ] **Step 3: SettingsPage implementáció**

`src/Admin/SettingsPage.php`:
```php
<?php
namespace Elallas\Admin;

class SettingsPage
{
    public function register(): void
    {
        register_setting('elallas', 'elallas_license_token', ['sanitize_callback' => [$this, 'sanitizeToken']]);
        register_setting('elallas', 'elallas_license_server', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('elallas', 'elallas_admin_email', ['sanitize_callback' => 'sanitize_email']);
    }

    public function sanitizeToken($value): string
    {
        return trim(sanitize_text_field((string)$value));
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Elállási funkció – Beállítások', 'elallasi-funkcio') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('elallas');
        echo '<table class="form-table">';
        printf('<tr><th>%s</th><td><input type="text" name="elallas_license_token" value="%s" class="regular-text"></td></tr>',
            esc_html__('Licenc token', 'elallasi-funkcio'), esc_attr(get_option('elallas_license_token', '')));
        printf('<tr><th>%s</th><td><input type="url" name="elallas_license_server" value="%s" class="regular-text"></td></tr>',
            esc_html__('Licenc-szerver URL', 'elallasi-funkcio'), esc_attr(get_option('elallas_license_server', '')));
        printf('<tr><th>%s</th><td><input type="email" name="elallas_admin_email" value="%s" class="regular-text"></td></tr>',
            esc_html__('Értesítendő admin e-mail', 'elallasi-funkcio'), esc_attr(get_option('elallas_admin_email', '')));
        echo '</table>';
        submit_button();
        echo '</form></div>';
    }
}
```

- [ ] **Step 4: AdminPage (lista) implementáció**

`src/Admin/AdminPage.php`:
```php
<?php
namespace Elallas\Admin;

use Elallas\Repository\WithdrawalRepository;

class AdminPage
{
    private WithdrawalRepository $repo;

    public function __construct(WithdrawalRepository $repo)
    {
        $this->repo = $repo;
    }

    public function render(): void
    {
        global $wpdb;
        $table = $this->repo->tableName();
        $rows = $wpdb->get_results("SELECT id, created_at, status, consumer_name, contact_email, order_reference, confirmed_at FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A) ?: [];

        echo '<div class="wrap"><h1>' . esc_html__('Beérkezett elállások', 'elallasi-funkcio') . '</h1>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['Dátum','Állapot','Név','E-mail','Azonosító','Véglegesítve'] as $h) {
            echo '<th>' . esc_html__($h, 'elallasi-funkcio') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html($r['created_at']) . '</td>';
            echo '<td>' . esc_html($r['status']) . '</td>';
            echo '<td>' . esc_html($r['consumer_name']) . '</td>';
            echo '<td>' . esc_html($r['contact_email']) . '</td>';
            echo '<td>' . esc_html($r['order_reference']) . '</td>';
            echo '<td>' . esc_html((string)$r['confirmed_at']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}
```

- [ ] **Step 5: Admin menü a Plugin::boot-ba**

`src/Plugin.php` — a `boot()` végére add:
```php
        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_init', [$this, 'registerSettings']);
        }
```
És új metódusok a Plugin osztályba:
```php
    public function registerAdminMenu(): void
    {
        global $wpdb;
        $list = new \Elallas\Admin\AdminPage(new \Elallas\Repository\WithdrawalRepository($wpdb));
        $settings = new \Elallas\Admin\SettingsPage();

        add_menu_page(
            __('Elállások', 'elallasi-funkcio'),
            __('Elállások', 'elallasi-funkcio'),
            'manage_options', 'elallas', [$list, 'render'], 'dashicons-undo'
        );
        add_submenu_page('elallas', __('Beállítások', 'elallasi-funkcio'),
            __('Beállítások', 'elallasi-funkcio'), 'manage_options', 'elallas-settings', [$settings, 'render']);
    }

    public function registerSettings(): void
    {
        (new \Elallas\Admin\SettingsPage())->register();
    }
```

- [ ] **Step 6: Teszt zöld + teljes suite**

Run: `vendor/bin/phpunit`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Admin/ src/Plugin.php tests/Unit/SettingsPageTest.php
git commit -m "feat: admin list + settings page (license token)"
```

---

## Task 12: Updater (token-hitelesített frissítés) + i18n + uninstall

**Files:**
- Create: `src/Licensing/Updater.php`
- Create: `uninstall.php`
- Create: `languages/elallasi-funkcio.pot`
- Test: `tests/Unit/UpdaterTest.php`

- [ ] **Step 1: Failing test az update-transient módosítására**

`tests/Unit/UpdaterTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Elallas\Licensing\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
    public function test_injects_update_when_remote_version_newer(): void
    {
        $updater = new Updater(
            pluginBasename: 'elallasi-funkcio/elallasi-funkcio.php',
            currentVersion: '0.1.0',
            remoteCheck: fn() => ['latest_version' => '0.2.0', 'package_url' => 'https://ex/p.zip']
        );

        $transient = (object)['response' => []];
        $out = $updater->filterUpdate($transient);

        $this->assertArrayHasKey('elallasi-funkcio/elallasi-funkcio.php', $out->response);
        $this->assertSame('0.2.0', $out->response['elallasi-funkcio/elallasi-funkcio.php']->new_version);
    }

    public function test_no_update_when_same_version(): void
    {
        $updater = new Updater(
            pluginBasename: 'elallasi-funkcio/elallasi-funkcio.php',
            currentVersion: '0.2.0',
            remoteCheck: fn() => ['latest_version' => '0.2.0', 'package_url' => 'https://ex/p.zip']
        );
        $transient = (object)['response' => []];
        $out = $updater->filterUpdate($transient);
        $this->assertSame([], $out->response);
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter UpdaterTest`
Expected: FAIL ("Class Updater not found").

- [ ] **Step 3: Updater implementáció**

`src/Licensing/Updater.php`:
```php
<?php
namespace Elallas\Licensing;

class Updater
{
    private string $pluginBasename;
    private string $currentVersion;
    /** @var callable */ private $remoteCheck;

    public function __construct(string $pluginBasename, string $currentVersion, callable $remoteCheck)
    {
        $this->pluginBasename = $pluginBasename;
        $this->currentVersion = $currentVersion;
        $this->remoteCheck = $remoteCheck;
    }

    /** @param object $transient */
    public function filterUpdate($transient)
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        $remote = ($this->remoteCheck)();
        $latest = (string)($remote['latest_version'] ?? '');
        if ($latest === '' || version_compare($latest, $this->currentVersion, '<=')) {
            return $transient;
        }

        $transient->response[$this->pluginBasename] = (object)[
            'slug' => 'elallasi-funkcio',
            'plugin' => $this->pluginBasename,
            'new_version' => $latest,
            'package' => (string)($remote['package_url'] ?? ''),
        ];
        return $transient;
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter UpdaterTest`
Expected: PASS.

- [ ] **Step 5: uninstall.php**

`uninstall.php`:
```php
<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

global $wpdb;
$table = $wpdb->prefix . 'elallas_requests';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

foreach (['elallas_license_token','elallas_license_server','elallas_admin_email','elallas_db_version'] as $opt) {
    delete_option($opt);
}
```

- [ ] **Step 6: .pot generálás**

Run: `wp i18n make-pot . languages/elallasi-funkcio.pot --domain=elallasi-funkcio`
Expected: `languages/elallasi-funkcio.pot` létrejön a sztringekkel.
(Ha nincs WP-CLI: hozd létre manuálisan a fejléccel és a `__()`-ben szereplő sztringekkel.)

- [ ] **Step 7: Updater bekötése a Plugin::boot-ba**

`src/Plugin.php` — a `boot()`-ba add:
```php
        add_filter('pre_set_site_transient_update_plugins', function ($transient) {
            $server = (string)get_option('elallas_license_server', '');
            $token = (string)get_option('elallas_license_token', '');
            if ($server === '' || $token === '') { return $transient; }
            $updater = new \Elallas\Licensing\Updater(
                plugin_basename(ELALLAS_FILE),
                ELALLAS_VERSION,
                function () use ($server, $token) {
                    $resp = wp_remote_get(rtrim($server, '/') . '/update-check?token=' . rawurlencode($token) . '&current_version=' . ELALLAS_VERSION, ['timeout' => 10]);
                    if (is_wp_error($resp)) { return []; }
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    return is_array($data) ? $data : [];
                }
            );
            return $updater->filterUpdate($transient);
        });
```

- [ ] **Step 8: Teljes suite**

Run: `vendor/bin/phpunit`
Expected: PASS (minden teszt).

- [ ] **Step 9: Commit**

```bash
git add src/Licensing/Updater.php uninstall.php languages/ src/Plugin.php tests/Unit/UpdaterTest.php
git commit -m "feat: token-authenticated updater + uninstall cleanup + i18n pot"
```

---

## Task 13: GDPR exporter/eraser

**Files:**
- Create: `src/Privacy/GdprExporter.php`
- Modify: `src/Plugin.php` (hook regisztráció)
- Test: `tests/Unit/GdprExporterTest.php`

- [ ] **Step 1: Failing test**

`tests/Unit/GdprExporterTest.php`:
```php
<?php
namespace Elallas\Tests\Unit;

use Elallas\Privacy\GdprExporter;
use PHPUnit\Framework\TestCase;

class GdprExporterTest extends TestCase
{
    public function test_export_returns_items_for_matching_email(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $output) {
                return [['id' => 1, 'consumer_name' => 'A', 'order_reference' => 'X', 'created_at' => 'now']];
            }
        };
        $exporter = new GdprExporter($wpdb);
        $result = $exporter->export('a@b.hu', 1);
        $this->assertNotEmpty($result['data']);
        $this->assertTrue($result['done']);
    }
}
```

- [ ] **Step 2: Teszt megbukik**

Run: `vendor/bin/phpunit --filter GdprExporterTest`
Expected: FAIL ("Class GdprExporter not found").

- [ ] **Step 3: GdprExporter implementáció**

`src/Privacy/GdprExporter.php`:
```php
<?php
namespace Elallas\Privacy;

use Elallas\Activator;

class GdprExporter
{
    /** @var \wpdb|object */ private $wpdb;

    public function __construct($wpdb) { $this->wpdb = $wpdb; }

    private function table(): string { return $this->wpdb->prefix . Activator::TABLE; }

    public function export(string $email, int $page = 1): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT id, consumer_name, order_reference, created_at FROM {$this->table()} WHERE contact_email = %s", $email),
            ARRAY_A
        ) ?: [];

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'group_id' => 'elallas_requests',
                'group_label' => __('Elállási kérelmek', 'elallasi-funkcio'),
                'item_id' => 'elallas-' . $r['id'],
                'data' => [
                    ['name' => __('Név', 'elallasi-funkcio'), 'value' => $r['consumer_name']],
                    ['name' => __('Azonosító', 'elallasi-funkcio'), 'value' => $r['order_reference']],
                    ['name' => __('Dátum', 'elallasi-funkcio'), 'value' => $r['created_at']],
                ],
            ];
        }
        return ['data' => $items, 'done' => true];
    }

    public function erase(string $email, int $page = 1): array
    {
        $removed = $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$this->table()} WHERE contact_email = %s", $email)
        );
        return ['items_removed' => (bool)$removed, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
```

- [ ] **Step 4: Teszt zöld**

Run: `vendor/bin/phpunit --filter GdprExporterTest`
Expected: PASS.

- [ ] **Step 5: Hook regisztráció a Plugin::boot-ba**

`src/Plugin.php` — a `boot()`-ba add:
```php
        add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
            global $wpdb;
            $ex = new \Elallas\Privacy\GdprExporter($wpdb);
            $exporters['elallasi-funkcio'] = [
                'exporter_friendly_name' => __('Elállási funkció', 'elallasi-funkcio'),
                'callback' => fn($email, $page) => $ex->export($email, $page),
            ];
            return $exporters;
        });
        add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
            global $wpdb;
            $ex = new \Elallas\Privacy\GdprExporter($wpdb);
            $erasers['elallasi-funkcio'] = [
                'eraser_friendly_name' => __('Elállási funkció', 'elallasi-funkcio'),
                'callback' => fn($email, $page) => $ex->erase($email, $page),
            ];
            return $erasers;
        });
```

- [ ] **Step 6: Teljes suite**

Run: `vendor/bin/phpunit`
Expected: PASS (minden teszt zöld).

- [ ] **Step 7: Commit**

```bash
git add src/Privacy/GdprExporter.php src/Plugin.php tests/Unit/GdprExporterTest.php
git commit -m "feat: GDPR exporter and eraser"
```

---

## Záró ellenőrzés (a teljes terv lefutása után)

- [ ] `vendor/bin/phpunit` — minden teszt zöld.
- [ ] Kézi füstteszt egy lokális WP-n: plugin aktiválás → tábla létrejön → `[elallasi_urlap]` shortcode megjelenik → beküldés → megerősítő képernyő → véglegesítés → rekord `confirmed` → token beállítása után visszaigazoló e-mail megérkezik.
- [ ] WooCommerce aktív állapotban: rendelésazonosító alapján e-mail előtöltés működik.
- [ ] README/telepítési útmutató megírása (licenc token, licenc-szerver URL beállítása).

## Spec-lefedettség (self-review jegyzet)

| Spec követelmény | Task |
|------------------|------|
| Form + „Elállás a szerződéstől" felirat | Task 9 |
| Kétlépcsős külön megerősítő funkció | Task 6, 9, 10 |
| Received → confirmed rekord, DB-tábla | Task 2, 4 |
| Pro visszaigazoló e-mail (tartós adathordozó) | Task 7, 10 |
| Pro WooCommerce integráció | Task 8 (+10 hook) |
| Licenc token, cache, grace period | Task 5 |
| Token-hitelesített frissítés | Task 12 |
| Admin lista + beállítások | Task 11 |
| i18n (.pot, hu/en) | Task 12 |
| GDPR export/eraser | Task 13 |
| Uninstall cleanup | Task 12 |
