<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\TestCase\FixtureFactoryTrait;
use TestApp\Application;

/**
 * Dashboard controller test.
 *
 * @ported-from \DebugKit\Test\TestCase\Controller\DashboardControllerTest
 */
class DashboardControllerTest extends TestCase
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

    public function testIndexNoRequests(): void
    {
        $requests = $this->fetchTable('Crustum/DebugBar.Requests');
        $requests->Panels->deleteAll('1=1');
        $requests->deleteAll('1=1');

        $this->get('/debug-bar/dashboard');

        $this->assertResponseOk();
        $this->assertResponseContains('Database');
        $this->assertResponseNotContains('Reset database');
    }

    public function testIndexWithRequests(): void
    {
        $request = $this->makeRequest();
        $this->makePanel($request);

        $this->get('/debug-bar/dashboard');

        $this->assertResponseOk();
        $this->assertResponseContains('Database');
        $this->assertResponseContains('Reset database');
    }

    public function testReset(): void
    {
        $request = $this->makeRequest();
        $this->makePanel($request);

        $this->post('/debug-bar/dashboard/reset');

        $this->assertRedirect('/debug-bar');
        $requests = $this->fetchTable('Crustum/DebugBar.Requests');
        $this->assertSame(0, $requests->find()->count());
    }
}
