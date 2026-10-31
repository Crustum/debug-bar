<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Manifest;

use Crustum\DebugBar\DebugBarPlugin;
use Crustum\PluginManifest\Manifest\ManifestOps;
use Crustum\PluginManifest\Manifest\Tag;

/**
 * Standalone manifest provider for the DebugBar plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class DebugBarManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(DebugBarPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'debug_bar.php',
                CONFIG . 'debug_bar.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'debug_bar.php')) {\n    Configure::load('debug_bar', 'default');\n}",
                '// DebugBar Plugin Configuration',
            ),
            ManifestOps::manifestDependencies($plugin, [
                'Crustum/Mcp' => [
                    'required' => false,
                    'tags' => [Tag::CONFIG, Tag::BOOTSTRAP],
                    'reason' => 'MCP runtime for cake-debugbar (Registrar, ContainerInvoker, mcp start)',
                ],
            ]),
            ManifestOps::manifestStarRepo($plugin, 'crustum/debug-bar'),
        );
    }
}
