<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ELALLAS_DIR')) {
    define('ELALLAS_DIR', dirname(__DIR__) . '/');
}
if (!defined('ELALLAS_FILE')) {
    define('ELALLAS_FILE', dirname(__DIR__) . '/elallasi-funkcio.php');
}
// Load Patchwork explicitly so that files required after this point are
// instrumented by its stream wrapper and can be redefined in tests.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
// Brain Monkey biztosítja a WP függvény-stubokat tesztenként.
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) { return $text; }
}
// WooCommerce function stubs – loaded after Patchwork so they are
// instrumented and can be overridden per-test.
require_once __DIR__ . '/helpers/WooCommerceStubs.php';
