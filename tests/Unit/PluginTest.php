<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('load_plugin_textdomain')->justReturn(true);
        Functions\when('plugin_basename')->returnArg(1);
        Functions\when('register_block_type')->justReturn(true);
        Functions\when('is_admin')->justReturn(false);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_plugin_class_boots_without_error(): void
    {
        $this->expectNotToPerformAssertions();
        (new Plugin())->boot();
    }
}
