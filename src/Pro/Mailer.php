<?php
namespace Elallas\Pro;

class Mailer
{
    /** @var callable */ private $clock;

    public function __construct(callable $clock)
    {
        $this->clock = $clock;
    }

    public function sendReceipt(array $data): bool
    {
        $data['received_at'] = (string)($this->clock)();
        $subject = __('Átvételi elismervény – Elállás a szerződéstől', 'elallasi-funkcio');
        $body = $this->renderBody($data);
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        return (bool)wp_mail($data['contact_email'], $subject, $body, $headers);
    }

    private function renderBody(array $data): string
    {
        ob_start();
        include defined('ELALLAS_DIR') ? ELALLAS_DIR . 'templates/email-receipt.php'
            : __DIR__ . '/../../templates/email-receipt.php';
        return (string)ob_get_clean();
    }
}
