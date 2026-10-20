<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Authorization;

use Authorization\AuthorizationServiceInterface;
use Authorization\IdentityInterface;
use Authorization\Policy\ResultInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\DebugBar\Service\CallsiteCapture;
use ReflectionMethod;
use ReflectionObject;
use Throwable;

/**
 * Decorates the authorization service to record every check.
 *
 * Delegates to the inner service so decisions stay identical; after each
 * `can()`/`canResult()` it dispatches `DebugBar.Authorization.checked`
 * with the user, action, resource, result and the policy behind the
 * decision. Recording never interrupts the host application — a failure
 * inside the recorder is swallowed while authorization errors bubble up.
 */
class DebugBarAuthorizationServiceDecorator implements AuthorizationServiceInterface
{
    /**
     * Wraps the decorated authorization service.
     *
     * @param \Authorization\AuthorizationServiceInterface $inner The decorated service.
     */
    public function __construct(
        protected AuthorizationServiceInterface $inner,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function can(?IdentityInterface $user, string $action, mixed $resource, mixed ...$optionalArgs): bool
    {
        $result = $this->inner->canResult($user, $action, $resource, ...$optionalArgs);
        $this->record($user, $action, $resource, $optionalArgs, $result);

        return $result->getStatus();
    }

    /**
     * @inheritDoc
     */
    public function canResult(
        ?IdentityInterface $user,
        string $action,
        mixed $resource,
        mixed ...$optionalArgs,
    ): ResultInterface {
        $result = $this->inner->canResult($user, $action, $resource, ...$optionalArgs);
        $this->record($user, $action, $resource, $optionalArgs, $result);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function applyScope(
        ?IdentityInterface $user,
        string $action,
        mixed $resource,
        mixed ...$optionalArgs,
    ): mixed {
        return $this->inner->applyScope($user, $action, $resource, ...$optionalArgs);
    }

    /**
     * @inheritDoc
     */
    public function authorizationChecked(): bool
    {
        return $this->inner->authorizationChecked();
    }

    /**
     * @inheritDoc
     */
    public function skipAuthorization()
    {
        $this->inner->skipAuthorization();

        return $this;
    }

    /**
     * Records one completed check as a global event for the panel.
     *
     * The policy behind the decision resolves off the inner service's own
     * resolver, so custom resolvers keep working with no extra config.
     *
     * @param \Authorization\IdentityInterface|null $user Checked identity.
     * @param string $action Checked action.
     * @param mixed $resource Checked resource.
     * @param array $optionalArgs Extra policy arguments.
     * @param \Authorization\Policy\ResultInterface $result Check result.
     * @return void
     */
    protected function record(
        ?IdentityInterface $user,
        string $action,
        mixed $resource,
        array $optionalArgs,
        ResultInterface $result,
    ): void {
        try {
            $policy = $this->resolvePolicy($resource, $action);
            $capture = CallsiteCapture::capture([__FILE__]);
            $event = new Event('DebugBar.Authorization.checked', $this, [
                'user' => $user,
                'action' => $action,
                'resource' => $resource,
                'optional_args' => $optionalArgs,
                'result' => $result,
                'allowed' => $result->getStatus(),
                'policy' => $policy['class'] ?? null,
                'policy_method' => $policy['method'] ?? null,
                'policy_source' => $policy['source'] ?? null,
                'callsite' => $capture['callsite'],
                'stack' => $capture['stack'],
            ]);
            EventManager::instance()->dispatch($event);
        } catch (Throwable) {
            // Recording must never break authorization.
        }
    }

    /**
     * Resolves the policy class, method and source behind a resource.
     *
     * Reads the inner service's resolver through reflection — the service
     * exposes no accessor — and stays silent when no policy exists.
     *
     * @param mixed $resource Checked resource.
     * @param string $action Checked action.
     * @return array{class: string|null, method: string|null, source: array{file: string|null, line: int|null}|null}
     */
    protected function resolvePolicy(mixed $resource, string $action): array
    {
        $empty = ['class' => null, 'method' => null, 'source' => null];
        try {
            $reflection = new ReflectionObject($this->inner);
            if (!$reflection->hasProperty('resolver')) {
                return $empty;
            }

            $property = $reflection->getProperty('resolver');
            $resolver = $property->getValue($this->inner);
            if (!is_object($resolver) || !method_exists($resolver, 'getPolicy')) {
                return $empty;
            }

            $policy = $resolver->getPolicy($resource);
        } catch (Throwable) {
            return $empty;
        }

        if (!is_object($policy)) {
            return $empty;
        }

        $class = $policy::class;
        $methodName = 'can' . ucfirst($action);
        $source = null;
        try {
            $method = new ReflectionMethod($policy, $methodName);
            $file = $method->getFileName();
            $line = $method->getStartLine();
            $source = [
                'file' => is_string($file) ? $file : null,
                'line' => $line === false ? null : $line,
            ];
        } catch (Throwable) {
            $source = null;
        }

        return ['class' => $class, 'method' => $methodName, 'source' => $source];
    }
}
