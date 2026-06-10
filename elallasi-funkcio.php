<?php
/**
 * Plugin Name: Elállási funkció
 * Description: Kötelező online elállási funkció magyar webshopoknak (45/2014. Korm. rendelet).
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Text Domain: elallasi-funkcio
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) { exit; }

define('ELALLAS_VERSION', '0.1.0');
define('ELALLAS_FILE', __FILE__);
define('ELALLAS_DIR', plugin_dir_path(__FILE__));

require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook(__FILE__, [\Elallas\Activator::class, 'activate']);

add_action('plugins_loaded', static function () {
    (new \Elallas\Plugin())->boot();
});
