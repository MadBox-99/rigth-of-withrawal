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
