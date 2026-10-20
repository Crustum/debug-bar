<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\DebugBar\ToolbarService;
use Throwable;

/**
 * Records CLI units of work as toolbar rows.
 *
 * Generic capture behind every non-HTTP run — queue jobs, console
 * commands, workers: `open()` builds one toolbar service, loads only
 * panels listing the `cli` context, and resets static inboxes, while
 * `finish()` stores the row through the regular `saveData()` path. Web
 * requests never touch it (the middleware owns those). Nothing here
 * throws: a failed capture must never break the process it observes.
 */
class CliRecorder
{
    /**
     * Toolbar service for the running unit of work, if any.
     *
     * @var \Crustum\DebugBar\ToolbarService|null
     */
    protected static ?ToolbarService $service = null;

    /**
     * Cached toolbar availability for the process.
     *
     * @var bool|null
     */
    protected static ?bool $available = null;

    /**
     * Synthetic request URL of the running unit of work.
     *
     * @var string
     */
    protected static string $url = '';

    /**
     * Synthetic request method of the running unit of work.
     *
     * @var string
     */
    protected static string $method = 'CLI';

    /**
     * Resets process state (tests).
     *
     * @return void
     */
    public static function reset(): void
    {
        static::$service = null;
        static::$available = null;
        static::$url = '';
        static::$method = 'CLI';
    }

    /**
     * Opens one toolbar capture.
     *
     * Reuses the process service across units of work; static inboxes
     * reset on every open so jobs and commands never share records.
     *
     * @param string $url Synthetic row name, e.g. `SendMail`.
     * @param string $method Synthetic row method, defaults to `CLI`.
     * @return bool False when the toolbar is unavailable.
     */
    public static function open(string $url, string $method = 'CLI'): bool
    {
        if (static::$available === false) {
            return false;
        }

        try {
            $service = static::$service;
            if (!$service instanceof ToolbarService) {
                $service = new ToolbarService(EventManager::instance(), (array)Configure::read('DebugBar'));
                if (!$service->isEnabled()) {
                    static::$available = false;

                    return false;
                }

                static::$available = true;
                $service->loadPanels();
                static::unloadWebPanels($service);
                $service->initializePanels();
                static::$service = $service;
            }

            static::clearInboxes($service);

            static::$url = $url;
            static::$method = $method;
        } catch (Throwable) {
            static::$service = null;

            return false;
        }

        return true;
    }

    /**
     * Reads one loaded panel from the running capture.
     *
     * Lets captures push payloads directly: panels attach while their
     * own start event is already dispatching, so events alone cannot
     * deliver it. Aliases are registry-short (`Request`, never the
     * plugin-prefixed config key).
     *
     * @param string $alias Panel registry alias.
     * @return object|null Loaded panel or null when nothing is open.
     */
    public static function panel(string $alias): ?object
    {
        $service = static::$service;
        if (!$service instanceof ToolbarService) {
            return null;
        }

        try {
            $panel = $service->panel($alias);
        } catch (Throwable) {
            return null;
        }

        return is_object($panel) ? $panel : null;
    }

    /**
     * Stores the running capture.
     *
     * @param int $statusCode Synthetic status for the row.
     * @return bool False when nothing is open.
     */
    public static function finish(int $statusCode = 200): bool
    {
        $service = static::$service;
        if (!$service instanceof ToolbarService) {
            return false;
        }

        try {
            $request = new ServerRequest([
                'url' => static::$url !== '' ? static::$url : '/cli/run',
                'environment' => [
                    'REQUEST_METHOD' => static::$method,
                    'REQUEST_TIME' => time(),
                ],
            ]);
            $service->saveData($request, new Response(['status' => $statusCode]));
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Unloads panels that only support web requests.
     *
     * Panels declare contexts via `DebugPanel::contexts()`; third-party
     * panels without the method stay loaded everywhere.
     *
     * @param \Crustum\DebugBar\ToolbarService $service Toolbar service.
     * @return void
     */
    protected static function unloadWebPanels(ToolbarService $service): void
    {
        foreach ($service->registry()->loaded() as $alias) {
            $panel = $service->registry()->{$alias};
            if (!is_object($panel) || !method_exists($panel, 'contexts')) {
                continue;
            }

            if (!in_array('cli', (array)$panel->contexts(), true)) {
                $service->registry()->unload($alias);
            }
        }
    }

    /**
     * Clears static panel inboxes the web middleware would clear.
     *
     * Any loaded panel exposing a static `clear()` joins in, so
     * third-party panels opt into worker isolation the same way.
     *
     * @param \Crustum\DebugBar\ToolbarService $service Toolbar service.
     * @return void
     */
    protected static function clearInboxes(ToolbarService $service): void
    {
        foreach ($service->registry()->loaded() as $alias) {
            $panel = $service->registry()->{$alias};
            $clear = [$panel, 'clear'];
            if (!is_callable($clear)) {
                continue;
            }

            try {
                $clear();
            } catch (Throwable) {
                continue;
            }
        }
    }
}
