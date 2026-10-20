<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Error\Debug\NodeInterface;
use Cake\Error\Debugger;
use Throwable;

/**
 * One-line var export for panel authors.
 *
 * Same pipeline the Variables inspector uses: Cake's
 * `Debugger::exportVarAsNodes()` keeps inner objects (entities, dates,
 * throwables) as expandable class nodes instead of collapsing them to
 * bare class names, sensitive leaves are masked, and
 * `NodeFlattener::toPlain()` flattens to JSON-safe plain arrays the
 * `ui/debug_tree` element renders with zero panel JavaScript.
 *
 * Never throws: unexportable values collapse to a scalar marker so a
 * strange value can never break event capture.
 */
class VarExporter
{
    /**
     * Export any value to plain arrays for panel storage.
     *
     * @param mixed $value Value to export.
     * @param int $depth Export depth budget.
     * @param bool $mask Mask sensitive leaves.
     * @return array<string, mixed> Plain data.
     */
    public static function export(mixed $value, int $depth = 5, bool $mask = true): array
    {
        try {
            $node = Debugger::exportVarAsNodes($value, max(1, $depth));
            if ($mask) {
                $node = NodeFlattener::maskSensitive($node);
            }

            $plain = NodeFlattener::toPlain($node);
        } catch (Throwable) {
            return ['value' => static::fallback($value)];
        }

        if (is_array($plain)) {
            return $plain;
        }

        return ['value' => $plain];
    }

    /**
     * Flatten an already-exported node tree to plain arrays.
     *
     * Generalizes the export-mask-flatten repeat in panel endpoints:
     * same output as `export()` for stored node trees.
     *
     * @param \Cake\Error\Debug\NodeInterface $node Exported tree.
     * @param bool $mask Mask sensitive leaves.
     * @return array<string, mixed> Plain data.
     */
    public static function flatten(NodeInterface $node, bool $mask = true): array
    {
        try {
            if ($mask) {
                $node = NodeFlattener::maskSensitive($node);
            }

            $plain = NodeFlattener::toPlain($node);
        } catch (Throwable) {
            return ['value' => 'unexportable value'];
        }

        if (is_array($plain)) {
            return $plain;
        }

        return ['value' => $plain];
    }

    /**
     * Collapse an unexportable value to a scalar marker.
     *
     * @param mixed $value Value that failed to export.
     * @return string Marker.
     */
    protected static function fallback(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return var_export($value, true);
        }

        if (is_object($value)) {
            return $value::class;
        }

        return get_debug_type($value);
    }
}
