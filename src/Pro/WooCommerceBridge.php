<?php
namespace Elallas\Pro;

class WooCommerceBridge
{
    public function isAvailable(): bool
    {
        return function_exists('wc_get_order');
    }

    public function findOrderEmail(string $reference): ?string
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
        $email = $order->get_billing_email();
        return $email !== '' ? $email : null;
    }
}
