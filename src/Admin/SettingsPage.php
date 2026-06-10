<?php
namespace Elallas\Admin;

class SettingsPage
{
    public function register(): void
    {
        register_setting('elallas', 'elallas_license_token', ['sanitize_callback' => [$this, 'sanitizeToken']]);
        register_setting('elallas', 'elallas_license_server', ['sanitize_callback' => 'esc_url_raw']);
        register_setting('elallas', 'elallas_admin_email', ['sanitize_callback' => 'sanitize_email']);
    }

    public function sanitizeToken($value): string
    {
        return trim(sanitize_text_field((string)$value));
    }

    public function render(): void
    {
        echo '<div class="wrap"><h1>' . esc_html__('Elállási funkció – Beállítások', 'elallasi-funkcio') . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('elallas');
        echo '<table class="form-table">';
        printf('<tr><th>%s</th><td><input type="password" name="elallas_license_token" value="%s" class="regular-text"></td></tr>',
            esc_html__('Licenc token', 'elallasi-funkcio'), esc_attr(get_option('elallas_license_token', '')));
        printf('<tr><th>%s</th><td><input type="url" name="elallas_license_server" value="%s" class="regular-text"></td></tr>',
            esc_html__('Licenc-szerver URL', 'elallasi-funkcio'), esc_attr(get_option('elallas_license_server', '')));
        printf('<tr><th>%s</th><td><input type="email" name="elallas_admin_email" value="%s" class="regular-text"></td></tr>',
            esc_html__('Értesítendő admin e-mail', 'elallasi-funkcio'), esc_attr(get_option('elallas_admin_email', '')));
        echo '</table>';
        submit_button();
        echo '</form></div>';
    }
}
