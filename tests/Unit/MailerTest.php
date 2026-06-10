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

    public function test_returns_false_when_wp_mail_fails(): void
    {
        Functions\when('wp_mail')->justReturn(false);

        $mailer = new Mailer(fn() => '2026-06-10 12:00:00');
        $ok = $mailer->sendReceipt([
            'contact_email' => 'elek@example.com',
            'consumer_name' => 'Teszt Elek',
            'order_reference' => 'WC-1001',
            'intent_text' => 'Elállok.',
        ]);

        $this->assertFalse($ok);
    }
}
