<?php
namespace Elallas\Privacy;

use Elallas\Activator;

class GdprExporter
{
    /** @var \wpdb|object */ private $wpdb;

    public function __construct($wpdb) { $this->wpdb = $wpdb; }

    private function table(): string { return $this->wpdb->prefix . Activator::TABLE; }

    /** @param int $page Ignored; a tárgyhoz tartozó rekordok egy menetben elférnek. */
    public function export(string $email, int $page = 1): array
    {
        $rows = $this->wpdb->get_results(
            $this->wpdb->prepare("SELECT id, consumer_name, contact_email, order_reference, intent_text, status, created_at, confirmed_at, ip_hash, lang FROM {$this->table()} WHERE contact_email = %s", $email),
            ARRAY_A
        ) ?: [];

        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'group_id' => 'elallas_requests',
                'group_label' => __('Elállási kérelmek', 'elallasi-funkcio'),
                'item_id' => 'elallas-' . $r['id'],
                'data' => [
                    ['name' => __('Név', 'elallasi-funkcio'), 'value' => $r['consumer_name'] ?? ''],
                    ['name' => __('E-mail', 'elallasi-funkcio'), 'value' => $r['contact_email'] ?? ''],
                    ['name' => __('Rendelés/szerződés azonosító', 'elallasi-funkcio'), 'value' => $r['order_reference'] ?? ''],
                    ['name' => __('Elállási szándék', 'elallasi-funkcio'), 'value' => $r['intent_text'] ?? ''],
                    ['name' => __('Állapot', 'elallasi-funkcio'), 'value' => $r['status'] ?? ''],
                    ['name' => __('Beérkezés dátuma', 'elallasi-funkcio'), 'value' => $r['created_at'] ?? ''],
                    ['name' => __('Véglegesítés dátuma', 'elallasi-funkcio'), 'value' => (string)($r['confirmed_at'] ?? '')],
                    ['name' => __('IP hash (álnevesített)', 'elallasi-funkcio'), 'value' => (string)($r['ip_hash'] ?? '')],
                    ['name' => __('Nyelv', 'elallasi-funkcio'), 'value' => $r['lang'] ?? ''],
                ],
            ];
        }
        return ['data' => $items, 'done' => true];
    }

    /** @param int $page Ignored; egy menetben törlünk. */
    public function erase(string $email, int $page = 1): array
    {
        $removed = $this->wpdb->query(
            $this->wpdb->prepare("DELETE FROM {$this->table()} WHERE contact_email = %s", $email)
        );
        return ['items_removed' => (bool)$removed, 'items_retained' => false, 'messages' => [], 'done' => true];
    }
}
