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

    public function test_renders_confirm_with_heading_id_and_token(): void
    {
        $data = ['consumer_name' => 'Teszt Elek', 'order_reference' => 'WC-1001'];
        $html = (new FormRenderer())->renderConfirm($data, 42, 'tok-abc');

        $this->assertStringContainsString('Elállás véglegesítése', $html);
        $this->assertStringContainsString('value="42"', $html);
        $this->assertStringContainsString('value="tok-abc"', $html);
        $this->assertStringContainsString('_wpnonce', $html);
        $this->assertStringContainsString('Teszt Elek', $html);
    }

    public function test_renders_form_with_field_errors(): void
    {
        $html = (new FormRenderer())->renderForm([
            'consumer_name' => 'A név megadása kötelező.',
            'contact_email' => 'Érvényes e-mail cím szükséges.',
        ]);

        $this->assertSame(2, substr_count($html, 'class="elallas-error"'));
        $this->assertStringContainsString('A név megadása kötelező.', $html);
        $this->assertStringContainsString('Érvényes e-mail cím szükséges.', $html);
    }
}
