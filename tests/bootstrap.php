<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Brain Monkey biztosítja a WP függvény-stubokat tesztenként.
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) { return $text; }
}
