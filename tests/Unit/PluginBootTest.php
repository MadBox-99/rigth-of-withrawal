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
        Functions\when('is_admin')->justReturn(false);
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_boot_registers_admin_post_actions(): void
    {
        (new Plugin())->boot();
        $this->assertTrue(Actions\has('admin_post_nopriv_elallas_prepare'));
        $this->assertTrue(Actions\has('admin_post_nopriv_elallas_confirm'));
    }

    public function test_resolve_confirmation_returns_id_when_token_matches(): void
    {
        $plugin = new Plugin();
        $pending = ['id' => 42, 'token' => 'good-token', 'data' => []];
        $this->assertSame(42, $plugin->resolveConfirmationId($pending, 'good-token'));
    }

    public function test_resolve_confirmation_rejects_wrong_token(): void
    {
        $plugin = new Plugin();
        $pending = ['id' => 42, 'token' => 'good-token', 'data' => []];
        $this->assertNull($plugin->resolveConfirmationId($pending, 'wrong-token'));
    }

    public function test_resolve_confirmation_rejects_missing_pending(): void
    {
        $plugin = new Plugin();
        $this->assertNull($plugin->resolveConfirmationId(false, 'any'));
        $this->assertNull($plugin->resolveConfirmationId(['id' => 1], 'any')); // no token
    }

    public function test_render_step_shows_confirm_screen_when_pending_exists(): void
    {
        $_GET['elallas_step'] = 'confirm';
        Functions\when('sanitize_key')->returnArg(1);
        Functions\when('__')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-post.php');
        Functions\when('wp_nonce_field')->justReturn('<input type="hidden" name="_wpnonce" value="x">');
        Functions\when('get_transient')->justReturn([
            'id' => 42, 'token' => 'tok-xyz',
            'data' => ['consumer_name' => 'Teszt Elek', 'order_reference' => 'WC-1001'],
        ]);

        $plugin = new Plugin();
        $plugin->boot(); // sets up $this->renderer
        $html = $plugin->renderStep();

        unset($_GET['elallas_step']);

        $this->assertStringContainsString('Elállás véglegesítése', $html);
        $this->assertStringContainsString('value="42"', $html);
        $this->assertStringContainsString('value="tok-xyz"', $html);
        $this->assertStringContainsString('Teszt Elek', $html);
    }

    public function test_render_step_shows_form_by_default(): void
    {
        unset($_GET['elallas_step']);
        Functions\when('sanitize_key')->returnArg(1);
        Functions\when('__')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-post.php');
        Functions\when('wp_nonce_field')->justReturn('<input>');
        Functions\when('get_transient')->justReturn(false);

        $plugin = new Plugin();
        $plugin->boot();
        $html = $plugin->renderStep();

        $this->assertStringContainsString('Elállás a szerződéstől', $html);
        $this->assertStringContainsString('name="consumer_name"', $html);
    }
}
