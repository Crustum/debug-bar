<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Collection\CollectionInterface;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Paging\PaginatedInterface;
use Cake\Datasource\QueryInterface;
use Cake\Error\Debug\ArrayNode;
use Cake\Error\Debugger;
use Cake\Event\EventInterface;
use Cake\Form\Form;
use Cake\Utility\Hash;
use Crustum\DebugBar\DebugPanel;
use Exception;
use Throwable;
use Traversable;

/**
 * Provides debug information on the View variables.
 *
 * @ported-from \DebugKit\Panel\VariablesPanel
 */
class VariablesPanel extends DebugPanel
{
    protected const RESULT_LIMIT = 100;

    /**
     * Extracts nested validation errors.
     *
     * Delegates to `EntityTrait::getErrors()`, which already walks nested
     * entities cycle-safe (`_hasBeenVisited`). A manual walk here used to
     * recurse unboundedly over user→role→user graphs, stalling php-cgi
     * until the memory limit killed it — and duplicated what getErrors()
     * already returns.
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to extract
     * @param array<int, true> $ancestors Unused, kept for BC.
     * @param int $depth Unused, kept for BC.
     * @return array
     */
    protected function _getErrors(EntityInterface $entity, array $ancestors = [], int $depth = 0): array
    {
        return $entity->getErrors();
    }

    /**
     * Safely retrieves debug information from an object
     * and applies a callback.
     *
     * @param callable $walker The walker to apply on the debug info array.
     * @param object $item The item whose debug info to retrieve.
     * @return array|string
     */
    protected function _walkDebugInfo(callable $walker, object $item): array|string
    {
        try {
            /** @phpstan-ignore method.notFound */
            $info = $item->__debugInfo();
        } catch (Exception $exception) {
            return sprintf(
                'Could not retrieve debug info - %s. Error: %s in %s, line %d',
                $item::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            );
        }

        return array_map($walker, $info);
    }

    /**
     * Materializes paginated and lazy collection view vars before export.
     *
     * `Debugger::exportVarAsNodes()` spends one depth level per wrapper
     * (`PaginatedResultSet` → `ResultSet` → entity), so entities land exactly
     * on the depth cap and flatten to bare `__class` markers the tree renders
     * as `[]`. Unwrapping to plain arrays (capped at `RESULT_LIMIT`) keeps
     * entities inside the budget. Queries are left untouched so shutdown never
     * executes them implicitly.
     *
     * @param mixed $value Raw view variable value.
     * @param int $depth Recursion guard for nested arrays.
     * @return mixed Plain array or the untouched value.
     */
    protected function normalizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($value instanceof PaginatedInterface) {
            try {
                $items = [];
                foreach ($value->items() as $key => $item) {
                    if (count($items) >= static::RESULT_LIMIT) {
                        break;
                    }

                    $items[$key] = $item;
                }

                return [
                    'items' => $items,
                    'pagingParams' => $value->pagingParams(),
                ];
            } catch (Throwable) {
                return $value;
            }
        }

        if ($value instanceof CollectionInterface) {
            try {
                return $value->take(static::RESULT_LIMIT)->toArray();
            } catch (Throwable) {
                return $value;
            }
        }

        if ($value instanceof Traversable && !$value instanceof QueryInterface) {
            try {
                $items = [];
                foreach ($value as $key => $item) {
                    if (count($items) >= static::RESULT_LIMIT) {
                        break;
                    }

                    if (is_int($key) || is_string($key)) {
                        $items[$key] = $item;
                    } else {
                        $items[] = $item;
                    }
                }

                return $items;
            } catch (Throwable) {
                return $value;
            }
        }

        if (is_array($value) && $depth < 2) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalizeValue($item, $depth + 1);
            }
        }

        return $value;
    }

    /**
     * Shutdown event
     *
     * All view values export in one node walk so shared subgraphs
     * (connections, repositories, the same entity in several vars)
     * traverse once. Per-var exports repeated the walk per var and
     * OOMed php-cgi on real pages.
     *
     * @param \Cake\Event\EventInterface $event The event
     * @return void
     */
    public function shutdown(EventInterface $event): void
    {
        /** @var \Cake\Controller\Controller $controller */
        $controller = $event->getSubject();
        $errors = [];
        $values = [];
        $vars = $controller->viewBuilder()->getVars();
        $varsMaxDepth = (int)Configure::read('DebugBar.variablesPanelMaxDepth', 5);

        foreach ($vars as $k => $v) {
            if ($v instanceof EntityInterface) {
                $entityErrors = Hash::filter($this->_getErrors($v));
                if ($entityErrors !== []) {
                    $errors[$k] = $entityErrors;
                }
            } elseif ($v instanceof Form) {
                $formErrors = $v->getErrors();
                if ($formErrors !== []) {
                    $errors[$k] = $formErrors;
                }
            }

            $values[$k] = $this->normalizeValue($v);
        }

        ksort($values, SORT_NATURAL | SORT_FLAG_CASE);

        $content = [];
        $root = Debugger::exportVarAsNodes($values, max(1, $varsMaxDepth));
        if ($root instanceof ArrayNode) {
            foreach ($root->getChildren() as $item) {
                $content[(string)$item->getKey()->getValue()] = $item->getValue();
            }
        }

        $this->_data = [
            'variables' => $content,
            'errors' => $errors,
            'varsMaxDepth' => $varsMaxDepth,
        ];
    }

    /**
     * Get summary data for the variables panel.
     *
     * @return string
     */
    public function summary(): string
    {
        if (!isset($this->_data['variables'])) {
            return '0';
        }

        return (string)count($this->_data['variables']);
    }
}
