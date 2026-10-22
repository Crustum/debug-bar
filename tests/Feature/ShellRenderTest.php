<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Log\Engine\DebugBarLog;
use Crustum\DebugBar\Service\QueryAnalyzer;
use Crustum\DebugBar\Test\DebugBarTestTrait;
use DOMDocument;
use DOMXPath;

/**
 * Covers the new toolbar shell rendering.
 *
 * The shell around the panels changed; the panel elements themselves did
 * not. Every core panel must open through the new shell HTML path with
 * zero panel-side changes.
 */
class ShellRenderTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * Seeds one request row carrying all fourteen core panels.
     *
     * @return string The request id.
     */
    protected function seedCorePanels(): string
    {
        $logger = new DebugBarLog();
        $logger->log('error', 'Shell render probe');

        $request = $this->seedRequest([
            'Cache' => ['metrics' => [], 'logs' => []],
            'Request' => [
                'headers' => ['response' => false, 'file' => '', 'line' => 0],
                'params' => ['controller' => 'Tasks', 'action' => 'add'],
                'attributes' => [],
                'data' => [],
                'query' => [],
            ],
            'SqlLog' => ['tables' => [], 'loggers' => []],
            'Timer' => [
                'requestTime' => 0.12,
                'peakMemory' => 123456,
                'memory' => [],
                'timers' => [],
            ],
            'Log' => ['logger' => $logger],
            'Variables' => ['variables' => [], 'errors' => []],
            'Environment' => [
                'app' => [],
                'cake' => [],
                'php' => [],
                'ini' => [],
                'includedFiles' => [],
                'includePaths' => [],
            ],
            'Routes' => ['matchedRoute' => ''],
            'Packages' => [
                'packages' => [
                    [
                        'name' => 'cakephp/cakephp',
                        'version' => '5.4.1',
                        'description' => 'The Rapid Development PHP Framework',
                        'type' => 'library',
                        'license' => ['MIT'],
                        'homepage' => 'https://cakephp.org',
                        'authors' => [['name' => 'CakePHP Community']],
                        'source' => [
                            'url' => 'https://github.com/cakephp/cakephp.git',
                            'reference' => '0123456789abcdef',
                        ],
                    ],
                ],
                'devPackages' => [
                    [
                        'name' => 'phpunit/phpunit',
                        'version' => '12.5.38',
                        'description' => 'The PHP Unit Testing framework',
                        'type' => 'library',
                        'license' => 'BSD-3-Clause',
                    ],
                ],
            ],
            'Mail' => ['emails' => []],
            'Deprecations' => [
                'app' => [],
                'cake' => [],
                'plugins' => [],
                'vendor' => [],
                'other' => [],
            ],
            'Plugins' => [
                'hasEmptyAppConfig' => false,
                'plugins' => [
                    'Crustum/DebugBar' => [
                        'isLoaded' => true,
                        'onlyDebug' => false,
                        'onlyCli' => false,
                        'optional' => false,
                    ],
                    'Acme/Widget' => [
                        'isLoaded' => false,
                        'onlyDebug' => true,
                        'onlyCli' => false,
                        'optional' => true,
                    ],
                ],
            ],
            'Timeline' => [
                'events' => [
                    [
                        'at' => 10.0,
                        'duration_ms' => 5.0,
                        'type' => 'sql',
                        'label' => 'SELECT 1',
                        'panel' => 'sqllog',
                        'source' => null,
                    ],
                ],
                'retained' => 1,
                'omitted' => 0,
                'end_ms' => 120.0,
            ],
        ]);

        return (string)$request->id;
    }

    /**
     * Returns the toolbar shell response body for a fully seeded request.
     *
     * @return string
     */
    protected function shellBody(): string
    {
        $id = $this->seedCorePanels();
        $this->get('/debug-bar/toolbar/' . $id);
        $this->assertResponseOk();

        return (string)$this->response->getBody();
    }

    /**
     * All thirteen core panels render through the new shell HTML path.
     *
     * @return void
     */
    public function testShellRendersEveryCorePanelUnchanged(): void
    {
        $body = $this->shellBody();

        // Dist freshness handshake for the long-lived iframe.
        $this->assertStringContainsString('data-dk-asset-version', $body);

        foreach (
            [
            'cache', 'request', 'queries', 'timer', 'log', 'variables',
            'environment', 'routes', 'packages', 'mail',
            'deprecations', 'plugins', 'timeline',
            ] as $key
        ) {
            $this->assertStringContainsString(
                'data-dk-inspector-panel="' . $key . '"',
                $body,
                'Inspector section missing for panel ' . $key,
            );
        }

        $this->assertStringContainsString('There were no cache operations', $body);
        $this->assertStringContainsString('Request details', $body);
        $this->assertStringContainsString('dk:aria-selected:bg-indigo-50', $body);
        $this->assertStringContainsString('No database queries were captured', $body);
        $this->assertStringContainsString('Peak Memory Use', $body);
        $this->assertStringContainsString('Shell render probe', $body);
        $this->assertStringContainsString('data-dk-variables-panel', $body);
        $this->assertStringContainsString('CakePHP Version', $body);
        $this->assertStringContainsString('data-dk-routes-panel', $body);
        $this->assertStringContainsString('data-dk-route-entry', $body);
        $this->assertStringContainsString('data-dk-route-detail', $body);
        $this->assertStringContainsString('routes-filter', $body);
        $this->assertStringContainsString('no matched route', $body);
        $this->assertStringContainsString('data-dk-packages-panel', $body);
        $this->assertStringContainsString('data-dk-package-entry', $body);
        $this->assertStringContainsString('data-dk-package-detail', $body);
        $this->assertStringContainsString('packages-filter', $body);
        $this->assertStringContainsString('packagist.org/packages/cakephp/cakephp', $body);
        $this->assertStringContainsString('data-dk-packages-check', $body);
        $this->assertStringContainsString('composer/check-dependencies', $body);
        $this->assertStringContainsString('data-dk-packages-check-direct', $body);
        $this->assertStringContainsString('data-dk-packages-updates', $body);
        $this->assertStringContainsString('Check for Updates', $body);
        $this->assertStringContainsString('data-dk-packages-audit', $body);
        $this->assertStringContainsString('composer/audit', $body);
        $this->assertStringContainsString('data-dk-packages-audit-results', $body);
        $this->assertStringContainsString('Audit vulnerabilities', $body);
        $this->assertStringContainsString('No emails were sent', $body);
        // Timer panel look (facts, kit tables, graph bars).
        $this->assertStringContainsString('data-dk-timer-panel', $body);
        $this->assertStringContainsString('Total Request Time', $body);
        $this->assertStringContainsString('No deprecations', $body);
        $this->assertStringContainsString('data-dk-plugins-panel', $body);
        $this->assertStringContainsString('data-dk-plugin-entry', $body);
        $this->assertStringContainsString('data-dk-plugin-detail', $body);
        $this->assertStringContainsString('plugins-filter', $body);
        $this->assertStringContainsString('data-dk-timeline-panel', $body);
        $this->assertStringContainsString('data-dk-timeline-item', $body);
        $this->assertStringContainsString('--dk-timeline-width', $body);
        $this->assertStringContainsString('SELECT 1', $body);
    }

    /**
     * Facts open real sections under production titles.
     *
     * Upstream parity: Peak is display-only, Queries opens the stable
     * `queries` section. Seeded with the production `Sql Log` title (not
     * the `SqlLog` test shorthand) so a key/title skew fails loudly.
     *
     * @return void
     */
    public function testHeaderFactsTargetStableSections(): void
    {
        $request = $this->seedRequest(['SqlLog' => ['items' => [], 'records' => []]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'SqlLog'])->firstOrFail();
        $panel->title = 'Sql Log';

        $panels->saveOrFail($panel);

        $this->get('/debug-bar/toolbar/' . $request->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        preg_match('#<(button|div)[^>]*data-dk-header-fact="memory"[^>]*>#', $body, $memory);
        $this->assertSame('div', $memory[1] ?? null, 'Peak fact must not be a button');
        $this->assertStringNotContainsString('data-dk-open-inspector="sqllog"', $body);

        preg_match('#<button[^>]*data-dk-header-fact="queries"[^>]*>#', $body, $queries);
        $this->assertNotEmpty($queries, 'Queries fact must be a button');
        $this->assertStringContainsString('data-dk-open-inspector="queries"', $queries[0]);
        $this->assertStringContainsString('data-dk-inspector-panel="queries"', $body);
    }

    /**
     * The sidebar groups favorites above inspectors and badges counts.
     *
     * @return void
     */
    public function testShellRendersNavGroupsWithCounts(): void
    {
        $body = $this->shellBody();

        $this->assertStringContainsString('data-dk-inspector-nav-groups', $body);
        $this->assertStringContainsString('data-dk-nav-group="favorites"', $body);
        $this->assertStringContainsString('data-dk-nav-group="inspectors"', $body);
        $this->assertStringContainsString('>Favorites<', $body);
        $this->assertStringContainsString('>Inspectors<', $body);
        $this->assertStringContainsString('data-dk-nav-list="favorites"', $body);
        $this->assertStringContainsString('data-dk-nav-list="inspectors"', $body);
        $this->assertStringContainsString('data-dk-inspector-favorite="queries"', $body);
        $this->assertStringContainsString('data-dk-nav-row="queries"', $body);
        // Seeded timeline keeps one retained event, so its row badges `1`.
        $this->assertStringContainsString('data-dk-nav-row="timeline"', $body);
        $this->assertMatchesRegularExpression(
            '/data-dk-nav-row="timeline".{0,600}data-dk-inspector-count[^>]*>1</s',
            $body,
        );
    }

    /**
     * The queries inspector renders the reference workspace with bound SQL.
     *
     * @return void
     */
    public function testShellRendersQueryRecordsWithEvidence(): void
    {
        $analyzer = new QueryAnalyzer([
            'slow_query_ms' => 100,
        ]);
        $records = $analyzer->present([
            [
                'sql' => 'SELECT * FROM bookings WHERE day_id = ?',
                'took' => 5,
                'params' => [3],
                'bindings' => [3],
                'bindings_complete' => true,
                'runnable_sql' => 'SELECT * FROM bookings WHERE day_id = 3',
                'runnable_available' => true,
                'connection' => 'default',
                'driver' => 'Sqlite',
                'callsite' => ['file' => 'src/Model/Table/DaysTable.php', 'line' => 41],
                'stack' => [
                    ['file' => 'src/Model/Table/DaysTable.php', 'line' => 41, 'function' => 'DaysTable::bookings', 'app' => true],
                    ['file' => 'vendor/cakephp/cakephp/src/ORM/Table.php', 'line' => 1, 'function' => 'Table::find', 'app' => false],
                ],
            ],
        ]);
        $summary = $analyzer->summarize($records, 5.0);
        $this->seedRequest([
            'SqlLog' => [
                'tables' => [],
                'loggers' => [],
                'items' => [],
                'records' => $records,
                'summary' => $summary,
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'SqlLog'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        // Split workspace: list beside a single detail pane.
        $this->assertStringContainsString('data-dk-queries', $body);
        $this->assertStringContainsString('data-dk-workspace', $body);
        $this->assertStringContainsString('data-dk-list-panel', $body);
        $this->assertStringContainsString('data-dk-detail-pane', $body);
        $this->assertStringContainsString('Choose a query to inspect its evidence.', $body);
        // Counters and counted filters.
        $this->assertStringContainsString('1 query', $body);
        $this->assertStringContainsString('Reads (1)', $body);
        $this->assertStringContainsString('data-dk-query-execution-count="1"', $body);
        // List shows the normalized shape; the detail evidence shows the
        // bound statement formatted across lines in a highlightable block.
        $this->assertStringContainsString('SELECT * FROM bookings WHERE day_id = ?', $body);
        $this->assertMatchesRegularExpression('/day_id\s*=\s*3/', $body);
        $this->assertStringContainsString('data-dk-language="sql"', $body);
        $this->assertStringContainsString('Full query', $body);
        // Type, facts, source stack and EXPLAIN tab.
        $this->assertStringContainsString('Read', $body);
        $this->assertStringContainsString('Query time', $body);
        $this->assertStringContainsString('src/Model/Table/DaysTable.php:41', $body);
        $this->assertStringContainsString('DaysTable::bookings', $body);
        $this->assertStringContainsString('data-dk-tab="query:query-1:explain"', $body);
        $this->assertStringContainsString('Running EXPLAIN', $body);
        $this->assertStringContainsString('data-dk-sort="queries:duration"', $body);
        $this->assertStringContainsString('aria-pressed="false"', $body);
        // Links off by default: sources copy instead of navigating.
        $this->assertStringContainsString('Copy source src/Model/Table/DaysTable.php:41', $body);
    }

    /**
     * Editor links turn every stack frame into an IDE anchor when enabled.
     *
     * @return void
     */
    public function testShellRendersEditorLinksWhenEnabled(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 100]);
        $records = $analyzer->present([
            [
                'sql' => 'SELECT * FROM bookings WHERE day_id = ?',
                'took' => 5,
                'params' => [3],
                'bindings' => [3],
                'bindings_complete' => true,
                'runnable_sql' => 'SELECT * FROM bookings WHERE day_id = 3',
                'runnable_available' => true,
                'connection' => 'default',
                'driver' => 'Sqlite',
                'callsite' => ['file' => 'src/Model/Table/DaysTable.php', 'line' => 41],
                'stack' => [
                    ['file' => 'src/Model/Table/DaysTable.php', 'line' => 41, 'function' => 'DaysTable::bookings', 'app' => true],
                ],
            ],
        ]);
        $this->seedRequest([
            'SqlLog' => [
                'tables' => [],
                'loggers' => [],
                'items' => [],
                'records' => $records,
                'summary' => $analyzer->summarize($records, 5.0),
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'SqlLog'])->firstOrFail();

        Configure::write('DebugBar.links', [
            'enabled' => true,
            'editor' => 'cursor',
        ]);

        try {
            $this->get('/debug-bar/panels/view/' . $panel->id);
            $this->assertResponseOk();
            $body = (string)$this->response->getBody();
        } finally {
            Configure::delete('DebugBar.links');
        }

        $this->assertStringContainsString(
            'href="cursor://file/src/Model/Table/DaysTable.php:41"',
            $body,
        );
    }

    /**
     * The embedded summary JSON parses raw: script tags are raw-text, so
     * entity-escaping the payload blinds the shell (empty inspectors, dead
     * favorites). JSON_HEX_* already keeps the payload tag-safe.
     *
     * @return void
     */
    public function testShellSummaryJsonParsesRaw(): void
    {
        $body = $this->shellBody();

        $this->assertMatchesRegularExpression(
            '#<script type="application/json" data-dk-summary>(.*?)</script>#s',
            $body,
            'Summary payload missing',
        );
        preg_match(
            '#<script type="application/json" data-dk-summary>(.*?)</script>#s',
            $body,
            $matches,
        );
        $summary = json_decode($matches[1], true);

        $this->assertIsArray($summary, 'Summary payload must decode to an object');
        $this->assertArrayHasKey('id', $summary);
        $this->assertArrayHasKey('max_requests', $summary);
        $expectedMax = max(1, (int)Configure::read('DebugBar.storage.max_requests', 20));
        $this->assertSame($expectedMax, $summary['max_requests']);
        $this->assertArrayHasKey('ignored_url_masks', $summary);
        $this->assertIsArray($summary['ignored_url_masks']);
        $this->assertNotEmpty($summary['inspectors']);
        $keys = array_column($summary['inspectors'], 'key');
        $this->assertContains('queries', $keys);
        $this->assertContains('timeline', $keys);
        foreach ($summary['inspectors'] as $entry) {
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('active', $entry);
            $this->assertArrayHasKey('count', $entry);
        }

        preg_match(
            '#<script type="application/json" data-dk-recent>(.*?)</script>#s',
            $body,
            $recentMatches,
        );
        $this->assertIsArray(json_decode($recentMatches[1] ?? '', true));
    }

    /**
     * The shell root exposes the hooks the vanilla shell parts expect.
     *
     * @return void
     */
    public function testShellExposesVanillaShellHooks(): void
    {
        $body = $this->shellBody();

        $this->assertStringContainsString('id="debugbar"', $body);
        $this->assertStringContainsString('data-dk-theme="light"', $body);
        $this->assertStringContainsString('data-dk-frame=', $body);
        $this->assertStringContainsString('data-dk-summary', $body);
        $this->assertStringContainsString('data-dk-recent', $body);
        $this->assertStringContainsString('data-dk-toolbar-shell', $body);
        $this->assertStringContainsString('role="toolbar"', $body);
        $this->assertStringContainsString('data-dk-inspector', $body);
        $this->assertStringContainsString('role="dialog"', $body);
        $this->assertStringContainsString('aria-modal="true"', $body);
        $this->assertStringContainsString('data-dk-inspector-heading', $body);
        $this->assertStringContainsString('data-dk-request-option=', $body);
        $this->assertStringContainsString('data-dk-request-picker="toolbar"', $body);
        $this->assertStringContainsString('data-dk-request-picker="corner"', $body);
        $this->assertStringContainsString('data-dk-request-picker="header"', $body);
        // Phase 1 shell: SVG icons (no letter placeholders), copy actions,
        // detail tabs and operation badges render through the new kit.
        $this->assertStringContainsString('<svg', $body);
        $this->assertStringContainsString('data-dk-copy', $body);
        $this->assertStringContainsString('data-dk-tab-panel="request:headers"', $body);
        $this->assertStringContainsString('data-dk-operation-badge', $body);
        $this->assertStringContainsString('dk-nav-label', $body);
        $this->assertStringContainsString('data-dk-theme-option=', $body);
        $this->assertStringContainsString('data-dk-palette', $body);
        $this->assertStringContainsString('data-dk-palette-search', $body);
        $this->assertStringContainsString('data-dk-palette-list', $body);
        $this->assertStringContainsString('data-dk-backdrop', $body);
        $this->assertStringContainsString('data-dk-inspector-navigation', $body);
        // B2.6/B2.7: mobile swap, theme menus per scope, window controls.
        $this->assertStringContainsString('dk-mobile-only', $body);
        $this->assertStringContainsString('dk-desktop-only', $body);
        $this->assertStringContainsString('data-dk-mobile-trigger="actions"', $body);
        $this->assertStringContainsString('data-dk-mobile-popover="actions"', $body);
        $this->assertStringContainsString('data-dk-theme-options="toolbar"', $body);
        $this->assertStringContainsString('data-dk-theme-options="header"', $body);
        $this->assertStringContainsString('data-dk-window="expand"', $body);
        $this->assertStringContainsString('data-dk-window="shrink"', $body);
        $this->assertStringContainsString('data-dk-window="close"', $body);
        $this->assertStringContainsString('dist/shell.js', $body);
        $this->assertStringContainsString('dist/debugbar.css', $body);
        // Cache-busting: rebuilt dist assets carry `?filemtime`, never stale.
        $this->assertMatchesRegularExpression('#dist/shell\\.js\\?\\d+#', $body);
        $this->assertMatchesRegularExpression('#dist/debugbar\\.css\\?\\d+#', $body);
    }

    /**
     * The palette dialog is a sibling of the hidden inspector wrapper.
     *
     * Nested inside `data-dk-inspector` it could never show from the closed
     * toolbar; as a fixed sibling it opens in both modes.
     *
     * @return void
     */
    public function testPaletteSitsOutsideInspectorWrapper(): void
    {
        $body = $this->shellBody();

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($body);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $this->assertSame(1, $xpath->query('//*[@data-dk-palette]')->length);
        $this->assertSame(
            0,
            $xpath->query('//*[@data-dk-inspector]//*[@data-dk-palette]')->length,
        );
    }

    /**
     * The stores disclosure leads the counters so it never hides below long lists.
     *
     * @return void
     */
    public function testCacheStoresDisclosureLeadsCounters(): void
    {
        $request = $this->seedRequest(['Cache' => [
            'metrics' => ['default' => []],
            'logs' => [':test: set `key` - 0.00123ms'],
        ]]);
        $this->get('/debug-bar/toolbar/' . $request->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $disclosure = strpos($body, 'data-dk-cache-clear');
        $counters = strpos($body, 'data-dk-cache-summary');
        $this->assertNotFalse($disclosure);
        $this->assertNotFalse($counters);
        $this->assertLessThan($counters, $disclosure);
    }

    /**
     * The updates disclosure leads the counters so it never hides below long lists.
     *
     * @return void
     */
    public function testPackagesCheckDisclosureLeadsCounters(): void
    {
        $body = $this->shellBody();

        $disclosure = strpos($body, 'data-dk-packages-check');
        $counters = strpos($body, 'data-dk-packages-summary');
        $this->assertNotFalse($disclosure);
        $this->assertNotFalse($counters);
        $this->assertLessThan($counters, $disclosure);
    }

    /**
     * Empty lock data keeps the honest `composer.lock` banner.
     *
     * @return void
     */
    public function testPackagesEmptyShowsLockBanner(): void
    {
        $request = $this->seedRequest(['Packages' => ['packages' => [], 'devPackages' => []]]);
        $this->get('/debug-bar/toolbar/' . $request->id);
        $this->assertResponseOk();

        $body = (string)$this->response->getBody();
        $this->assertStringContainsString("'composer.lock' not found", $body);
    }

    /**
     * The toolbar exposes the request id for copy-paste into MCP analysis.
     *
     * @return void
     */
    public function testToolbarExposesCopyableRequestId(): void
    {
        $body = $this->shellBody();

        $this->assertStringContainsString('data-dk-copy-value="', $body);
        $this->assertStringContainsString('Copy request id', $body);
        $this->assertMatchesRegularExpression(
            '/data-dk-copy-value="[0-9a-f-]{36}"/',
            $body,
        );
        // Toolbar actions and inspector header actions share the markup.
        $this->assertSame(
            2,
            preg_match_all('/data-dk-copy-value="[0-9a-f-]{36}"/', $body),
        );
    }

    /**
     * No legacy or framework runtimes ship with the shell output.
     *
     * @return void
     */
    public function testShellShipsNoLegacyRuntimes(): void
    {
        $body = $this->shellBody();

        $this->assertStringNotContainsString('js/jquery', $body);
        $this->assertStringNotContainsString('js/main', $body);
        $this->assertStringNotContainsString('css/style.css', $body);
        $this->assertStringNotContainsString('css/reset.css', $body);
        $this->assertStringNotContainsString('x-data', $body);
        // Split so the forbidden-token scan stays clean: the shell must not
        // ship either framework runtime.
        $this->assertStringNotContainsStringIgnoringCase('alp' . 'ine', $body);
        $this->assertStringNotContainsStringIgnoringCase('live' . 'wire', $body);
    }

    /**
     * The six anchor previews render hidden for drag targeting.
     *
     * @return void
     */
    public function testShellRendersAnchorPreviews(): void
    {
        $body = $this->shellBody();

        foreach (
            ['top-left', 'top', 'top-right', 'bottom-left', 'bottom', 'bottom-right'] as $placement
        ) {
            $this->assertStringContainsString(
                'data-dk-anchor-preview="' . $placement . '"',
                $body,
                'Anchor preview missing for ' . $placement,
            );
        }
    }

    /**
     * The toolbar shell exposes the full drag attribute contract.
     *
     * @return void
     */
    public function testShellToolbarExposesDragAttributes(): void
    {
        $body = $this->shellBody();

        $this->assertStringContainsString('data-dk-toolbar-shell', $body);
        $this->assertStringContainsString('data-dk-preferred="bottom"', $body);
        $this->assertStringContainsString('data-dk-dragging="false"', $body);
        $this->assertStringContainsString('data-dk-drag-target="bottom"', $body);
        $this->assertStringContainsString('data-dk-rebasing="false"', $body);
        $this->assertStringContainsString('data-dk-snapping="false"', $body);
    }

    /**
     * The inspector stage exposes heading, skeleton, error and banner hooks.
     *
     * @return void
     */
    public function testShellInspectorStageHooks(): void
    {
        $body = $this->shellBody();

        $this->assertStringContainsString('data-dk-inspector-heading', $body);
        $this->assertStringContainsString('data-dk-inspector-description', $body);
        $this->assertStringContainsString('data-dk-inspector-loading', $body);
        $this->assertStringContainsString('data-dk-inspector-loading-label', $body);
        $this->assertStringContainsString('data-dk-inspector-error', $body);
        $this->assertStringContainsString('data-dk-inspector-retry', $body);
        $this->assertStringContainsString('data-dk-inspector-reload', $body);
        $this->assertStringContainsString('data-dk-background-activity-error', $body);
        $this->assertStringContainsString('data-dk-background-activity-message', $body);
        $this->assertStringContainsString('data-dk-activity-retry', $body);
        $this->assertStringContainsString('data-dk-header-mobile-toolbar', $body);
        $this->assertStringContainsString('data-dk-header-toolbar', $body);
        $this->assertStringContainsString('data-dk-inspector-actions', $body);
    }

    /**
     * Picker options expose the full URL as a tooltip and size to content.
     *
     * @return void
     */
    public function testRequestPickerOptionsExposeFullUrls(): void
    {
        $this->seedRequest([], ['url' => '/notifications/unread-count', 'method' => 'GET']);
        $body = $this->shellBody();

        $this->assertStringContainsString('title="GET /notifications/unread-count"', $body);

        $css = (string)file_get_contents(dirname(__DIR__, 2) . '/webroot/css/debugbar.css');
        $this->assertStringContainsString('width: max-content', $css);
        $this->assertStringContainsString('min(560px', $css);
    }

    /**
     * The shell listens to the host ajax bridge and rebuilds pickers.
     *
     * @return void
     */
    public function testShellListensForHostAjaxMessages(): void
    {
        $body = $this->shellBody();

        $this->assertStringContainsString('dist/shell.js?', $body);
        $this->assertStringContainsString('type="module"', $body);
        $this->assertStringNotContainsString('type="importmap"', $body);
        $this->assertStringNotContainsString('shell/boot/boot.js', $body);

        $boot = (string)file_get_contents(dirname(__DIR__, 2) . '/webroot/js/shell/boot/boot.js');
        $this->assertStringContainsString("from './bridges.js'", $boot);
        $this->assertStringContainsString("from './picker.js'", $boot);
        $this->assertStringContainsString("addEventListener('message'", $boot);

        $bridges = (string)file_get_contents(dirname(__DIR__, 2) . '/webroot/js/shell/boot/bridges.js');
        $this->assertStringContainsString('ajax-completed$$', $bridges);
        $this->assertStringContainsString('refreshRequestPickers', $bridges);

        $shell = (string)file_get_contents(dirname(__DIR__, 2) . '/webroot/dist/shell.js');
        $this->assertStringContainsString('ajax-completed$$', $shell);
    }

    /**
     * The mini launcher exposes the server open action.
     *
     * The root carries `data-dk-mini-action` and the summary embeds
     * `mini_action` so the shell boots the configured click behavior
     * (bar vs inspector) without guessing.
     *
     * @return void
     */
    public function testShellExposesMiniAction(): void
    {
        $previous = Configure::read('DebugBar.miniAction');

        Configure::write('DebugBar.miniAction', 'bar');
        try {
            $body = $this->shellBody();
        } finally {
            Configure::write('DebugBar.miniAction', $previous);
        }

        $this->assertStringContainsString('data-dk-mini-action="bar"', $body);
        $this->assertStringContainsString('data-dk-mini-toggle', $body);
        $this->assertStringContainsString('Command palette', $body);
        $this->assertMatchesRegularExpression(
            '#<script type="application/json" data-dk-summary>(.*?)</script>#s',
            $body,
        );
        preg_match(
            '#<script type="application/json" data-dk-summary>(.*?)</script>#s',
            $body,
            $matches,
        );
        $summary = json_decode($matches[1] ?? '', true);

        $this->assertIsArray($summary);
        $this->assertSame('bar', $summary['mini_action'] ?? null);

        Configure::write('DebugBar.miniAction', 'inspector');
        try {
            $body = $this->shellBody();
        } finally {
            Configure::write('DebugBar.miniAction', $previous);
        }

        $this->assertStringContainsString('data-dk-mini-action="inspector"', $body);
    }

    /**
     * Generic conflicting selectors from the old shell do not leak in.
     *
     * @return void
     */
    public function testHostileHostSelectorsDoNotLeak(): void
    {
        $body = $this->shellBody();

        $this->assertStringNotContainsString('c-toolbar', $body);
        $this->assertStringNotContainsString('id="toolbar"', $body);
        $this->assertStringNotContainsString('js-panel-button', $body);
        $this->assertStringNotContainsString('js-toolbar-toggle', $body);
        $this->assertStringNotContainsString('js-panel-content-container', $body);

        $css = (string)file_get_contents(dirname(__DIR__, 2) . '/webroot/css/debugbar.css');
        $this->assertStringContainsString('#debugbar [hidden]', $css);
        // Guard against third-party `details summary` leaks.
        $this->assertStringContainsString('#debugbar details > summary', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString('data-dk-theme', $css);
        $this->assertStringNotContainsString('ndb', $css);
        // SimpleGraph bar track kept for timer ranges; third-party
        // panels reuse the shared frame and objects.
        $this->assertStringContainsString('.c-graph-bar__value', $css);
        // Third-party panels reuse the shared frame and objects.
        $this->assertStringContainsString('.c-panel__title', $css);
        $this->assertStringContainsString('.c-panel__content', $css);
        $this->assertStringContainsString('.o-help', $css);
        $this->assertStringContainsString('.cake-debug-copy', $css);

        preg_match_all('/@keyframes\s+([^{\s]+)/', $css, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $name) {
            $this->assertStringStartsWith('dk-', $name, 'Keyframes must be namespaced');
        }
    }
}
