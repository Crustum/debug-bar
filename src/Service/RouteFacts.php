<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Route\Route;
use Throwable;

/**
 * Route display facts.
 *
 * Single home for the CakePHP routing concepts the routes inspector
 * surfaces, so the template and the tests agree: a route prefix lives in
 * the route defaults (`prefix` scope option, e.g. `Admin`), not in the
 * template path. Absent prefixes report as an empty string, never null.
 */
class RouteFacts
{
    /**
     * Booted application captured for middleware reconstruction.
     *
     * @var \Cake\Http\BaseApplication|null
     */
    private static ?BaseApplication $application = null;

    /**
     * Application middleware names captured at the plugin hook.
     *
     * Fallback for contexts without a captured application.
     *
     * @var list<string>
     */
    private static array $appMiddleware = [];

    /**
     * Reports the route prefix from the route defaults.
     *
     * @param \Cake\Routing\Route\Route $route Route to inspect.
     * @return string The prefix name or an empty string when scopeless.
     */
    public static function prefix(Route $route): string
    {
        $prefix = $route->defaults['prefix'] ?? null;
        if (!is_string($prefix)) {
            return '';
        }

        return trim($prefix);
    }

    /**
     * Reports the middleware registered on the route itself.
     *
     * Entries normalize to display strings: class names shorten to the
     * class short name, `[class, method]` pairs render as
     * `Short::method`, closures and invokables use their debug type.
     *
     * @param \Cake\Routing\Route\Route $route Route to inspect.
     * @return list<string> Display names, empty when the route runs bare.
     */
    public static function routeMiddleware(Route $route): array
    {
        $names = [];
        foreach ($route->getMiddleware() as $middleware) {
            $names[] = self::shortName(self::middlewareName($middleware));
        }

        return $names;
    }

    /**
     * Remembers the booted application for middleware reconstruction.
     *
     * Stores the reference only — no boot, no side effects. The same
     * instance the server runs, so a later reconstruction reads the
     * genuine container, config and plugin hooks.
     *
     * @param \Cake\Http\BaseApplication $app Booted host application.
     * @return void
     */
    public static function rememberApplication(BaseApplication $app): void
    {
        self::$application = $app;
    }

    /**
     * Forgets the remembered application and hook snapshot.
     *
     * Test support for process isolation between cases.
     *
     * @return void
     * @internal
     */
    public static function forgetApplication(): void
    {
        self::$application = null;
        self::$appMiddleware = [];
    }

    /**
     * Remembers the application middleware stack seen at boot.
     *
     * Called from the plugin `middleware()` hook with the queue under
     * construction: the application stack plus every plugin hook before
     * ours. A second application boot is deliberately never used for
     * this — it reloads plugins and corrupts template paths. Names are
     * resolved eagerly so only strings are kept.
     *
     * @param iterable<mixed> $queue Middleware queue under construction.
     * @return void
     */
    public static function rememberAppMiddleware(iterable $queue): void
    {
        $names = [];
        foreach ($queue as $middleware) {
            $names[] = self::middlewareName($middleware);
        }

        self::$appMiddleware = $names;
    }

    /**
     * Reports the application middleware stack.
     *
     * Reconstructs the exact boot sequence against the remembered
     * application — host middleware, plugin hooks, then the global
     * `Server.buildMiddleware` listeners — mirroring what the server
     * runs. Falls back to the plugin-hook snapshot, then to an empty
     * list. A second application boot is never used for this: it
     * reloads plugins and corrupts template paths.
     *
     * @return list<string> Middleware class names or an empty list.
     */
    public static function appMiddleware(): array
    {
        $reconstructed = self::reconstructAppMiddleware();
        if ($reconstructed !== null) {
            return $reconstructed;
        }

        return self::$appMiddleware;
    }

    /**
     * Reconstructs the middleware stack without rebooting.
     *
     * @return list<string>|null Names, or null when no application is remembered.
     */
    private static function reconstructAppMiddleware(): ?array
    {
        $app = self::$application;
        if (!$app instanceof BaseApplication) {
            return null;
        }

        try {
            $queue = $app->middleware(new MiddlewareQueue());
            $queue = $app->pluginMiddleware($queue);

            $manager = EventManager::instance();
            $wasTracking = $manager->isTrackingEvents();
            $manager->trackEvents(false);
            try {
                $manager->dispatch(
                    new Event('Server.buildMiddleware', null, ['middleware' => $queue]),
                );
            } finally {
                $manager->trackEvents($wasTracking);
            }

            $names = [];
            foreach ($queue as $middleware) {
                $names[] = self::middlewareName($middleware);
            }

            return $names;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Normalizes one middleware entry to its full display name.
     *
     * @param mixed $middleware Queue entry (class name, pair or object).
     * @return string Full class name, optionally with `::method`.
     */
    private static function middlewareName(mixed $middleware): string
    {
        if (is_string($middleware)) {
            return $middleware;
        }

        if (is_array($middleware)) {
            $class = (string)($middleware[0] ?? '');
            $method = (string)($middleware[1] ?? '');
            if ($method !== '') {
                return $class . '::' . $method;
            }

            return $class;
        }

        if (is_object($middleware)) {
            return get_debug_type($middleware);
        }

        return (string)$middleware;
    }

    /**
     * Shortens a class name to its short name.
     *
     * @param string $class Fully qualified class name.
     * @return string Short name or the input when already short.
     */
    private static function shortName(string $class): string
    {
        $position = strrpos($class, '\\');
        if ($position === false) {
            return $class;
        }

        return substr($class, $position + 1);
    }
}
