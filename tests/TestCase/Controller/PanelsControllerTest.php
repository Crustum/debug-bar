<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Cake\Error\Debugger;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\TestCase\FixtureFactoryTrait;
use TestApp\Application;

/**
 * Panel controller test.
 *
 * @ported-from \DebugKit\Test\TestCase\Controller\PanelsControllerTest
 */
class PanelsControllerTest extends TestCase
{
    use FixtureFactoryTrait;
    use IntegrationTestTrait;

    /**
     * Tables to reset each test.
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum/DebugBar.Requests',
        'plugin.Crustum/DebugBar.Panels',
    ];

    /**
     * Setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configApplication(Application::class, []);
    }

    /**
     * tests index page returns as JSON
     *
     * @return void
     */
    public function testIndex(): void
    {
        $this->configRequest([
            'headers' => [
                'accept' => 'application/json, text/javascript, */*; q=0.01',
            ],
        ]);
        $request = $this->makeRequest();
        $this->makePanel($request);
        $this->get("/debug-bar/panels/{$request->id}");

        $this->assertResponseOk();
        $this->assertContentType('application/json');
    }

    /**
     * Test getting a panel that exists.
     *
     * @return void
     */
    public function testView(): void
    {
        $request = $this->makeRequest();
        $panel = $this->makePanel($request);

        $this->get("/debug-bar/panels/view/{$panel->id}");

        $this->assertResponseOk();
        $this->assertResponseContains('Request</h2>');
        $this->assertResponseContains('data-dk-request-panel');
        $this->assertResponseContains('data-dk-tab-panel="request:headers"');
        $this->assertResponseContains('data-dk-tab-panel="request:session"');
        $this->assertResponseContains('data-dk-copy');
    }

    /**
     * Test getting a panel that does notexists.
     *
     * @return void
     */
    public function testViewNotExists(): void
    {
        $this->get('/debug-bar/panels/view/aaaaaaaa-ffff-ffff-ffff-aaaaaaaaaaaa');
        $this->assertResponseError();
        $this->assertResponseContains('Panel not found.');
    }

    /**
     * Deprecations panel renders the "No deprecations" flash only when
     * every category is empty (including the `other` bucket, which was
     * previously missing from the check).
     *
     * @return void
     */
    public function testViewDeprecationsPanelEmpty(): void
    {
        $request = $this->makeRequest();
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Deprecations',
            'Deprecations',
            'Crustum/DebugBar.deprecations_panel',
            ['app' => [], 'cake' => [], 'vendor' => [], 'plugins' => [], 'other' => []],
        );

        $this->get("/debug-bar/panels/view/{$panel->id}");

        $this->assertResponseOk();
        $this->assertResponseContains('No deprecations');
    }

    /**
     * @return void
     */
    public function testViewDeprecationsPanelWithEntries(): void
    {
        $request = $this->makeRequest();
        $entry = ['niceFile' => 'src/Foo.php', 'line' => 1, 'message' => 'deprecated thing'];
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Deprecations',
            'Deprecations',
            'Crustum/DebugBar.deprecations_panel',
            ['app' => [$entry], 'cake' => [], 'vendor' => [], 'plugins' => [], 'other' => []],
        );

        $this->get("/debug-bar/panels/view/{$panel->id}");

        $this->assertResponseOk();
        $this->assertResponseNotContains('No deprecations');
        $this->assertResponseContains('deprecated thing');
    }

    /**
     * Deprecations only present in the `other` bucket should also suppress
     * the "No deprecations" flash. Guards against a regression of M3 where
     * `count($other)` was missing from the empty-check sum.
     *
     * @return void
     */
    public function testViewDeprecationsPanelOtherOnly(): void
    {
        $request = $this->makeRequest();
        $entry = ['niceFile' => 'src/Bar.php', 'line' => 2, 'message' => 'only-other deprecation'];
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Deprecations',
            'Deprecations',
            'Crustum/DebugBar.deprecations_panel',
            ['app' => [], 'cake' => [], 'vendor' => [], 'plugins' => [], 'other' => [$entry]],
        );

        $this->get("/debug-bar/panels/view/{$panel->id}");

        $this->assertResponseOk();
        $this->assertResponseNotContains('No deprecations');
        $this->assertResponseContains('only-other deprecation');
    }

    /**
     * The Variables panel surfaces serialization-error messages from
     * `ToolbarService`; those messages can echo back attacker-controlled
     * data (e.g. via `__sleep` exceptions), so the template must escape
     * them. Guards against a regression of M4.
     *
     * @return void
     */
    public function testViewVariablesPanelErrorIsEscaped(): void
    {
        $request = $this->makeRequest();
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Variables',
            'Variables',
            'Crustum/DebugBar.variables_panel',
            ['error' => '<script>alert(1)</script>', 'variables' => [], 'errors' => []],
        );

        $this->get("/debug-bar/panels/view/{$panel->id}");

        $this->assertResponseOk();
        $this->assertResponseNotContains('<script>alert(1)</script>');
        $this->assertResponseContains('&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    /**
     * The lazy variable endpoint serves one variable's tree as HTML.
     *
     * @return void
     */
    public function testViewVariableServesOneVariable(): void
    {
        $request = $this->makeRequest();
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Variables',
            'Variables',
            'Crustum/DebugBar.variables_panel',
            [
                'variables' => [
                    'foo' => Debugger::exportVarAsNodes(['a' => 1]),
                    'bar' => Debugger::exportVarAsNodes('two'),
                ],
                'errors' => [],
            ],
        );

        $this->get("/debug-bar/panels/view-variable/{$panel->id}.json?var=foo");

        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);
        $this->assertSame('foo', $body['name']);
        $this->assertStringContainsString('data-dk-debug-tree', (string)$body['html']);
    }

    /**
     * The lazy variable endpoint masks secrets before rendering.
     *
     * Stored rows keep raw nodes (read-time masking); the served tree
     * must never carry a cleartext hash into the inspector.
     *
     * @return void
     */
    public function testViewVariableMasksSensitiveValues(): void
    {
        $request = $this->makeRequest();
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Variables',
            'Variables',
            'Crustum/DebugBar.variables_panel',
            [
                'variables' => [
                    'user' => Debugger::exportVarAsNodes([
                        'name' => 'ann',
                        'password' => '$2y$12$llQ6Frzaejh0hiHedemB2OJIofraHqLetJby',
                    ]),
                ],
                'errors' => [],
            ],
        );

        $this->get("/debug-bar/panels/view-variable/{$panel->id}.json?var=user");

        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);
        $this->assertStringContainsString('********', (string)$body['html']);
        $this->assertStringNotContainsString('$2y$12$llQ6Frzaejh0hiHedemB2', (string)$body['html']);
    }

    /**
     * Unknown variable names 404 instead of leaking panel content.
     *
     * @return void
     */
    public function testViewVariableNotExists(): void
    {
        $request = $this->makeRequest();
        $panel = $this->makePanel(
            $request,
            'Crustum/DebugBar.Variables',
            'Variables',
            'Crustum/DebugBar.variables_panel',
            ['variables' => [], 'errors' => []],
        );

        $this->get("/debug-bar/panels/view-variable/{$panel->id}.json?var=nope");

        $this->assertResponseError();
    }
}
