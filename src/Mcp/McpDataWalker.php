<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mcp;

use DateTimeInterface;
use JsonSerializable;
use SplObjectStorage;
use Throwable;

/**
 * Bounded JSON walker over stored panel data.
 *
 * Stored rows predate capture-time redaction and may hold objects
 * (loggers, entities), so values sanitize first: scalars pass through,
 * arrays recurse with a depth cap, objects collapse to class markers
 * (data carriers unwrap through `toArray()`), cycles and resources
 * become markers. Nothing thrown here is ever stored — the walker only
 * reads for one tool call.
 *
 * Reads follow JSON Pointer (`/panels/SqlLog/records/0/sql`, empty for
 * the root). Lists and maps paginate by cursor/limit; strings beyond
 * the byte budget chunk with `omitted_due_to_bytes` instead of dumping
 * megabytes into the response.
 */
class McpDataWalker
{
    /**
     * Creates the walker with response bounds.
     *
     * @param int $maxItems Default page size.
     * @param int $maxBytes Per-string byte budget.
     */
    public function __construct(
        protected int $maxItems = 50,
        protected int $maxBytes = 100000,
    ) {
    }

    /**
     * Sanitizes one stored panel document into JSON-safe data.
     *
     * @param mixed $value Stored value.
     * @return mixed JSON-safe value.
     */
    public function sanitize(mixed $value): mixed
    {
        return $this->clean($value, 0, new SplObjectStorage());
    }

    /**
     * Reads one JSON Pointer path from a sanitized document.
     *
     * @param array<string, mixed> $document Sanitized document.
     * @param string $path JSON Pointer path or empty for the root.
     * @return array{found: bool, value: mixed}
     */
    public function locate(array $document, string $path): array
    {
        if ($path === '' || $path === '/') {
            return ['found' => true, 'value' => $document];
        }

        $segments = explode('/', ltrim($path, '/'));
        $node = $document;
        foreach ($segments as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);
            if (is_array($node)) {
                if (array_is_list($node)) {
                    if (!ctype_digit($segment) || !array_key_exists((int)$segment, $node)) {
                        return ['found' => false, 'value' => null];
                    }

                    $node = $node[(int)$segment];
                    continue;
                }

                if (!array_key_exists($segment, $node)) {
                    return ['found' => false, 'value' => null];
                }

                $node = $node[$segment];
                continue;
            }

            return ['found' => false, 'value' => null];
        }

        return ['found' => true, 'value' => $node];
    }

    /**
     * Renders one node with pagination for lists and maps.
     *
     * @param mixed $value Sanitized node.
     * @param string $path Node path (for child entries).
     * @param int $cursor First child index.
     * @param int $limit Maximum children.
     * @return array<string, mixed> Node view with type, value/entries and pagination.
     */
    public function render(mixed $value, string $path, int $cursor = 0, int $limit = 0): array
    {
        $limit = $limit > 0 ? $limit : $this->maxItems;
        $type = $this->typeOf($value);
        if ($type === 'string') {
            return $this->renderString((string)$value);
        }

        if ($type === 'list' || $type === 'object') {
            return $this->renderChildren((array)$value, $type, $path, $cursor, $limit);
        }

        $view = ['type' => $type];
        if ($value !== null || $type === 'null') {
            $view['value'] = $value;
        }

        return $view;
    }

    /**
     * Recursively converts a stored value into JSON-safe data.
     *
     * @param mixed $value Stored value.
     * @param int $depth Current depth.
     * @param \SplObjectStorage<object, null> $seen Visited objects.
     * @return mixed
     */
    protected function clean(mixed $value, int $depth, SplObjectStorage $seen): mixed
    {
        if ($value === null || is_bool($value) || is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : null;
        }

        if (is_string($value)) {
            return $value;
        }

        if ($depth >= 12) {
            return '[maximum depth reached]';
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[$key] = $this->clean($item, $depth + 1, $seen);
            }

            return $out;
        }

        if (is_object($value)) {
            if ($seen->contains($value)) {
                return ['__circular' => $value::class];
            }

            $seen->attach($value);
            try {
                if ($value instanceof DateTimeInterface) {
                    return $value->format(DATE_ATOM);
                }

                if ($value instanceof JsonSerializable) {
                    return $this->clean($value->jsonSerialize(), $depth + 1, $seen);
                }
            } catch (Throwable) {
                return ['__class' => $value::class];
            }

            return ['__class' => $value::class];
        }

        return '[' . get_debug_type($value) . ']';
    }

    /**
     * Names the node type for one sanitized value.
     *
     * @param mixed $value Sanitized value.
     * @return string object|list|string|integer|number|boolean|null|unknown
     */
    protected function typeOf(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'number';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            return array_is_list($value) ? 'list' : 'object';
        }

        return 'unknown';
    }

    /**
     * Renders one string with byte-budget chunking.
     *
     * @param string $content String value.
     * @return array<string, mixed>
     */
    protected function renderString(string $content): array
    {
        $bytes = strlen($content);
        if ($bytes <= $this->maxBytes) {
            return ['type' => 'string', 'value' => $content, 'length_bytes' => $bytes, 'chunked' => false];
        }

        $chunks = str_split(substr($content, 0, $this->maxBytes * 5), max(1, $this->maxBytes));
        $omitted = max(0, $bytes - array_sum(array_map(strlen(...), $chunks)));

        return [
            'type' => 'string',
            'length_bytes' => $bytes,
            'chunked' => true,
            'chunks' => $chunks,
            'omitted_due_to_bytes' => $omitted,
        ];
    }

    /**
     * Renders one list or map page with child entries.
     *
     * @param array<array-key, mixed> $node Children.
     * @param string $type list|object.
     * @param string $path Node path.
     * @param int $cursor First child.
     * @param int $limit Page size.
     * @return array<string, mixed>
     */
    protected function renderChildren(array $node, string $type, string $path, int $cursor, int $limit): array
    {
        $keys = array_keys($node);
        $total = count($keys);
        $cursor = max(0, min($cursor, $total));
        $slice = array_slice($keys, $cursor, $limit);
        $entries = [];
        $omitted = 0;
        foreach ($slice as $key) {
            $childPath = $path === '' ? '/' . $this->escape((string)$key) : $path . '/' . $this->escape((string)$key);
            $entries[] = $this->summarize($node[$key], (string)$key, $childPath);
        }

        foreach (array_slice($keys, $cursor + count($slice)) as $key) {
            $omitted += strlen((string)json_encode($node[$key]));
        }

        return [
            'type' => $type,
            'count' => $total,
            'entries' => $entries,
            'pagination' => [
                'cursor' => $cursor,
                'returned' => count($entries),
                'total' => $total,
                'truncated' => $cursor + count($entries) < $total,
                'next_cursor' => $cursor + count($entries) < $total ? $cursor + count($entries) : null,
                'omitted_due_to_bytes' => $omitted,
            ],
        ];
    }

    /**
     * Summarizes one child entry with its path and type.
     *
     * @param mixed $value Sanitized child.
     * @param string $key Child key.
     * @param string $path Child path.
     * @return array<string, mixed>
     */
    protected function summarize(mixed $value, string $key, string $path): array
    {
        $entry = ['key' => $key, 'path' => $path, 'type' => $this->typeOf($value)];
        if (is_array($value)) {
            $entry['count'] = count($value);
        } elseif (is_string($value)) {
            $entry['length_bytes'] = strlen($value);
            if (strlen($value) <= 200) {
                $entry['value'] = $value;
            } else {
                $entry['chunked'] = true;
            }
        } elseif ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            $entry['value'] = $value;
        }

        return $entry;
    }

    /**
     * Escapes one JSON Pointer path segment.
     *
     * @param string $segment Path segment.
     * @return string Escaped segment.
     */
    protected function escape(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
