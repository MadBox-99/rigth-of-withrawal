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

    private function __construct(array $data)
    {
        $this->consumerName = (string)($data['consumer_name'] ?? '');
        $this->contactEmail = (string)($data['contact_email'] ?? '');
        $this->orderReference = (string)($data['order_reference'] ?? '');
        $this->intentText = (string)($data['intent_text'] ?? '');
        $this->lang = (string)($data['lang'] ?? 'hu');
        $this->status = (string)($data['status'] ?? 'received');
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }

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
