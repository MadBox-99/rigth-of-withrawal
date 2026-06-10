<?php
namespace Elallas\Tests\Unit;

use Elallas\Licensing\LicenseManager;
use PHPUnit\Framework\TestCase;

class LicenseManagerTest extends TestCase
{
    public function test_pro_inactive_without_token(): void
    {
        $mgr = new LicenseManager(
            tokenProvider: fn() => '',
            cacheGet: fn($k) => false,
            cacheSet: fn($k, $v, $ttl) => null,
            validator: fn($token, $site) => ['status' => 'valid']
        );
        $this->assertFalse($mgr->isProActive());
    }

    public function test_pro_active_when_validator_returns_valid(): void
    {
        $mgr = new LicenseManager(
            tokenProvider: fn() => 'TOKEN',
            cacheGet: fn($k) => false,
            cacheSet: fn($k, $v, $ttl) => null,
            validator: fn($token, $site) => ['status' => 'valid', 'expires_at' => '2099-01-01']
        );
        $this->assertTrue($mgr->isProActive());
    }

    public function test_grace_period_uses_cache_when_validator_fails(): void
    {
        $mgr = new LicenseManager(
            tokenProvider: fn() => 'TOKEN',
            cacheGet: fn($k) => ['status' => 'valid', 'expires_at' => '2099-01-01'],
            cacheSet: fn($k, $v, $ttl) => null,
            validator: function ($token, $site) { throw new \RuntimeException('server down'); }
        );
        $this->assertTrue($mgr->isProActive());
    }
}
