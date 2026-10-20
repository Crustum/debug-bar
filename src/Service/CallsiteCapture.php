<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Core\Configure;

/**
 * Application callsite capture.
 *
 * Single home for the first-application-frame walk so collectors agree on
 * what "source" means: vendor frames are recognized slash-agnostically
 * (`AppPath`) because Windows frames carry backslashes. Arguments are
 * never captured and everything returned is serialization-safe.
 */
class CallsiteCapture
{
    /**
     * Captures the first application frame plus a short stack.
     *
     * Files listed in `$skipFiles` (usually the collector itself) never
     * become the callsite. When a request never leaves vendor code the
     * stack keeps a short raw head instead of nothing.
     *
     * @param array<int, string> $skipFiles Absolute paths to skip.
     * @param int $depth Maximum backtrace depth. ORM dispatches bury the
     *   controller dozens of frames deep (middleware, auth, behaviors), so
     *   collectors with deep stacks pass a wider window — a cut window
     *   drops the outer application frames first and misattributes the
     *   callsite to whatever app frame survived (often a middleware).
     * @param int|null $appFrames Useful application frames to keep. Null reads
     *   `DebugBar.call_site_frames` (12 by default). Honours the
     *   `DebugBar.call_sites` toggle: disabled returns an empty shape.
     * @return array{callsite: array{file: string|null, line: int|null}, stack: array<int, array{file: string|null, line: int|null, function: string, app: bool}>}
     */
    public static function capture(array $skipFiles = [], int $depth = 40, ?int $appFrames = null): array
    {
        if (!Configure::read('DebugBar.call_sites', true)) {
            return ['callsite' => ['file' => null, 'line' => null], 'stack' => []];
        }

        $keep = $appFrames ?? max(1, (int)Configure::read('DebugBar.call_site_frames', 12));
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth);
        $ordered = [];
        $callsite = ['file' => null, 'line' => null];
        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;
            if (is_string($file) && in_array($file, $skipFiles, true)) {
                continue;
            }

            $isApp = AppPath::isApp(is_string($file) ? $file : null);
            if ($callsite['file'] === null && $isApp) {
                $callsite = [
                    'file' => $file,
                    'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                ];
            }

            $function = (string)$frame['function'];
            if (isset($frame['class'])) {
                $function = $frame['class'] . ($frame['type'] ?? '::') . $function;
            }

            $ordered[] = [
                'file' => $file,
                'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                'function' => $function,
                'app' => $isApp,
            ];
        }

        $appOnly = array_values(array_filter(
            $ordered,
            static fn(array $frame): bool => $frame['app'],
        ));
        $stack = $appOnly !== []
            ? array_slice($appOnly, 0, $keep)
            : array_slice($ordered, 0, min(6, $keep));

        return ['callsite' => $callsite, 'stack' => $stack];
    }

    /**
     * Formats a callsite as a short `file:line` label.
     *
     * @param array{file: string|null, line: int|null} $callsite Callsite pair.
     * @return string Empty when no application frame was found.
     */
    public static function label(array $callsite): string
    {
        $file = (string)($callsite['file'] ?? '');
        if ($file === '') {
            return '';
        }

        $line = $callsite['line'] ?? null;

        return $line !== null ? $file . ':' . $line : $file;
    }

    /**
     * Splits a `file:line` label back into its parts.
     *
     * Splits on the LAST colon so Windows paths (`C:\app\file.php:123`)
     * survive — a naive `explode(':', ...)[0]` yields `C` with line `0`.
     *
     * @param string $label Label produced by `label()`.
     * @return array{file: string, line: int|null} File and line number.
     */
    public static function split(string $label): array
    {
        if (preg_match('/^(.*):(\d+)$/', $label, $matches)) {
            return ['file' => $matches[1], 'line' => (int)$matches[2]];
        }

        return ['file' => $label, 'line' => null];
    }
}
