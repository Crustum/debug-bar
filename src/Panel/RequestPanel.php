<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use ArrayAccess;
use Authentication\Authenticator\ResultInterface;
use Authentication\IdentityInterface;
use Cake\Console\Arguments;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Error\Debug\NodeInterface;
use Cake\Error\Debugger;
use Cake\Event\EventInterface;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\NodeFlattener;
use Crustum\DebugBar\Service\RouteFacts;
use Exception;
use Throwable;

/**
 * Provides debug information on the Current request params.
 *
 * @inspired-by \DebugKit\Panel\RequestPanel
 */
class RequestPanel extends DebugPanel
{
    /**
     * Subscribes the panel to web and console lifecycle events.
     *
     * A command run is a request with CLI context: same entry point,
     * conditional rendering.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Controller.startup' => 'startup',
            'Controller.shutdown' => 'shutdown',
            'Command.beforeExecute' => 'captureCommandStart',
            'Command.afterExecute' => 'captureCommandFinish',
        ];
    }

    /**
     * Starts one command run timer.
     *
     * Captures the invocation payload (named arguments, passed options,
     * parser definition, environment, copyable command line) so the CLI
     * branch renders the same perfect-UI timeline as web requests.
     *
     * @param \Cake\Event\EventInterface $event Command start event.
     * @return void
     */
    public function captureCommandStart(EventInterface $event): void
    {
        $command = $event->getSubject();
        if (!is_object($command)) {
            return;
        }

        $this->startCommand($command, $event->getData('args'));
    }

    /**
     * Records one unit of work directly.
     *
     * Panels attach to the event manager while their own start event is
     * already dispatching, so listeners created at open time miss it —
     * captures call this instead of relying on the event. Accepts any
     * object (commands read `getName()`/`getOptionParser()` when
     * present) or a plain class/name string, so queue workers and other
     * third-party captures push context without depending on console
     * shapes.
     *
     * @param object|string $command Command instance or class/name string.
     * @param mixed $args Event arguments payload.
     * @return void
     */
    public function startCommand(object|string $command, mixed $args): void
    {
        $class = is_string($command) ? $command : $command::class;
        $target = is_object($command) ? $command : null;
        $name = $this->commandDisplayName($target ?? $class);
        $values = $this->commandArgValues($args);
        $options = $target !== null ? $this->commandOptions($target, $args) : [];
        $this->data = [
            'context' => 'cli',
            'command' => $class,
            'label' => $this->short(ltrim($class, '\\')),
            'name' => $name,
            'arguments' => $this->commandNamedArgs($target ?? $class, $values),
            'options' => $options,
            'definition' => $target !== null ? $this->commandDefinition($target) : ['arguments' => [], 'options' => []],
            'environment' => $this->commandEnvironment(),
            'copy_command' => $this->buildCopyCommand($name, $values, $options),
            'result' => null,
            'exit_code' => null,
            'started' => microtime(true),
            'duration_ms' => 0.0,
        ];
    }

    /**
     * Completes one command run.
     *
     * @param \Cake\Event\EventInterface $event Command finish event.
     * @return void
     */
    public function captureCommandFinish(EventInterface $event): void
    {
        $this->finishCommand($event->getData('result'));
    }

    /**
     * Completes one command run directly.
     *
     * Shared with the finish event so the capture path and the event
     * path resolve the same exit code and duration. An explicit code
     * wins — queue workers report processor outcomes (`ack`, `reject`,
     * `requeue`) as the result with their own attention code.
     *
     * @param mixed $result Command result.
     * @param int|null $exitCode Explicit attention code or null to derive it.
     * @return void
     */
    public function finishCommand(mixed $result, ?int $exitCode = null): void
    {
        if (($this->data['context'] ?? '') !== 'cli') {
            return;
        }

        $this->data['result'] = is_scalar($result) ? $result : null;
        $this->data['exit_code'] = $exitCode ?? (is_int($result) ? $result : 0);
        $this->data['duration_ms'] = (microtime(true) - (float)$this->data['started']) * 1000;
    }

    /**
     * Reads console positional values as a plain list.
     *
     * Shared by the named-argument pairing and the copyable command
     * line; parser names resolve separately so raw order never shifts.
     *
     * @param mixed $args Event arguments payload.
     * @return array<int, mixed>
     */
    protected function commandArgValues(mixed $args): array
    {
        if ($args instanceof Arguments) {
            try {
                return array_values($args->getArguments());
            } catch (Throwable) {
                return [];
            }
        }

        return is_array($args) ? array_values($args) : [];
    }

    /**
     * Pairs positional values with parser argument names by position.
     *
     * Extra values beyond the declared names keep a null name instead
     * of being dropped, so the run renders exactly what was passed.
     *
     * @param object|string $command Command instance or class string.
     * @param array<int, mixed> $values Positional values in order.
     * @return array<int, array{name: string|null, value: mixed}>
     */
    protected function commandNamedArgs(object|string $command, array $values): array
    {
        $names = [];
        if (is_object($command) && method_exists($command, 'getOptionParser')) {
            try {
                $parser = $command->getOptionParser();
                if (is_object($parser) && method_exists($parser, 'argumentNames')) {
                    $listed = $parser->argumentNames();
                    $names = is_array($listed) ? array_values($listed) : [];
                } elseif (is_object($parser) && method_exists($parser, 'arguments')) {
                    foreach ((array)$parser->arguments() as $argument) {
                        if (is_object($argument) && method_exists($argument, 'name')) {
                            $names[] = (string)$argument->name();
                        }
                    }
                }
            } catch (Throwable) {
                $names = [];
            }
        }

        $paired = [];
        foreach ($values as $index => $value) {
            $paired[] = [
                'name' => isset($names[$index]) && is_string($names[$index]) ? $names[$index] : null,
                'value' => $value,
            ];
        }

        return $paired;
    }

    /**
     * Resolves the display name of a command run.
     *
     * Prefers the routed command name (`users add_user`) over the class
     * short name, which is what the operator actually typed. Plain
     * strings pass through (class strings shorten to their base name).
     *
     * @param object|string $command Command instance or class/name string.
     * @return string
     */
    protected function commandDisplayName(object|string $command): string
    {
        if (is_string($command)) {
            return $this->short($command);
        }

        if (method_exists($command, 'getName')) {
            try {
                $name = (string)$command->getName();
                if ($name !== '') {
                    return $name;
                }
            } catch (Throwable) {
            }
        }

        return $this->short($command::class);
    }

    /**
     * Reads only options differing from parser defaults.
     *
     * The parsed payload merges defaults in, so everything ever
     * definable would render as noise — comparing against the command
     * parser keeps options the run actually passed.
     *
     * @param object $command Command instance.
     * @param mixed $args Event arguments payload.
     * @return array<string, mixed>
     */
    protected function commandOptions(object $command, mixed $args): array
    {
        $values = [];
        if ($args instanceof Arguments) {
            try {
                $values = $args->getOptions();
            } catch (Throwable) {
                return [];
            }
        } elseif (is_array($args)) {
            $values = $args;
        }

        if ($values === [] || !method_exists($command, 'getOptionParser')) {
            return $values;
        }

        try {
            $parser = $command->getOptionParser();
        } catch (Throwable) {
            return $values;
        }

        if (!is_object($parser) || !method_exists($parser, 'options')) {
            return $values;
        }

        try {
            $defined = $parser->options();
        } catch (Throwable) {
            return $values;
        }

        if (!is_array($defined)) {
            return $values;
        }

        $shown = [];
        foreach ($values as $name => $value) {
            $option = $defined[$name] ?? null;
            if (
                is_object($option)
                && method_exists($option, 'defaultValue')
                && $this->equalsDefault($option, $value)
            ) {
                continue;
            }

            $shown[$name] = $value;
        }

        return $shown;
    }

    /**
     * Dumps the parser structure as plain data.
     *
     * The reference behind the Definition tab: declared argument and
     * option names with flags, defaults and choices — the contract the
     * run was validated against. Help prose stays out; it belongs to
     * `--help`, not to a debug row.
     *
     * @param object $command Command instance.
     * @return array{arguments: array<int, array<string, mixed>>, options: array<int, array<string, mixed>>}
     */
    protected function commandDefinition(object $command): array
    {
        if (!method_exists($command, 'getOptionParser')) {
            return ['arguments' => [], 'options' => []];
        }

        try {
            $parser = $command->getOptionParser();
        } catch (Throwable) {
            return ['arguments' => [], 'options' => []];
        }

        $arguments = [];
        if (is_object($parser) && method_exists($parser, 'arguments')) {
            try {
                foreach ((array)$parser->arguments() as $argument) {
                    if (!is_object($argument) || !method_exists($argument, 'name')) {
                        continue;
                    }

                    $arguments[] = [
                        'name' => $argument->name(),
                        'required' => method_exists($argument, 'isRequired') && (bool)$argument->isRequired(),
                        'default' => method_exists($argument, 'defaultValue') ? $argument->defaultValue() : null,
                    ];
                }
            } catch (Throwable) {
                $arguments = [];
            }
        }

        $options = [];
        if (is_object($parser) && method_exists($parser, 'options')) {
            try {
                foreach ((array)$parser->options() as $option) {
                    if (!is_object($option) || !method_exists($option, 'name')) {
                        continue;
                    }

                    $options[] = [
                        'name' => $option->name(),
                        'short' => method_exists($option, 'short') ? (string)$option->short() : '',
                        'boolean' => method_exists($option, 'isBoolean') && (bool)$option->isBoolean(),
                        'required' => method_exists($option, 'isRequired') && (bool)$option->isRequired(),
                        'default' => method_exists($option, 'defaultValue') ? $option->defaultValue() : null,
                        'choices' => method_exists($option, 'choices') ? (array)$option->choices() : [],
                    ];
                }
            } catch (Throwable) {
                $options = [];
            }
        }

        return ['arguments' => $arguments, 'options' => $options];
    }

    /**
     * Captures the honest launch context of a command run.
     *
     * Raw `argv`, working directory and PHP version — the CLI answer
     * to request headers: where and how this process was started.
     *
     * @return array{argv: list<string>, cwd: string|null, php: string}
     */
    protected function commandEnvironment(): array
    {
        $argv = $_SERVER['argv'] ?? [];
        $argv = is_array($argv) ? array_values(array_filter($argv, is_string(...))) : [];

        $cwd = getcwd();

        return [
            'argv' => $argv,
            'cwd' => is_string($cwd) ? $cwd : null,
            'php' => PHP_VERSION,
        ];
    }

    /**
     * Builds the copyable command line of a run.
     *
     * Copies the command with its params, never the binary: the binary
     * is environment-specific (`bin/cake`, `bin\cake`, `cake`) while the
     * rest pastes anywhere. Prefers the raw `argv` the process was
     * launched with (minus the script itself); falls back to
     * reconstructing from the captured name, values and options so
     * tests and embedded runs still copy something meaningful.
     *
     * @param string $name Command display name.
     * @param array<int, mixed> $values Positional values in order.
     * @param array<string, mixed> $options Passed options only.
     * @return string
     */
    protected function buildCopyCommand(string $name, array $values, array $options): string
    {
        $argv = $_SERVER['argv'] ?? [];
        if (
            is_array($argv)
            && count($argv) >= 2
            && isset($argv[1])
            && $argv[1] === $name
        ) {
            return implode(' ', array_map($this->escapeShell(...), array_slice($argv, 1)));
        }

        $parts = [$name];
        foreach ($values as $value) {
            $parts[] = $this->escapeShell($value);
        }

        foreach ($options as $option => $value) {
            if ($value === true) {
                $parts[] = '--' . $option;

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = '--' . $option . '=' . $this->escapeShell($item);
                }

                continue;
            }

            $parts[] = '--' . $option . '=' . $this->escapeShell($value);
        }

        return implode(' ', $parts);
    }

    /**
     * Escapes one shell token only when it needs quoting.
     *
     * Plain tokens stay readable for copy-paste; anything outside a
     * safe token set falls back to `escapeshellarg`.
     *
     * @param mixed $value Token value.
     * @return string
     */
    protected function escapeShell(mixed $value): string
    {
        $token = (string)$value;
        if ($token !== '' && preg_match('/^[A-Za-z0-9_@%+=:,.\-\/]+$/', $token) === 1) {
            return $token;
        }

        return escapeshellarg($token);
    }

    /**
     * Compares an option value against its parser default.
     *
     * @param object $option Parser option definition.
     * @param mixed $value Parsed value.
     * @return bool
     */
    protected function equalsDefault(object $option, mixed $value): bool
    {
        if (!method_exists($option, 'defaultValue')) {
            return false;
        }

        try {
            $default = $option->defaultValue();
        } catch (Throwable) {
            return false;
        }

        return $default === $value;
    }

    /**
     * Short class name without namespace.
     *
     * @param string $class Fully qualified class name.
     * @return string
     */
    protected function short(string $class): string
    {
        $class = ltrim($class, '\\');
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }

    /**
     * Data collection callback.
     *
     * @param \Cake\Event\EventInterface $event The shutdown event.
     * @return void
     */
    public function shutdown(EventInterface $event): void
    {
        /** @var \Cake\Controller\Controller $controller */
        $controller = $event->getSubject();
        $this->collectController($controller, true);
    }

    /**
     * Captures request facts early so error pages keep them.
     *
     * Controller actions throwing past this point skip `shutdown`;
     * without this pass every error page would render an empty panel.
     *
     * @param \Cake\Event\EventInterface $event The startup event.
     * @return void
     */
    public function startup(EventInterface $event): void
    {
        /** @var \Cake\Controller\Controller $controller */
        $controller = $event->getSubject();
        $this->collectController($controller, false);
    }

    /**
     * Collects request facts with optional response facts.
     *
     * @param \Cake\Controller\Controller $controller Controller instance.
     * @param bool $includeResponse Whether the response is final.
     * @return void
     */
    protected function collectController(Controller $controller, bool $includeResponse): void
    {
        $request = $controller->getRequest();
        $maxDepth = Configure::read('DebugBar.maxDepth', 10);

        $identity = $request->getAttribute('identity');

        $authResult = $request->getAttribute('authenticationResult')
            ?? $request->getAttribute('authentication');

        $outputMask = Debugger::outputMask();
        $narrowedMask = $outputMask;
        unset($narrowedMask['prefix']);
        Debugger::setOutputMask($narrowedMask, false);
        try {
            $attributes = [];
            foreach ($request->getAttributes() as $attr => $value) {
                $node = NodeFlattener::maskSensitive(
                    Debugger::exportVarAsNodes($value, $maxDepth),
                );
                try {
                    serialize($node);
                } catch (Exception) {
                    $node = Debugger::exportVarAsNodes(
                        get_debug_type($value),
                        $maxDepth,
                    );
                }

                $attributes[$attr] = $node;
            }
        } finally {
            Debugger::setOutputMask($outputMask, false);
        }

        $httpHeaders = [];
        foreach ($request->getHeaders() as $name => $lines) {
            $httpHeaders[$name] = implode(', ', $lines);
        }

        $rawParams = (array)$request->getAttribute('params');
        $routeParams = [];
        foreach (['controller', 'action', 'prefix', 'plugin', 'pass', '_matchedRoute', '?', '_ext'] as $key) {
            if (!array_key_exists($key, $rawParams)) {
                continue;
            }

            $param = $rawParams[$key];
            $routeParams[$key] = is_scalar($param) || $param === null || is_array($param) ? $param : get_debug_type($param);
        }

        $matchedRoute = $request->getParam('_matchedRoute');

        $uri = $request->getUri();
        $origin = '';
        if ($uri->getHost() !== '') {
            $origin = ($uri->getScheme() !== '' ? $uri->getScheme() : 'http') . '://' . $uri->getHost();
            $port = $uri->getPort();
            if ($port !== null) {
                $origin .= ':' . $port;
            }
        }

        $fullUrl = $origin . $uri->getPath();
        $queryString = $uri->getQuery();
        if ($queryString !== '') {
            $fullUrl .= '?' . $queryString;
        }

        $sessionData = $request->getSession()->read();
        if (!is_array($sessionData)) {
            $sessionData = [];
        }

        $tabNode = static fn(mixed $value): NodeInterface => NodeFlattener::maskSensitive(
            Debugger::exportVarAsNodes($value, $maxDepth),
        );
        $this->data = [
            'context' => 'web',
            'params' => $request->getAttribute('params'),
            'routeParams' => $routeParams,
            'attributes' => $attributes,
            'httpHeaders' => $tabNode($httpHeaders),
            'query' => $tabNode($request->getQueryParams()),
            'data' => $tabNode((array)$request->getData()),
            'cookie' => $tabNode($request->getCookieParams()),
            'get' => $tabNode($request->getQueryParams()),
            'session' => $tabNode($sessionData),
            'matchedRoute' => $matchedRoute,
            'origin' => $origin,
            'fullUrl' => $fullUrl,
            'routeMiddleware' => $this->matchedRouteMiddleware($matchedRoute),
            'appMiddleware' => RouteFacts::appMiddleware(),
            'auth' => $this->collectAuth($identity, $authResult),
            'requestSize' => $this->resolveRequestSize($request),
            'responseSize' => $includeResponse ? $controller->getResponse()->getBody()->getSize() : null,
            'headers' => [
                'response' => headers_sent($file, $line),
                'file' => $file,
                'line' => $line,
            ],
        ];
    }

    /**
     * Collects the authentication fact without depending on the auth plugin.
     *
     * `instanceof` against the plugin interfaces is inherently safe: it
     * resolves false when the package is not installed, so no method is
     * ever called on an unknown object. Only the identifier, the identity
     * class and the failure status/errors are kept — never identity fields.
     *
     * @param mixed $identity The `identity` request attribute.
     * @param mixed $authResult The `authenticationResult` request attribute.
     * @return array{authenticated: bool, identifier: string, display: string, identity_class: string|null, status: string|null, errors: list<string>, label: string}
     */
    protected function collectAuth(mixed $identity, mixed $authResult): array
    {
        $identifier = '';
        $identityClass = null;
        $displayName = '';
        if ($identity instanceof IdentityInterface) {
            $rawIdentifier = $identity->getIdentifier();
            if (is_array($rawIdentifier)) {
                $identifier = implode(', ', $rawIdentifier);
            } else {
                $identifier = (string)($rawIdentifier ?? '');
            }

            $className = $identity::class;
            $separator = strrpos($className, '\\');
            $identityClass = $separator === false ? $className : substr($className, $separator + 1);
            $displayName = self::identityDisplayName($identity->getOriginalData());
        }

        $status = null;
        $errors = [];
        if ($authResult instanceof ResultInterface) {
            $status = $authResult->getStatus();
            foreach ($authResult->getErrors() as $error) {
                $messages = is_array($error) ? $error : [$error];
                foreach ($messages as $message) {
                    if (is_string($message) && $message !== '') {
                        $errors[] = $message;
                    }
                }
            }
        }

        $label = 'Guest';
        if ($identity instanceof IdentityInterface) {
            $label = $displayName !== '' ? $displayName : ($identifier !== '' ? $identifier : (string)$identityClass);
        } elseif ($status !== null && $status !== ResultInterface::SUCCESS) {
            $label = 'Failed: ' . ucwords(strtolower(str_replace('_', ' ', (string)preg_replace('/^FAILURE_/', '', $status))));
        }

        return [
            'authenticated' => $identity instanceof IdentityInterface,
            'identifier' => $identifier,
            'display' => $displayName,
            'identity_class' => $identityClass,
            'status' => $status,
            'errors' => $errors,
            'label' => $label,
        ];
    }

    /**
     * Prefers a human-readable name from the identity original data.
     *
     * Reads the `username`, `email`, `name` or `login` field only — never
     * dumps identity fields. Falls back to an empty string when none of
     * the usual fields carries a printable value.
     *
     * @param \ArrayAccess|array $data Identity original data.
     * @return string Display name or an empty string.
     */
    protected static function identityDisplayName(ArrayAccess|array $data): string
    {
        foreach (['username', 'email', 'name', 'login'] as $field) {
            if (is_array($data)) {
                $value = $data[$field] ?? null;
            } else {
                $value = $data->offsetExists($field) ? $data->offsetGet($field) : null;
            }

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_int($value)) {
                return (string)$value;
            }
        }

        return '';
    }

    /**
     * Resolves the request body size in bytes.
     *
     * Body streams routinely report an unknown size, so the exact
     * `Content-Length` header wins when present, then the stream size,
     * then the measured stringified body (empty for bodyless requests).
     * Only a detached stream degrades to null.
     *
     * @param \Cake\Http\ServerRequest $request Current request.
     * @return int|null Byte count or null when unreadable.
     */
    protected function resolveRequestSize(ServerRequest $request): ?int
    {
        $headerLength = $request->getHeaderLine('Content-Length');
        if (is_numeric($headerLength)) {
            return max(0, (int)$headerLength);
        }

        $streamSize = $request->getBody()->getSize();
        if ($streamSize !== null) {
            return max(0, $streamSize);
        }

        try {
            $body = (string)$request->getBody();
        } catch (Throwable) {
            return null;
        }

        return strlen($body);
    }

    /**
     * Resolves the middleware list of the matched route template.
     *
     * @param mixed $matchedRoute The `_matchedRoute` request param.
     * @return list<string> Display names or an empty list when unmatched.
     */
    protected function matchedRouteMiddleware(mixed $matchedRoute): array
    {
        if (!is_string($matchedRoute) || $matchedRoute === '') {
            return [];
        }

        foreach (Router::routes() as $route) {
            if ($route->template === $matchedRoute) {
                return RouteFacts::routeMiddleware($route);
            }
        }

        return [];
    }
}
