<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use ArrayAccess;
use BackedEnum;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Utility\Hash;
use Closure;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\CallsiteCapture;
use ReflectionMethod;
use Throwable;
use UnitEnum;

/**
 * Authorization panel.
 *
 * Records ability checks from two unobstructive sources without changing
 * any decision:
 *
 * - the authorization service decorator (`DebugBar.Authorization.checked`,
 *   installed by `DebugBarAuthorizationMiddleware` after the authorization
 *   middleware): action, resource, identity, result and the resolved policy;
 * - CakeDC Auth vendor events (`Auth.Rbac.checked`,
 *   `Auth.Authorization.checked`): role, matched permission rule and reason.
 *
 * Values are never stored — resources keep class names, identifiers and
 * labels only. Result codes and HTTP statuses do not exist in CakePHP
 * checks, so the response section shows the message alone.
 */
class AuthorizationPanel extends DebugPanel
{
    /**
     * Raw captures keyed by insertion order.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $inbox = [];

    /**
     * Subscribes to authorization check events.
     *
     * @return void
     */
    public function initialize(): void
    {
        $manager = EventManager::instance();
        $manager->on('DebugBar.Authorization.checked', $this->captureServiceCheck(...));
        $manager->on('Auth.Rbac.checked', $this->captureRbacCheck(...));
        $manager->on('Auth.Authorization.checked', $this->captureSuperuserCheck(...));
    }

    /**
     * Records a service-decorator check.
     *
     * @param \Cake\Event\EventInterface $event Check event with user, action, resource and result.
     * @return void
     */
    public function captureServiceCheck(EventInterface $event): void
    {
        static::$inbox[] = [
            'kind' => 'service',
            'action' => (string)$event->getData('action'),
            'resource' => $event->getData('resource'),
            'optional_args' => array_values((array)$event->getData('optional_args')),
            'user' => $event->getData('user'),
            'allowed' => (bool)$event->getData('allowed'),
            'reason' => $this->resultReason($event->getData('result')),
            'policy' => $event->getData('policy'),
            'policy_method' => $event->getData('policy_method'),
            'policy_source' => $event->getData('policy_source'),
            'callsite' => $event->getData('callsite'),
            'stack' => $event->getData('stack'),
        ];
    }

    /**
     * Records a CakeDC Auth rule check.
     *
     * @param \Cake\Event\EventInterface $event Check event with role, user, request and permission.
     * @return void
     */
    public function captureRbacCheck(EventInterface $event): void
    {
        static::$inbox[] = [
            'kind' => 'rbac',
            'role' => $event->getData('role'),
            'user' => $event->getData('user'),
            'request' => $event->getData('request'),
            'permission' => $event->getData('permission'),
            'allowed' => (bool)$event->getData('allowed'),
            'reason' => $event->getData('reason'),
            'policy' => null,
            'callsite' => null,
            'stack' => [],
        ];
    }

    /**
     * Records a CakeDC Auth superuser check.
     *
     * @param \Cake\Event\EventInterface $event Check event with policy, role, user and request.
     * @return void
     */
    public function captureSuperuserCheck(EventInterface $event): void
    {
        static::$inbox[] = [
            'kind' => 'superuser',
            'role' => $event->getData('role'),
            'user' => $event->getData('user'),
            'request' => $event->getData('request'),
            'permission' => $event->getData('permission'),
            'allowed' => (bool)$event->getData('allowed'),
            'reason' => $event->getData('reason'),
            'policy' => $event->getData('policy'),
            'callsite' => null,
            'stack' => [],
        ];
    }

    /**
     * Resets captured checks (long-lived workers and tests).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$inbox = [];
    }

    /**
     * Get the data for this panel.
     *
     * Presents one decision per captured check with the ability, result,
     * user, arguments, handler and source — then drains the inbox so
     * workers never bleed across requests.
     *
     * @return array
     */
    public function data(): array
    {
        $decisions = [];
        foreach (array_values(static::$inbox) as $index => $raw) {
            $decisions[] = $this->present((array)$raw, $index + 1);
        }

        static::$inbox = [];

        $allowed = 0;
        $denied = 0;
        foreach ($decisions as $decision) {
            if ($decision['result'] === 'allowed') {
                $allowed++;
            } else {
                $denied++;
            }
        }

        return [
            'decisions' => $decisions,
            'summary' => [
                'count' => count($decisions),
                'allowed' => $allowed,
                'denied' => $denied,
            ],
        ];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        return '';
    }

    /**
     * Presents one raw capture as a decision row with detail fields.
     *
     * @param array<string, mixed> $raw Raw capture.
     * @param int $execution 1-based position.
     * @return array<string, mixed>
     */
    protected function present(array $raw, int $execution): array
    {
        $kind = (string)($raw['kind'] ?? 'service');
        $allowed = (bool)($raw['allowed'] ?? false);
        $result = $allowed ? 'allowed' : 'denied';

        if ($kind === 'service') {
            $ability = (string)($raw['action'] ?? '');
            $user = $this->presentUser($raw['user'] ?? null);
            $arguments = $this->presentArguments(
                $raw['resource'] ?? null,
                array_values((array)($raw['optional_args'] ?? [])),
                true,
            );
            $handler = $this->presentPolicyHandler(
                $raw['policy'] ?? null,
                $raw['policy_method'] ?? null,
                $raw['policy_source'] ?? null,
            );
            $role = $user['role'];
        } else {
            $ability = $this->requestAbility($raw['request'] ?? null);
            $user = $this->presentArrayUser($raw['user'] ?? null);
            $arguments = $this->presentRequestArguments($raw['request'] ?? null);
            $handler = $kind === 'superuser'
                ? $this->presentSuperuserHandler($raw['policy'] ?? null)
                : $this->presentRbacHandler();
            $eventRole = $raw['role'] ?? null;
            $role = is_string($eventRole) && trim($eventRole) !== ''
                ? trim($eventRole)
                : $user['role'];
        }

        if ($ability === '') {
            $ability = 'Ability unavailable';
        }

        $argumentSummary = $this->argumentSummary($arguments);
        $origin = match ($kind) {
            'superuser' => 'Superuser check',
            'rbac' => 'Rbac rule',
            default => 'Policy check',
        };
        $callsite = null;
        $rawCallsite = $raw['callsite'] ?? null;
        if (is_array($rawCallsite)) {
            $callsiteFile = $rawCallsite['file'] ?? null;
            $callsite = [
                'file' => is_string($callsiteFile) ? $callsiteFile : null,
                'line' => isset($rawCallsite['line']) ? (int)$rawCallsite['line'] : null,
            ];
        }

        $callsiteLabel = $callsite !== null ? CallsiteCapture::label($callsite) : null;
        if ($callsiteLabel === '') {
            $callsiteLabel = null;
        }

        $stack = array_values(array_filter(
            array_map(
                static fn(mixed $frame): array => (array)$frame,
                (array)($raw['stack'] ?? []),
            ),
            static fn(array $frame): bool => isset($frame['function']) || isset($frame['file']),
        ));
        $reason = $raw['reason'] ?? null;
        $reason = is_string($reason) && $reason !== '' ? $reason : null;
        if ($this->isStructuredMatchReason($reason)) {
            $reason = null;
        }

        $permission = $raw['permission'] ?? null;
        $matchedRule = is_array($permission) ? $this->normalizePermission($permission) : null;
        $matchedRulePretty = $matchedRule !== null
            ? json_encode($matchedRule, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;
        if (!is_string($matchedRulePretty)) {
            $matchedRulePretty = null;
        }

        $copy = [
            'result' => $result,
            'ability' => $ability,
            'origin' => $origin,
            'role' => $role,
            'user' => $user['raw'],
            'arguments' => $this->copyArguments($arguments),
            'authorization_logic' => [
                'kind' => $handler['kind'],
                'name' => $handler['name'],
                'source' => $handler['source'],
            ],
            'authorization_response' => ['message' => $reason, 'code' => null, 'status' => null],
        ];
        if ($matchedRule !== null) {
            $copy['matched_rule'] = $matchedRule;
        }

        $firstFrame = $stack[0] ?? null;
        $duplicatesStack = $callsite !== null
            && is_array($firstFrame)
            && ($callsite['file'] ?? null) === ($firstFrame['file'] ?? null)
            && ($callsite['line'] ?? null) === ($firstFrame['line'] ?? null);
        if ($callsite !== null && !$duplicatesStack) {
            $copy['checked_from'] = $callsite;
        }

        $copyEvidence = json_encode(
            $copy,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        if (!is_string($copyEvidence)) {
            $copyEvidence = '{}';
        }

        return [
            'execution' => $execution,
            'key' => 'authorization-' . $execution,
            'ability' => $ability,
            'origin' => $origin,
            'result' => $result,
            'result_label' => ucfirst($result),
            'result_message' => $reason,
            'result_code' => null,
            'result_status' => null,
            'user_label' => $user['label'],
            'user_type' => $user['type'],
            'user_identifier_name' => $user['identifier_name'],
            'user_identifier' => $user['identifier'],
            'role' => $role,
            'matched_rule_pretty' => $matchedRulePretty,
            'arguments' => $arguments,
            'argument_summary' => $argumentSummary,
            'handler_kind' => $handler['kind'],
            'handler_label' => $handler['label'],
            'handler_available' => $handler['available'],
            'handler_name' => $handler['name'],
            'handler_source_label' => $handler['source_label'],
            'callsite_label' => $callsiteLabel,
            'stack' => $stack,
            'copy_evidence' => $copyEvidence,
            'search' => mb_strtolower(implode(' ', array_filter([
                $ability,
                $origin,
                $result,
                $role,
                $user['label'],
                $user['type'],
                $argumentSummary,
                $handler['name'],
                $handler['source_label'],
                $callsiteLabel,
                $reason,
            ], is_scalar(...)))),
        ];
    }

    /**
     * Presents an identity object as a user row.
     *
     * @param mixed $user Checked identity or null.
     * @return array{label: string, type: string|null, identifier_name: string|null, identifier: mixed, role: string|null, raw: array<string, mixed>}
     */
    protected function presentUser(mixed $user): array
    {
        if (!is_object($user)) {
            return [
                'label' => 'Guest',
                'type' => null,
                'identifier_name' => null,
                'identifier' => null,
                'role' => null,
                'raw' => [],
            ];
        }

        $identifier = null;
        if (method_exists($user, 'getIdentifier')) {
            try {
                $identifier = $user->getIdentifier();
            } catch (Throwable) {
                $identifier = null;
            }
        }

        $original = null;
        if (method_exists($user, 'getOriginalData')) {
            try {
                $original = $user->getOriginalData();
            } catch (Throwable) {
                $original = null;
            }
        }

        if ($original instanceof EntityInterface) {
            $data = $original->toArray();
            $type = $original::class;
        } elseif (is_array($original) || $original instanceof ArrayAccess) {
            $data = (array)$original;
            $type = null;
        } elseif (is_object($original)) {
            $data = get_object_vars($original);
            $type = $original::class;
        } else {
            $data = [];
            $type = null;
        }

        if (!is_scalar($identifier)) {
            $identifier = null;
        }

        return $this->userRow($data, $type, $identifier);
    }

    /**
     * Presents an array user (CakeDC Auth) as a user row.
     *
     * The vendor passes the identity's original data, which is usually an
     * entity — a blind `(array)` cast exposes engine properties instead of
     * fields, so entities unwrap through `toArray()` first.
     *
     * @param mixed $user User data or null.
     * @return array{label: string, type: string|null, identifier_name: string|null, identifier: mixed, role: string|null, raw: array<string, mixed>}
     */
    protected function presentArrayUser(mixed $user): array
    {
        if ($user instanceof EntityInterface) {
            $data = $user->toArray();
            $type = $user::class;
        } elseif ($user instanceof ArrayAccess) {
            if (isset($user['User']) && (is_array($user['User']) || $user['User'] instanceof ArrayAccess)) {
                return $this->presentArrayUser($user['User']);
            }

            if (method_exists($user, 'getArrayCopy')) {
                /** @var array<string, mixed> $data */
                $data = (array)$user->getArrayCopy();
            } elseif (method_exists($user, 'toArray')) {
                /** @var array<string, mixed> $data */
                $data = (array)$user->toArray();
            } else {
                $data = (array)$user;
            }

            $type = $user::class;
        } elseif (is_array($user)) {
            if (isset($user['User']) && (is_array($user['User']) || $user['User'] instanceof ArrayAccess)) {
                return $this->presentArrayUser($user['User']);
            }

            $data = $user;
            $type = null;
        } else {
            return [
                'label' => 'Guest',
                'type' => null,
                'identifier_name' => null,
                'identifier' => null,
                'role' => null,
                'raw' => [],
            ];
        }

        $identifier = Hash::get($data, 'id');
        if (!is_scalar($identifier)) {
            $identifier = null;
        }

        return $this->userRow($data, $type, $identifier);
    }

    /**
     * Builds one user row with the display label.
     *
     * The name prefers the login fields over the display name so rows stay
     * recognizable across applications.
     *
     * @param array<string, mixed> $data User fields.
     * @param string|null $type Original data class.
     * @param mixed $identifier Scalar identifier or null.
     * @return array{label: string, type: string|null, identifier_name: string|null, identifier: mixed, role: string|null, raw: array<string, mixed>}
     */
    protected function userRow(array $data, ?string $type, mixed $identifier): array
    {
        $name = null;
        foreach (['username', 'email', 'name'] as $field) {
            $candidate = $data[$field] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                $name = trim($candidate);
                break;
            }
        }

        $short = $type !== null ? $this->shortClass($type) : null;
        $role = $data['role'] ?? null;
        $role = is_string($role) && trim($role) !== '' ? trim($role) : null;

        $label = 'Guest';
        if ($type === null && $name === null && $identifier === null) {
            $label = 'Guest';
        } elseif ($name !== null) {
            $label = $name;
        } elseif ($identifier !== null && $short !== null) {
            $label = $short . ' ' . $identifier;
        } elseif ($short !== null) {
            $label = $short;
        }

        return [
            'label' => $label,
            'type' => $type,
            'identifier_name' => $identifier !== null ? 'id' : null,
            'identifier' => $identifier,
            'role' => $role,
            'raw' => ['type' => $type, 'identifier' => $identifier, 'name' => $name],
        ];
    }

    /**
     * Presents the resource plus extra policy arguments.
     *
     * @param mixed $resource Checked resource.
     * @param array<int, mixed> $extra Extra policy arguments.
     * @param bool $isPolicy Whether a policy decides (drives role labels).
     * @return array<int, array{position: int, role_label: string, kind: string, type: string, label: string, identity_label: string|null}>
     */
    protected function presentArguments(mixed $resource, array $extra, bool $isPolicy): array
    {
        $subjects = [$resource, ...$extra];
        $arguments = [];
        foreach ($subjects as $index => $subject) {
            if ($index === 0 && $subject === null) {
                continue;
            }

            $position = $index + 1;
            $entry = $this->presentArgument($subject, $position);
            $entry['role_label'] = match (true) {
                $isPolicy && $position === 1 => 'Resource',
                $isPolicy => 'Additional context ' . ($position - 1),
                default => 'Argument ' . $position,
            };
            $arguments[] = $entry;
        }

        return $arguments;
    }

    /**
     * Presents one argument value without storing the value itself.
     *
     * @param mixed $subject Argument value.
     * @param int $position 1-based position.
     * @return array{position: int, role_label: string, kind: string, type: string, label: string, identity_label: string|null}
     */
    protected function presentArgument(mixed $subject, int $position): array
    {
        if ($subject instanceof EntityInterface) {
            $type = $subject::class;
            $identifier = $subject->has('id') ? $subject->get('id') : null;
            if (!is_scalar($identifier)) {
                $identifier = null;
            }

            $label = $this->shortClass($type) . ($identifier !== null ? ' ' . $identifier : '');

            return [
                'position' => $position,
                'role_label' => 'Resource',
                'kind' => 'model',
                'type' => $type,
                'label' => $label,
                'identity_label' => $identifier !== null ? 'Identifier ' . $identifier : null,
            ];
        }

        if (is_object($subject)) {
            $type = $subject::class;

            return [
                'position' => $position,
                'role_label' => 'Resource',
                'kind' => 'object',
                'type' => $type,
                'label' => $this->shortClass($type),
                'identity_label' => null,
            ];
        }

        if (is_string($subject) && class_exists($subject)) {
            return [
                'position' => $position,
                'role_label' => 'Resource',
                'kind' => 'object',
                'type' => $subject,
                'label' => $this->shortClass($subject),
                'identity_label' => null,
            ];
        }

        $type = get_debug_type($subject);
        $label = match (true) {
            is_bool($subject) => $subject ? 'true' : 'false',
            $subject === null => 'null',
            is_scalar($subject) => (string)$subject,
            default => $type,
        };

        return [
            'position' => $position,
            'role_label' => 'Resource',
            'kind' => 'value',
            'type' => $type,
            'label' => $label,
            'identity_label' => is_scalar($subject) ? 'Value ' . $subject : null,
        ];
    }

    /**
     * Presents the checked request target as arguments.
     *
     * A rule check takes the request itself as its resource, so the
     * controller/action/prefix/plugin behind the ability render as the
     * argument rows — the CakePHP answer to "what was checked".
     *
     * @param mixed $request Checked request or null.
     * @return array<int, array{position: int, role_label: string, kind: string, type: string, label: string, identity_label: string|null}>
     */
    protected function presentRequestArguments(mixed $request): array
    {
        if (!is_object($request) || !method_exists($request, 'getAttribute')) {
            return [];
        }

        try {
            $params = (array)$request->getAttribute('params');
        } catch (Throwable) {
            return [];
        }

        $rows = [];
        $position = 0;
        foreach (['Controller', 'Action', 'Prefix', 'Plugin'] as $field) {
            $value = $params[strtolower($field)] ?? null;
            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            if ($field === 'Prefix' || $field === 'Plugin') {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }

            $position++;
            $rows[] = [
                'position' => $position,
                'role_label' => $field,
                'kind' => 'value',
                'type' => 'string',
                'label' => $value,
                'identity_label' => null,
            ];
        }

        return $rows;
    }

    /**
     * Summarizes arguments for the list row.
     *
     * @param array<int, array<string, mixed>> $arguments Presented arguments.
     * @return string Summary label.
     */
    protected function argumentSummary(array $arguments): string
    {
        $count = count($arguments);
        if ($count === 0) {
            return '—';
        }

        if ($count === 1) {
            return (string)$arguments[0]['label'];
        }

        return $arguments[0]['label'] . ' and ' . ($count - 1) . ' more';
    }

    /**
     * Presents the resolved policy as the handler row.
     *
     * @param mixed $policy Policy class name.
     * @param mixed $method Policy method name.
     * @param mixed $source Source pair or null.
     * @return array{kind: string, label: string, available: bool, name: string, source: array|null, source_label: string|null}
     */
    protected function presentPolicyHandler(mixed $policy, mixed $method, mixed $source): array
    {
        if (!is_string($policy) || $policy === '') {
            return [
                'kind' => 'policy',
                'label' => 'Authorization logic',
                'available' => false,
                'name' => 'Authorization logic',
                'source' => null,
                'source_label' => null,
            ];
        }

        $name = $policy . '@' . (is_string($method) && $method !== '' ? $method : 'can');
        $sourceLabel = null;
        if (is_array($source) && isset($source['file']) && is_string($source['file'])) {
            $sourceLabel = CallsiteCapture::label([
                'file' => $source['file'],
                'line' => isset($source['line']) ? (int)$source['line'] : null,
            ]);
        }

        return [
            'kind' => 'policy',
            'label' => 'Matched policy method',
            'available' => true,
            'name' => $name,
            'source' => is_array($source) ? $source : null,
            'source_label' => $sourceLabel !== '' ? $sourceLabel : null,
        ];
    }

    /**
     * Presents the CakeDC Auth rule match as the handler row.
     *
     * @return array{kind: string, label: string, available: bool, name: string, source: array|null, source_label: string|null}
     */
    protected function presentRbacHandler(): array
    {
        return [
            'kind' => 'callback',
            'label' => 'Matched check callback',
            'available' => true,
            'name' => 'Rbac permission rule',
            'source' => null,
            'source_label' => null,
        ];
    }

    /**
     * Presents the superuser policy as the handler row.
     *
     * @param mixed $policy Policy class name.
     * @return array{kind: string, label: string, available: bool, name: string, source: array|null, source_label: string|null}
     */
    protected function presentSuperuserHandler(mixed $policy): array
    {
        $class = is_string($policy) && $policy !== '' ? $policy : null;
        $sourceLabel = null;
        $source = null;
        if ($class !== null && class_exists($class)) {
            try {
                $method = new ReflectionMethod($class, 'canAccess');
                $file = $method->getFileName();
                $line = $method->getStartLine();
                $source = [
                    'file' => is_string($file) ? $file : null,
                    'line' => $line === false ? null : $line,
                ];
                $label = CallsiteCapture::label([
                    'file' => is_string($file) ? $file : '',
                    'line' => $line === false ? null : $line,
                ]);
                $sourceLabel = $label !== '' ? $label : null;
            } catch (Throwable) {
                $source = null;
            }
        }

        return [
            'kind' => 'policy',
            'label' => 'Matched policy method',
            'available' => $class !== null,
            'name' => $class !== null ? $class . '@canAccess' : 'Authorization logic',
            'source' => $source,
            'source_label' => $sourceLabel,
        ];
    }

    /**
     * Derives the ability label from a checked request.
     *
     * @param mixed $request Checked request or null.
     * @return string `Controller/action`, the path, or empty.
     */
    protected function requestAbility(mixed $request): string
    {
        if (!is_object($request) || !method_exists($request, 'getAttribute')) {
            return '';
        }

        try {
            $params = (array)$request->getAttribute('params');
        } catch (Throwable) {
            return '';
        }

        $controller = is_string($params['controller'] ?? null) ? $params['controller'] : '';
        $action = is_string($params['action'] ?? null) ? $params['action'] : '';
        $ability = trim($controller . '/' . $action, '/');
        if ($ability !== '') {
            return $ability;
        }

        if (method_exists($request, 'getUri')) {
            try {
                return $request->getUri()->getPath() ?: '';
            } catch (Throwable) {
                return '';
            }
        }

        return '';
    }

    /**
     * Reads the failure reason off an authorization result.
     *
     * @param mixed $result Result object or null.
     * @return string|null Reason or null.
     */
    protected function resultReason(mixed $result): ?string
    {
        if (!is_object($result) || !method_exists($result, 'getReason')) {
            return null;
        }

        try {
            $reason = $result->getReason();
        } catch (Throwable) {
            return null;
        }

        return is_string($reason) && $reason !== '' ? $reason : null;
    }

    /**
     * Detects the vendor debug reason that duplicates checked + rule JSON.
     *
     * `For {...} --> Rule matched {...}` restates the request context and
     * the matched rule already stored beside it, so the message stays empty
     * and the rule renders in its own block instead.
     *
     * @param string|null $reason Raw reason.
     * @return bool
     */
    protected function isStructuredMatchReason(?string $reason): bool
    {
        if ($reason === null || $reason === '') {
            return false;
        }

        return (bool)preg_match('/^For .+ --> Rule matched/s', $reason);
    }

    /**
     * Builds the JSON-safe arguments for the copy evidence.
     *
     * @param array<int, array<string, mixed>> $arguments Presented arguments.
     * @return array<int, mixed>
     */
    protected function copyArguments(array $arguments): array
    {
        $copy = [];
        foreach ($arguments as $argument) {
            $copy[] = [
                'position' => $argument['position'],
                'kind' => $argument['kind'],
                'type' => $argument['type'],
            ];
        }

        return $copy;
    }

    /**
     * Normalizes a permission rule for JSON storage (objects to names only).
     *
     * @param array<string, mixed> $permission Raw permission rule.
     * @return array<string, mixed>
     */
    protected function normalizePermission(array $permission): array
    {
        $normalized = [];
        foreach ($permission as $key => $value) {
            $normalized[(string)$key] = $this->normalizePermissionValue($value);
        }

        return $normalized;
    }

    /**
     * Normalizes one raw permission value for display.
     *
     * @param mixed $value Permission value.
     * @return mixed
     */
    protected function normalizePermissionValue(mixed $value): mixed
    {
        if ($value instanceof Closure) {
            return 'Closure';
        }

        if ($value instanceof UnitEnum) {
            return $value instanceof BackedEnum ? $value->value : $value->name;
        }

        if (is_object($value)) {
            return $value::class;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $out[(string)$key] = $this->normalizePermissionValue($item);
            }

            return $out;
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return (string)$value;
    }

    /**
     * Shortens a class name to its last segment.
     *
     * @param string $class Fully qualified class name.
     * @return string Short name.
     */
    protected function shortClass(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
