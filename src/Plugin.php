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
    private ?bool $proActive = null;

    public function boot(): void
    {
        $this->renderer = new FormRenderer();

        require_once ELALLAS_DIR . 'src/Form/block.php';
        \Elallas\Form\register_block();

        add_action('init', [$this, 'loadTextdomain']);
        add_shortcode('elallasi_urlap', [$this, 'shortcode']);

        add_action('admin_post_nopriv_elallas_prepare', [$this, 'handlePrepare']);
        add_action('admin_post_elallas_prepare', [$this, 'handlePrepare']);
        add_action('admin_post_nopriv_elallas_confirm', [$this, 'handleConfirm']);
        add_action('admin_post_elallas_confirm', [$this, 'handleConfirm']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu']);
            add_action('admin_init', [$this, 'registerSettings']);
        }
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

    private function isProActive(): bool
    {
        if ($this->proActive === null) {
            $this->proActive = $this->licenseManager()->isProActive();
        }
        return $this->proActive;
    }

    private function handler(array $confirmData = []): SubmissionHandler
    {
        global $wpdb;
        $repo = new WithdrawalRepository($wpdb);
        $proActive = $this->isProActive();

        return new SubmissionHandler(
            new Validator(),
            $repo,
            fn() => current_time('mysql'),
            fn() => wp_generate_password(32, false),
            fn() => hash('sha256', $_SERVER['REMOTE_ADDR'] ?? ''),
            function (int $id) use ($repo, $proActive, $confirmData) {
                if ($proActive) {
                    $this->sendReceiptFor($repo, $id, $confirmData);
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

    /**
     * Eldönti, melyik rekord erősíthető meg. A tokent időzítésbiztosan
     * ellenőrzi a szerveroldali pending adathoz képest. null = nincs jogosultság.
     */
    public function resolveConfirmationId($pending, string $postedToken): ?int
    {
        if (!is_array($pending) || empty($pending['id']) || empty($pending['token'])) {
            return null;
        }
        if (!hash_equals((string)$pending['token'], $postedToken)) {
            return null;
        }
        return (int)$pending['id'];
    }

    public function handleConfirm(): void
    {
        check_admin_referer('elallas_confirm');

        $key = 'elallas_pending_' . $this->clientKey();
        $pending = get_transient($key);
        $postedToken = sanitize_text_field($_POST['token'] ?? '');

        $id = $this->resolveConfirmationId($pending, $postedToken);
        if ($id === null) {
            wp_safe_redirect(add_query_arg('elallas_step', 'error', wp_get_referer() ?: home_url()));
            exit;
        }

        $data = is_array($pending) ? ($pending['data'] ?? []) : [];
        $this->handler($data)->confirm($id);
        delete_transient($key);

        wp_safe_redirect(add_query_arg('elallas_step', 'done', wp_get_referer() ?: home_url()));
        exit;
    }

    private function sendReceiptFor(WithdrawalRepository $repo, int $id, array $data): void
    {
        $mailer = new Mailer(fn() => current_time('mysql'));
        if ($mailer->sendReceipt($data)) {
            $repo->markReceiptSent($id, current_time('mysql'));
        }
    }

    private function clientKey(): string
    {
        return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    public function registerAdminMenu(): void
    {
        global $wpdb;
        $list = new \Elallas\Admin\AdminPage(new \Elallas\Repository\WithdrawalRepository($wpdb));
        $settings = new \Elallas\Admin\SettingsPage();

        add_menu_page(
            __('Elállások', 'elallasi-funkcio'),
            __('Elállások', 'elallasi-funkcio'),
            'manage_options', 'elallas', [$list, 'render'], 'dashicons-undo'
        );
        add_submenu_page('elallas', __('Beállítások', 'elallasi-funkcio'),
            __('Beállítások', 'elallasi-funkcio'), 'manage_options', 'elallas-settings', [$settings, 'render']);
    }

    public function registerSettings(): void
    {
        (new \Elallas\Admin\SettingsPage())->register();
    }
}
