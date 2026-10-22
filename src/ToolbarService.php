<?php
declare(strict_types=1);

namespace Crustum\DebugBar;

use Cake\AttributeResolver\AttributeResolver;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Core\Plugin as CorePlugin;
use Cake\Datasource\Exception\MissingDatasourceConfigException;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\Routing\Router;
use Crustum\DebugBar\Model\Entity\Request;
use Crustum\DebugBar\Panel\PanelRegistry;
use Crustum\DebugBar\Service\FindingsService;
use Crustum\DebugBar\Service\MiniLauncher;
use Crustum\DebugBar\Service\RedactionService;
use Crustum\DebugBar\Service\TimelineBuilder;
use Exception;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use function Cake\Core\env;

/**
 * Used to create the panels and inject a toolbar into
 * matching responses.
 *
 * Used by the Routing Filter and Middleware.
 *
 * @ported-from \DebugKit\ToolbarService
 */
class ToolbarService
{
    use InstanceConfigTrait;
    use LocatorAwareTrait;

    /**
     * The panel registry.
     *
     * @var \Crustum\DebugBar\Panel\PanelRegistry
     */
    protected PanelRegistry $registry;

    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $defaultConfig = [
        'panels' => [
            'Crustum/DebugBar.Cache' => true,
            'Crustum/DebugBar.Request' => true,
            'Crustum/DebugBar.SqlLog' => true,
            'Crustum/DebugBar.Timer' => true,
            'Crustum/DebugBar.Log' => true,
            'Crustum/DebugBar.Variables' => true,
            'Crustum/DebugBar.Environment' => true,
            'Crustum/DebugBar.Routes' => true,
            'Crustum/DebugBar.Packages' => true,
            'Crustum/DebugBar.Mail' => true,
            'Crustum/DebugBar.Deprecations' => true,
            'Crustum/DebugBar.Plugins' => true,
            'Crustum/DebugBar.Validation' => true,
            'Crustum/DebugBar.Timeline' => true,
            'Crustum/DebugBar.Findings' => true,
            'Crustum/DebugBar.HttpClient' => true,
            'Crustum/DebugBar.Events' => true,
            'Crustum/DebugBar.Authorization' => true,
            'Crustum/DebugBar.Models' => true,
            'Crustum/DebugBar.Exceptions' => true,
            'Crustum/DebugBar.Views' => true,
            'Crustum/DebugBar.Attributes' => true,
        ],
        'forceEnable' => false,
        'safeTld' => [],
        'ignorePathsPattern' => null,
        'ignored_url_masks' => [],
        'miniPosition' => 'bottom-right',
        'miniAction' => 'bar',
    ];

    /**
     * Constructor
     *
     * @param \Cake\Event\EventManager $events The event manager to use defaults to the global manager
     * @param array $config The configuration data for Crustum/DebugBar.
     */
    public function __construct(EventManager $events, array $config)
    {
        $this->setConfig($config);
        $this->registry = new PanelRegistry($events);
    }

    /**
     * Fetch the PanelRegistry
     *
     * @return \Crustum\DebugBar\Panel\PanelRegistry
     */
    public function registry(): PanelRegistry
    {
        return $this->registry;
    }

    /**
     * Check whether or not debug kit is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] ??= false;

        if (
            defined('PHPUNIT_COMPOSER_INSTALL') &&
            !$GLOBALS['FORCE_DEBUGBAR_TOOLBAR']
        ) {
            return false;
        }

        $enabled = (bool)Configure::read('debug')
                && !$this->isSuspiciouslyProduction()
                && PHP_SAPI !== 'phpdbg';

        if ($enabled) {
            return true;
        }

        $force = $this->getConfig('forceEnable');
        if (is_callable($force)) {
            return $force();
        }

        return $force;
    }

    /**
     * Returns true if this application is being executed on a domain with a TLD
     * that is commonly associated with a production environment, or if the IP
     * address is not in a private or reserved range.
     *
     * Private  IPv4 = 10.0.0.0/8, 172.16.0.0/12 and 192.168.0.0/16
     * Reserved IPv4 = 0.0.0.0/8, 169.254.0.0/16, 127.0.0.0/8 and 240.0.0.0/4
     *
     * Private  IPv6 = fc00::/7
     * Reserved IPv6 = ::1/128, ::/128, ::ffff:0:0/96 and fe80::/10
     *
     * @return bool
     */
    protected function isSuspiciouslyProduction(): bool
    {
        $host = parse_url('http://' . env('HTTP_HOST'), PHP_URL_HOST);
        if ($host === false || $host === null) {
            return false;
        }

        $host = trim($host, '[]');

        $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        if ($isIp) {
            $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;

            return filter_var($host, FILTER_VALIDATE_IP, $flags) !== false;
        }

        $parts = explode('.', $host);
        if (count($parts) === 1) {
            return false;
        }

        $tld = end($parts);
        $safeTlds = ['localhost', 'invalid', 'test', 'example', 'local', 'internal'];
        $safeTlds = array_merge($safeTlds, (array)$this->getConfig('safeTld'));

        if (in_array($tld, $safeTlds, true)) {
            return false;
        }

        if (!$this->getConfig('forceEnable')) {
            $safeList = implode(', ', $safeTlds);
            Log::warning(
                "DebugBar is disabling itself as your host `{$host}` " .
                "is not in the known safe list of top-level-domains ({$safeList}). " .
                'If you would like to force DebugBar on use the `Crustum/DebugBar.forceEnable` Configure option.',
            );
        }

        return true;
    }

    /**
     * Get the list of loaded panels
     *
     * @return array
     */
    public function loadedPanels(): array
    {
        return $this->registry->loaded();
    }

    /**
     * Get the a loaded panel
     *
     * @param string $name The name of the panel you want to get.
     * @return \Crustum\DebugBar\DebugPanel|null The panel or null.
     */
    public function panel(string $name): ?DebugPanel
    {
        return $this->registry->{$name};
    }

    /**
     * Panels requiring an optional dependency.
     *
     * The Attributes panel talks to `Cake\AttributeResolver`, which is
     * only a suggested package. It stays registered (and keeps its
     * default) but never loads when the class is unavailable, so hosts
     * without the resolver pay nothing and see no empty panel.
     *
     * @var array<string, class-string>
     */
    protected array $optionalPanels = [
        'Crustum/DebugBar.Attributes' => AttributeResolver::class,
    ];

    /**
     * Load all the panels being used
     *
     * @return void
     */
    public function loadPanels(): void
    {
        foreach ($this->getConfig('panels') as $panel => $enabled) {
            [$panel, $enabled] = is_numeric($panel) ? [$enabled, true] : [$panel, $enabled];
            if ($enabled && $this->isAvailable((string)$panel)) {
                $this->registry->load($panel);
            }
        }
    }

    /**
     * Check an optional panel dependency is present.
     *
     * @param string $panel Panel alias, e.g. `Crustum/DebugBar.Attributes`.
     * @return bool True when the panel has no requirement or it is met.
     */
    protected function isAvailable(string $panel): bool
    {
        $required = $this->optionalPanels[$panel] ?? null;
        if ($required === null) {
            return true;
        }

        return class_exists($required);
    }

    /**
     * Call the initialize method onl all the loaded panels.
     *
     * @return void
     */
    public function initializePanels(): void
    {
        foreach ($this->registry->loaded() as $panel) {
            $this->registry->{$panel}->initialize();
        }
    }

    /**
     * Save the toolbar state.
     *
     * CLI and job rows keep bare names (`users-add-user`, never
     * `/cli/users-add-user`): the URI path always carries a leading slash,
     * so it is trimmed for the synthetic `CLI`/`JOB` methods only. Web
     * rows keep their path untouched.
     *
     * @param \Cake\Http\ServerRequest $request The request
     * @param \Psr\Http\Message\ResponseInterface $response The response
     * @return \Crustum\DebugBar\Model\Entity\Request|false Saved request data.
     */
    public function saveData(ServerRequest $request, ResponseInterface $response): Request|false
    {
        $path = $request->getUri()->getPath();
        $dashboardUrl = '/debug-bar';
        if (str_contains($path, 'debug_bar') || str_contains($path, 'debug-bar')) {
            if ($path !== $dashboardUrl && $path !== $dashboardUrl . '/') {
                return false;
            }
        }

        $ignorePathsPattern = $this->getConfig('ignorePathsPattern');
        $statusCode = $response->getStatusCode();
        if ($this->isIgnoredUrl($path)) {
            return false;
        }

        if (
            $ignorePathsPattern &&
            $statusCode >= 200 &&
            $statusCode <= 299 &&
            preg_match($ignorePathsPattern, $path)
        ) {
            return false;
        }

        $method = $request->getMethod();
        $url = $path;
        if (($method === 'CLI' || $method === 'JOB') && str_starts_with($url, '/')) {
            $url = substr($url, 1);
        }

        $data = [
            'url' => $url,
            'content_type' => $response->getHeaderLine('Content-Type'),
            'method' => $method,
            'status_code' => $response->getStatusCode(),
            'requested_at' => $request->getEnv('REQUEST_TIME'),
            'panels' => [],
        ];
        try {
            /** @var \Crustum\DebugBar\Model\Table\RequestsTable $requests */
            $requests = $this->fetchTable('Crustum/DebugBar.Requests');
            $requests->gc();
        } catch (MissingDatasourceConfigException $missingDatasourceConfigException) {
            Log::warning(
                'Unable to save request. Check your debug_bar datasource connection ' .
                'or ensure that PDO SQLite extension is enabled.',
            );
            Log::warning($missingDatasourceConfigException->getMessage());

            return false;
        }

        $row = $requests->newEntity($data);
        $row->setNew(true);

        /** @var array<string, array<string, mixed>> $collected */
        $collected = [];
        foreach ($this->registry->loaded() as $name) {
            $panel = $this->registry->{$name};
            $data = null;
            $handlerInstalled = false;
            try {
                $data = $panel->data();

                set_error_handler(
                    function ($errno, $errstr) use ($name): bool {
                        throw new Exception("Serialization error in panel '{$name}': {$errstr}");
                    },
                    E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE,
                );
                $handlerInstalled = true;

                $content = serialize($data);
            } catch (Exception $e) {
                $errorMessage = sprintf(
                    'Failed to serialize data for panel "%s": %s',
                    $name,
                    $e->getMessage(),
                );

                Log::warning($errorMessage);
                Log::debug('Panel data type: ' . gettype($data));

                $content = serialize([
                    'error' => $errorMessage,
                    'panel' => $name,
                ]);
            } finally {
                if ($handlerInstalled) {
                    restore_error_handler();
                }
            }

            $row->panels[] = $requests->Panels->newEntity([
                'panel' => $name,
                'element' => $panel->elementName(),
                'title' => $panel->title(),
                'summary' => $panel->summary(),
                'content' => $content,
            ]);
            if (is_array($data)) {
                $collected[$name] = $data;
            }
        }

        $this->patchResponseSize($row, $collected, $method, $response);

        $durationMs = $this->buildTimeline($row, $collected, $request);
        $this->buildFindings($row, $collected, $response, $durationMs);

        try {
            $saved = $requests->save($row);
        } catch (Throwable $throwable) {
            Log::warning('Unable to save request. This is probably due to concurrent requests.');
            Log::warning($throwable->getMessage());

            return false;
        }

        return $saved;
    }

    /**
     * Overwrites the early shutdown size with the final response body size.
     *
     * `RequestPanel` captures at `Controller.shutdown`, before the view
     * renders, so its `responseSize` is constant across pages. The final
     * PSR-7 body here varies per page. CLI/JOB runs have no HTTP body
     * and always resolve to null so the switcher hides the size.
     *
     * @param \Crustum\DebugBar\Model\Entity\Request $row Request entity with panels.
     * @param array<string, array<string, mixed>> $collected Sibling data keyed by registry alias.
     * @param string $method Stored method (`GET`, `CLI`, `JOB`, …).
     * @param \Psr\Http\Message\ResponseInterface $response Final response.
     * @return void
     */
    protected function patchResponseSize(Request $row, array $collected, string $method, ResponseInterface $response): void
    {
        if ($method === 'CLI' || $method === 'JOB') {
            $size = null;
        } else {
            $size = null;
            try {
                $bodySize = $response->getBody()->getSize();
                if (is_int($bodySize) && $bodySize >= 0) {
                    $size = $bodySize;
                }
            } catch (Exception) {
                $size = null;
            }
        }

        foreach ((array)$row->panels as $panel) {
            $alias = (string)($panel->panel ?? '');
            if ($alias !== 'Request' && !str_ends_with($alias, '/Request') && !str_ends_with($alias, '.Request')) {
                continue;
            }

            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $stored = @unserialize((string)$panel->get('content'));
            if (!is_array($stored)) {
                $stored = [];
            }

            $stored['responseSize'] = $size;
            $panel->set('content', serialize($stored));
        }

        foreach ($collected as $alias => $data) {
            if ($alias !== 'Request' && !str_ends_with((string)$alias, '/Request') && !str_ends_with((string)$alias, '.Request')) {
                continue;
            }

            $collected[$alias]['responseSize'] = $size;
        }
    }

    /**
     * Assembles the derived timeline row from sibling panel data.
     *
     * Runs after every panel collected its own data so the merge sees the
     * full request. Only touches the Timeline row; every other row stays
     * exactly as its panel produced it.
     *
     * @param \Crustum\DebugBar\Model\Entity\Request $row Request entity with panels.
     * @param array<string, array<string, mixed>> $collected Sibling data keyed by registry alias.
     * @param \Cake\Http\ServerRequest $request The request.
     * @return float Request duration in milliseconds (the timeline window end).
     */
    protected function buildTimeline(Request $row, array $collected, ServerRequest $request): float
    {
        if (!in_array('Timeline', $this->registry->loaded(), true)) {
            return 0.0;
        }

        $envStart = $request->getEnv('REQUEST_TIME_FLOAT');
        $requestStart = $envStart !== null ? (float)$envStart : microtime(true);
        $redaction = new RedactionService((array)Configure::read('DebugBar'));
        $builder = new TimelineBuilder();
        $timelineData = $builder->build(
            $collected,
            $requestStart,
            microtime(true),
            $redaction->maxItems(),
        );

        foreach ((array)$row->panels as $panel) {
            if (($panel->panel ?? null) !== 'Timeline') {
                continue;
            }

            $panel->set('content', serialize($timelineData));
            $count = (int)$timelineData['retained'];
            $panel->set('summary', $count === 0 ? '' : "{$count} events");
        }

        return (float)$timelineData['end_ms'];
    }

    /**
     * Assembles the derived findings row from sibling panel data.
     *
     * Runs after every panel collected its own data so rules see the full
     * request. Only touches the Findings row; every other row stays exactly
     * as its panel produced it.
     *
     * @param \Crustum\DebugBar\Model\Entity\Request $row Request entity with panels.
     * @param array<string, array<string, mixed>> $collected Sibling data keyed by registry alias.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param float $durationMs Request duration in milliseconds (timeline window end).
     * @return void
     */
    protected function buildFindings(Request $row, array $collected, ResponseInterface $response, float $durationMs): void
    {
        if (!in_array('Findings', $this->registry->loaded(), true)) {
            return;
        }

        $service = new FindingsService((array)Configure::read('DebugBar'));
        $titles = [];
        foreach ((array)$row->panels as $panel) {
            $alias = (string)($panel->panel ?? '');
            if ($alias !== '') {
                $titles[$alias] = (string)($panel->title ?? $alias);
            }
        }

        $findingsData = $service->build($collected, [
            'status_code' => $response->getStatusCode(),
            'method' => $row->method ?? '',
            'url' => $row->url ?? '',
            'duration_ms' => $durationMs,
        ], $titles);

        foreach ((array)$row->panels as $panel) {
            if (($panel->panel ?? null) !== 'Findings') {
                continue;
            }

            $panel->set('content', serialize($findingsData));
            $count = (int)$findingsData['retained'];
            $panel->set('summary', $count === 0 ? '' : "{$count} findings");
        }
    }

    /**
     * Checks the noise-URL masks from `ignored_url_masks`.
     *
     * Glob syntax (`*`, `?`, `[...]`) matched against the request path, any
     * status: masked URLs are never stored, so they never pollute the
     * request switcher or the recent list.
     *
     * @param string $path Request path.
     * @return bool True when the path matches a configured mask.
     */
    protected function isIgnoredUrl(string $path): bool
    {
        $masks = (array)$this->getConfig('ignored_url_masks');
        foreach ($masks as $mask) {
            $mask = (string)$mask;
            if ($mask === '') {
                continue;
            }

            if (fnmatch($mask, $path)) {
                return true;
            }

            if (str_ends_with($mask, '/*') && $path === substr($mask, 0, -2)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reads the modified date of a file in the webroot, and returns the integer
     *
     * @return string
     */
    public function getToolbarUrl(): string
    {
        $url = 'js/inject-iframe.js';
        $filePaths = [
            str_replace('/', DIRECTORY_SEPARATOR, WWW_ROOT . 'crustum/debug_bar/' . $url),
            str_replace('/', DIRECTORY_SEPARATOR, CorePlugin::path('Crustum/DebugBar') . 'webroot/' . $url),
        ];

        $url = '/crustum/debug_bar/' . $url;
        foreach ($filePaths as $filePath) {
            if (file_exists($filePath)) {
                return $url . '?' . filemtime($filePath);
            }
        }

        return $url;
    }

    /**
     * Injects the JS to build the toolbar.
     *
     * The toolbar will only be injected if the response's content type
     * contains HTML and there is a </body> tag.
     *
     * @param \Crustum\DebugBar\Model\Entity\Request $row The request data to inject.
     * @param \Psr\Http\Message\ResponseInterface $response The response to augment.
     * @return \Psr\Http\Message\ResponseInterface The modified response
     */
    public function injectScripts(Request $row, ResponseInterface $response): ResponseInterface
    {
        $response = $response->withHeader('X-DEBUGBAR-ID', (string)$row->id);
        if (!str_contains($response->getHeaderLine('Content-Type'), 'html')) {
            return $response;
        }

        $body = $response->getBody();
        $seekable = $body->isSeekable() && $body->isWritable();
        if ($seekable) {
            $body->rewind();
        }

        $contents = $body->getContents();

        $pos = strrpos($contents, '</body>');
        if ($pos === false) {
            return $response;
        }

        $request = Router::getRequest();
        $nonce = '';
        if ($request instanceof ServerRequest && $request->getAttribute('cspScriptNonce')) {
            $nonce = sprintf(' nonce="%s"', $request->getAttribute('cspScriptNonce'));
        }

        $url = Router::url('/', true);
        $miniPosition = MiniLauncher::sanitizePosition($this->getConfig('miniPosition'));
        $script = sprintf(
            '<script id="__debug_bar_script" data-id="%s" data-url="%s" data-mini-position="%s" type="module" src="%s"%s></script>',
            $row->id,
            $url,
            $miniPosition,
            Router::url($this->getToolbarUrl()),
            $nonce,
        );
        $contents = substr($contents, 0, $pos) . $script . substr($contents, $pos);

        if ($seekable) {
            $body->rewind();
            $body->write($contents);

            return $response->withBody($body)->withoutHeader('Content-Length');
        }

        $stream = new Stream('php://temp', 'wb+');
        $stream->write($contents);

        return $response->withBody($stream)->withoutHeader('Content-Length');
    }
}
