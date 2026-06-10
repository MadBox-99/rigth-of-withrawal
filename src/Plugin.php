<?php
namespace Elallas;

use Elallas\Form\FormRenderer;
use Elallas\Licensing\LicenseClient;
use Elallas\Licensing\LicenseManager;
use Elallas\Pro\Mailer;
use Elallas\Pro\WooCommerceBridge;
use Elallas\Repository\WithdrawalRepository;
use Elallas\Submission\SubmissionHandler;
use Elallas\Submission\Validator;

class Plugin
{
    private FormRenderer $renderer;

    public function boot(): void
    {
        $this->renderer = new FormRenderer();

        add_action('init', [$this, 'loadTextdomain']);
        add_shortcode('elallasi_urlap', [$this, 'shortcode']);

        add_action('admin_post_nopriv_elallas_prepare', [$this, 'handlePrepare']);
        add_action('admin_post_elallas_prepare', [$this, 'handlePrepare']);
        add_action('admin_post_nopriv_elallas_confirm', [$this, 'handleConfirm']);
        add_action('admin_post_elallas_confirm', [$this, 'handleConfirm']);
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('elallasi-funkcio', false, dirname(plugin_basename(ELALLAS_FILE)) . '/languages');
    }

    public function shortcode($atts = []): string
    {
        return $this->renderer->renderForm();
    }

    private function licenseManager(): LicenseManager
    {
        $client = new LicenseClient((string)get_option('elallas_license_server', ''));
        return new LicenseManager(
            fn() => (string)get_option('elallas_license_token', ''),
            fn($k) => get_transient($k),
            fn($k, $v, $ttl) => set_transient($k, $v, $ttl),
            fn($token, $site) => $client->validate($token, $site)
        );
    }

    private function handler(): SubmissionHandler
    {
        global $wpdb;
        $repo = new WithdrawalRepository($wpdb);
        $proActive = $this->licenseManager()->isProActive();

        return new SubmissionHandler(
            new Validator(),
            $repo,
            fn() => current_time('mysql'),
            fn() => wp_generate_password(32, false),
            fn() => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
            function (int $id) use ($repo, $proActive) {
                if ($proActive) {
                    $this->sendReceiptFor($repo, $id);
                }
            }
        );
    }

    public function handlePrepare(): void
    {
        check_admin_referer('elallas_prepare');
        $result = $this->handler()->prepare($_POST);
        if (!$result['ok']) {
            set_transient('elallas_errors_' . $this->clientKey(), $result['errors'], 60);
            wp_safe_redirect(wp_get_referer() ?: home_url());
            exit;
        }
        set_transient('elallas_pending_' . $this->clientKey(), [
            'id' => $result['id'], 'token' => $result['confirmation_token'], 'data' => $_POST,
        ], 900);
        wp_safe_redirect(add_query_arg('elallas_step', 'confirm', wp_get_referer() ?: home_url()));
        exit;
    }

    public function handleConfirm(): void
    {
        check_admin_referer('elallas_confirm');
        $id = (int)($_POST['id'] ?? 0);
        $this->handler()->confirm($id);
        wp_safe_redirect(add_query_arg('elallas_step', 'done', wp_get_referer() ?: home_url()));
        exit;
    }

    private function sendReceiptFor(WithdrawalRepository $repo, int $id): void
    {
        $pending = get_transient('elallas_pending_' . $this->clientKey());
        $data = is_array($pending) ? ($pending['data'] ?? []) : [];
        $mailer = new Mailer(fn() => current_time('mysql'));
        if ($mailer->sendReceipt($data)) {
            $repo->markReceiptSent($id, current_time('mysql'));
        }

        if ((new WooCommerceBridge())->isAvailable()) {
            // opcionális rendelés-összekötés a következő iterációban bővíthető
        }
    }

    private function clientKey(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }
}
