<?php
namespace Elallas\Licensing;

class Updater
{
    private string $pluginBasename;
    private string $currentVersion;
    /** @var callable */ private $remoteCheck;

    public function __construct(string $pluginBasename, string $currentVersion, callable $remoteCheck)
    {
        $this->pluginBasename = $pluginBasename;
        $this->currentVersion = $currentVersion;
        $this->remoteCheck = $remoteCheck;
    }

    /** @param object $transient */
    public function filterUpdate($transient)
    {
        if (!is_object($transient)) {
            $transient = new \stdClass();
        }
        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        $remote = ($this->remoteCheck)();
        $latest = (string)($remote['latest_version'] ?? '');
        if ($latest === '' || version_compare($latest, $this->currentVersion, '<=')) {
            return $transient;
        }

        $transient->response[$this->pluginBasename] = (object)[
            'slug' => 'elallasi-funkcio',
            'plugin' => $this->pluginBasename,
            'new_version' => $latest,
            'package' => (string)($remote['package_url'] ?? ''),
        ];
        return $transient;
    }
}
