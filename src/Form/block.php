<?php
namespace Elallas\Form;

function register_block(callable $render): void
{
    add_action('init', static function () use ($render) {
        if (!function_exists('register_block_type')) { return; }
        register_block_type('elallas/urlap', [
            'render_callback' => $render,
        ]);
    });
}
