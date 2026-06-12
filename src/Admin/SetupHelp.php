<?php
namespace Elallas\Admin;

/**
 * Beállítási / használati útmutató panel.
 *
 * Ugyanaz a tartalom jelenik meg a plugin több admin oldalán (Elállások lista,
 * Beállítások), hogy a bolt-tulajdonos ott lássa a teendőket, ahol dolgozik.
 */
class SetupHelp
{
    public static function render(): void
    {
        $block = '<code>elallas/urlap</code>';
        $shortcode = '<code>' . esc_html('[elallasi_urlap]') . '</code>';

        echo '<div class="card" style="max-width:820px;padding:4px 18px 14px;">';
        echo '<h2>' . esc_html__('Beállítási útmutató', 'elallasi-funkcio') . '</h2>';
        echo '<p>' . esc_html__('Három lépésben működőképes az elállási funkció:', 'elallasi-funkcio') . '</p>';
        echo '<ol style="margin-left:18px;">';

        // 1. Beállítások
        echo '<li style="margin-bottom:8px;"><strong>'
            . esc_html__('Töltsd ki a beállításokat', 'elallasi-funkcio')
            . '</strong> (' . esc_html__('Elállások → Beállítások', 'elallasi-funkcio') . '):';
        echo '<ul style="list-style:disc;margin:6px 0 0 22px;">';
        echo '<li><strong>' . esc_html__('Licenc token', 'elallasi-funkcio') . '</strong> – '
            . esc_html__('a Cégem360-tól kapott kulcs; ezzel kapcsolódnak be a Pro funkciók és az automatikus frissítések. Üresen hagyható – az alapfunkciók enélkül is működnek.', 'elallasi-funkcio')
            . '</li>';
        echo '<li><strong>' . esc_html__('Licenc-szerver URL', 'elallasi-funkcio') . '</strong> – '
            . esc_html__('a licencet ellenőrző szerver címe (a szolgáltatótól). Csak akkor töltsd ki, ha kaptál ilyet.', 'elallasi-funkcio')
            . '</li>';
        echo '<li><strong>' . esc_html__('Értesítendő admin e-mail', 'elallasi-funkcio') . '</strong> – '
            . esc_html__('ide érkezik értesítés minden új elállási kérelemről. Üresen hagyva a WordPress rendszer-adminisztrátor címére megy.', 'elallasi-funkcio')
            . '</li>';
        echo '</ul></li>';

        // 2. Űrlap kihelyezése
        echo '<li style="margin-bottom:8px;"><strong>'
            . esc_html__('Tedd ki az űrlapot egy oldalra', 'elallasi-funkcio') . '</strong>: ';
        printf(
            /* translators: %1$s: blokk neve <code>, %2$s: rövidkód <code> */
            esc_html__('a blokk-szerkesztőben az „Elállási űrlap” (%1$s) blokkal, vagy bárhol a %2$s rövidkóddal.', 'elallasi-funkcio'),
            $block,
            $shortcode
        );
        echo '</li>';

        // 3. Beérkezett elállások
        echo '<li><strong>' . esc_html__('A beérkezett elállások', 'elallasi-funkcio') . '</strong> '
            . esc_html__('az „Elállások” menüpontban listázódnak.', 'elallasi-funkcio') . '</li>';

        echo '</ol></div>';
    }
}
