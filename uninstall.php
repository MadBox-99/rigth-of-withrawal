<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

global $wpdb;
$table = $wpdb->prefix . 'elallas_requests';
$wpdb->query("DROP TABLE IF EXISTS {$table}");

foreach (['elallas_license_token','elallas_license_server','elallas_admin_email','elallas_db_version'] as $opt) {
    delete_option($opt);
}
