<?php
namespace Elallas\Pro;

class WooCommerceBridge
{
    public function isAvailable(): bool
    {
        return function_exists('wc_get_order');
    }

    public function findOrderId(string $reference): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }
        $id = preg_replace('/[^0-9]/', '', $reference);
        if ($id === '') {
            return null;
        }
        $order = wc_get_order($id);
        if (!$order) {
            return null;
        }
        return (int) $order->get_id();
    }

    public function findOrderEmail(string $reference): ?string
    {
        if (!$this->isAvailable()) {
            return null;
        }
        // Számjegyek kiszűrése: "ORD-1001" → "1001". Több szegmensű referencia
        // (pl. "2024-00456") egy összefűzött egésszé olvad; ha nincs találat, wc_get_order false-t ad.
        $id = preg_replace('/[^0-9]/', '', $reference);
        if ($id === '') {
            return null;
        }
        $order = wc_get_order($id);
        if (!$order) {
            return null;
        }
        $email = $order->get_billing_email();
        return $email !== '' ? $email : null;
    }
}
