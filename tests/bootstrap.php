<?php
require_once __DIR__ . '/../vendor/autoload.php';
// Brain Monkey biztosítja a WP függvény-stubokat tesztenként.
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
