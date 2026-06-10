<?php
namespace Elallas\Repository;

use Elallas\Activator;

class WithdrawalRepository
{
    /** @var \wpdb|object */
    private $wpdb;

    public function __construct($wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function tableName(): string
    {
        return $this->wpdb->prefix . Activator::TABLE;
    }

    public function insert(array $row): int
    {
        $this->wpdb->insert($this->tableName(), $row);
        return (int)$this->wpdb->insert_id;
    }

    public function markConfirmed(int $id, string $confirmedAt): bool
    {
        $ok = $this->wpdb->update(
            $this->tableName(),
            ['status' => 'confirmed', 'confirmed_at' => $confirmedAt],
            ['id' => $id]
        );
        return $ok !== false;
    }

    public function markReceiptSent(int $id, string $sentAt): bool
    {
        $ok = $this->wpdb->update(
            $this->tableName(),
            ['receipt_sent_at' => $sentAt],
            ['id' => $id]
        );
        return $ok !== false;
    }
}
