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

    public function test_mark_receipt_sent_updates_timestamp(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $lastUpdate = [];
            public function update($t, $data, $where) { $this->lastUpdate = [$t, $data, $where]; return 1; }
        };
        $repo = new WithdrawalRepository($wpdb);
        $ok = $repo->markReceiptSent(42, '2026-06-10 12:10:00');

        $this->assertTrue($ok);
        $this->assertSame('2026-06-10 12:10:00', $wpdb->lastUpdate[1]['receipt_sent_at']);
        $this->assertArrayNotHasKey('status', $wpdb->lastUpdate[1]);
        $this->assertSame(['id' => 42], $wpdb->lastUpdate[2]);
    }

    public function test_insert_returns_zero_on_wpdb_failure(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 99;
            public function insert($table, $data) { return false; }
        };
        $repo = new WithdrawalRepository($wpdb);
        $this->assertSame(0, $repo->insert(['x' => 'y']));
    }

    public function test_link_order_updates_wc_order_id(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public array $lastUpdate = [];
            public function update($t, $data, $where) { $this->lastUpdate = [$t, $data, $where]; return 1; }
        };
        $repo = new WithdrawalRepository($wpdb);
        $ok = $repo->linkOrder(7, 1001);

        $this->assertTrue($ok);
        $this->assertSame(1001, $wpdb->lastUpdate[1]['wc_order_id']);
        $this->assertSame(['id' => 7], $wpdb->lastUpdate[2]);
    }
}
