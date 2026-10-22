<?php
declare(strict_types=1);

namespace Crustum\DebugBar;

use Cake\Error\Debugger;
use function Cake\Core\env;

/**
 * Contains methods for Profiling and creating timers.
 *
 * @ported-from \DebugKit\DebugTimer
 */
class DebugTimer
{
    /**
     * Internal timers array
     *
     * @var array
     */
    protected static array $timers = [];

    /**
     * Start an benchmarking timer.
     *
     * @param string $name The name of the timer to start.
     * @param string $message A message for your timer
     * @return bool Always true
     */
    public static function start(?string $name = null, ?string $message = null): bool
    {
        $start = microtime(true);

        if (!$name) {
            $named = false;
            $calledFrom = debug_backtrace();
            $file = $calledFrom[0]['file'] ?? 'unknown file';
            $line = $calledFrom[0]['line'] ?? 'n/a';
            $name = Debugger::trimPath($file) . ' line ' . $line;
        } else {
            $named = true;
        }

        if (!$message) {
            $message = $name;
        }

        $baseName = $name;
        $i = 1;
        while (isset(self::$timers[$name])) {
            $i++;
            $name = $baseName . ' #' . $i;
        }

        if ($i > 1) {
            $message .= ' #' . $i;
        }

        self::$timers[$name] = [
            'start' => $start,
            'message' => $message,
            'named' => $named,
        ];

        return true;
    }

    /**
     * Stop a benchmarking timer.
     *
     * $name should be the same as the $name used in startTimer().
     *
     * @param string $name The name of the timer to end.
     * @return bool true if timer was ended, false if timer was not started.
     */
    public static function stop(?string $name = null): bool
    {
        $end = microtime(true);
        if (!$name) {
            $names = array_reverse(array_keys(self::$timers));
            $name = null;
            foreach ($names as $candidate) {
                if (!empty(self::$timers[$candidate]['end'])) {
                    continue;
                }

                if (empty(self::$timers[$candidate]['named'])) {
                    $name = $candidate;
                    break;
                }
            }

            if ($name === null) {
                return false;
            }
        } else {
            $i = 1;
            $baseName = $name;
            while (isset(self::$timers[$name])) {
                if (empty(self::$timers[$name]['end'])) {
                    break;
                }

                $i++;
                $name = $baseName . ' #' . $i;
            }
        }

        if (!isset(self::$timers[$name])) {
            return false;
        }

        self::$timers[$name]['end'] = $end;

        return true;
    }

    /**
     * Get all timers that have been started and stopped.
     * Calculates elapsed time for each timer. If clear is true, will delete existing timers
     *
     * @param bool $clear false
     * @return array
     */
    public static function getAll(bool $clear = false): array
    {
        $start = self::requestStartTime();
        $now = microtime(true);

        $times = [];
        if (self::$timers !== []) {
            $firstTimer = reset(self::$timers);
            $endTime = $firstTimer['start'];
        } else {
            $endTime = $now;
        }

        $times['Core Processing (Derived from $_SERVER["REQUEST_TIME"])'] = [
            'message' => 'Core Processing (Derived from $_SERVER["REQUEST_TIME"])',
            'start' => 0,
            'end' => $endTime - $start,
            'time' => round($endTime - $start, 6),
            'named' => null,
        ];
        foreach (self::$timers as $name => $timer) {
            $timer['end'] ??= $now;

            $times[$name] = array_merge($timer, [
                'start' => $timer['start'] - $start,
                'end' => $timer['end'] - $start,
                'time' => self::elapsedTime($name),
            ]);
        }

        if ($clear) {
            self::$timers = [];
        }

        return $times;
    }

    /**
     * Clear all existing timers
     *
     * @return bool true
     */
    public static function clear(): bool
    {
        self::$timers = [];

        return true;
    }

    /**
     * Get the difference in time between the timer start and timer end.
     *
     * @param string $name the name of the timer you want elapsed time for.
     * @param int $precision the number of decimal places to return, defaults to 5.
     * @return float number of seconds elapsed for timer name, 0 on missing key
     */
    public static function elapsedTime(string $name = 'default', int $precision = 5): float
    {
        if (!isset(self::$timers[$name]['start']) || !isset(self::$timers[$name]['end'])) {
            return 0;
        }

        return round(self::$timers[$name]['end'] - self::$timers[$name]['start'], $precision);
    }

    /**
     * Get the total execution time until this point
     *
     * @return float elapsed time in seconds since script start.
     */
    public static function requestTime(): float
    {
        $start = self::requestStartTime();
        $now = microtime(true);

        return $now - $start;
    }

    /**
     * get the time the current request started.
     *
     * @return float time of request start
     */
    public static function requestStartTime(): float
    {
        if (defined('TIME_START')) {
            $startTime = TIME_START;
        } elseif (isset($GLOBALS['TIME_START'])) {
            $startTime = $GLOBALS['TIME_START'];
        } else {
            $startTime = env('REQUEST_TIME');
        }

        return (float)$startTime;
    }
}
