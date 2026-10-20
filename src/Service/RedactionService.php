<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

/**
 * Bounds and redacts captured panel data before it is rendered or served.
 *
 * Limits come from the `DebugBar.collection`, `DebugBar.query_bindings` and
 * `DebugBar.key_policy` configuration keys. Redaction happens at read time so
 * stored rows stay untouched; every truncation surfaces a counter.
 */
final class RedactionService
{
    /**
     * Normalized redaction configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Creates the service from toolbar config.
     *
     * @param array<string, mixed> $config Service configuration.
     */
    public function __construct(array $config = [])
    {
        $collection = $config['collection'] ?? [];
        $this->config = [
            'max_items' => (int)($collection['max_items'] ?? 500),
            'max_items_per_array' => (int)($collection['max_items_per_array'] ?? 100),
            'max_depth' => (int)($collection['max_depth'] ?? 5),
            'max_string_length' => (int)($collection['max_string_length'] ?? 2000),
            'query_bindings' => $config['query_bindings'] ?? 'full',
            'key_policy' => $config['key_policy'] ?? 'full',
        ];
    }

    /**
     * The configured per-panel item maximum.
     *
     * @return int
     */
    public function maxItems(): int
    {
        return $this->config['max_items'];
    }

    /**
     * The configured string cap for captured payloads.
     *
     * @return int
     */
    public function maxStringLength(): int
    {
        return $this->config['max_string_length'];
    }

    /**
     * Caps a top-level item list and reports omission counts.
     *
     * @param array<int|string, mixed> $items Items to cap.
     * @param int|null $limit Optional override for the configured maximum.
     * @return array{items: array<int|string, mixed>, retained: int, dropped: int, truncated: bool}
     */
    public function processItems(array $items, ?int $limit = null): array
    {
        $max = $limit ?? $this->config['max_items'];
        if ($max < 1) {
            $max = 1;
        }

        $retained = count($items);
        $dropped = $retained - $max;
        if ($dropped < 0) {
            $dropped = 0;
        }

        return [
            'items' => array_slice($items, 0, $max, true),
            'retained' => $retained,
            'dropped' => $dropped,
            'truncated' => $dropped > 0,
        ];
    }

    /**
     * Recursively bounds a captured value.
     *
     * @param mixed $value Value to bound.
     * @param int $depth Current nesting depth.
     * @return mixed
     */
    public function redactValue(mixed $value, int $depth = 0): mixed
    {
        if (is_string($value)) {
            $max = $this->config['max_string_length'];

            return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if ($depth >= $this->config['max_depth']) {
            return '[depth limit]';
        }

        $result = [];
        $count = 0;
        foreach ($value as $key => $item) {
            if (++$count > $this->config['max_items_per_array']) {
                break;
            }

            $result[$key] = $this->redactValue($item, $depth + 1);
        }

        return $result;
    }

    /**
     * Reports whether a key name looks like a secret holder.
     *
     * Case-insensitive on the normalized name (lowercased, `_`/`-`/space
     * stripped): any key containing `password`, `passwd`, `secret`,
     * `token` or `credential`, or exactly `pwd`, `passw`,
     * `authorization`, `apikey`, `privatekey`, `clientsecret`, `cvv` or
     * `ssn`. Bare `pass` is deliberately excluded — routing stores URL
     * segments under it.
     *
     * @param string $key Key name to test.
     * @return bool True when values under it must be masked.
     */
    public static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        $normalized = str_replace(['_', '-', ' '], '', $normalized);

        return in_array(
            $normalized,
            ['pwd', 'passw', 'authorization', 'apikey', 'privatekey', 'clientsecret', 'cvv', 'ssn'],
            true,
        ) || str_contains($normalized, 'password')
            || str_contains($normalized, 'passwd')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'credential');
    }

    /**
     * Masks password-like values by key name, recursively.
     *
     * Key matching is case-insensitive on the normalized name (lowercased,
     * `_`/-,/space stripped): any key containing `password`, `passwd`,
     * `secret`, `token` or `credential`, or exactly `pwd`, `passw`,
     * `authorization`, `api_key`, `apikey`, `private_key`, `client_secret`,
     * `cvv` or `ssn` gets its value replaced with `********`. Bare `pass`
     * is deliberately excluded — routing stores URL segments under it.
     * Used for request input/query/cookies/session so stored rows never
     * carry cleartext credentials into the inspector or Copy-all JSON.
     *
     * @param array<int|string, mixed> $data Data to mask.
     * @return array<int|string, mixed> Data with sensitive values masked.
     */
    public function maskSensitive(array $data): array
    {
        $masked = [];
        foreach ($data as $key => $value) {
            if (self::isSensitiveKey((string)$key) && !is_bool($value)) {
                $masked[$key] = '********';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitive($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * The configured query bindings policy (`full`, `safe` or `none`).
     *
     * @return string
     */
    public function queryBindingsPolicy(): string
    {
        return $this->config['query_bindings'];
    }

    /**
     * Reports whether redacted bindings still carry every original value.
     *
     * Any masking marker (`[redacted]`, depth or truncation notes) means the
     * bound query cannot be rebuilt, so runnable SQL stays unavailable.
     *
     * @param array<int|string, mixed> $bindings Redacted bindings.
     * @return bool
     */
    public function bindingsComplete(array $bindings): bool
    {
        if ($this->config['query_bindings'] !== 'full') {
            return false;
        }

        return !$this->containsMaskedValue($bindings);
    }

    /**
     * Applies the query bindings policy to captured bindings.
     *
     * @param array<int|string, mixed> $bindings Captured bindings.
     * @return array<int|string, mixed>
     */
    public function redactBindings(array $bindings): array
    {
        $policy = $this->config['query_bindings'];
        if ($policy === 'none') {
            return [];
        }

        if ($policy !== 'safe') {
            return $this->redactValue($bindings);
        }

        $result = [];
        foreach ($bindings as $key => $binding) {
            $result[$key] = is_string($binding) ? '[redacted]' : $binding;
        }

        return $result;
    }

    /**
     * Recursively checks redacted bindings for masking markers.
     *
     * @param array<int|string, mixed> $bindings Redacted bindings.
     * @return bool True when any value was masked or cut off.
     */
    protected function containsMaskedValue(array $bindings): bool
    {
        foreach ($bindings as $key => $value) {
            if ($key === '__truncated__') {
                return true;
            }

            if (is_array($value) && $this->containsMaskedValue($value)) {
                return true;
            }

            if (
                is_string($value) && in_array(
                    $value,
                    ['[redacted]', '[depth limit]', '[maximum depth reached]'],
                    true,
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies the key policy to a captured cache, Redis or tag key.
     *
     * @param string $key Captured key.
     * @return string
     */
    public function redactKey(string $key): string
    {
        if (($this->config['key_policy'] ?? 'full') === 'hash') {
            return hash('sha256', $key);
        }

        return $key;
    }
}
