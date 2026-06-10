<?php
namespace Elallas\Tests\Unit;

use Elallas\Licensing\Updater;
use PHPUnit\Framework\TestCase;

class UpdaterTest extends TestCase
{
    public function test_injects_update_when_remote_version_newer(): void
    {
        $updater = new Updater(
            pluginBasename: 'elallasi-funkcio/elallasi-funkcio.php',
            currentVersion: '0.1.0',
            remoteCheck: fn() => ['latest_version' => '0.2.0', 'package_url' => 'https://ex/p.zip']
        );

        $transient = (object)['response' => []];
        $out = $updater->filterUpdate($transient);

        $this->assertArrayHasKey('elallasi-funkcio/elallasi-funkcio.php', $out->response);
        $this->assertSame('0.2.0', $out->response['elallasi-funkcio/elallasi-funkcio.php']->new_version);
    }

    public function test_no_update_when_same_version(): void
    {
        $updater = new Updater(
            pluginBasename: 'elallasi-funkcio/elallasi-funkcio.php',
            currentVersion: '0.2.0',
            remoteCheck: fn() => ['latest_version' => '0.2.0', 'package_url' => 'https://ex/p.zip']
        );
        $transient = (object)['response' => []];
        $out = $updater->filterUpdate($transient);
        $this->assertSame([], $out->response);
    }
}
