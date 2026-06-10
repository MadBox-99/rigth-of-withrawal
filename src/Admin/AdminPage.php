<?php
namespace Elallas\Admin;

use Elallas\Repository\WithdrawalRepository;

class AdminPage
{
    private WithdrawalRepository $repo;

    public function __construct(WithdrawalRepository $repo)
    {
        $this->repo = $repo;
    }

    public function render(): void
    {
        global $wpdb;
        $table = $this->repo->tableName();
        $rows = $wpdb->get_results("SELECT created_at, status, consumer_name, contact_email, order_reference, confirmed_at FROM {$table} ORDER BY created_at DESC LIMIT 200", ARRAY_A) ?: [];

        echo '<div class="wrap"><h1>' . esc_html__('Beérkezett elállások', 'elallasi-funkcio') . '</h1>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['Dátum','Állapot','Név','E-mail','Azonosító','Véglegesítve'] as $h) {
            echo '<th>' . esc_html__($h, 'elallasi-funkcio') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            echo '<td>' . esc_html($r['created_at']) . '</td>';
            echo '<td>' . esc_html($r['status']) . '</td>';
            echo '<td>' . esc_html($r['consumer_name']) . '</td>';
            echo '<td>' . esc_html($r['contact_email']) . '</td>';
            echo '<td>' . esc_html($r['order_reference']) . '</td>';
            echo '<td>' . esc_html((string)$r['confirmed_at']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}
