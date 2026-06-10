<?php
namespace Elallas\Submission;

class Validator
{
    /** @return array<string,string> field => error message */
    public function validate(array $input): array
    {
        $errors = [];

        if (trim((string)($input['consumer_name'] ?? '')) === '') {
            $errors['consumer_name'] = __('A név megadása kötelező.', 'elallasi-funkcio');
        }
        $email = trim((string)($input['contact_email'] ?? ''));
        if ($email === '' || !is_email($email)) {
            $errors['contact_email'] = __('Érvényes e-mail cím megadása kötelező.', 'elallasi-funkcio');
        }
        if (trim((string)($input['order_reference'] ?? '')) === '') {
            $errors['order_reference'] = __('A rendelés/szerződés azonosító megadása kötelező.', 'elallasi-funkcio');
        }
        if (trim((string)($input['intent_text'] ?? '')) === '') {
            $errors['intent_text'] = __('Az elállási szándék megadása kötelező.', 'elallasi-funkcio');
        }

        return $errors;
    }
}
