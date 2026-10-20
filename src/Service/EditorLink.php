<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Core\Configure;

/**
 * Editor links for stack traces and sources.
 *
 * Single home for the `DebugBar.links` config so every stack frame and
 * source reference opens the same way: `enabled` gates the feature,
 * `link` is a `{file}`/`{line}` template, otherwise a known `editor`
 * name maps to its URL scheme. Returns null when disabled, unconfigured
 * or file-less — templates fall back to the copy affordance.
 */
class EditorLink
{
    /**
     * Known editor URL schemes (`{file}`/`{line}` templates).
     *
     * @var array<string, string>
     */
    protected static array $editors = [
        'cursor' => 'cursor://file/{file}:{line}',
        'vscode' => 'vscode://file/{file}:{line}',
        'vs-code' => 'vscode://file/{file}:{line}',
        'phpstorm' => 'phpstorm://open?file={file}&line={line}',
        'sublime' => 'subl://open?url=file://{file}&line={line}',
        'textmate' => 'txmt://open?url=file://{file}&line={line}',
    ];

    /**
     * Builds the editor URL for one file/line pair.
     *
     * @param string|null $file Absolute file path.
     * @param int|null $line Line number.
     * @return string|null URL or null when linking is off.
     */
    public static function url(?string $file, ?int $line = null): ?string
    {
        if ($file === null || $file === '') {
            return null;
        }

        $config = (array)Configure::read('DebugBar.links', []);
        if (empty($config['enabled'])) {
            return null;
        }

        $template = (string)($config['link'] ?? '');
        if ($template === '') {
            $editor = strtolower((string)($config['editor'] ?? ''));
            $template = static::$editors[$editor] ?? '';
        }

        if ($template === '') {
            return null;
        }

        return strtr($template, [
            '{file}' => $file,
            '{line}' => $line !== null ? (string)$line : '',
        ]);
    }
}
