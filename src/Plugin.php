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
    private ?\Elallas\Admin\SettingsPage $settingsPage = null;

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

        add_filter('pre_set_site_transient_update_plugins', function ($transient) {
            $server = (string)get_option('elallas_license_server', '');
            $token = (string)get_option('elallas_license_token', '');
            if ($server === '' || $token === '') { return $transient; }
            $updater = new \Elallas\Licensing\Updater(
                plugin_basename(ELALLAS_FILE),
                ELALLAS_VERSION,
                function () use ($server, $token) {
                    $resp = wp_remote_get(
                        rtrim($server, '/') . '/update-check?current_version=' . rawurlencode(ELALLAS_VERSION),
                        ['timeout' => 10, 'headers' => ['Authorization' => 'Bearer ' . $token]]
                    );
                    if (is_wp_error($resp)) { return []; }
                    $data = json_decode(wp_remote_retrieve_body($resp), true);
                    return is_array($data) ? $data : [];
                }
            );
            return $updater->filterUpdate($transient);
        });

        add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
            $exporters['elallasi-funkcio'] = [
                'exporter_friendly_name' => __('Elállási funkció', 'elallasi-funkcio'),
                'callback' => fn($email, $page) => $this->gdprExporter()->export($email, $page),
            ];
            return $exporters;
        });
        add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
            $erasers['elallasi-funkcio'] = [
                'eraser_friendly_name' => __('Elállási funkció', 'elallasi-funkcio'),
                'callback' => fn($email, $page) => $this->gdprExporter()->erase($email, $page),
            ];
            return $erasers;
        });

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

    private function gdprExporter(): \Elallas\Privacy\GdprExporter
    {
        global $wpdb;
        return new \Elallas\Privacy\GdprExporter($wpdb);
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

    private function settingsPage(): \Elallas\Admin\SettingsPage
    {
        if ($this->settingsPage === null) {
            $this->settingsPage = new \Elallas\Admin\SettingsPage();
        }
        return $this->settingsPage;
    }

    public function registerAdminMenu(): void
    {
        global $wpdb;
        $list = new \Elallas\Admin\AdminPage(new \Elallas\Repository\WithdrawalRepository($wpdb));
        $settings = $this->settingsPage();

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
        $this->settingsPage()->register();
    }
}
