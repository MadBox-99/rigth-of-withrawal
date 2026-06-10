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
