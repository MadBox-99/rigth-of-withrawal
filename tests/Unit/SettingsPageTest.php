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
