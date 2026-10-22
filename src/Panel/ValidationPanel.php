<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Form\Form;
use Cake\Utility\Hash;
use Crustum\DebugBar\DebugPanel;
use SplObjectStorage;

/**
 * Validation Panel - explicit validation failures.
 *
 * CakePHP port of the upstream validation inspector: on shutdown the
 * panel walks the controller view vars exactly like the DebugKit
 * variables panel does — entities (recursively, including nested
 * entities) via `getErrors()`, plain forms via `getErrors()` — and
 * flattens the trees to one row per field. Static by design: stacked
 * articles, no search/filter.
 */
class ValidationPanel extends DebugPanel
{
    /**
     * Collects validation errors from the controller view vars.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The shutdown event.
     * @return void
     */
    public function shutdown(EventInterface $event): void
    {
        /** @var \Cake\Controller\Controller $controller */
        $controller = $event->getSubject();
        $response = $controller->getResponse();
        $status = $response->getStatusCode();
        $redirect = $response->getHeaderLine('Location');

        $errors = [];
        foreach ($controller->viewBuilder()->getVars() as $name => $value) {
            if ($value instanceof EntityInterface) {
                $tree = Hash::filter($this->extractErrors($value));
            } elseif ($value instanceof Form) {
                $tree = Hash::filter($value->getErrors());
            } else {
                continue;
            }

            foreach ($this->flattenErrors($tree) as $field => $rules) {
                $messages = [];
                $failed = [];
                foreach ((array)$rules as $rule => $message) {
                    $failed[] = (string)$rule;
                    $messages[] = is_string($message) ? $message : (string)$rule;
                }

                if ($failed === []) {
                    continue;
                }

                $errors[] = [
                    'field' => $name . '.' . $field,
                    'message' => implode('; ', $messages),
                    'bag' => (string)$name,
                    'status' => $status,
                    'redirect' => $redirect,
                    'failed_rules' => $failed,
                ];
            }
        }

        $this->_data = ['errors' => $errors];
    }

    /**
     * Extracts nested validation errors from an entity.
     *
     * Mirrors the DebugKit variables panel walk: own errors plus the
     * errors of nested entities and arrays of entities. Two guards keep
     * the walk safe on real graphs (this is what hangs the variables
     * panel): visited objects are never re-entered (association cycles
     * like user → roles → users), and only loaded properties are read
     * (`has()` never lazy-loads an association). Depth is capped.
     *
     * @param \Cake\Datasource\EntityInterface $entity Entity to extract.
     * @param \SplObjectStorage|null $seen Visited entities for cycle guard.
     * @param int $depth Current nesting depth.
     * @return array
     */
    protected function extractErrors(
        EntityInterface $entity,
        ?SplObjectStorage $seen = null,
        int $depth = 0,
    ): array {
        $seen ??= new SplObjectStorage();
        if ($seen->offsetExists($entity) || $depth > 5) {
            return [];
        }

        $seen->offsetSet($entity);
        $errors = $entity->getErrors();

        foreach ($entity->getVisible() as $property) {
            if (!$entity->has($property)) {
                continue;
            }

            $value = $entity->get($property);
            if ($value instanceof EntityInterface) {
                $errors[$property] = $this->extractErrors($value, $seen, $depth + 1);
            } elseif (is_array($value)) {
                foreach ($value as $key => $item) {
                    if ($item instanceof EntityInterface) {
                        $errors[$property][$key] = $this->extractErrors($item, $seen, $depth + 1);
                    }
                }
            }
        }

        return Hash::filter($errors);
    }

    /**
     * Flattens a nested error tree to dotted field paths.
     *
     * @param array $tree Nested `field => [rule => message]` tree.
     * @param string $prefix Current path prefix.
     * @return array<string, array> Dotted path to leaf rule maps.
     */
    protected function flattenErrors(array $tree, string $prefix = ''): array
    {
        $flat = [];
        foreach ($tree as $field => $rules) {
            $path = $prefix === '' ? (string)$field : $prefix . '.' . $field;
            if (!is_array($rules)) {
                $flat[$path] = ['error' => $rules];
                continue;
            }

            $leaf = true;
            foreach ($rules as $value) {
                if (is_array($value)) {
                    $leaf = false;
                    break;
                }
            }

            if ($leaf) {
                $flat[$path] = $rules;
            } else {
                $flat += $this->flattenErrors($rules, $path);
            }
        }

        return $flat;
    }

    /**
     * Get the panel data collected at shutdown.
     *
     * Each error row: `field`, `message`, `bag`, `status`, `redirect`,
     * `failed_rules` (list of rule names).
     *
     * @return array
    /**
     * Get the panel data collected at shutdown.
     *
     * Each error row: `field`, `message`, `bag`, `status`, `redirect`,
     * `failed_rules` (list of rule names).
     *
     * @return array
     */
    public function data(): array
    {
        return ['errors' => array_values((array)($this->_data['errors'] ?? []))];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        return (string)count((array)($this->_data['errors'] ?? []));
    }
}
