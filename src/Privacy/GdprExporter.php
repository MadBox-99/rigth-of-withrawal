<?php
namespace Elallas\Privacy;

use Elallas\Activator;

class GdprExporter
{
    /** @var \wpdb|object */ private $wpdb;

    public function __construct($wpdb) { $this->wpdb = $wpdb; }

    private function table(): string { return $this->wpdb->prefix . Activator::TABLE; }

    public function export(string $email, int $page = 1): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT id, consumer_name, order_reference, created_at FROM {$this->table()} WHERE contact_email = %s", $email),
            ARRAY_A
        ) ?: [];

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'group_id' => 'elallas_requests',
                'group_label' => __('Elállási kérelmek', 'elallasi-funkcio'),
                'item_id' => 'elallas-' . $r['id'],
                'data' => [
                    ['name' => __('Név', 'elallasi-funkcio'), 'value' => $r['consumer_name']],
                    ['name' => __('Azonosító', 'elallasi-funkcio'), 'value' => $r['order_reference']],
                    ['name' => __('Dátum', 'elallasi-funkcio'), 'value' => $r['created_at']],
                ],
            ];
        }
        return ['data' => $items, 'done' => true];
    }

    public function erase(string $email, int $page = 1): array
    {
        $removed = $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$this->table()} WHERE contact_email = %s", $email)
        );
        return ['items_removed' => (bool)$removed, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
