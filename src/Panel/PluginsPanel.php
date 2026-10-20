<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Core\Plugin;
use Cake\Core\PluginConfig;
use Crustum\DebugBar\DebugPanel;

/**
 * Provides debug information on the available plugins.
 *
 * @ported-from \DebugKit\Panel\PluginsPanel
 */
class PluginsPanel extends DebugPanel
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $loadedPluginsCollection = Plugin::getCollection();
        $config = PluginConfig::getAppConfig();

        $this->_data['hasEmptyAppConfig'] = $config === [];
        $plugins = [];

        foreach ($config as $pluginName => $options) {
            $plugins[$pluginName] = [
                'isLoaded' => $loadedPluginsCollection->has($pluginName),
                'onlyDebug' => $options['onlyDebug'] ?? false,
                'onlyCli' => $options['onlyCli'] ?? false,
                'optional' => $options['optional'] ?? false,
            ];
        }

        $this->_data['plugins'] = $plugins;
    }

    /**
     * Get summary data for the plugins panel.
     *
     * @return string
     */
    public function summary(): string
    {
        if (!isset($this->_data['plugins'])) {
            return '0';
        }

        return (string)count($this->_data['plugins']);
    }
}
