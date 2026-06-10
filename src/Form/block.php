<?php
namespace Elallas\Form;

function register_block(): void
{
    add_action('init', static function () {
        if (!function_exists('register_block_type')) { return; }
        register_block_type('elallas/urlap', [
            'render_callback' => static function () {
                return (new FormRenderer())->renderForm();
            },
        ]);
    });
}
