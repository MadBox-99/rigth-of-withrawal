<?php
namespace Elallas\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Elallas\Form\FormRenderer;
use PHPUnit\Framework\TestCase;

class FormRendererTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg(1);
        Functions\when('esc_attr')->returnArg(1);
        Functions\when('esc_html')->returnArg(1);
        Functions\when('esc_url')->returnArg(1);
        Functions\when('wp_nonce_field')->justReturn('<input type="hidden" name="_wpnonce" value="x">');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-post.php');
    }
    protected function tearDown(): void { Monkey\tearDown(); parent::tearDown(); }

    public function test_renders_form_with_legal_label_and_fields(): void
    {
        $html = (new FormRenderer())->renderForm([]);
        $this->assertStringContainsString('Elállás a szerződéstől', $html);
        $this->assertStringContainsString('name="consumer_name"', $html);
        $this->assertStringContainsString('name="contact_email"', $html);
        $this->assertStringContainsString('name="order_reference"', $html);
        $this->assertStringContainsString('name="intent_text"', $html);
    }
}
