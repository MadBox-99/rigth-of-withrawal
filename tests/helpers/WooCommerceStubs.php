<?php
/**
 * Stub declarations for WooCommerce functions used in unit tests.
 * This file is loaded AFTER Patchwork so it is instrumented and can be
 * redefined per-test via Brain Monkey Functions\when().
 */
if (!function_exists('wc_get_order')) {
    function wc_get_order($id)
    {
        return false;
    }
}
