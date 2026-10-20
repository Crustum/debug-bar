<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Manifest;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugBarPlugin;
use Crustum\DebugBar\Manifest\DebugBarManifest;
use Crustum\PluginManifest\Manifest\ManifestResolver;

/**
 * Covers the standalone manifest provider: identical operations to the
 * former plugin-class manifest, discoverable without the interface.
 */
class DebugBarManifestTest extends TestCase
{
    /**
     * The provider emits config, bootstrap, dependency and star operations.
     *
     * @return void
     */
    public function testManifestOperations(): void
    {
        $ops = DebugBarManifest::manifest();
        $tags = array_column($ops, 'tag');

        $this->assertContains('config', $tags);
        $this->assertContains('bootstrap', $tags);
        $this->assertContains('dependencies', $tags);
        $this->assertContains('star-repo', $tags);

        foreach ($ops as $op) {
            if (($op['type'] ?? null) === 'dependencies') {
                $this->assertFalse($op['dependencies']['Crustum/Mcp']['required']);
            }
        }
    }

    /**
     * The resolver finds the provider for the plugin class.
     *
     * @return void
     */
    public function testResolverFindsProvider(): void
    {
        $this->assertSame(
            DebugBarManifest::class,
            ManifestResolver::providerClass(DebugBarPlugin::class),
        );
        $this->assertSame(DebugBarManifest::manifest(), ManifestResolver::manifestFor(DebugBarPlugin::class));
    }

    /**
     * The plugin class itself carries no manifest contract.
     *
     * @return void
     */
    public function testPluginClassHasNoManifestContract(): void
    {
        $this->assertFalse(method_exists(DebugBarPlugin::class, 'manifest'));
    }
}
