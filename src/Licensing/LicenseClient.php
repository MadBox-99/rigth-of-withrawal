<?php
namespace Elallas\Licensing;

class LicenseClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /** @return array{status:string,expires_at?:string} */
    public function validate(string $token, string $siteUrl): array
    {
        $resp = wp_remote_post($this->baseUrl . '/validate', [
            'timeout' => 10,
            'body' => ['token' => $token, 'site_url' => $siteUrl],
        ]);
        if (is_wp_error($resp)) {
            throw new \RuntimeException($resp->get_error_message());
        }
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            throw new \RuntimeException(sprintf('License server returned HTTP %d', $code));
        }
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($data) ? $data : ['status' => 'invalid'];
    }
}
