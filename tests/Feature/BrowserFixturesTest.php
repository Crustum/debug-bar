<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the browser fixture flows and diagnostics pages.
 *
 * Flow tests drive the articles application like a user would and
 * assert toolbar injection plus tracked rows; diagnostics pages pin
 * deterministic panel states; edge responses keep tracking with or
 * without the injected tag.
 */
class BrowserFixturesTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @var bool
     */
    protected bool $restoreForce = false;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
        $this->restoreForce = (bool)($GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] ?? false);
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = true;
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = $this->restoreForce;
        parent::tearDown();
    }

    /**
     * The articles index captures queries, cache and model activity.
     *
     * @return void
     */
    public function testArticlesIndex(): void
    {
        $this->get('/browser-fixtures/articles');
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();
        $this->assertStringContainsString('Articles', $body);
        $this->assertStringContainsString('__debug_bar_script', $body);
        $this->assertNotEmpty($this->response->getHeaderLine('X-DEBUGBAR-ID'));

        $panels = $this->storedPanels('/browser-fixtures/articles');
        $this->assertArrayHasKey('SqlLog', $panels);
        $this->assertArrayHasKey('Cache', $panels);
        $this->assertArrayHasKey('Models', $panels);
    }

    /**
     * Full create flow: invalid post, valid post, view page, delete.
     *
     * @return void
     */
    public function testArticleCreateFlow(): void
    {
        $this->get('/browser-fixtures/article-add');
        $this->assertResponseOk();
        $this->assertStringContainsString('article-form', (string)$this->response->getBody());

        $this->post('/browser-fixtures/article-add', ['title' => '', 'body' => 'short']);
        $this->assertResponseCode(422);
        $this->assertStringContainsString('form-errors', (string)$this->response->getBody());
        $panels = $this->panelsForLastRequest();
        $this->assertArrayHasKey('Validation', $panels);
        $validation = unserialize((string)$panels['Validation']->content);
        $this->assertNotEmpty($validation['errors'] ?? []);

        $title = 'Flow article ' . time();
        $this->post('/browser-fixtures/article-add', [
            'title' => $title,
            'body' => 'A body long enough to validate.',
        ]);
        $this->assertResponseCode(302);
        $location = $this->response->getHeaderLine('Location');
        $this->assertStringContainsString('/browser-fixtures/article-view/', $location);
        $panels = $this->panelsForLastRequest();
        $this->assertArrayHasKey('Mail', $panels);

        $this->get($location);
        $this->assertResponseOk();
        $this->assertStringContainsString($title, (string)$this->response->getBody());
        $viewPanels = $this->panelsForLastRequest();
        $this->assertArrayHasKey('Models', $viewPanels);

        $id = (int)basename($location);
        $this->post('/browser-fixtures/article-delete/' . $id);
        $this->assertResponseCode(302);
        $deletePanels = $this->panelsForLastRequest();
        $this->assertArrayHasKey('Authorization', $deletePanels);
    }

    /**
     * Diagnostics pages render with injection and tracking.
     *
     * @param string $url Fixture URL.
     * @param string $heading Expected host heading.
     * @return void
     */
    #[DataProvider('diagnosticsPages')]
    public function testDiagnosticsPages(string $url, string $heading): void
    {
        $this->get($url);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();
        $this->assertStringContainsString($heading, $body);
        $this->assertStringContainsString('__debug_bar_script', $body);
        $this->assertNotEmpty($this->response->getHeaderLine('X-DEBUGBAR-ID'));
        $this->assertStoredRow($url);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function diagnosticsPages(): array
    {
        return [
            'logs' => ['/browser-fixtures/logs', 'Log diagnostics'],
            'cache rich' => ['/browser-fixtures/cache-rich', 'Cache diagnostics'],
            'events' => ['/browser-fixtures/events', 'Event diagnostics'],
            'views' => ['/browser-fixtures/views', 'Context view'],
            'context' => ['/browser-fixtures/context', 'Context view'],
            'http client' => ['/browser-fixtures/http-client', 'HTTP client diagnostics'],
            'mail' => ['/browser-fixtures/mail', 'Mail diagnostics'],
            'timeline long' => ['/browser-fixtures/timeline-long', 'Long timeline'],
            'exceptions' => ['/browser-fixtures/exceptions', 'Reported failure'],
            'authorization' => ['/browser-fixtures/authorization', 'Authorization diagnostics'],
            'hostile' => ['/browser-fixtures/hostile', 'Hostile styles'],
            'large index' => ['/browser-fixtures/articles?large=1', 'Articles'],
        ];
    }

    /**
     * The reported throwable is stored without failing the page.
     *
     * @return void
     */
    public function testExceptionsPageStoresReportedThrowable(): void
    {
        $this->get('/browser-fixtures/exceptions');
        $this->assertResponseOk();

        $panels = $this->storedPanels('/browser-fixtures/exceptions');
        $this->assertArrayHasKey('Exceptions', $panels);
        $exceptions = unserialize((string)$panels['Exceptions']->content);
        $this->assertSame('RuntimeException', $exceptions['exceptions'][0]['class'] ?? null);
    }

    /**
     * Edge responses keep tracking with or without the injected tag.
     *
     * @return void
     */
    public function testEdgeResponses(): void
    {
        $this->get('/browser-fixtures/plain-json');
        $this->assertResponseOk();
        $this->assertContentType('application/json');
        $this->assertStringNotContainsString(
            '__debug_bar_script',
            (string)$this->response->getBody(),
        );
        $this->assertStoredRow('/browser-fixtures/plain-json');

        $this->get('/browser-fixtures/plain-text');
        $this->assertResponseOk();
        $this->assertStringNotContainsString(
            '__debug_bar_script',
            (string)$this->response->getBody(),
        );
        $this->assertStoredRow('/browser-fixtures/plain-text');

        $this->get('/browser-fixtures/headless');
        $this->assertResponseOk();
        $this->assertStringNotContainsString(
            '__debug_bar_script',
            (string)$this->response->getBody(),
        );
        $this->assertStoredRow('/browser-fixtures/headless');

        $this->get('/browser-fixtures/download');
        $this->assertResponseOk();
        $this->assertStringContainsString(
            'attachment',
            $this->response->getHeaderLine('Content-Disposition'),
        );
        $this->assertStoredRow('/browser-fixtures/download');

        $this->get('/browser-fixtures/failed');
        $this->assertResponseCode(422);
        $this->assertStringContainsString(
            '__debug_bar_script',
            (string)$this->response->getBody(),
        );
        $this->assertStoredRow('/browser-fixtures/failed');

        $this->get('/browser-fixtures/redirect-to-articles');
        $this->assertResponseCode(302);
        $this->assertStoredRow('/browser-fixtures/redirect-to-articles');
    }

    /**
     * Asserts a tracked request row exists for the URL.
     *
     * @param string $url Request path.
     * @param string $method Request method.
     * @return void
     */
    protected function assertStoredRow(string $url, string $method = 'GET'): void
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $row = $this->fetchTable('Crustum/DebugBar.Requests')
            ->find()
            ->where(['url' => $path, 'method' => $method])
            ->orderBy(['requested_at' => 'DESC'])
            ->first();
        $this->assertNotEmpty($row, 'No tracked row for ' . $method . ' ' . $path);
    }

    /**
     * Reads stored panels for the last request keyed by panel name.
     *
     * The row is resolved through the `X-DEBUGBAR-ID` response header so
     * same-second requests to one URL never mix rows up.
     *
     * @return array<string, \Crustum\DebugBar\Model\Entity\Panel>
     */
    protected function panelsForLastRequest(): array
    {
        $id = $this->response->getHeaderLine('X-DEBUGBAR-ID');
        $this->assertNotEmpty($id, 'Missing X-DEBUGBAR-ID response header.');
        $row = $this->fetchTable('Crustum/DebugBar.Requests')
            ->find()
            ->where(['id' => $id])
            ->contain('Panels')
            ->firstOrFail();
        $panels = [];
        foreach ((array)$row->panels as $panel) {
            $panels[(string)$panel->panel] = $panel;
        }

        return $panels;
    }

    /**
     * Reads stored panels for a URL keyed by panel name.
     *
     * @param string $url Request path.
     * @param string $method Request method.
     * @return array<string, \Crustum\DebugBar\Model\Entity\Panel>
     */
    protected function storedPanels(string $url, string $method = 'GET'): array
    {
        $path = (string)parse_url($url, PHP_URL_PATH);
        $row = $this->fetchTable('Crustum/DebugBar.Requests')
            ->find()
            ->where(['url' => $path, 'method' => $method])
            ->contain('Panels')
            ->orderBy(['requested_at' => 'DESC'])
            ->firstOrFail();
        $panels = [];
        foreach ((array)$row->panels as $panel) {
            $panels[(string)$panel->panel] = $panel;
        }

        return $panels;
    }
}
