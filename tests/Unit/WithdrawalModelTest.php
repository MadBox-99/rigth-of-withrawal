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
        $this->assertSame('A', $row['consumer_name']);
        $this->assertSame('a@b.hu', $row['contact_email']);
        $this->assertNull($row['wc_order_id']);
    }
}
