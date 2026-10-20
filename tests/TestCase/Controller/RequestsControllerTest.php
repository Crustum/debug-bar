<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\TestCase\FixtureFactoryTrait;
use TestApp\Application;

/**
 * Request controller test.
 *
 * @ported-from \DebugKit\Test\TestCase\Controller\RequestsControllerTest
 */
class RequestsControllerTest extends TestCase
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
     * Test getting a toolbar that exists.
     *
     * @return void
     */
    public function testView(): void
    {
        $request = $this->makeRequest();
        $this->makePanel($request);

        $this->get("/debug-bar/toolbar/{$request->id}");

        $this->assertResponseOk();
        $this->assertResponseContains('Request', 'Has a panel button');
        $this->assertResponseContains('dist/debugbar.css', 'Has a CSS file');
    }

    /**
     * Test getting a toolb that does notexists.
     *
     * @return void
     */
    public function testViewNotExists(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->get('/debug-bar/toolbar/bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');

        $this->assertResponseError();
    }
}
