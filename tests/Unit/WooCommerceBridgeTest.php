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

    public function test_returns_null_when_order_not_found(): void
    {
        Functions\when('function_exists')->alias(fn($n) => $n === 'wc_get_order');
        Functions\when('wc_get_order')->justReturn(false);

        $bridge = new WooCommerceBridge();
        $this->assertNull($bridge->findOrderEmail('9999'));
    }

    public function test_returns_null_when_reference_has_no_digits(): void
    {
        Functions\when('function_exists')->alias(fn($n) => $n === 'wc_get_order');

        $bridge = new WooCommerceBridge();
        $this->assertNull($bridge->findOrderEmail('NO-ORDER'));
    }

    public function test_find_order_id_returns_null_when_woocommerce_inactive(): void
    {
        Functions\when('function_exists')->justReturn(false);
        $bridge = new WooCommerceBridge();
        $this->assertNull($bridge->findOrderId('WC-1001'));
    }

    public function test_find_order_id_returns_id_when_order_found(): void
    {
        Functions\when('function_exists')->alias(fn($n) => $n === 'wc_get_order');
        $order = new class { public function get_id() { return 1001; } };
        Functions\when('wc_get_order')->alias(fn($id) => $id === '1001' ? $order : false);

        $bridge = new WooCommerceBridge();
        $this->assertSame(1001, $bridge->findOrderId('ORD-1001'));
    }
}
