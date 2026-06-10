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
        Functions\when('is_email')->alias(fn($e) => filter_var($e, FILTER_VALIDATE_EMAIL) !== false ? $e : false);
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

    public function test_whitespace_only_name_is_rejected(): void
    {
        $errors = (new Validator())->validate([
            'consumer_name' => '   ',
            'contact_email' => 'elek@example.com',
            'order_reference' => 'WC-1001',
            'intent_text' => 'Elállok.',
        ]);
        $this->assertArrayHasKey('consumer_name', $errors);
    }

    public function test_missing_order_reference_is_rejected(): void
    {
        $errors = (new Validator())->validate([
            'consumer_name' => 'Teszt Elek',
            'contact_email' => 'elek@example.com',
            'order_reference' => '',
            'intent_text' => 'Elállok.',
        ]);
        $this->assertArrayHasKey('order_reference', $errors);
        $this->assertArrayNotHasKey('consumer_name', $errors);
    }

    public function test_missing_intent_text_is_rejected(): void
    {
        $errors = (new Validator())->validate([
            'consumer_name' => 'Teszt Elek',
            'contact_email' => 'elek@example.com',
            'order_reference' => 'WC-1001',
            'intent_text' => '',
        ]);
        $this->assertArrayHasKey('intent_text', $errors);
    }

    public function test_empty_array_reports_all_required_fields(): void
    {
        $errors = (new Validator())->validate([]);
        $this->assertArrayHasKey('consumer_name', $errors);
        $this->assertArrayHasKey('contact_email', $errors);
        $this->assertArrayHasKey('order_reference', $errors);
        $this->assertArrayHasKey('intent_text', $errors);
    }
}
