<?php
namespace Elallas\Tests\Unit;

use Elallas\Plugin;
use PHPUnit\Framework\TestCase;

class PluginTest extends TestCase
{
    public function test_plugin_class_boots_without_error(): void
    {
        $this->expectNotToPerformAssertions();
        (new Plugin())->boot();
    }
}
