<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Elallas\Plugin;
use PHPUnit\Framework\TestCase;

class PluginBootTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('load_plugin_textdomain')->justReturn(true);
        Functions\when('plugin_basename')->returnArg(1);
        Functions\when('register_block_type')->justReturn(true);
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_boot_registers_admin_post_actions(): void
    {
        (new Plugin())->boot();
        $this->assertTrue(Actions\has('admin_post_nopriv_elallas_prepare'));
        $this->assertTrue(Actions\has('admin_post_nopriv_elallas_confirm'));
    }
}
