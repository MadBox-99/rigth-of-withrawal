<?php
namespace Elallas\Submission;

use Elallas\Model\Withdrawal;

class SubmissionHandler
{
    private Validator $validator;
    /** @var object */ private $repo;
    /** @var callable */ private $clock;
    /** @var callable */ private $tokenFactory;
    /** @var callable */ private $ipHasher;
    /** @var callable */ private $onConfirmed;

    public function __construct(Validator $validator, $repo, callable $clock, callable $tokenFactory, callable $ipHasher, callable $onConfirmed)
    {
        $this->validator = $validator;
        $this->repo = $repo;
        $this->clock = $clock;
        $this->tokenFactory = $tokenFactory;
        $this->ipHasher = $ipHasher;
        $this->onConfirmed = $onConfirmed;
    }

    /** 1. lépcső: validálás + received rekord mentése. */
    public function prepare(array $raw): array
    {
        $input = [
            'consumer_name' => sanitize_text_field($raw['consumer_name'] ?? ''),
            'contact_email' => sanitize_text_field($raw['contact_email'] ?? ''),
            'order_reference' => sanitize_text_field($raw['order_reference'] ?? ''),
            'intent_text' => sanitize_textarea_field($raw['intent_text'] ?? ''),
            'lang' => sanitize_text_field($raw['lang'] ?? 'hu'),
        ];

        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $token = (string)($this->tokenFactory)();
        $row = Withdrawal::fromArray($input)->toDbRow(
            (string)($this->clock)(),
            $token,
            (string)($this->ipHasher)()
        );
        $id = $this->repo->insert($row);

        return ['ok' => true, 'errors' => [], 'id' => $id, 'confirmation_token' => $token];
    }

    /** 2. lépcső: külön megerősítő funkció — véglegesítés. */
    public function confirm(int $id): bool
    {
        $ok = $this->repo->markConfirmed($id, (string)($this->clock)());
        if ($ok) {
            ($this->onConfirmed)($id);
        }
        return $ok;
    }
}
