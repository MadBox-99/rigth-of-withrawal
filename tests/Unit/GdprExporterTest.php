<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Privacy\GdprExporter;
use PHPUnit\Framework\TestCase;

class GdprExporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_export_returns_items_for_matching_email(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $output) {
                return [['id' => 1, 'consumer_name' => 'A', 'contact_email' => 'a@b.hu', 'order_reference' => 'X', 'created_at' => 'now']];
            }
        };
        $exporter = new GdprExporter($wpdb);
        $result = $exporter->export('a@b.hu', 1);
        $this->assertNotEmpty($result['data']);
        $this->assertTrue($result['done']);
        $values = array_column($result['data'][0]['data'], 'value');
        $this->assertContains('a@b.hu', $values);
    }

    public function test_export_returns_empty_data_when_no_rows(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function get_results($q, $output) { return []; }
        };
        $exporter = new GdprExporter($wpdb);
        $result = $exporter->export('nobody@example.com', 1);
        $this->assertEmpty($result['data']);
        $this->assertTrue($result['done']);
    }

    public function test_erase_returns_items_removed_true_when_rows_deleted(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function query($q) { return 1; }
        };
        $exporter = new GdprExporter($wpdb);
        $result = $exporter->erase('a@b.hu', 1);
        $this->assertTrue($result['items_removed']);
        $this->assertFalse($result['items_retained']);
        $this->assertEmpty($result['messages']);
        $this->assertTrue($result['done']);
    }

    public function test_erase_returns_items_removed_false_when_no_rows(): void
    {
        $wpdb = new class {
            public string $prefix = 'wp_';
            public function prepare($q, ...$a) { return $q; }
            public function query($q) { return 0; }
        };
        $exporter = new GdprExporter($wpdb);
        $result = $exporter->erase('nobody@example.com', 1);
        $this->assertFalse($result['items_removed']);
        $this->assertTrue($result['done']);
    }
}
