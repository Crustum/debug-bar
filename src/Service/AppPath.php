<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Core\Exception\MissingPluginException;
use Cake\Core\Plugin;

/**
 * Application vs vendor path checks.
 *
 * Single home for the origin tests so collectors, analyzers and templates
 * agree: directory separators are normalized first because Windows frames
 * carry backslashes (`C:\app\vendor\...`) that a `/vendor/` needle misses.
 * A plugin template is plugin code, not framework — vendor alone must not
 * decide.
 */
class AppPath
{
    /**
     * Template origin buckets.
     *
     * @var string
     */
    public const ORIGIN_APPLICATION = 'application';

    /**
     * @var string
     */
    public const ORIGIN_PLUGIN = 'plugin';

    /**
     * @var string
     */
    public const ORIGIN_FRAMEWORK = 'framework';

    /**
     * Reports whether a frame file lives inside a vendor directory.
     *
     * @param string|null $file Frame file path.
     * @return bool
     */
    public static function isVendor(?string $file): bool
    {
        if ($file === null || $file === '') {
            return false;
        }

        return str_contains(str_replace('\\', '/', $file), '/vendor/');
    }

    /**
     * Reports whether a frame file holds application code.
     *
     * @param string|null $file Frame file path.
     * @return bool
     */
    public static function isApp(?string $file): bool
    {
        return $file !== null && $file !== '' && !static::isVendor($file);
    }

    /**
     * Buckets a file into application, plugin or framework origins.
     *
     * Any loaded plugin owns its files — including vendor-shipped ones —
     * so plugins never report as framework. Remaining vendor files are
     * framework; everything else is application code.
     *
     * @param string|null $file File path.
     * @return string One of the `ORIGIN_*` constants.
     */
    public static function origin(?string $file): string
    {
        if (static::pluginName($file) !== null) {
            return static::ORIGIN_PLUGIN;
        }

        if ($file === null || $file === '') {
            return static::ORIGIN_FRAMEWORK;
        }

        if (static::isVendor($file)) {
            return static::ORIGIN_FRAMEWORK;
        }

        return static::ORIGIN_APPLICATION;
    }

    /**
     * Names the loaded plugin owning a file, if any.
     *
     * @param string|null $file File path.
     * @return string|null Plugin name as registered, or null.
     */
    public static function pluginName(?string $file): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $path = rtrim(str_replace('\\', '/', $file), '/') . '/';
        foreach (Plugin::loaded() as $plugin) {
            try {
                $base = Plugin::path($plugin);
            } catch (MissingPluginException) {
                continue;
            }

            $base = rtrim(str_replace('\\', '/', $base), '/') . '/';
            if ($base !== '/' && str_starts_with($path, $base)) {
                return $plugin;
            }
        }

        return null;
    }
}
