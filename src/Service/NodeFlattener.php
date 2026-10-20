<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use BackedEnum;
use Cake\Error\Debug\ArrayItemNode;
use Cake\Error\Debug\ArrayNode;
use Cake\Error\Debug\ClassNode;
use Cake\Error\Debug\NodeInterface;
use Cake\Error\Debug\PropertyNode;
use Cake\Error\Debug\ScalarNode;
use Cake\Error\Debug\SpecialNode;
use Stringable;
use UnitEnum;

/**
 * Converts captured debug nodes back to plain values.
 *
 * Panels store `Debugger::exportVarAsNodes()` trees; inspectors that render
 * Name/Value tables (request details) need plain scalars and arrays again.
 * Depth-capped so deep graphs render as a marker instead of exploding.
 */
class NodeFlattener
{
    /**
     * Flattens one node to plain PHP values.
     *
     * @param \Cake\Error\Debug\NodeInterface $node Node to convert.
     * @param int $depth Remaining depth before truncation.
     * @return mixed Plain value.
     */
    public static function toPlain(NodeInterface $node, int $depth = 10): mixed
    {
        if ($node instanceof ScalarNode) {
            return static::plainScalar($node->getValue());
        }

        if ($node instanceof SpecialNode) {
            return $node->getValue();
        }

        if ($depth <= 0) {
            return '…';
        }

        if ($node instanceof ArrayNode) {
            $plain = [];
            foreach ($node->getChildren() as $item) {
                $key = static::toPlain($item->getKey(), 0);
                if (!is_string($key) && !is_int($key)) {
                    $key = (string)$key;
                }

                $plain[$key] = static::toPlain($item->getValue(), $depth - 1);
            }

            return $plain;
        }

        if ($node instanceof ClassNode) {
            $plain = ['__class' => $node->getValue()];
            foreach ($node->getChildren() as $property) {
                $name = $property->getName();
                if (strlen($name) > 2 && str_starts_with($name, "'") && str_ends_with($name, "'")) {
                    $name = substr($name, 1, -1);
                }

                $plain[$name] = static::toPlain($property->getValue(), $depth - 1);
            }

            return $plain;
        }

        return $node->getValue();
    }

    /**
     * Guards scalar payloads: enums, resources and objects are not JSON-safe.
     *
     * @param mixed $value Raw scalar node value.
     * @return mixed JSON-safe value.
     */
    protected static function plainScalar(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_resource($value)) {
            return 'resource(' . get_resource_type($value) . ')';
        }

        if (is_object($value)) {
            if ($value instanceof Stringable) {
                return (string)$value;
            }

            return 'object(' . $value::class . ')';
        }

        return $value;
    }

    /**
     * Pretty-prints flattened values as JSON, degrading per key.
     *
     * A single unencodable value must never blank the whole block: when the
     * full encode fails, each top-level key encodes alone and failures
     * become typed markers.
     *
     * @param mixed $value Flattened value.
     * @return string|null Pretty JSON or null when nothing encodable remains.
     */
    public static function jsonPretty(mixed $value): ?string
    {
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;
        $json = json_encode($value, $flags);
        if (is_string($json)) {
            return $json;
        }

        if (!is_array($value)) {
            return null;
        }

        $safe = [];
        foreach ($value as $key => $item) {
            $one = json_encode($item, $flags);
            $safe[$key] = is_string($one) ? json_decode($one, true) : '[unencodable ' . get_debug_type($item) . ']';
        }

        $json = json_encode($safe, $flags);

        return is_string($json) ? $json : null;
    }

    /**
     * Masks secret leaves of a node tree, rebuilding it.
     *
     * Generic across plugins: only the leaf NAME decides — array items
     * and object properties matching `RedactionService::isSensitiveKey()`
     * become `********`, everything around them (configs, resolvers,
     * identities, tokens metadata) stays visible. Attribute and tab names
     * themselves are never matched, so `authorization` or `formTokenData`
     * entries keep rendering with only their secret leaves blanked.
     *
     * @param \Cake\Error\Debug\NodeInterface $node Tree to mask.
     * @return \Cake\Error\Debug\NodeInterface Masked tree.
     */
    public static function maskSensitive(NodeInterface $node): NodeInterface
    {
        if ($node instanceof ArrayNode) {
            $masked = new ArrayNode();
            foreach ($node->getChildren() as $item) {
                $key = $item->getKey();
                $value = $item->getValue();
                $name = $key instanceof ScalarNode ? (string)$key->getValue() : null;
                if ($name !== null && RedactionService::isSensitiveKey($name) && !static::isPlainBool($value)) {
                    $value = new ScalarNode('string', '********');
                } else {
                    $value = static::maskSensitive($value);
                }

                $masked->add(new ArrayItemNode($key, $value));
            }

            return $masked;
        }

        if ($node instanceof ClassNode) {
            $masked = new ClassNode($node->getValue(), $node->getId());
            foreach ($node->getChildren() as $property) {
                $name = $property->getName();
                if (strlen($name) > 2 && str_starts_with($name, "'") && str_ends_with($name, "'")) {
                    $name = substr($name, 1, -1);
                }

                $value = $property->getValue();
                if (RedactionService::isSensitiveKey($name) && !static::isPlainBool($value)) {
                    $value = new ScalarNode('string', '********');
                } else {
                    $value = static::maskSensitive($value);
                }

                $masked->addProperty(
                    new PropertyNode($property->getName(), $property->getVisibility(), $value),
                );
            }

            return $masked;
        }

        return $node;
    }

    /**
     * Reports whether a node is a plain boolean scalar.
     *
     * Booleans carry no secret payload, so sensitive-name masking skips
     * them instead of replacing e.g. `password => true` with `********`.
     *
     * @param \Cake\Error\Debug\NodeInterface $node Node to test.
     * @return bool True for boolean scalar nodes.
     */
    protected static function isPlainBool(NodeInterface $node): bool
    {
        return $node instanceof ScalarNode && is_bool($node->getValue());
    }

    /**
     * Flattens a keyed node map to a plain array.
     *
     * @param array<string, mixed> $nodes Nodes by name (non-nodes skipped).
     * @param int $depth Remaining depth before truncation.
     * @return array<string, mixed> Plain values by name.
     */
    public static function map(array $nodes, int $depth = 10): array
    {
        $plain = [];
        foreach ($nodes as $key => $node) {
            if ($node instanceof NodeInterface) {
                $plain[(string)$key] = static::toPlain($node, $depth);
            }
        }

        return $plain;
    }
}
