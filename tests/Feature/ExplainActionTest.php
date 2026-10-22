<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\Core\Configure;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the live-only read-only EXPLAIN action.
 */
class ExplainActionTest extends TestCase
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
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::delete('DebugBar.TestApp.csrf');
        parent::tearDown();
    }

    /**
     * Enables host-style CSRF protection and returns a valid token.
     *
     * The token is stored as the `csrfToken` cookie, mirroring what
     * `CsrfProtectionMiddleware` sets on real responses. Callers send it
     * back via the `X-CSRF-Token` header, exactly like the shell does.
     *
     * @return string Valid CSRF token.
     */
    protected function enableTestAppCsrf(): string
    {
        Configure::write('DebugBar.TestApp.csrf', true);
        $token = new CsrfProtectionMiddleware()->createToken();
        $this->cookie('csrfToken', $token);

        return $token;
    }

    /**
     * The browser contract: extensionless URL with a JSON body and an
     * explicit JSON Accept header still answers JSON (not the Ajax view).
     *
     * @return void
     */
    public function testExplainBrowserContractWithoutExtension(): void
    {
        $id = $this->seedSqlRequest();

        $this->configRequest([
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
            'input' => json_encode(['request_id' => $id, 'execution' => 1]),
        ]);
        $this->post('/debug-bar/panels/explain');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertArrayHasKey('plan', $body);
        $this->assertSame('query-plan', $body['mode']);
    }

    /**
     * @return string Request id.
     */
    protected function seedSqlRequest(): string
    {
        $request = $this->seedRequest([
            'SqlLog' => [
                'tables' => [],
                'loggers' => [],
                'items' => [
                    [
                        'sql' => 'SELECT 1',
                        'params' => [],
                        'took' => 1,
                        'rows' => 1,
                        'role' => '',
                        'driver' => null,
                        'connection' => 'test',
                    ],
                    [
                        'sql' => 'UPDATE debug_bar_requests SET url = ?',
                        'params' => ['/x'],
                        'took' => 1,
                        'rows' => 0,
                        'role' => '',
                        'driver' => null,
                        'connection' => 'test',
                    ],
                ],
            ],
        ]);

        return (string)$request->id;
    }

    /**
     * Read statements explain on their own connection, live only.
     *
     * @return void
     */
    public function testExplainReadQuery(): void
    {
        $id = $this->seedSqlRequest();

        $this->post('/debug-bar/panels/explain.json', ['request_id' => $id, 'execution' => 1]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertArrayHasKey('plan', $body);
        $this->assertSame('query-plan', $body['mode']);
        $this->assertSame('Sqlite', $body['driver']);
    }

    /**
     * Non-read statements are refused, never executed.
     *
     * @return void
     */
    public function testExplainWriteQueryRefused(): void
    {
        $id = $this->seedSqlRequest();

        $this->post('/debug-bar/panels/explain.json', ['request_id' => $id, 'execution' => 2]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertArrayHasKey('unavailable_reason', $body);
        $this->assertArrayNotHasKey('plan', $body);
    }

    /**
     * Unknown requests and executions surface errors, not exceptions.
     *
     * @return void
     */
    public function testExplainMissingSurfacesError(): void
    {
        $this->post('/debug-bar/panels/explain.json', ['request_id' => 'missing', 'execution' => 1]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertStringContainsString('no longer stored', (string)$body['error']);

        $id = $this->seedSqlRequest();
        $this->post('/debug-bar/panels/explain.json', ['request_id' => $id, 'execution' => 99]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
    }

    /**
     * Stored requests without an SQL panel name the panel, not the request.
     *
     * @return void
     */
    public function testExplainWithoutSqlPanel(): void
    {
        $request = $this->seedRequest([]);
        $this->post('/debug-bar/panels/explain.json', ['request_id' => (string)$request->id, 'execution' => 1]);
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);
        $this->assertSame('This request has no SQL panel.', $body['error'] ?? null);
    }

    /**
     * The shell contract survives host CSRF: cookie token echoed back in
     * the `X-CSRF-Token` header answers JSON, not 403.
     *
     * @return void
     */
    public function testExplainAcceptsCsrfHeaderToken(): void
    {
        $id = $this->seedSqlRequest();
        $token = $this->enableTestAppCsrf();

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $token,
            ],
            'input' => json_encode(['request_id' => $id, 'execution' => 1]),
        ]);
        $this->post('/debug-bar/panels/explain.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertArrayHasKey('plan', $body);
        $this->assertSame('query-plan', $body['mode']);
    }

    /**
     * Without any token the host middleware answers 403, not EXPLAIN.
     *
     * @return void
     */
    public function testExplainRejectsMissingCsrfToken(): void
    {
        Configure::write('DebugBar.TestApp.csrf', true);
        $id = $this->seedSqlRequest();

        $this->post('/debug-bar/panels/explain.json', ['request_id' => $id, 'execution' => 1]);
        $this->assertResponseCode(403);
    }

    /**
     * A header token that does not match the cookie answers 403.
     *
     * @return void
     */
    public function testExplainRejectsMismatchedCsrfToken(): void
    {
        $id = $this->seedSqlRequest();
        $this->enableTestAppCsrf();
        $other = new CsrfProtectionMiddleware()->createToken();

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $other,
            ],
            'input' => json_encode(['request_id' => $id, 'execution' => 1]),
        ]);
        $this->post('/debug-bar/panels/explain.json');
        $this->assertResponseCode(403);
    }

    /**
     * The queries panel embeds the CSRF token for HttpOnly-cookie hosts.
     *
     * The token is read from the rendered panel (not the cookie jar) and
     * posted back with the cookie the toolbar response set — the full
     * browser round-trip on hosts like alerrt (`httponly => true`).
     *
     * @return void
     */
    public function testExplainAcceptsServerRenderedCsrfToken(): void
    {
        $id = $this->seedSqlRequest();
        Configure::write('DebugBar.TestApp.csrf', true);

        $this->get('/debug-bar/toolbar/' . $id);
        $this->assertResponseOk();
        $shell = (string)$this->response->getBody();
        $this->assertMatchesRegularExpression(
            '/data-dk-queries[^>]*data-dk-csrf-token="([^"]+)"/',
            $shell,
            'Queries panel must embed the CSRF token',
        );
        preg_match('/data-dk-queries[^>]*data-dk-csrf-token="([^"]+)"/', $shell, $matches);
        $token = html_entity_decode($matches[1] ?? '', ENT_QUOTES);

        $cookie = $this->response->getCookie('csrfToken');
        $this->assertNotEmpty($cookie['value'] ?? null);
        $this->cookie('csrfToken', (string)$cookie['value']);

        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-CSRF-Token' => $token,
            ],
            'input' => json_encode(['request_id' => $id, 'execution' => 1]),
        ]);
        $this->post('/debug-bar/panels/explain.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertArrayHasKey('plan', $body);
    }
}
