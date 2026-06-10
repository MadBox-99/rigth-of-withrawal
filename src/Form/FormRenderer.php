<?php
namespace Elallas\Form;

class FormRenderer
{
    public function renderForm(array $errors = []): string
    {
        return $this->render('form.php', ['errors' => $errors]);
    }

    public function renderConfirm(array $data, int $id, string $token): string
    {
        return $this->render('confirm.php', ['data' => $data, 'id' => $id, 'token' => $token]);
    }

    public function renderMessage(string $message): string
    {
        return '<div class="elallas-message"><p>' . esc_html($message) . '</p></div>';
    }

    private function render(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include defined('ELALLAS_DIR') ? ELALLAS_DIR . 'templates/' . $template
            : __DIR__ . '/../../templates/' . $template;
        return (string)ob_get_clean();
    }
}
