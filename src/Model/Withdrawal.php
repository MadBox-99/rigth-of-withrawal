<?php
namespace Elallas\Model;

class Withdrawal
{
    private string $consumerName;
    private string $contactEmail;
    private string $orderReference;
    private string $intentText;
    private string $lang;
    private string $status;

    private function __construct(array $d)
    {
        $this->consumerName = (string)($d['consumer_name'] ?? '');
        $this->contactEmail = (string)($d['contact_email'] ?? '');
        $this->orderReference = (string)($d['order_reference'] ?? '');
        $this->intentText = (string)($d['intent_text'] ?? '');
        $this->lang = (string)($d['lang'] ?? 'hu');
        $this->status = (string)($d['status'] ?? 'received');
    }

    public static function fromArray(array $d): self { return new self($d); }

    public function consumerName(): string { return $this->consumerName; }
    public function contactEmail(): string { return $this->contactEmail; }
    public function orderReference(): string { return $this->orderReference; }
    public function intentText(): string { return $this->intentText; }
    public function lang(): string { return $this->lang; }
    public function status(): string { return $this->status; }

    public function toDbRow(string $createdAt, string $confirmationToken, string $ipHash): array
    {
        return [
            'created_at' => $createdAt,
            'status' => $this->status,
            'consumer_name' => $this->consumerName,
            'contact_email' => $this->contactEmail,
            'order_reference' => $this->orderReference,
            'wc_order_id' => null,
            'intent_text' => $this->intentText,
            'confirmation_token' => $confirmationToken,
            'confirmed_at' => null,
            'receipt_sent_at' => null,
            'ip_hash' => $ipHash,
            'lang' => $this->lang,
        ];
    }
}
