<?php
declare(strict_types=1);

namespace Crustum\DebugBar;

use Cake\Console\CommandCollection;
use Cake\Container\ContainerInterface;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Error\PhpError;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Crustum\DebugBar\Command\BenchmarkCommand;
use Crustum\DebugBar\Command\McpCommand;
use Crustum\DebugBar\Compat\DebugKitCompat;
use Crustum\DebugBar\Mcp\DebugBarServer;
use Crustum\DebugBar\Middleware\DebugBarMiddleware;
use Crustum\DebugBar\Panel\DeprecationsPanel;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use Crustum\DebugBar\Panel\RequestPanel;
use Crustum\DebugBar\Service\CommandCapture;
use Crustum\DebugBar\Service\RouteFacts;
use Crustum\Mcp\Server;
use Override;
use Throwable;

/**
 * Plugin for Crustum/DebugBar
 *
 * @inspired-by \DebugKit\DebugKitPlugin
 */
class DebugBarPlugin extends BasePlugin
{
    /**
     * Whether the deprecation handler is already registered.
     *
     * The middleware hook reruns on middleware reconstruction — the
     * handler must stay a singleton, otherwise deprecations duplicate.
     *
     * @var bool
     */
    private static bool $deprecationHandlerInstalled = false;

    /**
     * Whether the exception listener is already registered.
     *
     * The middleware hook reruns on middleware reconstruction — the
     * listener must stay a singleton, otherwise exceptions duplicate.
     *
     * @var bool
     */
    private static bool $exceptionListenerInstalled = false;

    /**
     * Whether the command capture is already registered.
     *
     * The bootstrap hook reruns on application reconstruction — capture
     * and panel subscriptions must stay singletons.
     *
     * @var bool
     */
    private static bool $commandCaptureInstalled = false;

    /**
     * Shared toolbar service instance.
     *
     * @var \Crustum\DebugBar\ToolbarService|null
     */
    protected ?ToolbarService $service = null;

    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(PluginApplicationInterface $app): void
    {
        DebugKitCompat::register();

        if ($app instanceof BaseApplication) {
            RouteFacts::rememberApplication($app);
        }

        if (!Configure::check('DebugBar')) {
            if (file_exists(CONFIG . 'debug_bar.php')) {
                Configure::load('debug_bar', 'default');
            } elseif (file_exists($this->getConfigPath() . 'debug_bar.php')) {
                Configure::load('Crustum/DebugBar.debug_bar', 'default', false);
            }
        }

        $this->registerMcpServer();
        $this->registerCommandCapture();

        parent::bootstrap($app);
    }

    /**
     * Attaches console command capture.
     *
     * The capture opens before panels record and finishes after they
     * complete; both listeners stay quiet outside CLI workers and
     * commands. A singleton guard keeps middleware reconstructions from
     * duplicating subscriptions.
     *
     * @return void
     */
    protected function registerCommandCapture(): void
    {
        if (self::$commandCaptureInstalled) {
            return;
        }

        self::$commandCaptureInstalled = true;
        EventManager::instance()->on(new CommandCapture());
        EventManager::instance()->on(new RequestPanel());
    }

    /**
     * Registers the local STDIO MCP server with `crustum/mcp`.
     *
     * Skipped when the optional dependency is absent; the registrar reads
     * `Mcp.local` during its own bootstrap.
     *
     * @return void
     */
    protected function registerMcpServer(): void
    {
        if (!class_exists(Server::class)) {
            return;
        }

        $local = Configure::read('Mcp.local', []);
        $local = is_array($local) ? $local : [];
        $local['cake-debugbar'] = DebugBarServer::class;
        Configure::write('Mcp.local', $local);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        parent::routes($routes);
    }

    /**
     * Builds the toolbar service and inserts its middleware.
     *
     * Snapshots the queue under construction: at this point it holds the
     * application stack plus every plugin hook before ours (ours is last
     * in load order). `Server.buildMiddleware` listeners run on the
     * server's own manager, unreachable from here.
     *
     * The service is built here, not in bootstrap(): host apps write
     * their `DebugBar.*` Configure keys (e.g. safeTld in
     * bootstrap_after.php) after plugin bootstraps run, so a
     * bootstrap-time snapshot would miss them and wrongly disable
     * the toolbar.
     *
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        RouteFacts::rememberAppMiddleware($middlewareQueue);
        $service = new ToolbarService(EventManager::instance(), (array)Configure::read('DebugBar'));
        if (!$service->isEnabled()) {
            return $middlewareQueue;
        }

        $this->service = $service;
        if (!self::$deprecationHandlerInstalled) {
            $this->setDeprecationHandler($service);
            self::$deprecationHandlerInstalled = true;
        }

        $this->setExceptionListener($service);

        $middlewareQueue->insertAt(0, new DebugBarMiddleware($service));
        RouteFacts::rememberAppMiddleware($middlewareQueue);

        return $middlewareQueue;
    }

    /**
     * Registers the singleton exception collector.
     *
     * Cake's `ErrorHandlerMiddleware` sits inside our middleware and renders
     * error pages itself, so throwables never bubble to `DebugBarMiddleware`.
     * `ErrorHandlerMiddleware::handleException()` dispatches
     * `Exception.beforeRender` on the shared manager before rendering — the
     * listener records the throwable there, ahead of `saveData()`.
     *
     * @param \Crustum\DebugBar\ToolbarService $service The toolbar service instance
     * @return void
     */
    public function setExceptionListener(ToolbarService $service): void
    {
        if (empty($service->getConfig('panels')['Crustum/DebugBar.Exceptions'])) {
            return;
        }

        if (self::$exceptionListenerInstalled) {
            return;
        }

        self::$exceptionListenerInstalled = true;
        EventManager::instance()->on('Exception.beforeRender', function (EventInterface $event): void {
            $error = $event->getData('exception');
            if ($error instanceof Throwable) {
                ExceptionsPanel::record($error);
            }
        });
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function console(CommandCollection $commands): CommandCollection
    {
        return $commands
            ->add('benchmark', BenchmarkCommand::class)
            ->add('debug-bar mcp', McpCommand::class);
    }

    /**
     * Registers the singleton deprecation collector.
     *
     * Extracts the line/file from the message as deprecationWarning
     * calculates the application frame when generating the message.
     *
     * @param \Crustum\DebugBar\ToolbarService $service The toolbar service instance
     * @return void
     */
    public function setDeprecationHandler(ToolbarService $service): void
    {
        if (!empty($service->getConfig('panels')['Crustum/DebugBar.Deprecations'])) {
            EventManager::instance()->on('Error.beforeRender', function (EventInterface $event, PhpError $error): void {
                $code = $error->getCode();
                if ($code !== E_USER_DEPRECATED && $code !== E_DEPRECATED) {
                    return;
                }

                $file = $error->getFile();
                $line = $error->getLine();

                preg_match('/\\n([^\n,]+?), line: (\d+)\\n/', $error->getMessage(), $matches);
                if ($matches) {
                    $file = $matches[1];
                    $line = $matches[2];
                }

                DeprecationsPanel::addDeprecatedError([
                    'code' => $code,
                    'message' => $error->getMessage(),
                    'file' => $file,
                    'line' => $line,
                ]);
                $event->stopPropagation();
            });
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function services(ContainerInterface $container): void
    {
    }
}
