<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Core\Configure;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\AppPath;
use Throwable;

/**
 * Exceptions panel.
 *
 * Captures throwables bubbling through `DebugBarMiddleware` (plus manual
 * `record()` for handled ones): class, message, chained causes, app/vendor
 * stack frames with source context. Only shapes reach the stored row —
 * messages truncate, file excerpts cap at the configured context lines.
 * The inbox drains on `data()` for workers.
 */
class ExceptionsPanel extends DebugPanel
{
    /**
     * Captured throwables for the current request.
     *
     * @var array<int, \Throwable>
     */
    protected static array $inbox = [];

    /**
     * Records one throwable.
     *
     * @param \Throwable $error The throwable.
     * @return void
     */
    public static function record(Throwable $error): void
    {
        static::$inbox[] = $error;
    }

    /**
     * Resets captured throwables (long-lived workers and tests).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$inbox = [];
    }

    /**
     * Reads captured throwables without draining.
     *
     * Test support for the assertions trait.
     *
     * @return array<int, \Throwable>
     */
    public static function recorded(): array
    {
        return array_values(static::$inbox);
    }

    /**
     * Normalizes one throwable to its inspector shape.
     *
     * @param \Throwable $error The throwable.
     * @param array<string, mixed> $framesConfig `application`, `vendor` and
     *   `source_context_lines` counts.
     * @return array<string, mixed>
     */
    public static function normalize(Throwable $error, array $framesConfig = []): array
    {
        $appLimit = max(0, (int)($framesConfig['application'] ?? 12));
        $vendorLimit = max(0, (int)($framesConfig['vendor'] ?? 12));
        $contextLines = max(0, (int)($framesConfig['source_context_lines'] ?? 9));
        $frames = [];
        $appCount = 0;
        $vendorCount = 0;
        $trace = $error->getTrace();
        $traceCount = count($trace);
        for ($index = 0; $index < $traceCount; $index++) {
            $frame = (array)$trace[$index];
            $file = (string)($frame['file'] ?? '');
            $vendor = $file !== '' && AppPath::isVendor($file);
            if ($vendor && $vendorCount >= $vendorLimit) {
                continue;
            }

            if (!$vendor && $appCount >= $appLimit) {
                continue;
            }

            $vendor ? $vendorCount++ : $appCount++;
            $line = (int)($frame['line'] ?? 0);
            $frames[] = [
                'file' => $file,
                'line' => $line,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'],
                'vendor' => $vendor,
                'context' => $file !== '' && $line > 0 && !$vendor
                    ? static::context($file, $line, $contextLines)
                    : [],
            ];
        }

        $chain = [];
        $previous = $error->getPrevious();
        while ($previous instanceof Throwable) {
            $chain[] = $previous;
            $previous = $previous->getPrevious();
        }

        $causes = [];
        foreach (array_slice($chain, 0, 5) as $cause) {
            $causes[] = [
                'class' => $cause::class,
                'message' => mb_strcut($cause->getMessage(), 0, 2000),
                'file' => $cause->getFile(),
                'line' => $cause->getLine(),
            ];
        }

        return [
            'class' => $error::class,
            'message' => mb_strcut($error->getMessage(), 0, 2000),
            'code' => $error->getCode(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'frames' => $frames,
            'causes' => $causes,
        ];
    }

    /**
     * Get the data for this panel.
     *
     * @return array
     */
    public function data(): array
    {
        $framesConfig = (array)Configure::read('DebugBar.exception_frames', []);
        $exceptions = [];
        foreach (static::$inbox as $error) {
            $exceptions[] = static::normalize($error, $framesConfig);
        }

        static::$inbox = [];

        return [
            'exceptions' => $exceptions,
            'summary' => ['count' => count($exceptions)],
        ];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        $count = count(static::$inbox);

        return $count === 0 ? '' : "{$count} exceptions";
    }

    /**
     * Reads source context lines around a frame line.
     *
     * @param string $file File path.
     * @param int $line 1-based line number.
     * @param int $contextLines Lines to return centered on `$line`.
     * @return array<int, array{line: int, code: string, current: bool}>
     */
    protected static function context(string $file, int $line, int $contextLines): array
    {
        if ($contextLines < 1 || !is_readable($file)) {
            return [];
        }

        $half = (int)floor($contextLines / 2);
        $start = max(1, $line - $half);
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) {
            return [];
        }

        $context = [];
        foreach (array_slice($lines, $start - 1, $contextLines) as $offset => $code) {
            $number = $start + $offset;
            $context[] = [
                'line' => $number,
                'code' => mb_strcut((string)$code, 0, 500),
                'current' => $number === $line,
            ];
        }

        return $context;
    }
}
