<?php
namespace Elallas;

class Activator
{
    public const TABLE = 'elallas_requests';

    public static function activate(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'received',
            consumer_name VARCHAR(190) NOT NULL,
            contact_email VARCHAR(190) NOT NULL,
            order_reference VARCHAR(190) NOT NULL,
            wc_order_id BIGINT UNSIGNED NULL,
            intent_text TEXT NULL,
            confirmation_token VARCHAR(64) NOT NULL,
            confirmed_at DATETIME NULL,
            receipt_sent_at DATETIME NULL,
            ip_hash VARCHAR(64) NULL,
            lang VARCHAR(10) NOT NULL DEFAULT 'hu',
            PRIMARY KEY (id),
            KEY status (status),
            KEY confirmation_token (confirmation_token)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('elallas_db_version', ELALLAS_VERSION);
    }
}
