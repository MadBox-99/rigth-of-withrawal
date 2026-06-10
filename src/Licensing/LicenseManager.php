<?php
namespace Elallas\Licensing;

class LicenseManager
{
    private const CACHE_KEY = 'elallas_license_state';
    private const TTL = 12 * HOUR_IN_SECONDS;

    /** @var callable */ private $tokenProvider;
    /** @var callable */ private $cacheGet;
    /** @var callable */ private $cacheSet;
    /** @var callable */ private $validator;

    public function __construct(callable $tokenProvider, callable $cacheGet, callable $cacheSet, callable $validator)
    {
        $this->tokenProvider = $tokenProvider;
        $this->cacheGet = $cacheGet;
        $this->cacheSet = $cacheSet;
        $this->validator = $validator;
    }

    public function isProActive(): bool
    {
        $token = (string)($this->tokenProvider)();
        if ($token === '') {
            return false;
        }

        $cached = ($this->cacheGet)(self::CACHE_KEY);
        if (is_array($cached) && ($cached['status'] ?? '') === 'valid') {
            return true;
        }

        try {
            $state = ($this->validator)($token, $this->siteUrl());
            ($this->cacheSet)(self::CACHE_KEY, $state, self::TTL);
            return ($state['status'] ?? '') === 'valid';
        } catch (\Throwable $e) {
            // Grace period: a szerver elérhetetlen → utolsó ismert állapot.
            return is_array($cached) && ($cached['status'] ?? '') === 'valid';
        }
    }

    private function siteUrl(): string
    {
        return function_exists('home_url') ? home_url() : '';
    }
}
